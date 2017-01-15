
WG.ui.createWindow = function (opts) {

	opts = opts || {};
	opts = jQuery.extend({
		title: "New Window",
		resizable: true
	}, opts);

	// ID
	var id = Math.round(Math.random() * 99999999999);
	
	// Create window
	var window = document.createElement('div');
	window.setAttribute('class', 'window');
	window.setAttribute('id', 'window' + id);
	
	// Topbar
	var topbar = document.createElement('div');
	topbar.setAttribute('class', 'topbar');

	// Title
	var title = document.createElement('div');
	title.setAttribute('class', 'title');
	title.innerHTML = WG.util.htmlspecialchars(opts.title);

	// Construct window
	topbar.appendChild(title);
	window.appendChild(topbar);
	
	// Create jQuery node
	node = $(window);
	
	// Hide window
	node.hide();
	
	// Append to wrapper
	uidata.uiComponents.wrapper.appendChild(window);

	// Set draggable
	node.draggable({
		containment: uidata.uiComponents.wrapper,
		handle: 'div.topbar',
		scroll: false
	});
	
	// Set resizable
	if (opts.resizable) {
		node.resizable({

		});
	}

	return {
	
		node: node
	
	};

};