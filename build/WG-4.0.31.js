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
})();/*global Soho*/
/*global jQuery*/
Soho.util = {

	/**
	 * @param Object|Array obj
	 * @param Function callback
	 * @return void
	 */
	each: function (obj, callback) {
		if (!obj) return;
		if (obj instanceof Object) {
			for (let v in obj) {
				callback(obj[v], v);
			}
		}
	},

	createElement: function (tagname, attributes, parent) {
		var el = document.createElement(tagname);
		if (attributes) {
			for (let attr in attributes) {
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
				for (let i = 0, j = this.listeners.length; i < j; i++) {
					 let li = this.listeners[i];
					 if (!li) continue;
					 if ((li.e === event || event === "*") && li.c === callback) {
						this.listeners.splice(i, 1);
						return true;
					 }
				}
			}
			else if (event) {
				for (let i = 0, j = this.listeners.length; i < j; i++) {
					 let li = this.listeners[i];
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
				jQuery(iframe).remove();
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
Soho.util.implementListenerPattern(Soho);

// Create jQuery.reverse() to reverse a collection of jQuery elements.
jQuery.fn.reverse = [].reverse;

// Create jQuery.hasAttr() function
jQuery.fn.hasAttr = function(name) {  
   return this.attr(name) !== undefined;
};
/*global Soho*/
/*global jQuery*/

Soho.security = {

	inOperation: false,

	login: function (form) {
		
		// On test si le formulaire est expiré
		var expires = parseInt(form.getAttribute('expires')) * 1000;
		if (expires <= new Date().getTime()) {
			// On masque le formulaire
			form.style.display = 'none';
			// On programme une callback dans 3 secondes pour recharger le formulaire
			var timer = setTimeout(function () {
				Soho.setView('login', null, true);
			}, 3000);
			// On affiche une notification d'erreur
			Soho.setStatus(
				'Sorry, this form was expired. I will ask a new one...',
				Soho.status.WARNING,
				function () {
					clearTimeout(timer);
					Soho.setView('login', null, true);
				},
				'Go on!'
			);
			return false;
		}

		// Hide virtual keyboard
		Soho.security.vkb.div.style.display = 'none';

		// Check if an operation is pending
		if (Soho.security.inOperation) return false;

		// Get form data
		var	salt			= form.getAttribute('salt'),
			fields			= form.getElementsByTagName('input'),
			loginfield		= fields[0],
			passwordfield		= fields[1],
			aesfield		= fields[2],
			password		= passwordfield.value;

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
		Soho.security.inOperation = true;

		// Hide form
		form.style.display = 'none';

		// Reinforce password
		password = Soho.security.sha1(loginfield.value + ':' + password);
		//console.log("AUTH sha1(" + loginfield.value + ':' + passwordfield.value + ") = " + password);
		//console.log("AUTH salt = " + salt);

		if (form.hasAttribute('apikey')) {
			password = 's+k:' + Soho.security.sha1(salt + ':' + password + ':' + form.getAttribute('apikey'));
			//console.log("AUTH qop=s sha1(salt:password) = " + password);
		}
		else {
			password = 's:' + Soho.security.sha1(salt + ':' + password);
			//console.log("AUTH qop=b sha1(salt:password) = " + password);
		}
		
		// Fix pour le masquage du virtual kbd
		jQuery(Soho.security.vkb.div).hide().remove().appendTo('#viewLogin');

		// Replace password
		passwordfield.value = password;

		// With AES channel
		if (aesfield.checked === true) {
			// Update UI
			Soho.setStatus('Open secured channel...', Soho.status.WAIT);
			// Open AES channel
			Soho.security.openAES(
				// Authentication success
				function () {
					// Update UI
					Soho.setStatus('Authentication...', Soho.status.WAIT);
					// Create data
					var data = { };
					data[loginfield.getAttribute('name')] = loginfield.value;
					data[passwordfield.getAttribute('name')] = password;
					// Authentication
					Soho.security.authenticate(data);
					// Finish operation
					Soho.security.inOperation = false;
				},
				// Authentication failed
				function () {
					// Remove AES data
					Soho.security.AES = null;
					// Update UI
					Soho.setStatus('AES handshake failure', Soho.status.FAILURE);
					// Finish operation
					Soho.security.inOperation = false;
					// Back to login view
					setTimeout("WG.setView('login', null, true);", 1500);
				}
			);
		}

		// Without AES channel
		else {
			// Remove AES data
			Soho.security.AES = null;
			// Wait message
			Soho.setStatus('Authentication...', Soho.status.WAIT);
			// Create data
			var data = { };
			data[loginfield.getAttribute('name')] = loginfield.value;
			data[passwordfield.getAttribute('name')] = password;
			// Authentication
			Soho.security.authenticate(data);
		}

		return false;
	},

	authenticate: function (post) {

		// On error callback
		var onError = function (jqXHR, textStatus, errorThrown) {
			// Update UI
			Soho.setStatus(
				'Authentication Failure: <em>' + errorThrown + '</em>',
				Soho.status.FAILURE,
				function () {
					Soho.setView('login', null, true);
				}
			);
			if (console) {
				console.error('[security] Authentication failure: ' + errorThrown);
			}
			// Remove AES data ?
			//WG.security.AES = null;
			// Finish operation
			Soho.security.inOperation = false;
		};

		// On success callback
		var onSuccess = function (data, textStatus, jqXHR) {
			// Update UI
			Soho.setStatus(null);
			if (console) {
				console.log('[security] Authentication success!');
			}
			// Finish operation
			Soho.security.inOperation = false;
			// Unlock user shell
			Soho.welcome();
		};

		// Execute request
		post.w = 'auth';
		Soho.ajax({
			url: Soho.appURL() + 'ws.php',
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
		if (Soho.security.AES == null) {
			var key = Soho.security.random(32),
				hashObj = new jsSHA(key, 'ASCII');
			Soho.security.AES = {
				'hashObj': hashObj,
				'password': hashObj.getHash('SHA-512', 'HEX')
			};
			/*console.log(' AES key clear: ' + key);
			console.log(' AES key hash: ' + Soho.security.AES.password);*/
		}

		// Execute AES authentication
		jQuery.jCryption.authenticate(
			Soho.security.AES.password,
			Soho.appURL() + 'publickeys.php',
			Soho.appURL() + 'handshake.php',
			function () {
				// Add lock icon to app name
				uidata.uiComponents.appName.setAttribute('class', 'securized');
				// Callback
				onSuccess();
			},
			function () {
				// Remove AES data
				Soho.security.AES = null;
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
			for (let key in data) {
				data[key] = Soho.security.aesEncrypt(data);
			}
		}
		return jQuery.jCryption.encrypt("" + data, Soho.security.AES.password);
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
		return jQuery.jCryption.decrypt("" + data, Soho.security.AES.password);
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
				case '&larr;' :
				case '←' :
					if (Soho.security.vkb.pwd.value.length > 0) {
						Soho.security.vkb.pwd.value = Soho.security.vkb.pwd.value.substr(0, Soho.security.vkb.pwd.value.length - 1);
					}
					break;
				case 'Shift' :
					this.shift();
					break;
				case '&lt;' :
					Soho.security.vkb.pwd.value += '<';
					break;
				case '&gt;' :
					Soho.security.vkb.pwd.value += '>';
					break;
				default :
					Soho.security.vkb.pwd.value += this.key;
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
		return Soho.security.cookie('PHPSESSID'); // TODO Configurable
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

};/*global Soho*/
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

}

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

} 

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
	var href = href.substr(10).split('&'),
		view = null,
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
}

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
/*global Soho*/
/*global jQuery*/
Soho.TrayIcon = function (data) {
	this.name = data.name;
	this.data = data;
	this.badgeText = null;
	this.notification = null;
	this.node = null;
	this.initialized = false;
};

/**
 * Afficher un petit badge text
 * @return this
 */
Soho.TrayIcon.prototype.setBadgeText = function (text) {
	// Delete
	if (!text) {
		if (this.badgeText) {
			jQuery(this.badgeText).remove();
			this.badgeText = null;
		}
		return this;
	}
	// Set
	if (!this.badgeText) {
		this.badgeText = document.createElement('span');
		this.badgeText.setAttribute('class', 'badge');
		this.node.appendChild(this.badgeText);
		// Propager un clic sur le badge text au lien de l'icone
		var thiz = this;
		this.badgeText.onclick = function () {
			jQuery(thiz.a).click();
		};
	}
	this.badgeText.innerHTML = text;
	return this;
};

/**
 * @return string|null
 */
Soho.TrayIcon.prototype.getBadgeText = function () {
	return (this.badgeText) ? this.badgeText.innerHTML : null;
};

Soho.TrayIcon.prototype.setLoadingIndicator = function (enable) {
	if (enable) {
		jQuery(this.node).addClass('loading');
	}
	else {
		jQuery(this.node).removeClass('loading');
	}
	return this;
};

Soho.TrayIcon.prototype.hasLoadingIndicator = function () {
	return jQuery(this.node).hasClass('loading');
};



/**
 * @return this
 */
Soho.TrayIcon.prototype.setNotification = function (text, onClick, duration) {
	var thiz = this;
	// Delete
	if (!text) {
		if (this.notification) {
			jQuery(this.notification).stop().fadeTo(1000, 0, function () {
				jQuery(thiz.notification).remove();
				thiz.notification = null;
			});
		}
		return this;
	}
	// Create notification node
	if (!this.notification) {
		this.notification = document.createElement('div');
		this.notification.setAttribute('class', 'notification');
		jQuery(this.notification).fadeTo(0, 0);
		this.node.appendChild(this.notification);
	}
	// Bind onclick event
	if (onClick) {
		this.notification.onclick = onClick;
	}
	// 
	this.notification.innerHTML = text;
	// Appear animation
	jQuery(this.notification).stop().fadeTo(1000, 0.9);
	// Duration
	if (this.notificationThread != null) {
		clearTimeout(this.notificationThread);
	}
	if (!duration) duration = 6000;
	if (duration) {
		setTimeout(function () {
			thiz.setNotification(null);
		}, duration);
	}
	return this;
};

Soho.util.implementListenerPattern(Soho.TrayIcon.prototype);/*global Soho*/
/*global jQuery*/
/**
 * Constructor.
 *
 * @access public
 */
Soho.LiveService = function () {
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
Soho.LiveService.prototype.refresh = function () {

	if (!Soho.live) return;

	// Get query parameters
	var data = this.getParameters();

	// No events to listen
	if (!data) {
		return;
	}

	// Query to server
	Soho.ajax({
		url: Soho.appURL() + 'live.php',
		data: data,
		success: function (data) {
			if ('error' in data) {
				Soho.ui.getTrayIcon('power').setNotification('Error in live service.');
				return;
			}
			// Update lastUpdate with server time
			Soho.lastUpdate = data.serverTime;
			// Propagate event
			Soho.live.propagation(data);
		},
		error: function () {
			Soho.ui.getTrayIcon('power').setNotification('Unable to connect live service.');
		}
	});

};

/**
 * @access public
 */
Soho.LiveService.prototype.getParameters = function () {
	var i = 0,
		events = { };
	// Fetch live listeners
	for (let listener in Soho.live.bind) {
		var l = Soho.live.bind[listener];
		// Pour l'explication de ce truc, voir WG.LiveService.prototype.propagation()
		if (
			!('view' in l) || // Soit le listener n'est pas associé à une vue précise
			(Soho.ui.currentView != null && Soho.ui.currentView.name === l.view) // Soit il s'agit justement de cette vue là
			) {
				// Dans ce cas, on idique que cet event est bien à demander au serveur
				events[l.event] = 1;
				i++;
		}
	}
	// Nothing to retreive
	if (i < 1) return null;
	// Create a string with events
	var eventsList = [];
	for (event in events) {
		eventsList.push(event);
	}
	// Return request data
	return {
		't': Soho.lastUpdate,
		'l': eventsList.join('|')
	};
};

/**
 * @access public
 */
Soho.LiveService.prototype.stop = function () {
	if (console) {
		console.log('[live] Stop live service...');
	}
	clearInterval(this.thread); // marche pas ... TODO à cause du scope de this ?
};

/**
 * HashMap with all listeners.
 * @access public
 */
Soho.LiveService.prototype.bind = {};

/**
 * @access public
 */
Soho.LiveService.prototype.propagation = function (data) {
	// Fetch live listeners
	for (let listener in Soho.live.bind) {
		var l = Soho.live.bind[listener];
		// Dans un premier temps, on regarde si le listener a configur� une vue.
		// Si c'est le cas, on compare avec le nom de la vue actuelle. S'il s'agit
		// bien de la bonne vue on continue.
		// Donc, si le listener ne sp�cifie pas d'attribut 'view' il sera d�clanch�
		// � chaque fois.
		if (!('view' in l) || (Soho.ui.currentView != null && Soho.ui.currentView.name === l.view)) {
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

Soho.ui.addTrayIcon({

	name: 'power',

	onInit: function () {
		// Add icon class
		jQuery(this.node).addClass('icon');
		// Create tray menu
		this.trayMenu = document.createElement('ul');
		this.trayMenu.setAttribute('class', 'drop-down');
		this.node.appendChild(this.trayMenu);
	},

	onClick: function () {
		jQuery(this.trayMenu).toggle();
	}

});

/* CLOCK */

Soho.ui.addTrayIcon({

	name: 'clock',

	onInit: function () {
		this.node.innerHTML = '--:--';
		// Refresh
		this.updateClock = function () {
			var time = Soho.time.getServerTime();
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

});/*global Soho*/
/*global jQuery*/
Soho.search = {

	trayIcon: null,

	onQuickSearch: function (query, event, field) { },
	onQuickAction: function (query, event, field) { },
	onReset: function (event, field) { },

	setDefault: function () {

		//console.log('Quicksearch to default');
		
		// QUICK SEARCH
		Soho.search.onQuickSearch = function (query) {
			jQuery('[quicksearch]').each(function () {
				if (this.getAttribute('quicksearch').toLowerCase().match(query)) {
					jQuery(this).show();
				}
				else {
					jQuery(this).hide();
				}
			});
			return false;
		};
		
		// QUICK ACTION
		Soho.search.onQuickAction = function (query, event, field) {
			var v = jQuery('.[quicksearch]:visible');
			if (v.size() === 1) {
				let url = v.attr('url');
				if (url != null && url.length > 0) {
					// Cette portion de code permet de restituer le contexte de l'execution
					if (url.substr(0, 11) == 'javascript:') {
						Soho.search.onReset(event, field);
						let e = v.get(0);
						e.qstmp = function (d) { eval(d); };
						e.qstmp(url.substr(11));
						e.qstmp = null;
					}
					else {
						//document.location.href = url;
						event.preventDefault();
						Soho.View.handleLink(url);
					}
					return false;
				}
			}
			return true;
		};
		
		// RESET
		Soho.search.onReset = function (event, field) {
			if (event) {
				event.preventDefault();
			}
			jQuery('[quicksearch]').show();
			if (field) {
				jQuery(field).val('');
			}
			return false;
		};
		
		// Execute reset
		if (Soho.search.trayIcon.input) {
			Soho.search.onReset(null, Soho.search.trayIcon.input);
		}

	},

	quickSearch: function (query) {
		//console.log('Quicksearch: ' + query);
		Soho.search.onQuickSearch(query);
	}

};

// Create easing function
window['quickSearch'] = function (query) {
	Soho.search.quickSearch(query);
};

// Create search field in tray bar
Soho.search.trayIcon = Soho.ui.addTrayIcon({

	name: 'searchbox',

	onInit: function () {
		
		// Initialize interface
		Soho.search.setDefault();
		
		// Create search box
		this.input = document.createElement('input');
		this.input.setAttribute('type', 'search');
		this.input.setAttribute('id', 'qs');
		this.input.setAttribute('name', 'qs');
		this.input.setAttribute('placeholder', 'Search...');
		//this.input.setAttribute('x-webkit-speech', 'x-webkit-speech');
		
		// Create listener on input key up event
		jQuery(this.input).keyup(function (e) {
			
			var query = jQuery.trim(jQuery(this).val());
			if (query.length > 0) {
				if (e.keyCode == 13) { // Enter key
					// Execute quickAction
					if (!Soho.search.onQuickAction(query, e, this)) {
						return false;
					}
					// Execute plainSearch
					Soho.setView('search', {"q": query});
					return false;
				}
				// Execute quickSearch
				return Soho.search.onQuickSearch(query, e, this);
			}
			else {
				return Soho.search.onReset(e, this);
			}
		});
		
		// Install search box
		this.node.appendChild(this.input);
		
		// Focus searchbox when view change
		var thiz = this;
		this.getFocus = function () {
			thiz.input.focus();
		};
		Soho.bind('viewChange', this.getFocus);
		
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
		window['quickSearch'] = Soho.quickSearch = undefined;
		// Remove all listening
		Soho.unbind('*', this.getFocus);
	}

});
