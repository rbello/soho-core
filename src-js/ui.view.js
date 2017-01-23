/*global Soho*/
/*global jQuery*/
/**
 * Classe View.
 */
Soho.View = function (name) {

	// Save view name
	this.name = name;

	// View contents distribution model
	this.dist = Soho.View.DistributionModel.LOCAL_CACHE;

	// Remember if view has been loaded yet
	this.loadedTime = 0;
	this.loadedUrl = null;
	this.localCacheAge = 120000; // en ms

	// Si la page a été déclarée dans le message de welcome, donc on a ses données.
	// On va pouvoir adapter certains paramètres.
	if (name in Soho.viewdata.available) {
		var nfo = Soho.viewdata.available[name];
		// Le modèle de distribution
		if ('dist' in nfo) {
			// On vérifie qu'il existe bien
			if (nfo.dist in Soho.View.DistributionModel) {
				// Si oui on le modifie pour cette vue
				this.dist = Soho.View.DistributionModel[nfo.dist];
			}
		}
		// L'age du cache
		// TODO Mettre un moyen pour que l'age du cache soit spécifié par le serveur
		// Utiliser les headers renvoyées par le serveur ?
	}

	// Handle distribution model
	if (this.dist === Soho.View.DistributionModel.CONTINUE) {
		// On indique que le contenu n'a pas encore été chargé intégralement
		this.isAllDataFragmentsLoaded = false;
		// On initialise le curseur actuel de fragment chargés
		this.currentFragmentCursor = 0;
		// On met en place un listener qui détecte le scrolling et qui va lancer la méthode WG.View.nextFragment()
		var view = this;
		jQuery(window).scroll(function () {
			if  (jQuery(window).scrollTop() == jQuery(document).height() - jQuery(window).height()){
				view.nextFragment();
			}
		});
	}

	// Create view node
	this.node = document.createElement('div');
	this.node.setAttribute('class', 'view fit-height');
	this.node.setAttribute('id', 'view-' + name);

	// Prepare XHR object
	this.xhr = null;

};

/**
 * Renvoi l'URL de la vue avec les paramètres donnés.
 * @param Object params Un tableau associatif avec les données à envoyer.
 * @return string
 * @version 3.0.15 Utilisation d'un tableau pour la concaténation
 */
Soho.View.prototype.getURL = function (params) {
	var url = [
		Soho.appURL(),
		'view.php?v=',
		escape(this.name)
	];
	if (params instanceof Object) {
		for (let key in params) {
			url.push('&');
			url.push(encodeURIComponent(key));
			url.push('=');
			url.push(encodeURIComponent(params[key]));
		}
	}
	return url.join('');
};

/**
 * Affiche la vue avec les paramètres donnés.
 *
 * @return true Si une nouvelle version de la vue a été demandée au serveur
 * @return false Si une version en cache a été utilisée.
 */
Soho.View.prototype.display = function (params, noCache, hash) {

	// On recupère l'URL de la vue
	var url = this.getURL(params);

	// Debug
	if (console) {
		console.log('[ui] View URL: ' + url + ' (cache='+(noCache ? 'no' : 'yes')+', current='+(url != this.loadedUrl?'no':'yes')+')');
	}

	// On compare avec celle déjà chargée.
	// Si ce n'est pas l'url chargée, on ne se pose pas de question :
	// on télécharge une nouvelle version de la vue.
	if (noCache || url != this.loadedUrl) {
		this.download(url, hash);
		return true;
	}

	switch (this.dist) {

		// Dans le cas d'une distribution type KEEP_ALIVE, on regarde
		// si la vue a déjà été chargée. Si non, on la demande au serveur.
		// Si oui, on se contente d'afficher le noeud existant.
		// C'est à la vue de se mettre à jour par elle même, grâce à ses
		// données locales ou bien en utilisant le live service.
		case Soho.View.DistributionModel.KEEP_ALIVE :
			if (!this.loadedUrl) {
				this.download(url, hash);
				return true;
			}
			return false;

		// Dans le cas d'une distribution type REFRESH, on va chercher
		// une nouvelle version de la vue sur le serveur.
		case Soho.View.DistributionModel.REFRESH :
			this.download(url, hash);
			return true;

		// Dans le cas d'une distribution type LOCAL_CACHE, on commence
		// par télécharger la vue si ça n'a jamais été fait. Si un cache
		// existe, on verifie qu'il n'a pas expiré. Dans ce cas, on
		// télécharge un nouvelle version de la vue.
		case Soho.View.DistributionModel.LOCAL_CACHE :
		default :
			// Cas du premier téléchargement.
			if (!this.loadedUrl) {
				this.download(url, hash);
				return true;
			}
			// Verification de l'expiration du cache
			else if (new Date().getTime() - this.loadedTime > this.localCacheAge) {
				this.download(url, hash);
				return true;
			}
			// Le cache est valide
			return false;
	
	}

};

