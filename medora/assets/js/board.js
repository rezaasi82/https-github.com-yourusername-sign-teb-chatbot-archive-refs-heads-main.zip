/**
 * Medora — CRM Kanban board (vanilla JS, HTML5 drag & drop).
 * Dropping a card into a column persists the new lead status via AJAX.
 */
(function () {
	'use strict';
	if (typeof window.MDR_BOARD === 'undefined') { return; }
	var B = window.MDR_BOARD;

	var board = document.getElementById('mdr-board');
	var toast = document.getElementById('mdr-board-toast');
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
		board.querySelectorAll('.mdr-col').forEach(function (col) {
			var n = col.querySelectorAll('.mdr-card').length;
			var c = col.querySelector('.mdr-col-count');
			if (c) { c.textContent = n; }
		});
	}

	board.querySelectorAll('.mdr-card').forEach(bindCard);

	function bindCard(card) {
		card.addEventListener('dragstart', function () {
			dragged = card;
			card.classList.add('mdr-dragging');
		});
		card.addEventListener('dragend', function () {
			card.classList.remove('mdr-dragging');
		});
	}

	board.querySelectorAll('.mdr-col').forEach(function (col) {
		var body = col.querySelector('.mdr-col-body');
		col.addEventListener('dragover', function (e) {
			e.preventDefault();
			col.classList.add('mdr-col-over');
		});
		col.addEventListener('dragleave', function () {
			col.classList.remove('mdr-col-over');
		});
		col.addEventListener('drop', function (e) {
			e.preventDefault();
			col.classList.remove('mdr-col-over');
			if (!dragged) { return; }

			var fromCol = dragged.closest('.mdr-col');
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
		payload.append('action', 'mdr_lead_update');
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
