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
		// La page existe déjà, on la réutilise
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
		// On fait ça avant le refresh, pour que le script de la vue
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

	/* * *   TrayMenu   * * */

	TrayMenuStack: {
		TOP: 0,
		MIDDLE: 5,
		BOTTOM: 10
	},

	// TODO Gêrer le stack position
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

};