/**
 * Remplace le contenu actuel de la vue par une requête
 * au serveur.
 * @param string c Le contenu HTML de la vue.
 */
Soho.View.prototype.setContents = function (c) {

	// Append view to wrapper
	// jQuery est utilisé ici car il permet d'executer le code
	// javascript qui se trouve dans la page
	jQuery(this.node).html(c);

	// On force le lancement de cet event car il est utilisé
	// un peu partout pour le repaint de certains elements ou bien
	// l'application de certains comportements.
	// Exemple: le fit-height 100%
	jQuery(window).trigger('resize');

};

/**
 * Remplace le contenu actuel de la vue par une requête
 * au serveur.
 */
Soho.View.prototype.download = function (url, hash) {

	// Remove old URL, to detect ajax query abortion
	this.loadedUrl = null;

	this.node.innerHTML = '';

	Soho.setStatus('Loading view...', Soho.status.WAIT);

	if (console) {
		console.log('[ajax] GET ' + url + ' (AES=' + (Soho.security.AES == null ? 'off' : 'on')+', cache=off)');
	}

	// @todo Note: Pourquoi on n'utilise pas WG.ajax() ici ?
	this.xhr = jQuery.ajax({
		url: url,
		cache: false,
		context: this,
		dataType: Soho.security.AES != null ? 'text' : 'html',
		type: 'GET',
		success: function (data, textStatus, jqXHR) {

			// Remove XHR object
			this.xhr = null;

			// This page is loaded, the url is stored
			this.loadedUrl = url;

			// Remember when the view has been loaded
			this.loadedTime = new Date().getTime();

			// AES Decrypt
			if (Soho.security.AES != null) {
				// Update UI
				Soho.setStatus('Decrypting data...', Soho.status.WAIT);
				// Decrypt data
				data = Soho.security.aesDecrypt(data);
			}

			// Hide status indicator
			Soho.setStatus(null);

			// Set page contents
			this.setContents(data);

			// Jump to selected id (hash)
			if (typeof hash == 'string') {
				document.location.hash = '#' + hash;
			}

			// Trigger event
			Soho.trigger('viewLoaded', this.name);

		},
		error: function (jqXHR, textStatus, errorThrown) {
			// Remove XHR object
			this.xhr = null;
			// Display error
			Soho.setStatus(
				'Unable to get this view: <em>' + errorThrown + ' (' + textStatus + ')</em>',
				Soho.status.FAILURE,
				function () {
					if (this.refresh) {
						this.refresh();
					}
					else {
						Soho.setStatus('<b>Fatal Error</b>: please restart using F5 on your keyboard', Soho.status.FAILURE);
					}
				}
			);
		}
	});

};

// Make View class listenable
Soho.util.implementListenerPattern(Soho.View.prototype);

/**
 * Attention, cette fonction doit utiliser jQuery.on() car elle ne
 * sera pas appelée de nouveau après l'initialisation du système.
 *
 * Il ne faut pas appeler deux fois cette méthode. Il faut l'appliquer
 * au container global des vues (#main) une fois pour toute.
 */
