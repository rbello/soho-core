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
		if (form.hasAttribute('apikey')) {
			password = 's:' + WG.security.sha1(salt + ':' + WG.security.sha1(password) + ':' + form.getAttribute('apikey'));
		}
		else {
			password = 'b:' + WG.security.sha1(salt + ':' + WG.security.sha1(password));
		}

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