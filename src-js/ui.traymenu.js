/*global Soho*/
/*global jQuery*/
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
	},

	onHide: function () {
	},

	onAppear: function () {
	},

	onDestroy: function () {
	}

});