
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
			if (v.size() === 1) {
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