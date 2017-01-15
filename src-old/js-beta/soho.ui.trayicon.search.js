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