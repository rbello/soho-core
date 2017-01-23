/*global Soho*/
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
