/**
 * Live new-chat counter for the admin bar.
 *
 * Piggybacks on the WordPress Heartbeat API: every tick asks the server for
 * the current unseen-conversation count and updates the admin-bar bubble
 * (and the Pezhkam menu badge) in place — no reload required.
 */
(function ($) {
	'use strict';

	if (!$) { return; }

	function applyCount(n) {
		var node = document.getElementById('wp-admin-bar-pzk-chats');
		if (node) {
			var count = node.querySelector('.pzk-ab-count');
			if (count) { count.textContent = String(n); }
			node.classList.toggle('pzk-ab-zero', n < 1);
			node.classList.toggle('pzk-ab-has-new', n > 0);
		}
		// WordPress clones the menu title into several nodes — update them all.
		document.querySelectorAll('.pzk-menu-count').forEach(function (badge) {
			badge.textContent = String(n);
			badge.style.display = n > 0 ? '' : 'none';
		});
	}

	$(document).on('heartbeat-send', function (event, data) {
		data.pzk_notify = 1;
	});

	$(document).on('heartbeat-tick', function (event, data) {
		if (typeof data.pzk_new_chats === 'undefined') { return; }
		applyCount(parseInt(data.pzk_new_chats, 10) || 0);
	});
})(window.jQuery);
