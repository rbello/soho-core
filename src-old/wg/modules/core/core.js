WGCore = function () {

	var thiz = this;
	window["WG"] = this;

	this.history = [];
	this.behaviors = [];

	this.lastUpdate = <?php echo time(); ?>;
	this.lastLogsCounter = 0;
	this.connected = false;

	this.applyBehaviour = function (node) {
		//alert('applyBehaviour on ' + node.get(0).nodeName);
		for (var i = 0, b; b = this.behaviors[i]; i++) {
			if (b.init) {
				b.init();
				b.init = undefined;
			}
			b.apply(node);
		}
		return i;
	}

	/*** Behaviors ***/

	// Click on follow links
	this.behaviors.push({
		apply: function (node) {
			$('a[follow]', node).click(function (e) {
				var link = this;
				var data = $(this).attr('follow').split(':');
				$(this).hide();
				$(this).switchFollow(data[0], data[1], function (success, follow) {
					$(this).show();
					if (success) {
						var img = '<img src="wg/modules/activity/public/feed_' + (follow ? 'delete' : 'add') + '.png" /> ';
						$(link).html(img + (follow ? 'Unfollow' : 'Follow'));
					}
				});
				e.preventDefault();
				return false;
			});
		}
	});

	// Ajax links
	this.behaviors.push({
		init: function () {
			$(window).bind('hashchange', function (e) {
				var pos = parseInt(location.hash.substr(1, location.hash.length));
				if ('#' + pos == location.hash && pos > 0 && pos <= WG.history.length) {
					var url = WG.history[pos - 1];
					$("#wrapper")
						.html('<img style="margin:30px" src="<?php echo WG::url('modules/core/public/wait_16x16.gif'); ?>" />')
						.load(url + '&fragment=true', function () {
							WG.applyBehaviour($('#wrapper'));
						});
				}
			});
		},
		apply: function (node) {
			$('#menu a,a.asynch', node).bind('click', function () {
				WG.history.push($(this).attr('href'));
				document.location.hash = '#' + WG.history.length;
				return false;
			});
		}
	});

	// Height 100%
	this.behaviors.push({
		init: function () {
			//alert('init height');
			$(window).resize(function () {
				$('.vertical-panel,.vertical-content').trigger('fill');
			});
		},
		apply: function (node) {
			//alert('apply height on ' + node);
			$('.vertical-panel,.vertical-content', node)
				.bind('fill', function () {
					var top = $(this).offset().top;
					$(this).height($(window).height() - top - 2);
				})
				.trigger('fill');
		}
	});

	// Music
	this.behaviors.push({
		init: function () {
			PlayList.init();
		},
		apply: function (node) {
			// Controls
			$('.ctrl-music-rewind', node).click(function () {
				PlayList.previous();
				$(this).blur();
				return false;
			});
			$('.ctrl-music-forward', node).click(function () {
				PlayList.next();
				$(this).blur();
				return false;
			});
			$('.ctrl-music-play', node).click(function () {
				if (PlayList.playing()) {
					PlayList.pause();
				}
				else {
					PlayList.resume();
				}
				$(this).blur();
				return false;
			});
			// Playlist controls
			$('.playlist-delete', node).click(function () {
				PlayList.clean();
				return false;
			});
			// Playlist links
			$('.playlist-delete', node).click(function () {
				PlayList.clean();
				return false;
			});
			// Songs links
			$('a.song', node).click(function () {
				var thiz = $(this);
				var info = {
					name: thiz.attr('title'),
					artist: thiz.attr('artist'),
					album: thiz.attr('album'),
					genre: thiz.attr('genre'),
					type: 'mp3',
					mp3: '<?php echo WG::vars('appurl'); ?>?ws=getmusic&file=' + encodeURIComponent(thiz.attr('href'))
				};
				PlayList.add(info);
				return false;
			});
			// Render playlist
			PlayList.render();
		}
	});

	// Tabbed panes
	this.behaviors.push({
		apply: function (node) {
			$('.tabmenu', node).each(function () {
				var menu = $(this);
				// Menu behavior
				$('li a', menu).click(function () {
					var a = $(this);
					// Remove selected tab legend
					$('li.selected', menu).removeClass('selected');
					// Add selected indcator to this tab legend
					a.parent().addClass('selected');
					// Hide all tabs
					$('.tabcontent.tab-' + menu.attr('tab'), node).removeClass('selected');
					// Show the new tab
					$('.tabcontent' + a.attr('href'), node).addClass('selected');
				});
			});
		}
	});

	/*** Initialization ***/

	this.init = function () {

		this.applyBehaviour($('body'));

		// Optimiser en cherchant le premier parent ?
		$('#close-poptop').click(function () {
			$('#poptop').hide();
		});

		this.initActivitiesWs();
		this.initMailboxWs();

		// Max height for drop-down panels
		var f = function () {
			$('.drop-down').css('max-height', ($(window).height() - 60) + 'px');
		};
		$(window).resize(f);
		f();

	}

	this.initActivitiesWs = function () {
		setInterval(function () {
			$.ajax({
				url: '<?php echo WG::vars('appurl'); ?>index.php?ws=lastlog&time=' + thiz.lastUpdate,
				dataType: 'xml',
				error: function (x, t, e) {
					thiz.connected = false;
					$("#disconnected").show();
					//alert(e+' / '+t+' /' + x);
				},
				success: function (d, t, x) {
					if (!thiz.connected) {
						thiz.connected = true;
						$("#disconnected").hide();
					}
					// Get server's response
					var rsp = d.getElementsByTagName('rsp')[0];
					// Check response
					if (rsp.getAttribute('stat') != 'ok') {
						return;
					}
					// Get synch time
					thiz.lastUpdate = parseInt(rsp.getAttribute('utime'));
					// Fetch logs
					var logs = d.getElementsByTagName('log');
					for (var i = 0, log; log = logs[i]; i++) {
						//alert(log.getAttribute('ctime') + ' : ' +log.firstChild.nodeValue);
						$('#lastlogdock').prepend($('<div>').html(htmlspecialchars_decode(
							'<strong>' + log.getAttribute('user') + '</strong> ' + log.firstChild.nodeValue
						)));
					}
					// Update badge text
					thiz.lastLogsCounter += logs.length;
					if (thiz.lastLogsCounter > 0) {
						thiz.setActivitiesBadgeText(thiz.lastLogsCounter);
						document.title = '<?php echo WG::vars('appName'); ?> ('+thiz.lastLogsCounter+')';
					}
					else {
						thiz.setActivitiesBadgeText(null);
						document.title = '<?php echo WG::vars('appName'); ?>';
					}
				}
			});
		}, <?php echo WG::vars('dev_mode') ? '10000' : WG::vars('ui_ws_refresh') * 1000; ?>);
	}

	this.initMailboxWs = function () {
		setInterval(function () {
			$.ajax({
				url: '<?php echo WG::vars('appurl'); ?>index.php?ws=mailbox',
				dataType: 'xml',
				error: function (x, t, e) {
				},
				success: function (d, t, x) {
					// Get server's response
					var rsp = d.getElementsByTagName('rsp')[0];
					// Check response
					if (rsp.getAttribute('stat') != 'ok') {
						return;
					}
					// Fetch mails
					var mails = d.getElementsByTagName('mail');
					var html = '';
					for (var i = 0, mail; mail = mails[i]; i++) {
						html += '<div>From <b>'+mail.getAttribute('from')+'</b>: '+mail.getAttribute('subject')+'</div>';
					}
					//
					$('#mailboxdock').html(html + '<div class="hl"><a href="index.php?view=mailbox">More</a></div>');
					// Update badge text
					thiz.setMailsBadgeText(mails.length > 0 ? mails.length : null);
				}
			});
		}, <?php echo WG::vars('dev_mode') ? '3000' : WG::vars('ui_ws_refresh') * 1000; ?> + 5);
	}

	this.setMusicWaitDongle = function (s) {
		if (s) {
			$('#musicwait').show();
		}
		else {
			$('#musicwait').hide();
		}
	}

	this.setActivitiesBadgeText = function (t) {
		var m = $('#lastlogbox');
		$('.badge', m).remove();
		if (t !== null) {
			m.prepend('<span class="badge">'+t+'</span>');
		}
		return this;
	}

	this.setMailsBadgeText = function (t) {
		var m = $('#mailbox');
		$('.badge', m).remove();
		if (t !== null) {
			m.prepend('<span class="badge">'+t+'</span>');
		}
		return this;
	}

	// Observer pattern
	this.listeners = [];
	this.one = function (event, callback) {
		return this.bind(event, callback, true);
	}
	this.bind = function (event, callback, one) {
		if (jQuery.type(event) != "string") return this;
		if (jQuery.type(callback) != "function") return this;
		this.listeners.push({
			"event": event,
			"callback": callback,
			"one": one === true
		});
		return this;
	}
	this.trigger = function (event, data) {
		for (var i = 0, j = this.listeners.length; i < j; i++) {
			 var li = this.listeners[i];
			 if (!li) continue;
			 if (li.event == event || li.event == "*") {
				this.thread = li.callback;
				this.thread(event, data);
				if (li.one) {
					this.listeners.splice(i, 1);
				}
			 }
		}
		this.thread = null;
		return this;
	}
	this.unbind = function (event, callback) {
		if (event && callback) {
			for (var i = 0, j = this.listeners.length; i < j; i++) {
				 var li = this.listeners[i];
				 if (!li) continue;
				 if ((li.event == event || event == "*") && li.callback == callback) {
					this.listeners.splice(i, 1);
				 }
			}
		}
		else if (event) {
			for (var i = 0, j = this.listeners.length; i < j; i++) {
				 var li = this.listeners[i];
				 if (!li) continue;
				 if (li.event == event || event == "*") {
					this.listeners.splice(i, 1);
				 }
			}
		}
		else {
			this.listeners = [];
		}
		return this;
	}

	this.init();

	return this;

}