Soho.View.applyStandardBehavior = function (node) {

	// Create a jQuery wrapper
	jQuery(node)

	// Fix old url
	// Cette partie permet de transformer les liens en durs en
	// liens dynamiques asynchrones. L'avantage de cette methode,
	// c'est qu'il n'est pas neccessaire de changer le code
	// de la v2.0 (MIMO) pour la nouvelle version. En fait, il est
	// meme recommande de ne pas se prendre la tete et de continuer
	// a faire des liens en dur, plus simples a faire.
	// v3.0.6: utilisation de jQuery.on()
	// v3.0.15: les liens avec target=_blank ne sont plus concernés
	.on('click', 'a[href]:not([target="_blank"])', function (e) {
		// La fonction WG.View.handleLink() renvoi true si le lien a été
		// traité de manière asynchrone. Ici, on renvoi FALSE dans ce cas
		// pour que le comportement par défaut ne soit pas provoqué.
		return !Soho.View.handleLink(this.getAttribute('href'), e);
	})

	// La même chose pour les formulaires
	.on('submit', 'form', function (e) {
		return !Soho.View.handleForms(this, e);
	})

	// Onglets (Tabs)
	.on('click', 'ul.tabmenu li a', function (e) {
		var li = this.parentNode,
			menu = li.parentNode;
		// Remove selected tab legend
		jQuery('li.selected', menu).removeClass('selected');
		// Add selected indcator to this tab legend
		jQuery(li).addClass('selected');
		// Hide all tabs
		jQuery('.tabcontent.tab-' + menu.getAttribute('tab'), node).removeClass('selected');
		// Show the new tab
		jQuery('.tabcontent' + this.getAttribute('href'), node).addClass('selected');
		// Prevent default behavior
		e.preventDefault();
		return false;
	})
	
	// Menu "classics"
	.on('click', 'ul.view-topbar-menu > li', function (e) {

	})

	// Auto-height 100%
	// L'objectif de ce code est de permettre à des composants HTML de type
	// block de prendre toute la hauteur de leurs conteneurs.
	.on('fitheight', '.fit-height', function () {
		var t = jQuery(this),
			m = jQuery('body').height(),
			h = 0,
			o = 0;
		// Calcul des dimensions des bords
		o =
			+ parseInt(t.css('padding-top'), 10)
			+ parseInt(t.css('padding-bottom'), 10)
			+ parseInt(t.css('margin-top'), 10)
			+ parseInt(t.css('margin-bottom'), 10)
			+ parseInt(t.css('borderTopWidth'), 10)
			+ parseInt(t.css('borderBottomWidth'), 10);
		// Calcul de la hauteur de la div
		h = m - o - t.offset().top;
		// Modification de la hauteur du composant
		t.css('height', h + 'px');
	})

	// Elatic textareas (auto-height)
	// Ce comportement qui ne s'applique qu'au champs TEXTAREA permet
	// d'adapter la heuteur de la zone de texte en fonction de la quantitée
	// de texte saisie par l'utilisateur.
	.on('keydown', 'textarea.elastic', function () {
		this.style.height = '';
		this.rows = this.value.split('\n').length;
		this.style.height = this.scrollHeight + 'px';
	});

};

/**
 * Compatibilité des liens v2.X vers v3 : support de l'asynchrone.
 *
 * @param HTMLFormElement form
 * @param HTMLEvent event
 * @return boolean True si la lien a été traité en asynchrone
 * @access public static
 */
jQuery.View.handleLink = function (href) {
	// Check URL
	if (href.substr(0, 1) == '?') {
		href = 'index.php' + href;
	}
	else if (href.substr(0, 10) != 'index.php?') {
		return false;
	}
	// Split URL tokens
	href = href.substr(10).split('&');
	var view = null,
		hash = null,
		params = {},
		i = 0,
		j = href.length;
	for (; i < j; i++) {
		var token = href[i];
		// Hash
		if (token.indexOf('#') !== -1) {
			hash = unescape(token.substr(token.indexOf('#') + 1, token.length));
			token = token.substr(0, token.indexOf('#'));
		}
		// Split parameters
		var value = token.split('='),
			key = value.shift();
		value = value.join();
		// Save view
		if (key == 'view' || key == 'v') {
			view = value;
		}
		// Save variable
		else {
			params[key] = unescape(value);
		}
	}
	// Debug
	/*if (view != null && console) {
		console.log("* Convert static link: " + href + " >>> view=" + view + " params=" + params + " hash=" + hash);
	}*/
	// La vue a été trouvée, on va pouvoir la demander correctement
	if (view != null) {
		jQuery.setView(view, params, false, hash);
		return true;
	}
	// Sinon et bien on laisse faire, on verra bien...
	return false;
};

/**
 * Compatibilité des formulaires v2.x vers v3 : support de l'asynchrone.
 *
 * @param HTMLFormElement form
 * @param HTMLEvent event
 * @return boolean
 * @access public static
 */
