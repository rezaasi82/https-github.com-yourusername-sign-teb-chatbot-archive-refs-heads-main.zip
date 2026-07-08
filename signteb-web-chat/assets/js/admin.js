/**
 * SignTeb AI Web Chat — admin settings (Vanilla JS).
 * Shows only the API-key + model fields for the currently selected provider,
 * updated live without a page refresh.
 */
(function () {
	'use strict';

	var select = document.getElementById('swc-provider');
	if (!select) {
		return;
	}

	var blocks = document.querySelectorAll('.swc-provider-block');

	function sync() {
		var active = select.value;
		blocks.forEach(function (block) {
			block.style.display = block.getAttribute('data-provider') === active ? '' : 'none';
		});
	}

	select.addEventListener('change', sync);
	sync();
})();

/* ---------- Export module (leads table + integration tests) ---------- */
(function () {
	'use strict';
	if (typeof window.SWC_ADMIN === 'undefined') { return; }
	var A = window.SWC_ADMIN;

	function post(action, data) {
		var body = new URLSearchParams();
		body.append('action', action);
		body.append('nonce', A.nonce);
		Object.keys(data || {}).forEach(function (k) {
			if (Array.isArray(data[k])) {
				data[k].forEach(function (v) { body.append(k + '[]', v); });
			} else {
				body.append(k, data[k]);
			}
		});
		return fetch(A.ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		}).then(function (r) { return r.json(); });
	}

	// Per-row export actions.
	document.querySelectorAll('.swc-act').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var op = btn.getAttribute('data-op');
			var lead = btn.getAttribute('data-lead');
			var original = btn.textContent;
			btn.disabled = true; btn.textContent = A.strings.working;
			var action = op === 'webhook' ? 'swc_export_webhook' : 'swc_export_gsheet';
			post(action, { lead_id: lead }).then(function (res) {
				btn.textContent = res && res.ok ? '✓ ' + A.strings.ok : '✕ ' + A.strings.failed;
				setTimeout(function () { btn.disabled = false; btn.textContent = original; }, 2500);
			}).catch(function () {
				btn.textContent = '✕'; btn.disabled = false;
			});
		});
	});

	// Select-all + bulk actions.
	var checkAll = document.getElementById('swc-check-all');
	if (checkAll) {
		checkAll.addEventListener('change', function () {
			document.querySelectorAll('.swc-check').forEach(function (c) { c.checked = checkAll.checked; });
		});
	}
	var bulkApply = document.getElementById('swc-bulk-apply');
	if (bulkApply) {
		bulkApply.addEventListener('click', function () {
			var op = document.getElementById('swc-bulk-op').value;
			var ids = [];
			document.querySelectorAll('.swc-check:checked').forEach(function (c) { ids.push(c.value); });
			var out = document.getElementById('swc-bulk-result');
			if (!op || ids.length === 0) { out.textContent = A.strings.noSel; return; }
			bulkApply.disabled = true; out.textContent = A.strings.working;
			post('swc_export_bulk', { op: op, ids: ids }).then(function (res) {
				out.textContent = (res && res.ok) ? (res.processed + ' / ' + res.total) : A.strings.failed;
				bulkApply.disabled = false;
			}).catch(function () { out.textContent = A.strings.failed; bulkApply.disabled = false; });
		});
	}

	// Connection tests (Integrations tab).
	document.querySelectorAll('.swc-test-btn').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var target = btn.getAttribute('data-target');
			var out = document.querySelector('.swc-test-result[data-for="' + target + '"]');
			btn.disabled = true; if (out) { out.textContent = A.strings.working; }
			post(target === 'webhook' ? 'swc_test_webhook' : 'swc_test_gsheet', {}).then(function (res) {
				if (out) {
					out.textContent = (res && res.ok) ? ('✓ ' + A.strings.ok + (res.code ? ' (HTTP ' + res.code + ')' : '')) : ('✕ ' + (res && res.error ? res.error : A.strings.failed));
					out.style.color = (res && res.ok) ? '#1a7f37' : '#d63638';
				}
				btn.disabled = false;
			}).catch(function () { if (out) { out.textContent = '✕'; } btn.disabled = false; });
		});
	});
})();
