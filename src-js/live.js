/*global Soho*/
/*global jQuery*/
/**
 * Constructor.
 *
 * @access public
 */
Soho.LiveService = function () {
	var thiz = this;
	this.thead = setInterval(function () {
		thiz.refresh();
	}, 10000);
	if (console) {
		console.log('[live] Start live service...');
	}
};

/**
 * @access public
 */
Soho.LiveService.prototype.refresh = function () {

	if (!Soho.live) return;

	// Get query parameters
	var data = this.getParameters();

	// No events to listen
	if (!data) {
		return;
	}

	// Query to server
	Soho.ajax({
		url: Soho.appURL() + 'live.php',
		data: data,
		success: function (data) {
			if ('error' in data) {
				Soho.ui.getTrayIcon('power').setNotification('Error in live service.');
				return;
			}
			// Update lastUpdate with server time
			Soho.lastUpdate = data.serverTime;
			// Propagate event
			Soho.live.propagation(data);
		},
		error: function () {
			Soho.ui.getTrayIcon('power').setNotification('Unable to connect live service.');
		}
	});

};

/**
 * @access public
 */
Soho.LiveService.prototype.getParameters = function () {
	var i = 0,
		events = { };
	// Fetch live listeners
	for (let listener in Soho.live.bind) {
		var l = Soho.live.bind[listener];
		// Pour l'explication de ce truc, voir WG.LiveService.prototype.propagation()
		if (
			!('view' in l) || // Soit le listener n'est pas associé à une vue précise
			(Soho.ui.currentView != null && Soho.ui.currentView.name === l.view) // Soit il s'agit justement de cette vue là
			) {
				// Dans ce cas, on idique que cet event est bien à demander au serveur
				events[l.event] = 1;
				i++;
		}
	}
	// Nothing to retreive
	if (i < 1) return null;
	// Create a string with events
	var eventsList = [];
	for (event in events) {
		eventsList.push(event);
	}
	// Return request data
	return {
		't': Soho.lastUpdate,
		'l': eventsList.join('|')
	};
};

/**
 * @access public
 */
Soho.LiveService.prototype.stop = function () {
	if (console) {
		console.log('[live] Stop live service...');
	}
	clearInterval(this.thread); // marche pas ... TODO à cause du scope de this ?
};

/**
 * HashMap with all listeners.
 * @access public
 */
Soho.LiveService.prototype.bind = {};

/**
 * @access public
 */
Soho.LiveService.prototype.propagation = function (data) {
	// Fetch live listeners
	for (let listener in Soho.live.bind) {
		var l = Soho.live.bind[listener];
		// Dans un premier temps, on regarde si le listener a configur� une vue.
		// Si c'est le cas, on compare avec le nom de la vue actuelle. S'il s'agit
		// bien de la bonne vue on continue.
		// Donc, si le listener ne sp�cifie pas d'attribut 'view' il sera d�clanch�
		// � chaque fois.
		if (!('view' in l) || (Soho.ui.currentView != null && Soho.ui.currentView.name === l.view)) {
			// Ensuite on regarge si l'event que le listener a choisi a �t� diffus�
			// par le serveur.
			if (l.event in data) {
				// Si c'est le cas, on propage l'event au listener
				l.onChange(data[l.event]);
			}
		}
	}
}


/* POWER BUTTON */

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
	}

});

/* CLOCK */

Soho.ui.addTrayIcon({

	name: 'clock',

	onInit: function () {
		this.node.innerHTML = '--:--';
		// Refresh
		this.updateClock = function () {
			var time = Soho.time.getServerTime();
			// Get jours & minutes
			var h = time.getHours(),
				m = time.getMinutes();
			if (h < 10) h = '0' + h;
			if (m < 10) m = '0' + m;
			this.node.innerHTML = h + ':' + m;
		}
	},

	onHide: function () {
		clearInterval(this.intervalThread);
	},

	onAppear: function () {
		this.updateClock();
		// Update thread
		var thiz = this;
		this.intervalThread = setInterval(function () {
			thiz.updateClock();
		}, 5000);
	}

});