jQuery.View.handleForms = function (form, event) {
	var t    = jQuery(form),
		data = {};
	// La première chose c'est de détecter les formulaires qui envoient
	// des fichiers : ils auront un traitement spécial
	if (t.hasAttr('enctype') && form.enctype == 'multipart/form-data') {
		throw 'Not implemented yet';
	}
	// Ensuite on va récupérer les paramètres de l'attribut 'action' du formulaire.
	// C'est surtout pour que ces paramètres ne soient pas perdus, et que la vue puisse y être détecté.
	var action = t.attr('action'),
		url = Soho.util.parse_url(action),
		params = ('query' in url) ? url.query.split('&') : [],
		i = 0,
		j = params.length;
	for (; i < j; i++) {
		var token = params[i];
		// Split parameters
		var value = token.split('='),
			key = value.shift();
		value = value.join();
		// Save view
		if (key == 'view' || key == 'v') {
			data['v'] = value;
		}
		// Save variable
		else {
			data[key] = unescape(value);
		}
	}
	// On recupère les valeurs de champs
	jQuery('input,textarea,select', form).each(function () {
		var n = jQuery(this);
		if (n.hasAttr('name')) {
			// Ne pas envoyer les champs désactivés!
			if (n.is(':disabled')) {
				return;
			}
			// Pour les checkboxes, on ne met même pas le champ dans les données
			// à envoyer s'il n'est pas checké
			if (n.attr('type') == 'checkbox') {
				if (n.is(':checked')) {
					data[this.name] = this.value;
				}
				// Disable field
				n.attr('disabled', 'disabled');
				return;
			}
			// Un petit correctif pour le nom de la vue: s'il est indiqué
			// dans un paramètre et nom dans l'URL.
			if (n.attr('name') == 'view' && !('v' in data)) {
				data['v'] = n.val();
			}
			// Save field value
			else {
				data[this.name] = n.val();
			}
			// Disable field
			n.attr('disabled', 'disabled');
		}
	});
	// Debug
	//for (field in data) console.log('  ' + field + ' = ' + data[field]);
	// Update UI
	Soho.setStatus('Sending data...', Soho.status.WAIT);
	// Save current view
	var view = Soho.ui.currentView;
	// On fait une requête ajax asynchrone
	Soho.ajax({
		url: 'view.php',
		data: data,
		dataType: 'html',
		success: function (data) {
			// Update UI
			Soho.setStatus(null);
			// Save loaded URL
			if (console) {
				console.log('Loaded URL: ' + view.getURL(data));
			}
			view.loadedUrl = view.getURL(data);
			// Update contents
			view.setContents(data);
		},
		error: function (jqXHR, textStatus, errorThrown) {
			Soho.setStatus(
				'Unable to post this form: ' + errorThrown,
				Soho.status.FAILURE,
				function () {
					Soho.setStatus(null);
				},
				'close'
			);
		}
	});

	// Prevent default behavior (stop submit process)
	event.preventDefault();
	return true;
};

/**
 * Modèles de distribution des pages.
 */
Soho.View.DistributionModel = {

	/**
	 * La vue est rafraichie à chaque fois.
	 */
	REFRESH: 1,

	/**
	 * La vue est rafraichie si la durée de vie du cache local a expiré.
	 * C'est le model par défaut.
	 */
	LOCAL_CACHE: 2,

	/**
	 * La vue est téléchargée une fois, ensuite le cache est utilisé systématiquement.
	 * Soit la vue est statique, soit elle utilise le service live pour se mettre à jour.
	 */
	KEEP_ALIVE: 3,

	/**
	 * Le contenu de la vue est envoyé au fur et à mesure que l'utilisateur scroll la
	 * page en hauteur.
	 * Ce modèle de distribution va automatiquement déclancher la méthode WG.View.nextFragment()
	 * quand l'utilisateur atteindra le bas de la fenêtre.
	 *
	 * Côté serveur, la vue receptionne la variable HTTP 'frag' (le numéro de frament) qui
	 * contient un nombre entier (croissant, à partir de 0) pour lui permettre déterminer puis de
	 * renvoyer le bon fragment de contenu.
	 * Quand un fragment est téléchargé, l'évent 'fragmentLoaded' est propagé sur la vue.
	 */
	CONTINUE: 4,

	/**
	 * La page est téléchargée une fois, ensuite le cache est utilisé systématiquement.
	 * Le contenu est divisé en pages qui sont chargées à la demande du client.
	 *
	 * Ce mode de distribution implique que des composants de controle (a, button) dans la vue
	 * utilisent la méthode WG.View.setDataFragment() pour modifier le contenu dans la zone d'affichage.
	 * Côté serveur, la vue receptionne la variable HTTP 'frag' (le numéro de frament) qui
	 * contient un nombre entier (croissant, à partir de 0) pour lui permettre déterminer puis de
	 * renvoyer le bon fragment de contenu. Ici par fragment on désire une page du contenu.
	 * Quand une page est téléchargé, l'évent 'fragmentLoaded' est propagé sur la vue.
	 */
	PAGINATION: 5

};

