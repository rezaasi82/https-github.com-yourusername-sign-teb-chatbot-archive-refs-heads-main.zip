/**
 * Pazira — CRM Kanban board (vanilla JS, HTML5 drag & drop).
 * Dropping a card into a column persists the new lead status via AJAX.
 */
(function () {
	'use strict';
	if (typeof window.PZR_BOARD === 'undefined') { return; }
	var B = window.PZR_BOARD;

	var board = document.getElementById('pzr-board');
	var toast = document.getElementById('pzr-board-toast');
	if (!board) { return; }

	var dragged = null;

	function showToast(msg, ok) {
		if (!toast) { return; }
		toast.textContent = msg;
		toast.style.background = ok ? '#0f1f3d' : '#b32d2e';
		toast.hidden = false;
		clearTimeout(toast._t);
		toast._t = setTimeout(function () { toast.hidden = true; }, 2200);
	}

	function updateCounts() {
		board.querySelectorAll('.pzr-col').forEach(function (col) {
			var n = col.querySelectorAll('.pzr-card').length;
			var c = col.querySelector('.pzr-col-count');
			if (c) { c.textContent = n; }
		});
	}

	board.querySelectorAll('.pzr-card').forEach(bindCard);

	function bindCard(card) {
		card.addEventListener('dragstart', function () {
			dragged = card;
			card.classList.add('pzr-dragging');
		});
		card.addEventListener('dragend', function () {
			card.classList.remove('pzr-dragging');
		});
	}

	board.querySelectorAll('.pzr-col').forEach(function (col) {
		var body = col.querySelector('.pzr-col-body');
		col.addEventListener('dragover', function (e) {
			e.preventDefault();
			col.classList.add('pzr-col-over');
		});
		col.addEventListener('dragleave', function () {
			col.classList.remove('pzr-col-over');
		});
		col.addEventListener('drop', function (e) {
			e.preventDefault();
			col.classList.remove('pzr-col-over');
			if (!dragged) { return; }

			var fromCol = dragged.closest('.pzr-col');
			if (fromCol === col) { return; }

			var status = col.getAttribute('data-status');
			var leadId = dragged.getAttribute('data-lead');
			body.appendChild(dragged);
			updateCounts();
			persist(leadId, status);
		});
	});

	function persist(leadId, status) {
		var payload = new URLSearchParams();
		payload.append('action', 'pzr_lead_update');
		payload.append('nonce', B.nonce);
		payload.append('lead_id', leadId);
		payload.append('lead_status', status);

		fetch(B.ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: payload.toString()
		})
			.then(function (r) { return r.json(); })
			.then(function (res) { showToast(res && res.ok ? B.moved : B.failed, !!(res && res.ok)); })
			.catch(function () { showToast(B.failed, false); });
	}
})();