$.fn.switchFollow = function (target_type, target_id, callback) {
	$.ajax({
		url: 'index.php?ws=follow',
		dataType: 'json',
		context: this,
		data: {
			'type': target_type,
			'target': target_id
		},
		error: function (x, t, e) {
			if (callback) {
				this.tmpsw = callback;
				this.tmpsw(false, false, target_type, target_id);
				this.tmpsw = null;
			}
		},
		success: function (d, t, x) {
			if ("done" in d) {
				this.tmpsw = callback;
				this.tmpsw(true, d.done, target_type, target_id);
				this.tmpsw = null;
			}
			else {
				this.tmpsw = callback;
				this.tmpsw(false, false, target_type, target_id);
				this.tmpsw = null;
			}
		}
	});
	return this;
}

function htmlspecialchars_decode (string, quote_style) {
    // http://kevin.vanzonneveld.net
    // +   original by: Mirek Slugen
    // +   improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +   bugfixed by: Mateusz "loonquawl" Zalega
    // +      input by: ReverseSyntax
    // +      input by: Slawomir Kaniecki
    // +      input by: Scott Cariss
    // +      input by: Francois
    // +   bugfixed by: Onno Marsman
    // +    revised by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +   bugfixed by: Brett Zamir (http://brett-zamir.me)
    // +      input by: Ratheous
    // +      input by: Mailfaker (http://www.weedem.fr/)
    // +      reimplemented by: Brett Zamir (http://brett-zamir.me)
    // +    bugfixed by: Brett Zamir (http://brett-zamir.me)
    // *     example 1: htmlspecialchars_decode("<p>this -&gt; &quot;</p>", 'ENT_NOQUOTES');
    // *     returns 1: '<p>this -> &quot;</p>'
    // *     example 2: htmlspecialchars_decode("&amp;quot;");
    // *     returns 2: '&quot;'
    var optTemp = 0,
        i = 0,
        noquotes = false;
    if (typeof quote_style === 'undefined') {
        quote_style = 2;
    }
    string = string.toString().replace(/&lt;/g, '<').replace(/&gt;/g, '>');
    var OPTS = {
        'ENT_NOQUOTES': 0,
        'ENT_HTML_QUOTE_SINGLE': 1,
        'ENT_HTML_QUOTE_DOUBLE': 2,
        'ENT_COMPAT': 2,
        'ENT_QUOTES': 3,
        'ENT_IGNORE': 4
    };
    if (quote_style === 0) {
        noquotes = true;
    }
    if (typeof quote_style !== 'number') { // Allow for a single string or an array of string flags
        quote_style = [].concat(quote_style);
        for (i = 0; i < quote_style.length; i++) {
            // Resolve string input to bitwise e.g. 'PATHINFO_EXTENSION' becomes 4
            if (OPTS[quote_style[i]] === 0) {
                noquotes = true;
            } else if (OPTS[quote_style[i]]) {
                optTemp = optTemp | OPTS[quote_style[i]];
            }
        }
        quote_style = optTemp;
    }
    if (quote_style & OPTS.ENT_HTML_QUOTE_SINGLE) {
        string = string.replace(/&#0*39;/g, "'"); // PHP doesn't currently escape if more than one 0, but it should
        // string = string.replace(/&apos;|&#x0*27;/g, "'"); // This would also be useful here, but not a part of PHP
    }
    if (!noquotes) {
        string = string.replace(/&quot;/g, '"');
    }
    // Put this in last place to avoid escape being double-decoded
    string = string.replace(/&amp;/g, '&');

    return string;
}