
/**
 * Constructor.
 *
 * @access public
 */
WG.LiveService = function () {
	var thiz = this;
	this.thead = setInterval(function () {
		thiz.refresh();
	}, 10000);
	if (console) {
		console.log('/!\\ Start live service...');
	}
};

/**
 * @access public
 */
WG.LiveService.prototype.refresh = function () {

	if (!WG.live) return;

	// Get query parameters
	var data = this.getParameters();

	// No events to listen
	if (!data) {
		return;
	}

	// Query to server
	WG.ajax({
		url: WG.appURL + 'live.php',
		data: data,
		success: function (data) {
			if ('error' in data) {
				WG.ui.getTrayIcon('power').setNotification('Error in live service.');
				return;
			}
			// Update lastUpdate with server time
			WG.lastUpdate = data.serverTime;
			// Propagate event
			WG.live.propagation(data);
		},
		error: function () {
			WG.ui.getTrayIcon('power').setNotification('Unable to connect live service.');
		}
	});

};

/**
 * @access public
 */
WG.LiveService.prototype.getParameters = function () {
	var i = 0,
		events = { };
	// Fetch live listeners
	for (listener in WG.live.bind) {
		var l = WG.live.bind[listener];
		// Pour l'explication de ce truc, voir WG.LiveService.prototype.propagation()
		if (
			!('view' in l) || // Soit le listener n'est pas associé à une vue précise
			(WG.ui.currentView != null && WG.ui.currentView.name === l.view) // Soit il s'agit justement de cette vue là
			) {
				// Dans ce cas, on idique que cet event est bien à demander au serveur
				events[l.event] = 1;
				i++;
		}
	}
	// Nothing to retreive
	if (i < 1) return null;
	// Create a string with events
	eventsList = [];
	for (event in events) {
		eventsList.push(event);
	}
	// Return request data
	return {
		't': WG.lastUpdate,
		'l': eventsList.join('|')
	};
};

/**
 * @access public
 */
WG.LiveService.prototype.stop = function () {
	if (console) {
		console.log('/!\\ Stop live service...');
	}
	clearInterval(this.thread); // marche pas ...
};

/**
 * HashMap with all listeners.
 * @access public
 */
WG.LiveService.prototype.bind = {};

/**
 * @access public
 */
WG.LiveService.prototype.propagation = function (data) {
	// Fetch live listeners
	for (listener in WG.live.bind) {
		var l = WG.live.bind[listener];
		// Dans un premier temps, on regarde si le listener a configuré une vue.
		// Si c'est le cas, on compare avec le nom de la vue actuelle. S'il s'agit
		// bien de la bonne vue on continue.
		// Donc, si le listener ne spécifie pas d'attribut 'view' il sera déclanché
		// à chaque fois.
		if (!('view' in l) || (WG.ui.currentView != null && WG.ui.currentView.name === l.view)) {
			// Ensuite on regarge si l'event que le listener a choisi a été diffusé
			// par le serveur.
			if (l.event in data) {
				// Si c'est le cas, on propage l'event au listener
				l.onChange(data[l.event]);
			}
		}
	}
}