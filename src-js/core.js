/*global jQuery*/
var Soho = (function () {

	/**
	 * Application data
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
	 * User session data
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
	 * View renderer data
	 *
	 * @access protected
	 */
	let viewdata = {
		// Available views (loaded by WELCOME)
		available: {},
		// Allready loaded views
		loaded: { }
	};

	/**
	 * View navigation data
	 *
	 * @access protected
	 */
	let uidata = {
		lastAction: 0, // Last mouse move, used by auto-lock
		autolockThread: null, // Thread of auto-lock background task
		trayIcons: {}, // Tray icons
		uiComponents: null // Static components of the UI
	};

	/**
	 * Function to stop live service
	 *
	 * @access private
	 */
	var stopLive = function () {
		if (Soho.live) {
			Soho.live.stop();
			Soho.live = null;
		}
	};

	/**
	 * Function to start live service
	 *
	 * @access private
	 */
	var startLive = function () {
		if (!Soho.live) {
			Soho.live = new Soho.LiveService();
		}
	};

	/**
	 * Open the user session
	 *
	 * @access private
	 */
	var open = function (data) {

		// Log
		if (console.log) {
			console.log('[start] Open user session...');
		}

		// Update data
		// TODO Check data integrity
		userdata.wg_logged = true;
		userdata.wg_login = data.sessiondata.userLogin;
		userdata.wg_name = data.sessiondata.userName;
		userdata.wg_avatar = data.sessiondata.userAvatar;
		userdata.wg_pwdhash = data.sessiondata.userPwdHash;
		appdata.wg_lastUpdate = data.sessiondata.serverTime; // sec
		appdata.wg_sessionAge = data.sessiondata.sessionAge;
		appdata.wg_autolockDelay = data.settings.autolock * 60000; // min to ms
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

		// Set nightmode
		if ('localStorage' in window) {
			if (window.localStorage.getItem('WG.nightmode') === 'on') {
				document.body.setAttribute('nightmode', 'on');
			}
		}

		// Save available views
		viewdata.available = data.views;
		// Create main menu
		Soho.ui.createMenu(data.menu);
		// Init tray icons
		Soho.ui.createTrayIcons();
		// Add options in tray menu
		Soho.ui.addTrayMenuItem(
			'nightmode',
			'Switch night mode...',
			Soho.ui.TrayMenuStack.TOP,
			function () {
				var body = jQuery('body#wg');
				// Nightmode OFF
				if (body.hasAttr('nightmode')) {
					// Remove look&feel
					body.removeAttr('nightmode');
					// Save in localStorage
					if ('localStorage' in window) {
						window.localStorage.setItem('WG.nightmode', 'off');
					}
				}
				// Nightmode ON
				else {
					// Set look&feel
					body.attr('nightmode', 'on');
					// Save in localStorage
					if ('localStorage' in window) {
						window.localStorage.setItem('WG.nightmode', 'on');
					}
				}
				// Deprecated : force repaint, but delete listeners
				//$('body > *').detach().appendTo('body');
			}
		);
		Soho.ui.addTrayMenuItem(
			'lock',
			'Lock my session...',
			Soho.ui.TrayMenuStack.BOTTOM,
			function () { Soho.lock(); }
		);
		Soho.ui.addTrayMenuItem(
			'logout',
			'Log out...',
			Soho.ui.TrayMenuStack.BOTTOM,
			function () { Soho.logout(); }
		);
		// Apply default behavior
		Soho.View.applyStandardBehavior(uidata.uiComponents.main);
		// Autolock thread
		if (appdata.wg_autolockDelay > 0) {
			uidata.autolockThread = setInterval(
				function () {
					if (new Date().getTime() - uidata.lastAction > appdata.wg_autolockDelay) {
						Soho.lock();
					}
				},
				30000
			);
		};

		// Enable live service
		startLive();
		
		// Switch to default or last view
		if ('localStorage' in window && window.localStorage.getItem('WG.defaultView')) {
			Soho.setView(window.localStorage.getItem('WG.defaultView'));
		}
		else {
			Soho.setView('dashboard');
		}

		// Update last action
		uidata.lastAction = new Date().getTime();

	};
	
	/**
	 * Function to dispose all components of Soho 
	 *
	 * @access private
	 */
	var dispose = function () {
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
		Soho.ui.removeAllTrayMenuItems();
		// Destroy main menu
		Soho.ui.destroyMenu();
		// Destroy tray icons
		Soho.ui.removeAllTrayIcons();
		// Remove current view
		Soho.ui.removeCurrentView();
		// Clean history
		Soho.ui.viewHistory = {};
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
		if ('localStorage' in window) {
			window.localStorage.removeItem('WG.lock');
			window.localStorage.removeItem('WG.defaultView');
			//window.localStorage.removeItem('WG.nightmode');
		}
	};
	
	/**
	 * Function to sign out.
	 * 
	 * TODO Move to security.js
	 *
	 * @access protected
	 */
	let logout = function () {
		// If not logged
		if (!userdata.wg_logged) {
			Soho.setStatus('You are not logged.', Soho.status.ALERT, 'close');
			return;
		}
		// Update UI
		Soho.setStatus('Logout...', Soho.status.WAIT);
		// Save APP url
		var url = appdata.wg_url;
		
		// Destroy session
		Soho.dispose();
		// Ask logout webservice
		Soho.ajax({
			url: url + 'ws.php',
			data: {
				'w': 'auth',
				'logout': 'please'
			},
			success: function () {
				setTimeout('window.location.reload()', 1600);
				Soho.setStatus(
					'You are logged out.',
					Soho.status.SUCCESS
				);
			},
			error: function (data, textStatus, jqXHR) {
				setTimeout('window.location.reload()', 10);
				if (textStatus != 'success') {
					Soho.setStatus(
						'Unable to log out: ' + textStatus,
						Soho.status.FAILURE
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
	let lock = function () {
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
		uidata.uiComponents.locker.innerHTML = '<h1>' + Soho.util.htmlspecialchars(appdata.wg_appName)
			+ '</h1><p>This session is locked by <b>' + Soho.util.htmlspecialchars(userdata.wg_name)
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
			if (Soho.security.sha1(userdata.wg_login + ':' + input.value).substr(0, 15) === userdata.wg_pwdhash) {
				Soho.unlock();
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
		if ('localStorage' in window) {
			window.localStorage.setItem('WG.lock', true);
		}
	};

	/**
	 * TODO doc
	 *
	 * @access protected
	 */
	let unlock = function () {
		// Log
		if (console) {
			console.log('Unlock');
		}
		// Hide locker overlay
		uidata.uiComponents.locker.style.display = 'none';
		// Remove lock in local storage
		if ('localStorage' in window) {
			window.localStorage.removeItem('WG.lock');
		}
		// Update last action timestamp
		uidata.lastAction = new Date().getTime();
	};
	
	/**
	 * TODO doc
	 *
	 * @access protected
	 */
	let welcome = function (success, error) {
		
		// Log
		if (console) {
			console.log('[start] Ask for welcome message...');
		}
		
		// Update UI
		Soho.setStatus('Get welcome message...', Soho.status.WAIT);
		// On error callback
		var onError = function (jqXHR, textStatus, errorThrown) {
			// Log
			if (console) {
				console.error('[start] Unable to get welcome message. This attempt has been logged.');
			}
			// Update UI
			Soho.setStatus(
				'Unable to reach welcome service: <em>' + errorThrown + '</em>',
				Soho.status.FAILURE,
				function () {
					Soho.setView('login', null, true);
				}
			);
			// Callback
			if (error) error();
		}
		// On success callback
		var onSuccess = function (data) {
			// Update UI
			Soho.setStatus('Open session...', Soho.status.WAIT);
			// Unlock session
			open(data);
			// Update UI
			Soho.setStatus(null);
			// Callback
			if (success) success();
		}
		// Query to server
		Soho.ajax({
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
			throw 'Soho is allready started';
		}
		// Log
		if (console) {
			console.log('[start] Soho is starting, appURL is: ' + appdata.wg_url);
		}
		// Trigger event
		Soho.trigger('beforeStart');
		// Set body's ID
		document.body.setAttribute('id', 'wg');

		// Create UI
		uidata.uiComponents = Soho.ui.createUIComponents();
		// Install UI
		document.body.appendChild(uidata.uiComponents.container);

		// Install UI events listeners
		Soho.ui.bindEvents();
		// Application is started
		appdata.started = true;

		// If the user is not logged in
		if (!userdata.wg_logged) {
			if (console) {
				console.log('[start] Start is finished. User is not logged: display login view.');
			}
			Soho.setView('login', null, true);
		}
		// If the user is logged in, we ask for the welcome message
		else {
			if (console) {
				console.log('[start] Start is finished. User is allready logged: get welcome message.');
			}
			// Lock authentication process
			/*Soho.security.inOperation = true;
			// Update UI
			Soho.setStatus('Open secured channel...', Soho.status.WAIT);
			// Try to open AES channel
			Soho.security.openAES(
				// Authentication success
				function () {
					// Finish operation
					Soho.security.inOperation = false;
					// Welcome
					welcome(function () {
						// Lock
						lock();
					});
				},
				// Authentication failed
				function () {
					// Finish operation
					Soho.security.inOperation = false;
					// Update UI
					Soho.setStatus(
						'Unable to open secured channel. To retry, hit F5.',
						Soho.status.FAILURE,
						function () {
							// Remove AES data
							Soho.security.AES = null;
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
	// Can be extended using Soho.foo = bar
	return {
		init: function (config) {
			
			// Allready initialized
			if (jQuery('body').attr('id') == 'wg' || appdata.wg_started) {
				throw 'Soho is allready initialized';
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
			jQuery('body .nojs').remove();

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
			Soho.ui.setView(view, param, noCache, hash);
			return Soho;
		},
		currentView: function () {
			return Soho.ui.currentView;
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
			var useAES = Soho.security.AES != null;
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
					if (Soho.security.AES != null) {
						// Decrypt data
						//console.log('AVANT: ' + data);
						data = Soho.security.aesDecrypt(data);
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
					for (let name in args.data) {
						args.data[name] = jQuery.jCryption.encrypt('' + args.data[name], Soho.security.AES.password);
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
				case Soho.status.WAIT : theclass = 'wait-msg'; break;
				case Soho.status.SUCCESS : theclass = 'success-msg'; break;
				case Soho.status.INFO :
				case Soho.status.NOTICE : theclass = 'info-msg'; break;
				case Soho.status.ALERT :
				case Soho.status.WARNING : theclass = 'alert-msg'; break;
				case Soho.status.ERROR :
				case Soho.status.FAILURE : theclass = 'failure-msg'; break;
			}
			el.setAttribute('class', theclass);
			// Change contents
			el.innerHTML = msg;
			// Continue link
			if (onContinue) {
				if (onContinue == 'close') {
					continueLabel = 'close';
					onContinue = function () {
						Soho.setStatus(null);
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
			return Soho;
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
})();