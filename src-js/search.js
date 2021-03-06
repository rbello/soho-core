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
