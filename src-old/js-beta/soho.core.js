/**
 *
 */
WG = (function () {

	/**
	 * TODO doc
	 *
	 * Note: il faut absolument conserver la syntaxe condensée utilisée ici
	 *  car la réécriture en PHP dépends de ça.
	 *
	 * @access private
	 */
	var appdata = {
		wg_appName:"SoHo",
		wg_appVersion:"3.0",
		wg_url:"http://soho/",
		wg_lastUpdate:0,
		wg_serverTimeDelta:0,
		wg_updateDelay:30000,
		wg_sessionAge:0,
		wg_autolockDelay:0,
		wg_started:false
	};

	/**
	 * TODO doc
	 *
	 * @access private
	 */
	var userdata = {
		wg_logged:false,
		wg_login:null,
		wg_name:null,
		wg_avatar:null,
		wg_pwdhash:null
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

		// TODO Checker la validité de data ?

		// Update data
		userdata.wg_logged = true;
		userdata.wg_login = data.sessiondata.userLogin;
		userdata.wg_name = data.sessiondata.userName;
		userdata.wg_avatar = data.sessiondata.userAvatar;
		userdata.wg_pwdhash = data.sessiondata.userPwdHash;
		appdata.wg_lastUpdate = data.sessiondata.serverTime;
		appdata.wg_sessionAge = data.sessiondata.sessionAge;
		appdata.wg_autolockDelay = data.settings.autolock * 60000; // ms

		// Log
		if (console) {
			console.log('Unlock user shell for: ' + userdata.wg_login);
		}

		// Save available views
		viewdata.available = data.views;

		// Create main menu
		WG.ui.createMenu(data.menu);

		// Init tray icons
		WG.ui.createTrayIcons();

		// Add options in tray menu
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

		// Load CSS
		var css = document.createElement('link');
		css.type = 'text/css';
		css.rel = 'stylesheet';
		css.title = 'SoHo.Custom';
		css.href = appdata.wg_url + 'css.php';
		var s = document.getElementsByTagName('link')[0];
		s.parentNode.insertBefore(css, s);
		if (console) {
			console.log(' > GET = ' + appdata.wg_url + 'css.php');
		}

		// Enable live service
		startLive();

		// Switch to last view
		if (localStorage && localStorage.getItem('WG.defaultView')) {
			WG.setView(localStorage.getItem('WG.defaultView'));
		}
		else {
			WG.setView('dashboard');
		}
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

		// Destroy session
		destroy();

		// Ask logout webservice
		WG.ajax({
			url: WG.appURL + 'ws.php',
			data: {
				'w': 'auth',
				'logout': 'please'
			},
			success: function () {
				WG.setStatus(
					'You are logged out.',
					WG.status.SUCCESS
				);
				setTimeout('window.location.reload()', 1600);
			},
			error: function (data, textStatus, jqXHR) {
				if (textStatus != 'success') {
					WG.setStatus(
						'Unable to log out: ' + textStatus,
						WG.status.FAILURE
					);
				}
				setTimeout('window.location.reload()', 10);
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
			if (WG.security.sha1(input.value).substr(0, 15) === userdata.wg_pwdhash) {
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
		// Update UI
		WG.setStatus('Say welcome to server...', WG.status.WAIT);

		// On error callback
		var onError = function (jqXHR, textStatus, errorThrown) {
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
			url: WG.appURL + 'ws.php',
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
			console.log('WG is starting...');
		}

		// Trigger event
		WG.trigger('init');

		// Calculate server time delta
		appdata.wg_serverTimeDelta = (new Date().getTime() / 1000) - appdata.wg_lastUpdate;
		//alert('Delta is: ' + appdata.wg_serverTimeDelta + ' s');

		// Create UI
		uidata.uiComponents = WG.ui.createUIComponents();

		// Set body's ID
		document.body.setAttribute('id', 'wg');

		// Install UI
		document.body.appendChild(uidata.uiComponents.container);

		// Application is started
		appdata.started = true;

		// If the user is not logged in
		if (!userdata.wg_logged) {
			if (console) {
				console.log('User is not logged');
			}
			WG.setView('login', null, true);
		}
		// If the user is logged in, we ask for the welcome message
		else {
			if (console) {
				console.log('User is allready logged');
			}
			// Lock authentication process
			WG.security.inOperation = true;
			// Update UI
			/*WG.setStatus('Open secured channel...', WG.status.WAIT);
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

	// Init on load
	window.onload = function () {
		start();
	}

	// Public interface
	// Can be extended using WG.foo = bar
	return {

		appName: appdata.wg_appName,
		appURL: appdata.wg_url,
		userName: function () {
			return userdata.wg_name;
		},
		userAvatar: function () {
			return userdata.wg_avatar;
		},
		lastUpdate: function () {
			return appdata.wg_lastUpdate;
		},
		serverTimeDelta: function () {
			return appdata.wg_serverTimeDelta;
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
				cache : cache === true,
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
				console.log(' >> POST ' + args.url + ' (AES=' + (useAES ? 'on' : 'off')+', type='+dataType+')');
			}

			// On execute la requête
			return jQuery.ajax(p);

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
		}

	};

})();