/**
 * Indique si la vue supporte le chargement de contenu par fragments.
 */
Soho.View.prototype.supportDataFragments = function () {
	return (this.dist === Soho.View.DistributionModel.CONTINUE || this.dist === Soho.View.DistributionModel.PAGINATION);
};

/**
 * Indique si tous les fragments de données ont été chargés. C'est à dire si le serveur à revoyé autre chose
 * que le code 206 au moins une fois.
 */
Soho.View.prototype.isAllDataFragmentsLoaded = false;

// TODO
Soho.View.prototype.currentFragmentCursor = 0;

/**
 *
 *
 * @param HTMLElement container
 * @param int frag Le numéro du fragment à afficher. Les données peuvent provenir du cache ou bien du serveur
 *  en fonction de l'argument useCache et des données déjà téléchargées. Par défaut, les vues qui utilisent
 *  les modèles de distribution CONTINUE ou PAGINATION renvoient le code 206 (Partial Content) pour indiquer qu'il s'agit
 *  uniquement d'un fragment de l'information. Quand le client a demandé tout le contenu (ou en d'autre termes, quand
 *  la vue ne trouve pas de contenu pour le fragment demandé(1)) le serveur renvoi le code 204 (No Content).
 * @param array|null opts Options à passer au serveur lors de la requête du fragment. A utiliser par exemple pour
 *  mettre en place un système de filtre, ou bien pour déterminer combien de lignes de tableau renvoyer.
 * @param boolean replaceData Si cet argument vaut FALSE, alors le contenu du fragment sera ajouté à la suite
 *  du contenu existant dans le container. Sinon, il le remplacera. Par défaut, vaut TRUE.
 * @param useCache Indique si le cache propre au système de frament doit être utilisé.
 *
 * @note (1) Dans le cas du modèle CONTINUE, le compteur de fragment est incrémenté automatiquement jusqu'au moment où le serveur
 *  renvoi le code 204. Dans le cas du modèle PAGINATION, c'est la vue qui doit donner à l'utilisateur des controles (a ou input)
 *  pour demander les pages suivantes ou précédentes. 
 */
Soho.View.prototype.setDataFragment = function (container, frag, opts, replaceData, useCache) {
	var view = this;
	console.log('Load fragment: ' + frag);
	// TODO Mettre un status
	Soho.ajax({
		url: Soho.appURL() + 'view.php?v=' + escape(this.name),
		dataType: 'html',
		cache: (!useCache) ? false : true,
		data: {
			'frag': frag
		},
		success: function (data, textStatus, jqXHR) {
			// On met à jour le code HTML de la vue
			if (replaceData) {
				container.innerHTML = data;
			}
			else {
				container.innerHTML += data;
			}
			// Le serveur doit renvoyer le code 206 pour indiquer que du contenu reste à télécharger.
			// Sinon, on considère que tout le contenu a été chargé.
			if (jqXHR.status != 206) {
				view.isAllDataFragmentsLoaded = true;
				view.trigger('fragmentsLoaded', {frag: frag, opts: opts, status: jqXHR.status});
			}
			// On lance un event pour indiquer qu'un fragment a été chargé
			else {
				view.trigger('fragmentLoaded', {frag: frag, opts: opts, status: jqXHR.status});
			}
			// On met à jour le curseur de fragments
			view.currentFragmentCursor = frag;
		},
		error: function (jqXHR, textStatus, errorThrown) {
			// TODO
			console.log('Fragment load ERROR: ' + textStatus + ' (' + errorThrown + ')');
		}
	});
};

Soho.View.prototype.nextFragment = function () {
	// La vue ne supporte pas ce type de fragmentation du contenu
	if (!this.supportDataFragments()) {
		throw "Invalid distribution model";
	}
	// Tout le contenu a déjà été chargé
	if (this.isAllDataFragmentsLoaded) {
		return;
	}
	// On demande l'obtention du fragment de données
	this.setDataFragment(
		document.getElementById('dataContainer'),
		this.currentFragmentCursor + 1,
		null,
		false,
		true
	);
};
