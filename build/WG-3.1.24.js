WG = (function () {

	/**
	 * TODO doc
	 *
	 * Note: il faut absolument conserver la syntaxe condensée utilisée ici
	 *  car la suite du système dépends de ça.
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

		// Note : load javascript is deprecated, views should use
		// <script src=""></script> to load module's scripts
		
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
		// Remove nojs
		$('body .nojs').remove();
		// Start WG !
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

		currentView: function () {
			return WG.ui.currentView;
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
				console.log(' >> POST ' + args.url + ' (AES=' + (useAES ? 'on' : 'off')+', type='+dataType+', cache='+(cache === true)+')');
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
WG.util = {

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
				console.log('Authentication failure: ' + errorThrown);
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
				console.log('Authentication success: ' + data.userName);
			}
			// Finish operation
			WG.security.inOperation = false;
			// Unlock user shell
			welcome();
		};

		// Execute request
		post.w = 'auth';
		WG.ajax({
			url: WG.appURL + 'ws.php',
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
			WG.appURL + 'publickeys.php',
			WG.appURL + 'handshake.php',
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
	}

};
WG.ui = {


	/* * *   UI Components   * * */

	createUIComponents: function () {

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
		appName.innerHTML = WG.util.htmlspecialchars(WG.appName);
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
			locker: locker
		};

	},

	/* * *   Views   * * */

	viewCount: 1,

	setView: function (viewname, param, noCache, hash) {
		var view;
		// Log
		if (console) {
			console.log('Set view: ' + viewname);
		}
		// La page existe d�j�, on la r�utilise
		if (viewdata.loaded[viewname]) {
			view = viewdata.loaded[viewname];
		}
		// La page n'existe pas, on la fabrique
		else {
			view = new WG.View(viewname);
			viewdata.loaded[viewname] = view;
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
		// On fait �a avant le refresh, pour que le script de la vue
		// puisse overrider les actions des listeners (par exemple sur
		// le focus)
		WG.trigger('viewChange', view.name);
		// Change hash
		document.location.hash = '#view-' + WG.ui.viewCount++;
		// Remove status
		WG.setStatus(null);
		// Save view name
		if (localStorage && viewname != 'login') {
			localStorage.setItem('WG.defaultView', view.name);
		}
		// Reset quicksearch
		WG.search.setDefault();
		// Display view
		if (!view.display(param, (noCache === true), hash) && view.dist != WG.View.DistributionModel.KEEP_ALIVE) {
			// TODO Meilleur solution
			WG.ui.getTrayIcon('power').setNotification('This page has been restored from cache. Click here to refresh this page.', function () {
				WG.setView(view.name, null, true);
			}, 3000);
			// Trigger event
			WG.trigger('viewRestored', view.name);
		}
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
					console.log('Stop loading for view: ' + WG.ui.currentView.name);
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
	},

	destroyMenu: function () {
		uidata.uiComponents.menu.innerHTML = '';
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
		// Cette ic�ne est d�j� initialis�e
		if (icon.initialized) {
			return;
		}
		// Debug mode
		if (console) {
			console.log('  Init TrayIcon: ' + icon.name);
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
	removeAllTrayIcons: function (name) {
		// TODO !!!
		if (console) {
			console.log(' % TODO WG.ui.removeAllTrayIcons()');
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
		li.onclick = onClick;
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

};/** * Classe View. */WG.View = function (name) {	// Save view name	this.name = name;	// View contents distribution model	this.dist = WG.View.DistributionModel.CACHE;	// Remember if view has been loaded yet	this.loadedTime = 0;	this.loadedUrl = null;	this.localCacheAge = 120000; // en ms	// Si la page a été déclarée dans le message de welcome, donc on a ses données.	// On va pouvoir adapter certains paramètres.	if (name in viewdata.available) {		var nfo = viewdata.available[name];		// Le modèle de distribution		if ('dist' in nfo) {			// On vérifie qu'il existe bien			if (nfo.dist in WG.View.DistributionModel) {				// Si oui on le modifie pour cette vue				this.dist = WG.View.DistributionModel[nfo.dist];			}		}		// L'age du cache		// TODO Mettre un moyen pour que l'age du cache soit spécifié par le serveur		// Utiliser les headers renvoyées par le serveur ?	}	// Handle distribution model	if (this.dist === WG.View.DistributionModel.CONTINUE) {		// On indique que le contenu n'a pas encore été chargé intégralement		this.isAllDataFragmentsLoaded = false;		// On initialise le curseur actuel de fragment chargés		this.currentFragmentCursor = 0;		// On met en place un listener qui détecte le scrolling et qui va lancer la méthode WG.View.nextFragment()		var view = this;		$(window).scroll(function () {			if  ($(window).scrollTop() == $(document).height() - $(window).height()){				view.nextFragment();			}		});	}	// Create view node	this.node = document.createElement('div');	this.node.setAttribute('class', 'view fit-height');	this.node.setAttribute('id', 'view-' + name);	// Prepare XHR object	this.xhr = null;};/** * Renvoi l'URL de la vue avec les paramètres donnés. * @param Object params Un tableau associatif avec les données à envoyer. * @return string * @version 3.0.15 Utilisation d'un tableau pour la concaténation */WG.View.prototype.getURL = function (params) {	var url = [		WG.appURL,		'view.php?v=',		escape(this.name)	];	if (params instanceof Object) {		for (key in params) {			url.push('&');			url.push(escape(key));			url.push('=');			url.push(escape(params[key]));		}	}	return url.join('');};/** * Affiche la vue avec les paramètres donnés. * * @return true Si une nouvelle version de la vue a été demandée au serveur * @return false Si une version en cache a été utilisée. */WG.View.prototype.display = function (params, noCache, hash) {	// On recupère l'URL de la vue	var url = this.getURL(params);	// Debug	if (console) {		console.log('View URL: ' + url + ' (cache='+(noCache ? 'no' : 'yes')+', current='+(url != this.loadedUrl?'no':'yes')+')');	}	// On compare avec celle déjà chargée.	// Si ce n'est pas l'url chargée, on ne se pose pas de question :	// on télécharge une nouvelle version de la vue.	if (noCache || url != this.loadedUrl) {		this.download(url, hash);		return true;	}	switch (this.dist) {		// Dans le cas d'une distribution type KEEP_ALIVE, on regarde		// si la vue a déjà été chargée. Si non, on la demande au serveur.		// Si oui, on se contente d'afficher le noeud existant.		// C'est à la vue de se mettre à jour par elle même, grâce à ses		// données locales ou bien en utilisant le live service.		case WG.View.DistributionModel.KEEP_ALIVE :			if (!this.loadedUrl) {				this.download(url, hash);				return true;			}			return false;			break;		// Dans le cas d'une distribution type REFRESH, on va chercher		// une nouvelle version de la vue sur le serveur.		case WG.View.DistributionModel.REFRESH :			this.download(url, hash);			return true;			break;		// Dans le cas d'une distribution type LOCAL_CACHE, on commence		// par télécharger la vue si ça n'a jamais été fait. Si un cache		// existe, on verifie qu'il n'a pas expiré. Dans ce cas, on		// télécharge un nouvelle version de la vue.		case WG.View.DistributionModel.LOCAL_CACHE :		default :			// Cas du premier téléchargement.			if (!this.loadedUrl) {				this.download(url, hash);				return true;			}			// Verification de l'expiration du cache			else if (new Date().getTime() - this.loadedTime > this.localCacheAge) {				this.download(url, hash);				return true;			}			// Le cache est valide			return false;			break;		}}/** * Remplace le contenu actuel de la vue par une requête * au serveur. * @param string c Le contenu HTML de la vue. */WG.View.prototype.setContents = function (c) {	// Append view to wrapper	// jQuery est utilisé ici car il permet d'executer le code	// javascript qui se trouve dans la page	$(this.node).html(c);	// On force le lancement de cet event car il est utilisé	// un peu partout pour le repaint de certains elements ou bien	// l'application de certains comportements.	// Exemple: le fit-height 100%	$(window).trigger('resize');} /** * Remplace le contenu actuel de la vue par une requête * au serveur. */WG.View.prototype.download = function (url, hash) {	// Remove old URL, to detect ajax query abortion	this.loadedUrl = null;	this.node.innerHTML = '';	WG.setStatus('Loading view...', WG.status.WAIT);	if (console) {		console.log('  > GET ' + url + ' (AES=' + (WG.security.AES == null ? 'off' : 'on')+', cache=off)');	}	// @todo Note: Pourquoi on n'utilise pas WG.ajax() ici ?	this.xhr = jQuery.ajax({		url: url,		cache: false,		context: this,		dataType: WG.security.AES != null ? 'text' : 'html',		type: 'GET',		success: function (data, textStatus, jqXHR) {			// Remove XHR object			this.xhr = null;			// This page is loaded, the url is stored			this.loadedUrl = url;			// Remember when the view has been loaded			this.loadedTime = new Date().getTime();			// AES Decrypt			if (WG.security.AES != null) {				// Update UI				WG.setStatus('Decrypting data...', WG.status.WAIT);				// Decrypt data				data = WG.security.aesDecrypt(data);			}			// Hide status indicator			WG.setStatus(null);			// Set page contents			this.setContents(data);			// Jump to selected id (hash)			if (typeof hash == 'string') {				document.location.hash = '#' + hash;			}			// Trigger event			WG.trigger('viewChanged', this.name);		},		error: function (jqXHR, textStatus, errorThrown) {			// Remove XHR object			this.xhr = null;			// Display error			WG.setStatus(				'Unable to get this view: <em>' + errorThrown + ' (' + textStatus + ')</em>',				WG.status.FAILURE,				function () {					if (this.refresh) {						this.refresh();					}					else {						WG.setStatus('<b>Fatal Error</b>: please restart using F5 on your keyboard', WG.status.FAILURE);					}				}			);		}	});};// Make View class listenableWG.util.implementListenerPattern(WG.View.prototype);/* Surveille le redimensionnement de la fenêtre pour le comportement fit-height 100%. * @todo Déplacer cet appel vers un emplacement plus approprié * @see WG.View.applyStandardBehavior() */$(window).resize(function () {	$('.fit-height').trigger('fitheight');});/** * Attention, cette fonction doit utiliser jQuery.on() car elle ne * sera pas appelée de nouveau après l'initialisation du système. * * Il ne faut pas appeler deux fois cette méthode. Il faut l'appliquer * au container global des vues (#main) une fois pour toute. */WG.View.applyStandardBehavior = function (node) {	// Create a jQuery wrapper	$(node)	// Fix old url	// Cette partie permet de transformer les liens en durs en	// liens dynamiques asynchrones. L'avantage de cette methode,	// c'est qu'il n'est pas neccessaire de changer le code	// de la v2.0 (MIMO) pour la nouvelle version. En fait, il est	// meme recommande de ne pas se prendre la tete et de continuer	// a faire des liens en dur, plus simples a faire.	// v3.0.6: utilisation de jQuery.on()	// v3.0.15: les liens avec target=_blank ne sont plus concernés	.on('click', 'a[href]:not([target="_blank"])', function (e) {		// La fonction WG.View.handleLink() renvoi true si le lien a été		// traité de manière asynchrone. Ici, on renvoi FALSE dans ce cas		// pour que le comportement par défaut ne soit pas provoqué.		return !WG.View.handleLink(this.getAttribute('href'), e);	})	// La même chose pour les formulaires	.on('submit', 'form', function (e) {		return !WG.View.handleForms(this, e);	})	// Onglets	.on('click', 'ul.tabmenu li a', function (e) {		var li = this.parentNode,			menu = li.parentNode;		// Remove selected tab legend		$('li.selected', menu).removeClass('selected');		// Add selected indcator to this tab legend		$(li).addClass('selected');		// Hide all tabs		$('.tabcontent.tab-' + menu.getAttribute('tab'), node).removeClass('selected');		// Show the new tab		$('.tabcontent' + this.getAttribute('href'), node).addClass('selected');		// Prevent default behavior		e.preventDefault();		return false;	})	// Auto-height 100%	// L'objectif de ce code est de permettre à des composants HTML de type	// block de prendre toute la hauteur de leurs conteneurs.	.on('fitheight', '.fit-height', function () {		var t = $(this),			m = $(window).height(),			h = 0,			o = 0;		// Calcul des dimensions des bords		o =			+ parseInt(t.css('padding-top'), 10)			+ parseInt(t.css('padding-bottom'), 10)			+ parseInt(t.css('margin-top'), 10)			+ parseInt(t.css('margin-bottom'), 10)			+ parseInt(t.css('borderTopWidth'), 10)			+ parseInt(t.css('borderBottomWidth'), 10);		// Calcul de la hauteur de la div		h = m - o - t.offset().top;		// Modification de la hauteur du composant		t.css('min-height', h + 'px');	})	// Elatic textareas (auto-height)	// Ce comportement qui ne s'applique qu'au champs TEXTAREA permet	// d'adapter la heuteur de la zone de texte en fonction de la quantitée	// de texte saisie par l'utilisateur.	.on('keydown', 'textarea.elastic', function () {		this.style.height = '';		this.rows = this.value.split('\n').length;		this.style.height = this.scrollHeight + 'px';	});};/** * Compatibilité des liens v2.X vers v3 : support de l'asynchrone. * * @param HTMLFormElement form * @param HTMLEvent event * @return boolean True si la lien a été traité en asynchrone * @access public static */WG.View.handleLink = function (href) {	// Check URL	if (href.substr(0, 1) == '?') {		href = 'index.php' + href;	}	else if (href.substr(0, 10) != 'index.php?') {		return false;	}	// Split URL tokens	var href = href.substr(10).split('&'),		view = null,		hash = null,		params = {},		i = 0,		j = href.length;	for (; i < j; i++) {		var token = href[i];		// Hash		if (token.indexOf('#') !== -1) {			hash = token.substr(token.indexOf('#') + 1, token.length);			token = token.substr(0, token.indexOf('#'));		}		// Split parameters		var value = token.split('='),			key = value.shift();		value = value.join();		// Save view		if (key == 'view' || key == 'v') {			view = value;		}		// Save variable		else {			params[key] = unescape(value);		}	}	// La vue a été trouvée, on va pouvoir la demander correctement	if (view != null) {		WG.setView(view, params, false, hash);		return true;	}	// Sinon et bien on laisse faire, on verra bien...	return false;};/** * Compatibilité des formulaires v2.x vers v3 : support de l'asynchrone. * * @param HTMLFormElement form * @param HTMLEvent event * @return boolean * @access public static */WG.View.handleForms = function (form, event) {	var t = $(form),		data = {};	// La première chose c'est de détecter les formulaires qui envoient	// des fichiers : ils auront un traitement spécial	if (t.hasAttr('enctype') && form.enctype == 'multipart/form-data') {		throw 'Not implemented yet';	}	// Ensuite on va récupérer les paramètres de l'attribut 'action' du formulaire.	// C'est surtout pour que ces paramètres ne soient pas perdus, et que la vue puisse y être détecté.	var action = t.attr('action'),		url = WG.util.parse_url(action),		params = ('query' in url) ? url.query.split('&') : [],		i = 0,		j = params.length;	for (; i < j; i++) {		var token = params[i];		// Split parameters		var value = token.split('='),			key = value.shift();		value = value.join();		// Save view		if (key == 'view' || key == 'v') {			data['v'] = value;		}		// Save variable		else {			data[key] = unescape(value);		}	}	// On recupère les valeurs de champs	$('input,textarea,select', form).each(function () {		var n = $(this);		if (n.hasAttr('name')) {			// TODO Ne pas envoyer les champs désactivés!			// Un petit correctif pour le nom de la vue			if (n.attr('name') == 'view') {				data['v'] = n.val();			}			// Save field value			else {				data[this.name] = n.val();			}			// Disable field			n.attr('disabled', 'disabled');		}	});	// Debug	//for (field in data) console.log('  ' + field + ' = ' + data[field]);	// Update UI	WG.setStatus('Sending data...', WG.status.WAIT);	// Save current view	var view = WG.ui.currentView;	// On fait une requête ajax asynchrone	WG.ajax({		url: 'view.php',		data: data,		dataType: 'html',		success: function (data) {			// Update UI			WG.setStatus(null);			// Save loaded URL			if (console) {				console.log('Loaded URL: ' + view.getURL(data));			}			view.loadedUrl = view.getURL(data);			// Update contents			view.setContents(data);		},		error: function (jqXHR, textStatus, errorThrown) {			WG.setStatus(				'Unable to post this form: ' + errorThrown,				WG.status.FAILURE,				function () {					WG.setStatus(null);				},				'close'			);		}	});	// Prevent default behavior (stop submit process)	event.preventDefault();	return true;}/** * Modèles de distribution des pages. */WG.View.DistributionModel = {	/**	 * La vue est rafraichie à chaque fois.	 */	REFRESH: 1,	/**	 * La vue est rafraichie si la durée de vie du cache local a expiré.	 * C'est le model par défaut.	 */	LOCAL_CACHE: 2,	/**	 * La vue est téléchargée une fois, ensuite le cache est utilisé systématiquement.	 * Soit la vue est statique, soit elle utilise le service live pour se mettre à jour.	 */	KEEP_ALIVE: 3,	/**	 * Le contenu de la vue est envoyé au fur et à mesure que l'utilisateur scroll la	 * page en hauteur.	 * Ce modèle de distribution va automatiquement déclancher la méthode WG.View.nextFragment()	 * quand l'utilisateur atteindra le bas de la fenêtre.	 *	 * Côté serveur, la vue receptionne la variable HTTP 'frag' (le numéro de frament) qui	 * contient un nombre entier (croissant, à partir de 0) pour lui permettre déterminer puis de	 * renvoyer le bon fragment de contenu.	 * Quand un fragment est téléchargé, l'évent 'fragmentLoaded' est propagé sur la vue.	 */	CONTINUE: 4,	/**	 * La page est téléchargée une fois, ensuite le cache est utilisé systématiquement.	 * Le contenu est divisé en pages qui sont chargées à la demande du client.	 *	 * Ce mode de distribution implique que des composants de controle (a, button) dans la vue	 * utilisent la méthode WG.View.setDataFragment() pour modifier le contenu dans la zone d'affichage.	 * Côté serveur, la vue receptionne la variable HTTP 'frag' (le numéro de frament) qui	 * contient un nombre entier (croissant, à partir de 0) pour lui permettre déterminer puis de	 * renvoyer le bon fragment de contenu. Ici par fragment on désire une page du contenu.	 * Quand une page est téléchargé, l'évent 'fragmentLoaded' est propagé sur la vue.	 */	PAGINATION: 5};/** * Indique si la vue supporte le chargement de contenu par fragments. */WG.View.prototype.supportDataFragments = function () {	return (this.dist === WG.View.DistributionModel.CONTINUE || this.dist === WG.View.DistributionModel.PAGINATION);};/** * Indique si tous les fragments de données ont été chargés. C'est à dire si le serveur à revoyé autre chose * que le code 206 au moins une fois. */WG.View.prototype.isAllDataFragmentsLoaded = false;// TODOWG.View.prototype.currentFragmentCursor = 0;/** * * * @param HTMLElement container * @param int frag Le numéro du fragment à afficher. Les données peuvent provenir du cache ou bien du serveur *  en fonction de l'argument useCache et des données déjà téléchargées. Par défaut, les vues qui utilisent *  les modèles de distribution CONTINUE ou PAGINATION renvoient le code 206 (Partial Content) pour indiquer qu'il s'agit *  uniquement d'un fragment de l'information. Quand le client a demandé tout le contenu (ou en d'autre termes, quand *  la vue ne trouve pas de contenu pour le fragment demandé(1)) le serveur renvoi le code 204 (No Content). * @param array|null opts Options à passer au serveur lors de la requête du fragment. A utiliser par exemple pour *  mettre en place un système de filtre, ou bien pour déterminer combien de lignes de tableau renvoyer. * @param boolean replaceData Si cet argument vaut FALSE, alors le contenu du fragment sera ajouté à la suite *  du contenu existant dans le container. Sinon, il le remplacera. Par défaut, vaut TRUE. * @param useCache Indique si le cache propre au système de frament doit être utilisé. * * @note (1) Dans le cas du modèle CONTINUE, le compteur de fragment est incrémenté automatiquement jusqu'au moment où le serveur *  renvoi le code 204. Dans le cas du modèle PAGINATION, c'est la vue qui doit donner à l'utilisateur des controles (a ou input) *  pour demander les pages suivantes ou précédentes.  */WG.View.prototype.setDataFragment = function (container, frag, opts, replaceData, useCache) {	var view = this;	console.log('Load fragment: ' + frag);	// TODO Mettre un status	WG.ajax({		url: WG.appURL + 'view.php?v=' + escape(this.name),		dataType: 'html',		data: {			'frag': frag		},		success: function (data, textStatus, jqXHR) {			// On met à jour le code HTML de la vue			if (replaceData) {				container.innerHTML = data;			}			else {				container.innerHTML += data;			}			// Le serveur doit renvoyer le code 206 pour indiquer que du contenu reste à télécharger.			// Sinon, on considère que tout le contenu a été chargé.			if (jqXHR.status != 206) {				view.isAllDataFragmentsLoaded = true;				view.trigger('fragmentsLoaded', {frag: frag, opts: opts, status: jqXHR.status});			}			// On lance un event pour indiquer qu'un fragment a été chargé			else {				view.trigger('fragmentLoaded', {frag: frag, opts: opts, status: jqXHR.status});			}			// On met à jour le curseur de fragments			view.currentFragmentCursor = frag;		},		error: function (jqXHR, textStatus, errorThrown) {			// TODO			console.log('Fragment load ERROR: ' + textStatus + ' (' + errorThrown + ')');		}	});};WG.View.prototype.nextFragment = function () {	// La vue ne supporte pas ce type de fragmentation du contenu	if (!this.supportDataFragments()) {		throw "Invalid distribution model";	}	// Tout le contenu a déjà été chargé	if (this.isAllDataFragmentsLoaded) {		return;	}	// On demande l'obtention du fragment de données	this.setDataFragment(		document.getElementById('dataContainer'),		this.currentFragmentCursor + 1,		null,		false,		true	);};WG.TrayIcon = function (data) {	this.name = data.name;	this.data = data;	this.badgeText = null;	this.notification = null;	this.node = null;	this.initialized = false;};/** * Afficher un petit badge text * @return this */WG.TrayIcon.prototype.setBadgeText = function (text) {	// Delete	if (!text) {		if (this.badgeText) {			$(this.badgeText).remove();			this.badgeText = null;		}		return this;	}	// Set	if (!this.badgeText) {		this.badgeText = document.createElement('span');		this.badgeText.setAttribute('class', 'badge');		this.node.appendChild(this.badgeText);		// Propager un clic sur le badge text au lien de l'icone		var thiz = this;		this.badgeText.onclick = function () {			$(thiz.a).click();		};	}	this.badgeText.innerHTML = text;	return this;};WG.TrayIcon.prototype.setLoadingIndicator = function (enable) {	if (enable) {		$(this.node).addClass('loading');	}	else {		$(this.node).removeClass('loading');	}}/** * @return string|null */WG.TrayIcon.prototype.getBadgeText = function (text) {	return (this.badgeText) ? this.badgeText.innerHTML : null;};/** * @return this */WG.TrayIcon.prototype.setNotification = function (text, onClick, duration) {	var thiz = this;	// Delete	if (!text) {		if (this.notification) {			$(this.notification).stop().fadeTo(1000, 0, function () {				$(thiz.notification).remove();				thiz.notification = null;			});		}		return this;	}	// Create notification node	if (!this.notification) {		this.notification = document.createElement('div');		this.notification.setAttribute('class', 'notification');		$(this.notification).fadeTo(0, 0);		this.node.appendChild(this.notification);	}	// Bind onclick event	if (onClick) {		this.notification.onclick = onClick;	}	// 	this.notification.innerHTML = text;	// Appear animation	$(this.notification).stop().fadeTo(1000, 0.9);	// Duration	if (this.notificationThread != null) {		clearTimeout(this.notificationThread);	}	if (!duration) duration = 6000;	if (duration) {		setTimeout(function () {			thiz.setNotification(null);		}, duration);	}	return this;}WG.util.implementListenerPattern(WG.TrayIcon.prototype);
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
		console.log('/!\\ Start live service...');
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
		url: WG.appURL + 'live.php',
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
		console.log('/!\\ Stop live service...');
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
			var time = new Date();
			// Fix date
			time.setTime(time.getTime() + WG.serverTimeDelta() * 1000);
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
			if (v.size() === keyup1) {
				url = v.attr('url');
				if (url != null && url.length > 0) {
					// cette portion de code permet de restituer le contexte de l'execution
					if (url.substr(0, 11) == 'javascript:') {
						WG.search.onReset(event, field);
						e = v.get(0);
						e.qstmp = function (d) { eval(d); };
						e.qstmp(url.substr(11));
						e.qstmp = null;
					}
					else {
						//document.location.href = url;
						WG.View.handleLink(url);
						event.preventDefault();
					}
					return false;
				}
			}
			return true;
		};
		// RESET
		WG.search.onReset = function (e, f) {
			$('[quicksearch]').show();
			if (f) {
				$(f).val('');
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
			var query = jQuery.trim($(this).val()).toLowerCase();
			if (query.length > 0) {
				if (e.keyCode == 13) { // Enter key
					return WG.search.onQuickAction(query, e, this);
				}
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
