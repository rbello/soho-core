
WG.ui.addTrayIcon({

	name: 'clock',

	onInit: function () {
		this.node.innerHTML = '--:--';
		// Refresh
		this.updateClock = function () {
			var time = new Date();
			// Fix date
			time.setTime(time.getTime() + WG.serverTimeDelta() * 1000);
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