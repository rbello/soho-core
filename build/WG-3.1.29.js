WG = (function () {

	/**
	 * TODO doc
	 *
	 * @access private
	 */
	var appdata = {
		wg_appName: "SoHo",
		wg_appVersion: "3.0",
		wg_url: "/",
		wg_lastUpdate: 0,
		wg_serverTimeOffset: null,
		wg_updateDelay: 30000,
		wg_sessionAge: 0,
		wg_autolockDelay: 0,
		wg_started: false
	};

	/**
	 * TODO doc
	 *
	 * @access private
	 */
	var userdata = {
		wg_logged: false,
		wg_login: null,
		wg_name: null,
		wg_avatar: null,
		wg_pwdhash: null
	};

	/**
	 * TODO doc
	 *
	 * @access protected
	 */
	viewdata = {
		// Les vues disponibles : chargées par WELCOME
		available: {},
		// Les vues déjà chargées
		loaded: { }
	};

	/**
	 * TODO doc
	 *
	 * @access protected
	 */
	uidata = {
		lastAction: 0, // Dernier mouvement de la souris, utilisé par l'autolock
		autolockThread: null, // Id 
		trayIcons: {}, // Icônes du tray
		uiComponents: null // Tous les composants statiques de l'IHM
	};

	/**
	 * TODO doc
	 *
	 * @access private
	 */
	var stopLive = function () {
		if (WG.live) {
			WG.live.stop();
			WG.live = null;
		}
	};

	/**
	 * TODO doc
	 *
	 * @access private
	 */
	var startLive = function () {
		if (!WG.live) {
			WG.live = new WG.LiveService();
		}
	};

	/**
	 * TODO doc
	 *
	 * @access private
	 */
	var open = function (data) {
		
		// Log
		if (console.log) {
			console.log('[start] Open user session...');
		}

		// TODO Checker la validité de data ?

		// Update data
		userdata.wg_logged = true;
		userdata.wg_login = data.sessiondata.userLogin;
		userdata.wg_name = data.sessiondata.userName;
		userdata.wg_avatar = data.sessiondata.userAvatar;
		userdata.wg_pwdhash = data.sessiondata.userPwdHash;
		appdata.wg_lastUpdate = data.sessiondata.serverTime; // sec
		appdata.wg_sessionAge = data.sessiondata.sessionAge;
		appdata.wg_autolockDelay = data.settings.autolock * 60000; // min to ms
		
		// On enregistre l'offset du temps serveur
		// Le fait de multiplier par 1 converti -0200 et -200
		appdata.wg_serverTimeOffset = (1 * data.time.serverGMT);
		
		// Load CSS
		var css = document.createElement('link');
		css.type = 'text/css';
		css.rel = 'stylesheet';
		css.href = appdata.wg_url + 'css.php?t=' + new Date().getTime();
		var s = document.getElementsByTagName('link')[0];
		s.parentNode.insertBefore(css, s);
		if (console) {
			console.log('[start] Load modules CSS stylesheet (' + appdata.wg_url + 'css.php)');
		}

		// Note : load javascript is deprecated, views should use
		// script src="" /script to load module's scripts
		
		// Set nightmode
		if (localStorage) {
			if (localStorage.getItem('WG.nightmode') === 'on') {
				document.body.setAttribute('nightmode', 'on');
			}
		}

		// Save available views
		viewdata.available = data.views;

		// Create main menu
		WG.ui.createMenu(data.menu);

		// Init tray icons
		WG.ui.createTrayIcons();

		// Add options in tray menu
		WG.ui.addTrayMenuItem(
			'nightmode',
			'Switch night mode...',
			WG.ui.TrayMenuStack.TOP,
			function () {
				var body = $('body#wg');
				// Nightmode OFF
				if (body.hasAttr('nightmode')) {
					// Remove look&feel
					body.removeAttr('nightmode');
					// Save in localStorage
					if (localStorage) {
						localStorage.setItem('WG.nightmode', 'off');
					}
				}
				// Nightmode ON
				else {
					// Set look&feel
					body.attr('nightmode', 'on');
					// Save in localStorage
					if (localStorage) {
						localStorage.setItem('WG.nightmode', 'on');
					}
				}
				// Ici le seul moyen de faire un repaint des scrollbar
				// c'est de retirer le contenu et le remettre, mais ça nique les listeners
				//$('body > *').detach().appendTo('body');
			}
		);
		WG.ui.addTrayMenuItem(
			'lock',
			'Lock my session...',
			WG.ui.TrayMenuStack.BOTTOM,
			function () { lock(); }
		);
		WG.ui.addTrayMenuItem(
			'logout',
			'Log out...',
			WG.ui.TrayMenuStack.BOTTOM,
			function () { logout(); }
		);

		// Apply default behavior
		WG.View.applyStandardBehavior(uidata.uiComponents.main);

		// Autolock thread
		if (appdata.wg_autolockDelay > 0) {
			uidata.autolockThread = setInterval(
				function () {
					if (new Date().getTime() - uidata.lastAction > appdata.wg_autolockDelay) {
						lock();
					}
				},
				30000
			);
		};
		
		// Enable live service
		startLive();

		// Switch to last view
		if (localStorage && localStorage.getItem('WG.defaultView')) {
			WG.setView(localStorage.getItem('WG.defaultView'));
		}
		else {
			WG.setView('dashboard');
		}
		
		// Update last action
		uidata.lastAction = new Date().getTime();
		
	};

	/**
	 * TODO doc
	 *
	 * @access private
	 */
	var destroy = function () {
		// Reset data
		appdata = {
			wg_lastUpdate:-1,
			wg_updateDelay:-1,
			wg_sessionAge:-1,
			wg_started:true
		};
		userdata = {
			wg_logged:false,
			wg_login:null,
			wg_name:null,
			wg_avatar:null,
			wg_pwdhash:null
		};
		// Remove views infos
		viewdata = {
			available: {},
			loaded: { }
		};
		// Stop live service
		stopLive();
		// Reset options in tray menu
		WG.ui.removeAllTrayMenuItems();
		// Destroy main menu
		WG.ui.destroyMenu();
		// Destroy tray icons
		WG.ui.removeAllTrayIcons();
		// Remove current view
		WG.ui.removeCurrentView();
		// Clean history
		WG.ui.viewHistory = {};
		// Stop autolock thread
		if (uidata.autolockThread !== null) {
			clearInterval(uidata.autolockThread);
			uidata.autolockThread = null;
		}
		// Remove lock icon to app name
		uidata.uiComponents.appName.setAttribute('class', '');
		// TODO : contrecarrer cette fonction appelée dans open()
		//WG.View.applyStandardBehavior(uidata.uiComponents.wrapper);
		// Clean local storage
		if (localStorage) {
			localStorage.removeItem('WG.lock');
			localStorage.removeItem('WG.defaultView');
			//localStorage.removeItem('WG.nightmode');
		}
	};

	/**
	 * TODO doc
	 * TODO Move to security.js
	 *
	 * @access protected
	 */
	logout = function () {

		// If not logged
		if (!userdata.wg_logged) {
			WG.setStatus('You are not logged.', WG.status.ALERT, 'close');
			return;
		}

		// Update UI
		WG.setStatus('Logout...', WG.status.WAIT);

		// Save APP url
		var url = appdata.wg_url;
		
		// Destroy session
		destroy();

		// Ask logout webservice
		WG.ajax({
			url: url + 'ws.php',
			data: {
				'w': 'auth',
				'logout': 'please'
			},
			success: function () {
				setTimeout('window.location.reload()', 1600);
				WG.setStatus(
					'You are logged out.',
					WG.status.SUCCESS
				);
			},
			error: function (data, textStatus, jqXHR) {
				setTimeout('window.location.reload()', 10);
				if (textStatus != 'success') {
					WG.setStatus(
						'Unable to log out: ' + textStatus,
						WG.status.FAILURE
					);
				}
			}
		});

	};

	/**
	 * TODO doc
	 *
	 * @access protected
	 */
	lock = function () {
		// Don't lock if the session is not open
		if (!userdata.wg_logged) {
			return;
		}
		// Avoid autolock loop
		if (uidata.uiComponents.locker.style.display == 'block') {
			return;
		}
		// Log
		if (console) {
			console.log('Lock');
		}
		// Construct locker overlay
		uidata.uiComponents.locker.innerHTML = '<h1>' + WG.util.htmlspecialchars(appdata.wg_appName)
			+ '</h1><p>This session is locked by <b>' + WG.util.htmlspecialchars(userdata.wg_name)
			+ '</b><br />Enter your password</p>';
		// Password field
		var input = document.createElement('input');
		input.setAttribute('type', 'password');
		input.onkeydown = function () {
			this.style.backgroundColor = '#fff';
		};
		input.onblur = function () {
			this.focus();
		};
		// Form
		var form = document.createElement('form');
		form.onsubmit = function () {
			if (WG.security.sha1(userdata.wg_login + ':' + input.value).substr(0, 15) === userdata.wg_pwdhash) {
				unlock();
			}
			else {
				input.value = '';
				input.style.backgroundColor = '#666';
			}
			return false;
		};
		form.appendChild(input);
		// Install content
		uidata.uiComponents.locker.appendChild(form);
		// Display locker overlay
		uidata.uiComponents.locker.style.display = 'block';
		// Give focus to password field
		input.focus();
		// Save lock in local storage
		if (localStorage) {
			localStorage.setItem('WG.lock', true);
		}
	};

	/**
	 * TODO doc
	 *
	 * @access protected
	 */
	unlock = function () {
		// Log
		if (console) {
			console.log('Unlock');
		}
		// Hide locker overlay
		uidata.uiComponents.locker.style.display = 'none';
		// Remove lock in local storage
		if (localStorage) {
			localStorage.removeItem('WG.lock');
		}
		// Update last action timestamp
		uidata.lastAction = new Date().getTime();
	};

	/**
	 * TODO doc
	 *
	 * @access protected
	 */
	welcome = function (success, error) {
		
		// Log
		if (console) {
			console.log('[start] Ask for welcome message...');
		}
		
		// Update UI
		WG.setStatus('Get welcome message...', WG.status.WAIT);

		// On error callback
		var onError = function (jqXHR, textStatus, errorThrown) {
			// Log
			if (console) {
				console.error('[start] Unable to get welcome message. This attempt has been logged.');
			}
			// Update UI
			WG.setStatus(
				'Unable to reach welcome service: <em>' + errorThrown + '</em>',
				WG.status.FAILURE,
				function () {
					WG.setView('login', null, true);
				}
			);
			// Callback
			if (error) error();
		}

		// On success callback
		var onSuccess = function (data) {
			// Update UI
			WG.setStatus('Open session...', WG.status.WAIT);
			// Unlock session
			open(data);
			// Update UI
			WG.setStatus(null);
			// Callback
			if (success) success();
		}

		// Query to server
		WG.ajax({
			url: appdata.wg_url + 'ws.php',
			data: {
				'w': 'welcome'
			},
			success: onSuccess,
			error: onError
		});

	};

	/**
	 * TODO doc
	 *
	 * @access private
	 */
	var start = function () {

		if (appdata.wg_started) {
			throw 'WG is allready started';
		}

		// Log
		if (console) {
			console.log('[start] WG is starting, appURL is: ' + appdata.wg_url);
		}

		// Trigger event
		WG.trigger('beforeStart');

		// Set body's ID
		document.body.setAttribute('id', 'wg');
		
		// Create UI
		uidata.uiComponents = WG.ui.createUIComponents();

		// Install UI
		document.body.appendChild(uidata.uiComponents.container);
		
		// Install UI events listeners
		WG.ui.bindEvents();

		// Application is started
		appdata.started = true;
		
		// If the user is not logged in
		if (!userdata.wg_logged) {
			if (console) {
				console.log('[start] Start is finished. User is not logged: display login view.');
			}
			WG.setView('login', null, true);
		}
		// If the user is logged in, we ask for the welcome message
		else {
			if (console) {
				console.log('[start] Start is finished. User is allready logged: get welcome message.');
			}
			// Lock authentication process
			/*WG.security.inOperation = true;
			// Update UI
			WG.setStatus('Open secured channel...', WG.status.WAIT);
			// Try to open AES channel
			WG.security.openAES(
				// Authentication success
				function () {
					// Finish operation
					WG.security.inOperation = false;
					// Welcome
					welcome(function () {
						// Lock
						lock();
					});
				},
				// Authentication failed
				function () {
					// Finish operation
					WG.security.inOperation = false;
					// Update UI
					WG.setStatus(
						'Unable to open secured channel. To retry, hit F5.',
						WG.status.FAILURE,
						function () {
							// Remove AES data
							WG.security.AES = null;
							// Open session
							welcome(function () {
								// Lock
								lock();
							});
						},
						'Open a low security session'
					);
				}
			);
			*/
			welcome();
		}
	};

	// Public interface
	// Can be extended using WG.foo = bar
	return {

		init: function (config) {
			
			// Allready initialized
			if ($('body').attr('id') == 'wg') {
				return false;
			}
			
			// Log
			if (console) {
				console.log('[start] Initialization...');
			}
			
			// Config
			if ('appName' in config) {
				document.title = appdata.wg_appName = config.appName;
			}
			if ('appVersion' in config) {
				appdata.wg_appVersion = config.appVersion;
			}
			if ('appUrl' in config) {
				appdata.wg_url = config.appUrl;
			}
			if ('lastUpdate' in config) {
				appdata.wg_lastUpdate = parseInt(config.lastUpdate);
			}
			if ('updateDelay' in config) {
				appdata.wg_updateDelay = parseInt(config.updateDelay);
				if (console) {
					console.warn('[deprecated] Usage of appdata.wg_updateDelay is deprecated');
				}
			}
			if ('sessionAge' in config) {
				appdata.wg_sessionAge = parseInt(config.sessionAge);
				if (console) {
					console.warn('[deprecated] Usage of appdata.wg_sessionAge is deprecated');
				}
			}
			if ('logged' in config) {
				userdata.wg_logged = (config.logged === true);
			}
			
			// Remove NOJS warning
			$('body .nojs').remove();
			
			// Start WG !
			start();

		},
		
		appName: function () {
			return appdata.wg_appName;
		},
		appURL: function () {
			return appdata.wg_url;
		},
		userName: function () {
			return userdata.wg_name;
		},
		userAvatar: function () {
			return userdata.wg_avatar;
		},
		lastUpdate: function () {
			return appdata.wg_lastUpdate;
		},
		
		live: null,
		
		trim: jQuery.trim,

		isLogged: function () {
			return userdata.wg_logged;
		},

		setView: function (view, param, noCache, hash) {
			WG.ui.setView(view, param, noCache, hash);
			return WG;
		},

		currentView: function () {
			return WG.ui.currentView;
		},

		/**
		 * @param map args (required)
		 * @param boolean cache (default: false)
		 * @return jqXHR http://api.jquery.com/jQuery.ajax/#jqXHR
		 * @access public
		 * @issue On dirait que le paramètre context fonctionne mal
		 */
		ajax: function (args, cache) {

			// On regarde si le mode AES est activé
			var useAES = WG.security.AES != null;

			// Default data type is json
			var dataType = 'dataType' in args ? args.dataType : 'json';

			// On prépare la config de la requête ajax
			var p = {
				cache : (cache === true),
				url: args.url,
				dataType: useAES ? 'text' : dataType, // Override datatype if AES is used
				type: 'POST',
				success: function (data, textStatus, jqXHR) {
					// AES decrypt
					if (WG.security.AES != null) {
						// Decrypt data
						//console.log('AVANT: ' + data);
						data = WG.security.aesDecrypt(data);
						//console.log('APRES: ' + data);
						// Parse data as JSON (only if datatype is json)
						if (dataType == 'json') {
							try {
								data = JSON.parse(data);
							} catch (e) {
								// Propagate event
								if ('error' in args) {
									args.error(jqXHR, textStatus, 'invalid JSON after decrypt');
								}
								return;
							}
						}
					}
					/*else if (!(data instanceof Object)) {
						// Propagate event
						if ('error' in args) {
							args.error(jqXHR, textStatus, 'invalid JSON');
						}
						return;
					}*/
					// Propagate event
					if ('success' in args) {
						args.success(data, textStatus, jqXHR);
					}
				},
				error: function (jqXHR, textStatus, errorThrown) {
					// Propagate event
					if ('error' in args) {
						args.error(jqXHR, textStatus, errorThrown);
					}
				}
			};

			// Si il y a des donnéees à envoyer
			if ('data' in args) {
				// Si on utilise AES, on va crypter les données
				if (useAES) {
					for (name in args.data) {
						args.data[name] = $.jCryption.encrypt('' + args.data[name], WG.security.AES.password);
					}
				}
				// On ajoute des données à la requête
				p.data = args.data;
			}

			// Si un context a été spécifié
			if ('context' in args) {
				p.context = args.context;
			}

			// Log
			if (console) {
				console.log('[ajax] POST ' + args.url + ' (AES=' + (useAES ? 'on' : 'off')+', type='+dataType+', cache='+(cache === true)+')');
			}

			// On execute la requête
			return jQuery.ajax(p);

		},

		/**
		 * Status
		 * @access public
		 */
		status: {
			WAIT: 0,
			OK: 1,
			SUCCESS: 1,
			INFO: 5,
			NOTICE: 5,
			ALERT: 10,
			WARNING: 10,
			ERROR: 20,
			FAILURE: 20
		},
		
		/**
		 * @access public
		 */
		setStatus: function (msg, status, onContinue, continueLabel) {
			var el = uidata.uiComponents.status;
			// Hide status indicator
			if (!msg) {
				el.style.display = 'none';
				return this;
			}
			// Show status indicator
			el.style.display = 'block';
			// Set visual class
			var theclass = '';
			switch (status) {
				case WG.status.WAIT : theclass = 'wait-msg'; break;
				case WG.status.SUCCESS : theclass = 'success-msg'; break;
				case WG.status.INFO :
				case WG.status.NOTICE : theclass = 'info-msg'; break;
				case WG.status.ALERT :
				case WG.status.WARNING : theclass = 'alert-msg'; break;
				case WG.status.ERROR :
				case WG.status.FAILURE : theclass = 'failure-msg'; break;
			}
			el.setAttribute('class', theclass);
			// Change contents
			el.innerHTML = msg;
			// Continue link
			if (onContinue) {
				if (onContinue == 'close') {
					continueLabel = 'close';
					onContinue = function () {
						WG.setStatus(null);
					};
				}
				else if (!continueLabel) {
					continueLabel = 'continue';
				}
				var a = document.createElement('a');
				a.innerHTML = continueLabel;
				a.onclick = onContinue;
				a.setAttribute('class', 'continue');
				a.setAttribute('href', 'javascript:;');
				el.appendChild(a);
				a.focus();
			}
			return WG;
		},
		
		time: {
			
			getLocalTime: function () {
				return new Date();
			},
			
			getServerOffset: function () {
				return appdata.wg_serverTimeOffset;
			},
			
			getServerTime: function () {
				
				// Si on a pas encore reçu la timezone du serveur, on renvoi 
				// une date avec la config locale du client.
				if (appdata.wg_serverTimezone === null) {
					return new Date();
				}
	
				// On détermine les éléments dont on va avoir besoin
				var localDate = new Date(),
					localOffset = localDate.getTimezoneOffset(),
					serverOffset = appdata.wg_serverTimeOffset,
					serverTz = (serverOffset / 100 * -1) * 60,
					timeDiff = (localOffset - serverTz) * 60000;
				
				// On calcule le temps différé
				var shiftedTime = serverOffset < 360 ? localDate.getTime() + timeDiff : localDate.getTime() - timeDiff;
	
				// On renvoi un objet Date avec la config locale du serveur
				return new Date(shiftedTime);
				
			}
		}

	};

})();WG.util = {

	/**
	 * @param Object|Array obj
	 * @param Function callback
	 * @return void
	 */
	each: function (obj, callback) {
		if (!obj) return;
		if (obj instanceof Object) {
			for (v in obj) {
				callback(obj[v], v);
			}
		}
	},

	createElement: function (tagname, attributes, parent) {
		var el = document.createElement(tagname);
		if (attributes) {
			for (attr in attributes) {
				if (attr == 'onclick') {
					el.onclick = attributes[attr];
				}
				else {
					el.setAttribute(attr, attributes[attr]);
				}
			}
		}
		if (parent) {
			parent.appendChild(el);
		}
		return el;
	},

	/**
	 * @param Object.prototype proto
	 * @return void
	 */
	implementListenerPattern: function (proto) {
		proto.bind = function (event, callback, one) {
			if (!this.listeners) {
				this.listeners = [];
			}
			this.listeners.push({
				e: event,
				c: callback,
				o: one === true
			});
			return this;
		};
		proto.one = function (event, callback) {
			return this.bind(event, callback, true);
		};
		proto.unbind = function (event, callback) {
			if (!this.listeners) {
				return false;
			}
			if (event && callback) {
				for (var i = 0, j = this.listeners.length; i < j; i++) {
					 var li = this.listeners[i];
					 if (!li) continue;
					 if ((li.e === event || event === "*") && li.c === callback) {
						this.listeners.splice(i, 1);
						return true;
					 }
				}
			}
			else if (event) {
				for (var i = 0, j = this.listeners.length; i < j; i++) {
					 var li = this.listeners[i];
					 if (!li) continue;
					 if (li.e === event || event === "*") {
						this.listeners.splice(i, 1);
						return true;
					 }
				}
			}
			else {
				this.listeners = [];
				return true;
			}
			return false;
		};
		proto.trigger = function (event, data) {
			if (!this.listeners) {
				return this;
			}
			// Plutot utiliser un foreach non ? car le one va supprimer les cl�s...
			for (var i = 0, j = this.listeners.length; i < j; i++) {
				 var li = this.listeners[i];
				 if (!li) continue;
				 if (li.e === event || li.e === '*') {
					this.eventDispath = li.c;
					this.eventDispath(data, this, event);
					if (li.o) {
						this.listeners.splice(i, 1);
					}
				 }
			}
			this.eventDispath = null;
			return this;
		};
	},

	// http://phpjs.org/functions/htmlspecialchars:426
	htmlspecialchars: function (string, quote_style, charset, double_encode) {
		var optTemp = 0,
			i = 0,
			noquotes = false;
		if (typeof quote_style === 'undefined' || quote_style === null) {
			quote_style = 2;
		}
		string = string.toString();
		if (double_encode !== false) { // Put this first to avoid double-encoding
			string = string.replace(/&/g, '&amp;');
		}
		string = string.replace(/</g, '&lt;').replace(/>/g, '&gt;');

		var OPTS = {
			'ENT_NOQUOTES': 0,
			'ENT_HTML_QUOTE_SINGLE': 1,
			'ENT_HTML_QUOTE_DOUBLE': 2,
			'ENT_COMPAT': 2,
			'ENT_QUOTES': 3,
			'ENT_IGNORE': 4
		};
		if (quote_style === 0) {
			noquotes = true;
		}
		if (typeof quote_style !== 'number') { // Allow for a single string or an array of string flags
			quote_style = [].concat(quote_style);
			for (i = 0; i < quote_style.length; i++) {
				// Resolve string input to bitwise e.g. 'ENT_IGNORE' becomes 4
				if (OPTS[quote_style[i]] === 0) {
					noquotes = true;
				}
				else if (OPTS[quote_style[i]]) {
					optTemp = optTemp | OPTS[quote_style[i]];
				}
			}
			quote_style = optTemp;
		}
		if (quote_style & OPTS.ENT_HTML_QUOTE_SINGLE) {
			string = string.replace(/'/g, '&#039;');
		}
		if (!noquotes) {
			string = string.replace(/"/g, '&quot;');
		}

		return string;
	},

	// http://phpjs.org/functions/nl2br:480
	nl2br: function (str, is_xhtml) {
		var breakTag = (is_xhtml || typeof is_xhtml === 'undefined') ? '<br />' : '<br>';
		return (str + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1' + breakTag + '$2');
	},

	// http://phpjs.org/functions/str_replace:527
	str_replace: function (search, replace, subject, count) {
		var i = 0,
			j = 0,
			temp = '',
			repl = '',
			sl = 0,
			fl = 0,
			f = [].concat(search),
			r = [].concat(replace),
			s = subject,
			ra = Object.prototype.toString.call(r) === '[object Array]',
			sa = Object.prototype.toString.call(s) === '[object Array]';
			s = [].concat(s);
		if (count) {
			this.window[count] = 0;
		}
		for (i = 0, sl = s.length; i < sl; i++) {
			if (s[i] === '') {
				continue;
			}
			for (j = 0, fl = f.length; j < fl; j++) {
				temp = s[i] + '';
				repl = ra ? (r[j] !== undefined ? r[j] : '') : r[0];
				s[i] = (temp).split(f[j]).join(repl);
				if (count && s[i] !== temp) {
					this.window[count] += (temp.length - s[i].length) / f[j].length;
				}
			}
		}
		return sa ? s : s[0];
	},

	// http://stackoverflow.com/questions/37684/how-to-replace-plain-urls-with-links
	url2links: function (text) {
		var exp = /(\b(https?|ftp|file):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/ig;
		return text.replace(exp,"<a href='$1' target='_blank'>$1</a>"); 
	},

	asynchFileUpload: function (form, success, error) {
		// Remember when the upload begins
		var now = new Date().getTime();
		// Unique ID for operation
		var id = 'file-upload-asynch-' + now + '-' + Math.round(Math.random() * 9999);
		// Create an iframe
		var iframe = document.createElement('iframe');
		iframe.setAttribute('id', id);
		iframe.setAttribute('name', id);
		iframe.setAttribute('loaded', 'false');
		// Insert iframe in global container
		uidata.uiComponents.main.appendChild(iframe);
		// Listen at onLoad event on 
		iframe.onload = function () {
			if (this.getAttribute('loaded') != 'true') {
				// The frame is allready loaded
				this.loaded = 'true';
				// Get iframe's body
				var body = iframe.contentDocument.getElementsByTagName('body')[0];
				// The frame is allready loaded
				iframe.loaded = 'true';
				// Parse result data as JSON
				var data = body.innerHTML,
					json = null;
				try {
					json = JSON.parse(body.innerHTML);
				} catch (ex) {
					json = null;
				}
				// Remove the frame
				$(iframe).remove();
				// Invalid JSON
				if (json == null) {
					error(data);
				}
				// Valid
				else if ('upload' in json && json.upload == 'OK') {
					success(json);
				}
				// Server error
				else {
					error(json);
				}
			}
		};
		// Change form target to the iframe
		form.target = id;
		// Let the form submit
		return true;
	},

	// http://phpjs.org/functions/parse_url:485
	parse_url: function (str, component) {
		var	key = ['source', 'scheme', 'authority', 'userInfo', 'user', 'pass', 'host', 'port',
				'relative', 'path', 'directory', 'file', 'query', 'fragment'],
			ini = (this.php_js && this.php_js.ini) || {},
			mode = (ini['phpjs.parse_url.mode'] && 
			ini['phpjs.parse_url.mode'].local_value) || 'php',
			parser = {
			php: /^(?:([^:\/?#]+):)?(?:\/\/()(?:(?:()(?:([^:@]*):?([^:@]*))?@)?([^:\/?#]*)(?::(\d*))?))?()(?:(()(?:(?:[^?#\/]*\/)*)()(?:[^?#]*))(?:\?([^#]*))?(?:#(.*))?)/,
			strict: /^(?:([^:\/?#]+):)?(?:\/\/((?:(([^:@]*):?([^:@]*))?@)?([^:\/?#]*)(?::(\d*))?))?((((?:[^?#\/]*\/)*)([^?#]*))(?:\?([^#]*))?(?:#(.*))?)/,
			loose: /^(?:(?![^:@]+:[^:@\/]*@)([^:\/?#.]+):)?(?:\/\/\/?)?((?:(([^:@]*):?([^:@]*))?@)?([^:\/?#]*)(?::(\d*))?)(((\/(?:[^?#](?![^?#\/]*\.[^?#\/.]+(?:[?#]|$)))*\/?)?([^?#\/]*))(?:\?([^#]*))?(?:#(.*))?)/ // Added one optional slash to post-scheme to catch file:/// (should restrict this)
			};
		var m = parser[mode].exec(str),
		uri = {},
		i = 14;
		while (i--) {
			if (m[i]) {
				uri[key[i]] = m[i];  
			}
		}
		if (component) {
			return uri[component.replace('PHP_URL_', '').toLowerCase()];
		}
		if (mode !== 'php') {
			var name = (ini['phpjs.parse_url.queryKey'] && 
			ini['phpjs.parse_url.queryKey'].local_value) || 'queryKey';
			parser = /(?:^|&)([^&=]*)=?([^&]*)/g;
			uri[name] = {};
			uri[key[12]].replace(parser, function ($0, $1, $2) {
				if ($1) {uri[name][$1] = $2;}
			});
		}
		delete uri.source;
		return uri;
	}

};

// Make WG listenable
WG.util.implementListenerPattern(WG);

// Create jQuery.reverse() to reverse a collection of jQuery elements.
$.fn.reverse = [].reverse;

// Create jQuery.hasAttr() function
$.fn.hasAttr = function(name) {  
   return this.attr(name) !== undefined;
};
WG.security = {

	inOperation: false,

	login: function (form) {
		
		// On test si le formulaire est expiré
		var expires = parseInt(form.getAttribute('expires')) * 1000;
		if (expires <= new Date().getTime()) {
			// On masque le formulaire
			form.style.display = 'none';
			// On programme une callback dans 3 secondes pour recharger le formulaire
			var timer = setTimeout(function () {
				WG.setView('login', null, true);
			}, 3000);
			// On affiche une notification d'erreur
			WG.setStatus(
				'Sorry, this form was expired. I will ask a new one...',
				WG.status.WARNING,
				function () {
					clearTimeout(timer);
					WG.setView('login', null, true);
				},
				'Go on!'
			);
			return false;
		}

		// Hide virtual keyboard
		WG.security.vkb.div.style.display = 'none';

		// Check if an operation is pending
		if (WG.security.inOperation) return false;

		// Get form data
		var salt = form.getAttribute('salt'),
			fields = form.getElementsByTagName('input'),
			loginfield = fields[0],
			passwordfield = fields[1],
			aesfield = fields[2],
			password = passwordfield.value;

		// Check data
		if (loginfield.value.length == 0) {
			loginfield.focus();
			return false;
		}
		if (password.length == 0) {
			passwordfield.focus();
			return false;
		}

		// Confirm unsecured connexion
		if (window.location.protocol != 'https:' && aesfield.checked !== true) {
			if (!confirm('This connection will be unsecured. Are you sure to continue?')) {
				return false;
			}
		}

		// Begin operation
		WG.security.inOperation = true;

		// Hide form
		form.style.display = 'none';

		// Reinforce password
		password = WG.security.sha1(loginfield.value + ':' + password);
		//console.log("AUTH sha1(" + loginfield.value + ':' + passwordfield.value + ") = " + password);
		//console.log("AUTH salt = " + salt);

		if (form.hasAttribute('apikey')) {
			password = 's:' + WG.security.sha1(salt + ':' + password + ':' + form.getAttribute('apikey'));
			//console.log("AUTH qop=s sha1(salt:password) = " + password);
		}
		else {
			password = 'b:' + WG.security.sha1(salt + ':' + password);
			//console.log("AUTH qop=b sha1(salt:password) = " + password);
		}
		
		// Fix pour le masquage du virtual kbd
		$(WG.security.vkb.div).hide().remove().appendTo('#viewLogin');

		// With AES channel
		if (aesfield.checked === true) {
			// Update UI
			WG.setStatus('Open secured channel...', WG.status.WAIT);
			// Open AES channel
			WG.security.openAES(
				// Authentication success
				function () {
					// Update UI
					WG.setStatus('Authentication...', WG.status.WAIT);
					// Create data
					var data = { };
					data[loginfield.getAttribute('name')] = loginfield.value;
					data[passwordfield.getAttribute('name')] = password;
					// Authentication
					WG.security.authenticate(data);
					// Finish operation
					WG.security.inOperation = false;
				},
				// Authentication failed
				function () {
					// Remove AES data
					WG.security.AES = null;
					// Update UI
					WG.setStatus('AES handshake failure', WG.status.FAILURE);
					// Finish operation
					WG.security.inOperation = false;
					// Back to login view
					setTimeout("WG.setView('login', null, true);", 1500);
				}
			);
		}

		// Without AES channel
		else {
			// Remove AES data
			WG.security.AES = null;
			// Wait message
			WG.setStatus('Authentication...', WG.status.WAIT);
			// Create data
			var data = { };
			data[loginfield.getAttribute('name')] = loginfield.value;
			data[passwordfield.getAttribute('name')] = password;
			// Authentication
			WG.security.authenticate(data);
		}

		return false;
	},

	authenticate: function (post) {

		// On error callback
		var onError = function (jqXHR, textStatus, errorThrown) {
			// Update UI
			WG.setStatus(
				'Authentication Failure: <em>' + errorThrown + '</em>',
				WG.status.FAILURE,
				function () {
					WG.setView('login', null, true);
				}
			);
			if (console) {
				console.error('[security] Authentication failure: ' + errorThrown);
			}
			// Remove AES data ?
			//WG.security.AES = null;
			// Finish operation
			WG.security.inOperation = false;
		};

		// On success callback
		var onSuccess = function (data, textStatus, jqXHR) {
			// Update UI
			WG.setStatus(null);
			if (console) {
				console.log('[security] Authentication success!');
			}
			// Finish operation
			WG.security.inOperation = false;
			// Unlock user shell
			welcome();
		};

		// Execute request
		post.w = 'auth';
		WG.ajax({
			url: WG.appURL() + 'ws.php',
			data: post,
			success: onSuccess,
			error: onError
		});

	},

	// AES data
	AES: null,

	// Create AES channel
	openAES: function (onSuccess, onError) {

		// Log
		if (console) {
			console.log('Open AES channel...');
		}

		// Initialize AES
		if (WG.security.AES == null) {
			var key = WG.security.random(32),
				hashObj = new jsSHA(key, 'ASCII');
			WG.security.AES = {
				'hashObj': hashObj,
				'password': hashObj.getHash('SHA-512', 'HEX')
			};
			/*console.log(' AES key clear: ' + key);
			console.log(' AES key hash: ' + WG.security.AES.password);*/
		}

		// Execute AES authentication
		$.jCryption.authenticate(
			WG.security.AES.password,
			WG.appURL() + 'publickeys.php',
			WG.appURL() + 'handshake.php',
			function () {
				// Add lock icon to app name
				uidata.uiComponents.appName.setAttribute('class', 'securized');
				// Callback
				onSuccess();
			},
			function () {
				// Remove AES data
				WG.security.AES = null;
				// Remove lock icon to app name
				uidata.uiComponents.appName.setAttribute('class', '');
				// Callback
				onError();
			}
		);
	},

	aesEncrypt: function (data) {
		/* Ce test ralenti alors qu'il ne doit de toute manière pas se déclancher
		if (WG.security.AES == null) {
			throw 'AES not initialized';
		}*/
		if (data instanceof Object) {
			for (key in data) {
				data[key] = WG.security.aesEncrypt(data);
			}
		}
		return $.jCryption.encrypt("" + data, WG.security.AES.password);
	},

	aesDecrypt: function (data) {
		/* Ce test ralenti alors qu'il ne doit de toute manière pas se déclancher
		if (WG.security.AES == null) {
			throw 'AES not initialized';
		}*/
		/*if (data instanceof Object) {
			for (key in data) {
				data[key] = WG.security.aesDecrypt(data);
			}
		}*/
		return $.jCryption.decrypt("" + data, WG.security.AES.password);
	},

	vkb: {
		pwd: null,
		div: null,
		timer: null,
		key: null,
		shifted: false,
		shift: function () {
			for (var i = 0; i < 4; i++) {
				document.getElementById('row' + i).style.display = this.shifted ? 'inherit' : 'none';
				document.getElementById('row' + i + '_shift').style.display = this.shifted ? 'none' : 'inherit';
			}
			this.shifted = !this.shifted;
		},
		keypress: function () {
			switch (this.key) {
				case 'Backspace' :
					if (WG.security.vkb.pwd.value.length > 0) {
						WG.security.vkb.pwd.value = WG.security.vkb.pwd.value.substr(0, WG.security.vkb.pwd.value.length - 1);
					}
					break;
				case 'Shift' :
					this.shift();
					break;
				case '&lt;' :
					WG.security.vkb.pwd.value += '<';
					break;
				case '&gt;' :
					WG.security.vkb.pwd.value += '>';
					break;
				default :
					WG.security.vkb.pwd.value += this.key;
					break;
			}
			this.timer = this.key = null;
		}
	},

	random: function (length) {
		var chars = '012345689ABCDEFGHIJKLMNOPRSTUVWXTZabcefghiklmopqrstuvwyz;:-.!@-_~$%*^+()[],/!|',
			r = '',
			l = chars.length;
		while (length-- > 0) {
			var s = Math.floor(Math.random() * l);
			r += chars.substring(s, s+1);
		}
		return r;
	},

	cookies: function () {
		var r = [];
		for (var i = 0, x, y, cookies = document.cookie.split(";"); i < cookies.length; i++) {
			x = cookies[i].substr(0, cookies[i].indexOf("="));
			y = cookies[i].substr(cookies[i].indexOf("=") + 1);
			x = x.replace(/^\s+|\s+$/g, "");
			r[x] = unescape(y);
		}
		return r;
	},
	
	cookie: function (name) {
		for (var i = 0, x, y, cookies = document.cookie.split(";"); i < cookies.length; i++) {
			x = cookies[i].substr(0, cookies[i].indexOf("="));
			y = cookies[i].substr(cookies[i].indexOf("=") + 1);
			x = x.replace(/^\s+|\s+$/g, "");
			if (name == x) {
				return unescape(y);
			}
		}
		return null;
	},
	
	phpSessionID: function () {
		return WG.security.cookie('PHPSESSID');
	},
	
	// http://phpjs.org/functions/sha1:512
	sha1: function (str) {
		var rotate_left = function (n, s) {
			var t4 = (n << s) | (n >>> (32 - s));
			return t4;
		};
		var cvt_hex = function (val) {
			var str = "";
			var i;
			var v;
			for (i = 7; i >= 0; i--) {
				v = (val >>> (i * 4)) & 0x0f;
				str += v.toString(16);
			}
			return str;
		};
		var blockstart;
		var i, j;
		var W = new Array(80);
		var H0 = 0x67452301;
		var H1 = 0xEFCDAB89;
		var H2 = 0x98BADCFE;
		var H3 = 0x10325476;
		var H4 = 0xC3D2E1F0;
		var A, B, C, D, E;
		var temp;
		//str = this.utf8_encode(str);
		var str_len = str.length;
		var word_array = [];
		for (i = 0; i < str_len - 3; i += 4) {
			j = str.charCodeAt(i) << 24 | str.charCodeAt(i + 1) << 16 | str.charCodeAt(i + 2) << 8 | str.charCodeAt(i + 3);
			word_array.push(j);
		}
		switch (str_len % 4) {
		case 0:
			i = 0x080000000;
			break;
		case 1:
			i = str.charCodeAt(str_len - 1) << 24 | 0x0800000;
			break;
		case 2:
			i = str.charCodeAt(str_len - 2) << 24 | str.charCodeAt(str_len - 1) << 16 | 0x08000;
			break;
		case 3:
			i = str.charCodeAt(str_len - 3) << 24 | str.charCodeAt(str_len - 2) << 16 | str.charCodeAt(str_len - 1) << 8 | 0x80;
			break;
		}
		word_array.push(i);
		while ((word_array.length % 16) != 14) {
			word_array.push(0);
		}
		word_array.push(str_len >>> 29);
		word_array.push((str_len << 3) & 0x0ffffffff);
		for (blockstart = 0; blockstart < word_array.length; blockstart += 16) {
			for (i = 0; i < 16; i++) {
			W[i] = word_array[blockstart + i];
			}
			for (i = 16; i <= 79; i++) {
				W[i] = rotate_left(W[i - 3] ^ W[i - 8] ^ W[i - 14] ^ W[i - 16], 1);
			}
			A = H0;
			B = H1;
			C = H2;
			D = H3;
			E = H4;
			for (i = 0; i <= 19; i++) {
				temp = (rotate_left(A, 5) + ((B & C) | (~B & D)) + E + W[i] + 0x5A827999) & 0x0ffffffff;
				E = D;
				D = C;
				C = rotate_left(B, 30);
				B = A;
				A = temp;
			}
			for (i = 20; i <= 39; i++) {
				temp = (rotate_left(A, 5) + (B ^ C ^ D) + E + W[i] + 0x6ED9EBA1) & 0x0ffffffff;
				E = D;
				D = C;
				C = rotate_left(B, 30);
				B = A;
				A = temp;
			}
			for (i = 40; i <= 59; i++) {
				temp = (rotate_left(A, 5) + ((B & C) | (B & D) | (C & D)) + E + W[i] + 0x8F1BBCDC) & 0x0ffffffff;
				E = D;
				D = C;
				C = rotate_left(B, 30);
				B = A;
				A = temp;
			}
			for (i = 60; i <= 79; i++) {
				temp = (rotate_left(A, 5) + (B ^ C ^ D) + E + W[i] + 0xCA62C1D6) & 0x0ffffffff;
				E = D;
				D = C;
				C = rotate_left(B, 30);
				B = A;
				A = temp;
			}
			H0 = (H0 + A) & 0x0ffffffff;
			H1 = (H1 + B) & 0x0ffffffff;
			H2 = (H2 + C) & 0x0ffffffff;
			H3 = (H3 + D) & 0x0ffffffff;
			H4 = (H4 + E) & 0x0ffffffff;
		}
		temp = cvt_hex(H0) + cvt_hex(H1) + cvt_hex(H2) + cvt_hex(H3) + cvt_hex(H4);
		return temp.toLowerCase();
	},
	
	// http://phpjs.org/functions/base64_encode:358
	base64_encode: function (data) {
	    var b64 = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";
	    var o1, o2, o3, h1, h2, h3, h4, bits, i = 0,
	        ac = 0,
	        enc = "",
	        tmp_arr = [];
	 
	    if (!data) {
	        return data;
	    }
	 
	    //data = this.utf8_encode(data + '');
	 
	    do { // pack three octets into four hexets
	        o1 = data.charCodeAt(i++);
	        o2 = data.charCodeAt(i++);
	        o3 = data.charCodeAt(i++);
	 
	        bits = o1 << 16 | o2 << 8 | o3;
	 
	        h1 = bits >> 18 & 0x3f;
	        h2 = bits >> 12 & 0x3f;
	        h3 = bits >> 6 & 0x3f;
	        h4 = bits & 0x3f;
	 
	        // use hexets to index into b64, and append result to encoded string
	        tmp_arr[ac++] = b64.charAt(h1) + b64.charAt(h2) + b64.charAt(h3) + b64.charAt(h4);
	    } while (i < data.length);
	 
	    enc = tmp_arr.join('');
	    
	    var r = data.length % 3;
	    
	    return (r ? enc.slice(0, r - 3) : enc) + '==='.slice(r || 3);
	},

	// http://phpjs.org/functions/base64_decode:357
	base64_decode: function (data) {
	    var b64 = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";
	    var o1, o2, o3, h1, h2, h3, h4, bits, i = 0,
	        ac = 0,
	        dec = "",
	        tmp_arr = [];
	 
	    if (!data) {
	        return data;
	    }
	 
	    data += '';
	 
	    do { // unpack four hexets into three octets using index points in b64
	        h1 = b64.indexOf(data.charAt(i++));
	        h2 = b64.indexOf(data.charAt(i++));
	        h3 = b64.indexOf(data.charAt(i++));
	        h4 = b64.indexOf(data.charAt(i++));
	 
	        bits = h1 << 18 | h2 << 12 | h3 << 6 | h4;
	 
	        o1 = bits >> 16 & 0xff;
	        o2 = bits >> 8 & 0xff;
	        o3 = bits & 0xff;
	 
	        if (h3 == 64) {
	            tmp_arr[ac++] = String.fromCharCode(o1);
	        } else if (h4 == 64) {
	            tmp_arr[ac++] = String.fromCharCode(o1, o2);
	        } else {
	            tmp_arr[ac++] = String.fromCharCode(o1, o2, o3);
	        }
	    } while (i < data.length);
	 
	    dec = tmp_arr.join('');
	    //dec = this.utf8_decode(dec);
	 
	    return dec;
	}

};
WG.ui = {


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
		appName.innerHTML = WG.util.htmlspecialchars(WG.appName());
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
		wrapper.setAttribute('class', 'fit-height');
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
		
		$(window)
		
		// Surveille le changement de hash pour gêrer l'historique
		.bind('hashchange', function (e) {
			
			// TODO Verifier que la session soit ouverte!
			
			// On verifie que l'historique contienne bien ce hash
			if (!(document.location.hash in WG.ui.viewHistory)) {
				return;
			}
			
			// On recupère les informations sur la vue
			var info = WG.ui.viewHistory[document.location.hash];
				
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
				view = new WG.View(info.name);
				viewdata.loaded[info.name] = view;
			}
			
			// Remove old view
			WG.ui.removeCurrentView();
			
			// Scroll top wrapper
			$(uidata.uiComponents.wrapper).scrollTop(0);
			
			// This is the current view
			WG.ui.currentView = view;
			
			// Append view to wrapper
			uidata.uiComponents.wrapper.appendChild(view.node);
			
			// Trigger event
			// On fait ça avant le refresh, pour que le script de la vue
			// puisse overrider les actions des listeners (par exemple sur
			// le focus)
			WG.trigger('viewChange', view.name);
			
			// Display view
			if (!view.display(info.param, (info.noCache === true), info.hash) && view.dist != WG.View.DistributionModel.KEEP_ALIVE) {
				// TODO Meilleur solution
				WG.ui.getTrayIcon('power').setNotification('This page has been restored from cache. Click here to refresh this page.', function () {
					WG.setView(view.name, null, true);
				}, 3000);
				// Trigger event
				WG.trigger('viewRestored', view.name);
			}
			
			// A la fin, on modifie l'historique pour que le système de cache se barre
			// TODO Il faudrait p'tet faire en sorte que WG.ui.viewHistory ne sature pas à force...
			WG.ui.viewHistory[document.location.hash].noCache = false;

			// Si un hash a été spécifié, on l'intégre maintenant
			// Un event hashchange sera diffusé, donc il y a un cas de conflit si
			// le hash voulu est de la forme 'view-X' et que cette vue est dans l'historique.
			// En pratique, ça devrait pas trop se produire...
			if (info.hash) {
				document.location.hash = info.hash;
			}
			
			// A la fin, on lance un event
			WG.trigger('viewChanged', view.name);
			
		})
		
		// Surveille le redimensionnement de la fenêtre pour le comportement fit-height 100%.
		// @see WG.View.applyStandardBehavior()
		.resize(function (e) {
			$('.fit-height').trigger('fitheight');
		});
		
	},
	
	/* * *   Views   * * */

	viewCount: 1,
	
	viewHistory: {},

	setView: function (viewname, param, noCache, hash) {
		
		// Hash de la vue 
		var hashPos = '#view-' + WG.ui.viewCount++;
				
		// Add to history navigation
		WG.ui.viewHistory[hashPos] = {"name": viewname, "param": param, "noCache": noCache, "hash": hash};
		
		// Save view name (to reopen previous view, surtout pour le debug!)
		if (localStorage && viewname != 'login') {
			localStorage.setItem('WG.defaultView', viewname);
		}

		// Remove status
		WG.setStatus(null);
		
		// Reset quicksearch
		WG.search.setDefault();
		
		// On modifie le hash, ce qui automatiquement trigger la modification de la vue 
		document.location.hash = hashPos;

	},

	currentView: null,

	removeCurrentView: function () {
		// Clean HTML container
		// Use jquery to remove?
		uidata.uiComponents.wrapper.innerHTML = '';
		// Remove current view
		if (WG.ui.currentView != null) {
			// Stop ajax request
			if (WG.ui.currentView.xhr) {
				if (console) {
					console.log('[ui] Stop loading for view: ' + WG.ui.currentView.name);
				}
				WG.ui.currentView.xhr.abort();
			}
			// Trigger event
			WG.trigger('viewRemoved', WG.ui.currentView.name);
			WG.ui.currentView = null;
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
					$('ul', this).show();
				};
				li.onmouseout = function () {
					$('ul', this).hide();
				};
			}

			// A
			var a = document.createElement('a');
			a.setAttribute('view', item.view);
			a.onclick = function () {
				WG.setView(this.getAttribute('view'));
			};
			a.innerHTML = WG.util.htmlspecialchars(item.label);
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
					a2.innerHTML = WG.util.htmlspecialchars(sub.label);
					a2.setAttribute('view', sub.view);
					a2.onclick = function () {
						WG.setView(this.getAttribute('view'));
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
			WG.ui.toggleMenuVisibility();
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
			$(uidata.uiComponents.menu).removeClass('wide');
			$(uidata.uiComponents.wrapper).removeClass('fit-width');
			$(uidata.uiComponents.menuOpts).removeClass('min');
		}
		else {
			//$(uidata.uiComponents.menu).hide();
			$(uidata.uiComponents.menu).addClass('wide');
			$(uidata.uiComponents.wrapper).addClass('fit-width');
			$(uidata.uiComponents.menuOpts).addClass('min');
		}
		
	},
	
	toggleMenuVisibility: function () {
		WG.ui.setMenuVisible($(uidata.uiComponents.menuOpts).hasClass('min'));	
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
		if (!WG.isLogged()) {
			return false;
		}
		WG.util.each(uidata.trayIcons, function (icon) {
			WG.ui.initTrayIcon(icon);
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
			alert('Error in WG.ui.addTrayIcon: icon name missing');
			return null;
		}
		var obj;
		// La TrayIcon n'existe pas
		if (!(data.name in uidata.trayIcons)) {
			// Cr�ation de l'ic�ne
			obj = new WG.TrayIcon(data);
			// Enregistrement de l'ic�ne
			uidata.trayIcons[data.name] = obj;
		}
		// La TrayIcon existe d�j�
		else {
			obj = uidata.trayIcons[data.name];
		}
		// Si la session est ouverte, que les composants UI ont bien �t� intialis�s,
		// et que cette icone n'a jamais �t� initialis�e, c'est le moment de le faire.
		if (WG.isLogged() && uidata.uiComponents && !obj.initialized) {
			WG.ui.initTrayIcon(obj);
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
		// Il faut envoyer un signale � l'ic�ne pour qu'elle se d�sactive bien
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

	// TODO G�rer le stack position
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
			$(WG.ui.getTrayIcon('power').trayMenu).hide();
			// Throw event
			onClick(e);
		};
		// Add item to menu
		WG.ui.getTrayIcon('power').trayMenu.appendChild(li);
	},

	removeTrayMenuItem: function (name) {
		// TODO
	},

	removeAllTrayMenuItems: function () {
		WG.ui.getTrayIcon('power').trayMenu.innerHTML = '';
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
			return WG.ui.theme.rgb2hex($('body').css('background-color'));
		},
		getForegroundColor: function () {
			return WG.ui.theme.rgb2hex($('body').css('color'));
		}
	}

};
/**
 * Constructor.
 *
 * @access public
 */
WG.LiveService = function () {
	var thiz = this;
	this.thead = setInterval(function () {
		thiz.refresh();
	}, 10000);
	if (console) {
		console.log('[live] Start live service...');
	}
};

/**
 * @access public
 */
WG.LiveService.prototype.refresh = function () {

	if (!WG.live) return;

	// Get query parameters
	var data = this.getParameters();

	// No events to listen
	if (!data) {
		return;
	}

	// Query to server
	WG.ajax({
		url: WG.appURL() + 'live.php',
		data: data,
		success: function (data) {
			if ('error' in data) {
				WG.ui.getTrayIcon('power').setNotification('Error in live service.');
				return;
			}
			// Update lastUpdate with server time
			WG.lastUpdate = data.serverTime;
			// Propagate event
			WG.live.propagation(data);
		},
		error: function () {
			WG.ui.getTrayIcon('power').setNotification('Unable to connect live service.');
		}
	});

};

/**
 * @access public
 */
WG.LiveService.prototype.getParameters = function () {
	var i = 0,
		events = { };
	// Fetch live listeners
	for (listener in WG.live.bind) {
		var l = WG.live.bind[listener];
		// Pour l'explication de ce truc, voir WG.LiveService.prototype.propagation()
		if (
			!('view' in l) || // Soit le listener n'est pas associ� � une vue pr�cise
			(WG.ui.currentView != null && WG.ui.currentView.name === l.view) // Soit il s'agit justement de cette vue l�
			) {
				// Dans ce cas, on idique que cet event est bien � demander au serveur
				events[l.event] = 1;
				i++;
		}
	}
	// Nothing to retreive
	if (i < 1) return null;
	// Create a string with events
	eventsList = [];
	for (event in events) {
		eventsList.push(event);
	}
	// Return request data
	return {
		't': WG.lastUpdate,
		'l': eventsList.join('|')
	};
};

/**
 * @access public
 */
WG.LiveService.prototype.stop = function () {
	if (console) {
		console.log('[live] Stop live service...');
	}
	clearInterval(this.thread); // marche pas ...
};

/**
 * HashMap with all listeners.
 * @access public
 */
WG.LiveService.prototype.bind = {};

/**
 * @access public
 */
WG.LiveService.prototype.propagation = function (data) {
	// Fetch live listeners
	for (listener in WG.live.bind) {
		var l = WG.live.bind[listener];
		// Dans un premier temps, on regarde si le listener a configur� une vue.
		// Si c'est le cas, on compare avec le nom de la vue actuelle. S'il s'agit
		// bien de la bonne vue on continue.
		// Donc, si le listener ne sp�cifie pas d'attribut 'view' il sera d�clanch�
		// � chaque fois.
		if (!('view' in l) || (WG.ui.currentView != null && WG.ui.currentView.name === l.view)) {
			// Ensuite on regarge si l'event que le listener a choisi a �t� diffus�
			// par le serveur.
			if (l.event in data) {
				// Si c'est le cas, on propage l'event au listener
				l.onChange(data[l.event]);
			}
		}
	}
}


/* POWER BUTTON */

WG.ui.addTrayIcon({

	name: 'power',

	onInit: function () {
		// Add icon class
		$(this.node).addClass('icon');
		// Create tray menu
		this.trayMenu = document.createElement('ul');
		this.trayMenu.setAttribute('class', 'drop-down');
		this.node.appendChild(this.trayMenu);
	},

	onClick: function () {
		$(this.trayMenu).toggle();
	}

});

/* CLOCK */

WG.ui.addTrayIcon({

	name: 'clock',

	onInit: function () {
		this.node.innerHTML = '--:--';
		// Refresh
		this.updateClock = function () {
			var time = WG.time.getServerTime();
			// Get jours & minutes
			var h = time.getHours(),
				m = time.getMinutes();
			if (h < 10) h = '0' + h;
			if (m < 10) m = '0' + m;
			this.node.innerHTML = h + ':' + m;
		}
	},

	onHide: function () {
		clearInterval(this.intervalThread);
	},

	onAppear: function () {
		this.updateClock();
		// Update thread
		var thiz = this;
		this.intervalThread = setInterval(function () {
			thiz.updateClock();
		}, 5000);
	}

});
// Extend WG
WG.search = {

	trayIcon: null,

	onQuickSearch: function (query, event, field) { },
	onQuickAction: function (query, event, field) { },
	onReset: function (event, field) { },

	setDefault: function () {
		//console.log('Quicksearch to default');
		
		// QUICK SEARCH
		WG.search.onQuickSearch = function (query) {
			$('[quicksearch]').each(function () {
				if (this.getAttribute('quicksearch').toLowerCase().match(query)) {
					$(this).show();
				}
				else {
					$(this).hide();
				}
			});
			return false;
		};
		
		// QUICK ACTION
		WG.search.onQuickAction = function (query, event, field) {
			var v = $('.[quicksearch]:visible');
			if (v.size() === 1) {
				url = v.attr('url');
				if (url != null && url.length > 0) {
					// Cette portion de code permet de restituer le contexte de l'execution
					if (url.substr(0, 11) == 'javascript:') {
						WG.search.onReset(event, field);
						e = v.get(0);
						e.qstmp = function (d) { eval(d); };
						e.qstmp(url.substr(11));
						e.qstmp = null;
					}
					else {
						//document.location.href = url;
						event.preventDefault();
						WG.View.handleLink(url);
					}
					return false;
				}
			}
			return true;
		};
		
		// RESET
		WG.search.onReset = function (event, field) {
			if (event) {
				event.preventDefault();
			}
			$('[quicksearch]').show();
			if (field) {
				$(field).val('');
			}
			return false;
		};
		
		// Execute reset
		if (WG.search.trayIcon.input) {
			WG.search.onReset(null, WG.search.trayIcon.input);
		}

	},

	quickSearch: function (query) {
		//console.log('Quicksearch: ' + query);
		WG.search.onQuickSearch(query);
	}

};

// Create easing function
window['quickSearch'] = function (query) {
	WG.search.quickSearch(query);
};

// Create search field in tray bar
WG.search.trayIcon = WG.ui.addTrayIcon({

	name: 'searchbox',

	onInit: function () {
		
		// Initialize interface
		WG.search.setDefault();
		
		// Create search box
		this.input = document.createElement('input');
		this.input.setAttribute('type', 'search');
		this.input.setAttribute('id', 'qs');
		this.input.setAttribute('name', 'qs');
		this.input.setAttribute('placeholder', 'Search...');
		//this.input.setAttribute('x-webkit-speech', 'x-webkit-speech');
		
		// Create listener on input key up event
		$(this.input).keyup(function (e) {
			
			var query = jQuery.trim($(this).val());
			if (query.length > 0) {
				if (e.keyCode == 13) { // Enter key
					// Execute quickAction
					if (!WG.search.onQuickAction(query, e, this)) {
						return false;
					}
					// Execute plainSearch
					WG.setView('search', {"q": query});
					return false;
				}
				// Execute quickSearch
				return WG.search.onQuickSearch(query, e, this);
			}
			else {
				return WG.search.onReset(e, this);
			}
		});
		
		// Install search box
		this.node.appendChild(this.input);
		
		// Focus searchbox when view change
		thiz = this;
		this.getFocus = function () {
			thiz.input.focus();
		};
		WG.bind('viewChange', this.getFocus);
		
	},

	onClick: function () {
	},

	onMouseOver: function () {
	},

	onHide: function () {
	},

	onAppear: function () {
	},

	onDestroy: function () {
		// Disable quickSearch function
		window['quickSearch'] = WG.quickSearch = undefined;
		// Remove all listening
		WG.unbind('*', this.getFocus);
	}

});