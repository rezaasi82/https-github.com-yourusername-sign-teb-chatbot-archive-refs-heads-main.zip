/**
 * Pezhkam — premium dashboard (vanilla JS, no dependencies).
 * Animates the metric counters and refreshes the integrity badge live.
 */
(function () {
	'use strict';

	// Count-up animation for the metric tiles.
	document.querySelectorAll('.pzk-tile-num[data-count]').forEach(function (el) {
		var target = parseInt(el.getAttribute('data-count'), 10) || 0;
		if (target <= 0) { el.textContent = '0'; return; }
		var start = null;
		var dur = 900;
		function easeOut(t) { return 1 - Math.pow(1 - t, 3); }
		function step(ts) {
			if (start === null) { start = ts; }
			var p = Math.min((ts - start) / dur, 1);
			el.textContent = Math.round(target * easeOut(p)).toString();
			if (p < 1) { requestAnimationFrame(step); }
		}
		requestAnimationFrame(step);
	});

	// Live integrity refresh (nonce + capability enforced server-side).
	if (typeof window.PZK_DASH === 'undefined') { return; }
	var badge = document.getElementById('pzk-int-badge');
	if (!badge) { return; }

	var body = new URLSearchParams();
	body.append('action', 'pzk_integrity_check');
	body.append('nonce', window.PZK_DASH.nonce);

	fetch(window.PZK_DASH.ajaxUrl, {
		method: 'POST',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
		body: body.toString()
	})
		.then(function (r) { return r.json(); })
		.then(function (res) {
			if (res && res.ok && res.integrity) {
				badge.textContent = res.integrity.label;
				var tile = badge.closest('.pzk-tile-int');
				if (tile) {
					tile.className = tile.className.replace(/is-\w+/g, '');
					tile.classList.add('pzk-tile-int', 'is-' + res.integrity.level.replace(/[^a-z]/g, ''));
				}
			}
		})
		.catch(function () {});
})();
