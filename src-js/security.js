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
