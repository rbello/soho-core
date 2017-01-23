/*global Soho*/
/*global jQuery*/
Soho.ui = {


	/* * *   UI Components   * * */

	createUIComponents: function () {
		
		// Log
		if (console) {
			console.log('[start] Create UI components...');
		}

		// Global container
		var container = document.createElement('div');
		container.setAttribute('id', 'container');
		container.onmousemove = function () {
			uidata.lastAction = new Date().getTime();
		};

		// Header
		var header = document.createElement('header');
		container.appendChild(header);

		// Top
		var top = document.createElement('div');
		top.setAttribute('id', 'top');
		header.appendChild(top);

		// AppName
		var appName = document.createElement('div');
		appName.setAttribute('id', 'appName');
		appName.innerHTML = Soho.util.htmlspecialchars(Soho.appName());
		top.appendChild(appName);

		// Live
		var live = document.createElement('div');
		live.setAttribute('id', 'live');
		top.appendChild(live);

		// Menu
		var menu = document.createElement('ul');
		menu.setAttribute('id', 'menu');
		header.appendChild(menu);

		// Main
		var main = document.createElement('div');
		main.setAttribute('id', 'main');
		container.appendChild(main);

		// Wrapper
		var wrapper = document.createElement('div');
		wrapper.setAttribute('id', 'wrapper');
		//wrapper.setAttribute('class', 'fit-height');
		main.appendChild(wrapper);

		// Status
		var status = document.createElement('div');
		status.setAttribute('id', 'status');
		header.appendChild(status);

		// Locker
		var locker = document.createElement('div');
		locker.setAttribute('id', 'locker');
		container.appendChild(locker);
		locker.oncontextmenu = function () { return false; };
		locker.onmousedown = function () { return false; };
		locker.onmouseup = function () { return false; };
		
		// MenuOptions
		var menuOpts = document.createElement('div');
		menuOpts.setAttribute('id', 'menuOpts');
		header.appendChild(menuOpts);

		return {
			container: container,
			header: header,
			top: top,
			appName: appName,
			live: live,
			menu: menu,
			main: main,
			wrapper: wrapper,
			status: status,
			locker: locker,
			menuOpts: menuOpts
		};

	},

	bindEvents: function () {
		
		// Log
		if (console) {
			console.log('[start] Bind UI events...');
		}
		
		// Le hash doit être intialement vide
		// TODO C'est à mettre ici ça ?! En tout cas avant l'enregistrement du listener
		document.location.hash = '';
		
		jQuery(window)
		
		// Surveille le changement de hash pour gêrer l'historique
		.bind('hashchange', function (e) {
			
			// TODO Verifier que la session soit ouverte!
			
			// On verifie que l'historique contienne bien ce hash
			if (!(document.location.hash in Soho.ui.viewHistory)) {
				return;
			}
			
			// On recupère les informations sur la vue
			var info = Soho.ui.viewHistory[document.location.hash];
				
			// Log
			if (console) {
				console.log('[ui] Set view: ' + info.name);
			}
			
			// On prépare une variable pour la vue
			var view;
			
			// La page existe déjà, on la réutilise
			if (viewdata.loaded[info.name]) {
				view = viewdata.loaded[info.name];
			}
			
			// La page n'existe pas, on la fabrique
			else {
				view = new Soho.View(info.name);
				viewdata.loaded[info.name] = view;
			}
			
			// Remove old view
			Soho.ui.removeCurrentView();
			
			// Scroll top wrapper
			jQuery(uidata.uiComponents.wrapper).scrollTop(0);
			
			// This is the current view
			Soho.ui.currentView = view;
			
			// Append view to wrapper
			uidata.uiComponents.wrapper.appendChild(view.node);
			
			// Trigger event
			// On fait ça avant le refresh, pour que le script de la vue
			// puisse overrider les actions des listeners (par exemple sur
			// le focus)
			Soho.trigger('viewChange', view.name);
			
			// Display view
			if (!view.display(info.param, (info.noCache === true), info.hash) && view.dist != Soho.View.DistributionModel.KEEP_ALIVE) {
				// TODO Meilleur solution
				Soho.ui.getTrayIcon('power').setNotification('This page has been restored from cache. Click here to refresh this page.', function () {
					Soho.setView(view.name, null, true);
				}, 3000);
				// Trigger event
				Soho.trigger('viewRestored', view.name);
			}
			
			// A la fin, on modifie l'historique pour que le système de cache se barre
			// TODO Il faudrait p'tet faire en sorte que WG.ui.viewHistory ne sature pas à force...
			Soho.ui.viewHistory[document.location.hash].noCache = false;

			// Si un hash a été spécifié, on l'intégre maintenant
			// Un event hashchange sera diffusé, donc il y a un cas de conflit si
			// le hash voulu est de la forme 'view-X' et que cette vue est dans l'historique.
			// En pratique, ça devrait pas trop se produire...
			if (info.hash) {
				document.location.hash = info.hash;
			}
			
			// A la fin, on lance un event
			Soho.trigger('viewChanged', view.name);
			
		})
		
		// Surveille le redimensionnement de la fenêtre pour le comportement fit-height 100%.
		// @see WG.View.applyStandardBehavior()
		.resize(function (e) {
			jQuery('.fit-height').trigger('fitheight');
		});
		
	},
	
	/* * *   Views   * * */

	viewCount: 1,
	
	viewHistory: {},

	setView: function (viewname, param, noCache, hash) {
		
		// Hash de la vue 
		var hashPos = '#view-' + Soho.ui.viewCount++;
				
		// Add to history navigation
		Soho.ui.viewHistory[hashPos] = {"name": viewname, "param": param, "noCache": noCache, "hash": hash};
		
		// Save view name (to reopen previous view, surtout pour le debug!)
		if ('localStorage' in window && viewname != 'login') {
			window.localStorage.setItem('WG.defaultView', viewname);
		}

		// Remove status
		Soho.setStatus(null);
		
		// Reset quicksearch
		Soho.search.setDefault();
		
		// On modifie le hash, ce qui automatiquement trigger la modification de la vue 
		document.location.hash = hashPos;

	},

	currentView: null,

	removeCurrentView: function () {
		// Clean HTML container
		// Use jquery to remove?
		uidata.uiComponents.wrapper.innerHTML = '';
		// Remove current view
		if (Soho.ui.currentView != null) {
			// Stop ajax request
			if (Soho.ui.currentView.xhr) {
				if (console) {
					console.log('[ui] Stop loading for view: ' + Soho.ui.currentView.name);
				}
				Soho.ui.currentView.xhr.abort();
			}
			// Trigger event
			Soho.trigger('viewRemoved', Soho.ui.currentView.name);
			Soho.ui.currentView = null;
		}
	},

	/* * *   Build UI   * * */

	createMenu: function (map) {
		
		// Création des items
		for (var i = 0, j = map.length; i < j; i++) {

			var item = map[i];

			// LI
			var li = document.createElement('li');
			li.setAttribute('class', 'top-level-menu module-' + item.module);
			if ('subs' in item) {
				li.onmouseover = function () {
					jQuery('ul', this).show();
				};
				li.onmouseout = function () {
					jQuery('ul', this).hide();
				};
			}

			// A
			var a = document.createElement('a');
			a.setAttribute('view', item.view);
			a.onclick = function () {
				Soho.setView(this.getAttribute('view'));
			};
			a.innerHTML = Soho.util.htmlspecialchars(item.label);
			li.appendChild(a);

			// SUBS
			if ('subs' in item) {

				// UL
				var ul = document.createElement('ul');
				li.appendChild(ul);

				for (var k = 0, l = item.subs.length; k < l; k++) {
					var sub = item.subs[k],
					    li2 = document.createElement('li'),
					    a2 = document.createElement('a');
					a2.innerHTML = Soho.util.htmlspecialchars(sub.label);
					a2.setAttribute('view', sub.view);
					a2.onclick = function () {
						Soho.setView(this.getAttribute('view'));
						this.parentNode.parentNode.style.display = 'none';
					};
					li2.appendChild(a2);
					ul.appendChild(li2);
				}
			}

			// 
			uidata.uiComponents.menu.appendChild(li);
		}
		
		// Lien de switch pour masquer/afficher le panel de menu
		var a = document.createElement('a');
		a.setAttribute('class', 'toggleMenu');
		a.onclick = function () {
			Soho.ui.toggleMenuVisibility();
		};
		uidata.uiComponents.menuOpts.appendChild(a);
		
	},

	destroyMenu: function () {
		uidata.uiComponents.menu.innerHTML = '';
	},
	
	/* * *    Menu visibility    * * */
	
	setMenuVisible: function (value) {
		
		if (value) {
			//$(uidata.uiComponents.menu).show();
			jQuery(uidata.uiComponents.menu).removeClass('wide');
			jQuery(uidata.uiComponents.wrapper).removeClass('fit-width');
			jQuery(uidata.uiComponents.menuOpts).removeClass('min');
		}
		else {
			//$(uidata.uiComponents.menu).hide();
			jQuery(uidata.uiComponents.menu).addClass('wide');
			jQuery(uidata.uiComponents.wrapper).addClass('fit-width');
			jQuery(uidata.uiComponents.menuOpts).addClass('min');
		}
		
	},
	
	toggleMenuVisibility: function () {
		Soho.ui.setMenuVisible(jQuery(uidata.uiComponents.menuOpts).hasClass('min'));	
	},

	/* * *   TrayIcons   * * */

	/**
	 * Cette m�thode permet d'afficher toutes les ic�nes du tray.
	 * Elle est appel�e lors du processus d'ouverture de la session (open)
	 * apr�s l'authentification accept�e de la session.
	 *
	 * Cette m�thode v�rifie que la session est bien ouverte avant
	 * d'initialiser les ic�nes. Si cette condition n'est pas remplie, cette
	 * m�thode ne fait rien et renvoi false.
	 *
	 * @return boolean
	 * @access public
	 */
	createTrayIcons: function () {
		if (!Soho.isLogged()) {
			return false;
		}
		Soho.util.each(uidata.trayIcons, function (icon) {
			Soho.ui.initTrayIcon(icon);
		});
		return true;
	},

	/**
	 * @param WG.TrayIcon icon
	 * @return void
	 * @access private
	 */
	initTrayIcon: function (icon) {
		// Cette icône est déjà initialisée
		if (icon.initialized) {
			return;
		}
		// Debug mode
		if (console) {
			console.log('[ui] Init TrayIcon: ' + icon.name);
		}
		// Create node
		icon.node = document.createElement('div');
		icon.node.setAttribute('id', icon.name);
		icon.node.setAttribute('class', 'tray');
		// Onclick event
		if ('onClick' in icon.data) {
			icon.onclick_substitut = icon.data.onClick;
			icon.a = document.createElement('a');
			icon.a.onclick = function () {
				icon.onclick_substitut();
			};
			icon.node.appendChild(icon.a);
		}
		// Init node
		if ('onInit' in icon.data) {
			icon.onInit = icon.data.onInit;
			icon.onInit();
		}
		// Append node
		uidata.uiComponents.live.appendChild(icon.node);
		// Save that this icon is now initialized
		icon.initialized = true;
		// Appear!
		if ('onAppear' in icon.data) {
			icon.onAppear = icon.data.onAppear;
			icon.onAppear();
		}
	},

	/**
	 * Cette m�thode permet de cr�er une nouvelle ic�ne dans le system tray � partir
	 * des donn�es data. C'est la m�thode � utiliser par les modules ou en interne
	 * pour cr�er une nouvelle ic�ne et la placer dans le tray.
	 *
	 * Si l'utilisateur est logg� et que la session est ouverte, l'ic�ne est automatiquement
	 * initialis�e et affich�e.
	 *
	 * @param object data Configuration de l'ic�ne.
	 * @return TrayIcon En cas de succ�s.
	 * @return null Si data ne contient pas d'attribut name.
	 * @access public
	 */
	addTrayIcon: function (data) {
		// Erreur
		if (!data.name) {
			alert('Error in Soho.ui.addTrayIcon: icon name missing');
			return null;
		}
		var obj;
		// La TrayIcon n'existe pas
		if (!(data.name in uidata.trayIcons)) {
			obj = new Soho.TrayIcon(data);
			uidata.trayIcons[data.name] = obj;
		}
		else {
			obj = uidata.trayIcons[data.name];
		}
		// Si la session est ouverte, que les composants UI ont bien été intialisés,
		// et que cette icone n'a jamais été initialisé, c'est le moment de le faire.
		if (Soho.isLogged() && uidata.uiComponents && !obj.initialized) {
			Soho.ui.initTrayIcon(obj);
		}
		return obj;
	},

	/**
	 * @return boolean
	 */
	removeTrayIcon: function (name) {
		// TODO !!!
	},

	/**
	 * @return boolean
	 */
	removeAllTrayIcons: function () {
		// TODO !!!
		if (console) {
			console.warn('[todo] WG.ui.removeAllTrayIcons()');
		}
		// Note: il faut aussi s'occuper des icones listeners
		// Il faut envoyer un signal à l'icône pour qu'elle se désactive bien
		// + Propager les events onHide et onDestroy
	},

	/**
	 * @return Object<TrayIcon>
	 */
	getTrayIcons: function () {
		return uidata.trayIcons;
	},

	/**
	 * @return TrayIcon|null
	 */
	getTrayIcon: function (name) {
		return uidata.trayIcons.hasOwnProperty(name) ? uidata.trayIcons[name] : null;
	},

	/**
	 * @return int
	 */
	getTrayIconsCount: function () {
		return Object.keys(uidata.trayIcons).length;
	},

	/* * *   TrayMenu   * * */

	TrayMenuStack: {
		TOP: 0,
		MIDDLE: 5,
		BOTTOM: 10
	},

	// TODO Gérer le stack position
	addTrayMenuItem: function (name, label, stack, onClick, shortcut) {
		// Create list item
		var li = document.createElement('li');
		// Item name
		li.setAttribute('name', name);
		// Keyboard shortcut
		if (shortcut) {
			li.setAttribute('vkb', shortcut);
		}
		// Item label
		li.innerHTML = label;
		// On click event
		li.onclick = function (e) {
			// Hide menu
			jQuery(Soho.ui.getTrayIcon('power').trayMenu).hide();
			// Throw event
			onClick(e);
		};
		// Add item to menu
		Soho.ui.getTrayIcon('power').trayMenu.appendChild(li);
	},

	removeTrayMenuItem: function (name) {
		// TODO
	},

	removeAllTrayMenuItems: function () {
		Soho.ui.getTrayIcon('power').trayMenu.innerHTML = '';
	},

	theme: {
		rgb2hex: function (color) {
			color = color.match(/^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/);
			function hex(x) {
				return ("0" + parseInt(x).toString(16)).slice(-2);
			}
			return hex(color[1]) + hex(color[2]) + hex(color[3]);
		},
		getBackgroundColor: function () {
			return Soho.ui.theme.rgb2hex(jQuery('body').css('background-color'));
		},
		getForegroundColor: function () {
			return Soho.ui.theme.rgb2hex(jQuery('body').css('color'));
		}
	}

};