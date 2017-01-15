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
			// Plutot utiliser un foreach non ? car le one va supprimer les clés...
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