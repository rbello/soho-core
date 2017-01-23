/*global Soho*/
/*global jQuery*/
Soho.TrayIcon = function (data) {
	this.name = data.name;
	this.data = data;
	this.badgeText = null;
	this.notification = null;
	this.node = null;
	this.initialized = false;
};

/**
 * Afficher un petit badge text
 * @return this
 */
Soho.TrayIcon.prototype.setBadgeText = function (text) {
	// Delete
	if (!text) {
		if (this.badgeText) {
			jQuery(this.badgeText).remove();
			this.badgeText = null;
		}
		return this;
	}
	// Set
	if (!this.badgeText) {
		this.badgeText = document.createElement('span');
		this.badgeText.setAttribute('class', 'badge');
		this.node.appendChild(this.badgeText);
		// Propager un clic sur le badge text au lien de l'icone
		var thiz = this;
		this.badgeText.onclick = function () {
			jQuery(thiz.a).click();
		};
	}
	this.badgeText.innerHTML = text;
	return this;
};

/**
 * @return string|null
 */
Soho.TrayIcon.prototype.getBadgeText = function () {
	return (this.badgeText) ? this.badgeText.innerHTML : null;
};

Soho.TrayIcon.prototype.setLoadingIndicator = function (enable) {
	if (enable) {
		jQuery(this.node).addClass('loading');
	}
	else {
		jQuery(this.node).removeClass('loading');
	}
	return this;
};

Soho.TrayIcon.prototype.hasLoadingIndicator = function () {
	return jQuery(this.node).hasClass('loading');
};



/**
 * @return this
 */
Soho.TrayIcon.prototype.setNotification = function (text, onClick, duration) {
	var thiz = this;
	// Delete
	if (!text) {
		if (this.notification) {
			jQuery(this.notification).stop().fadeTo(1000, 0, function () {
				jQuery(thiz.notification).remove();
				thiz.notification = null;
			});
		}
		return this;
	}
	// Create notification node
	if (!this.notification) {
		this.notification = document.createElement('div');
		this.notification.setAttribute('class', 'notification');
		jQuery(this.notification).fadeTo(0, 0);
		this.node.appendChild(this.notification);
	}
	// Bind onclick event
	if (onClick) {
		this.notification.onclick = onClick;
	}
	// 
	this.notification.innerHTML = text;
	// Appear animation
	jQuery(this.notification).stop().fadeTo(1000, 0.9);
	// Duration
	if (this.notificationThread != null) {
		clearTimeout(this.notificationThread);
	}
	if (!duration) duration = 6000;
	if (duration) {
		setTimeout(function () {
			thiz.setNotification(null);
		}, duration);
	}
	return this;
};

Soho.util.implementListenerPattern(Soho.TrayIcon.prototype);