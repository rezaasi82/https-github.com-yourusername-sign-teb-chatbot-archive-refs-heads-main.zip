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
				if (res && res.ok) {
					out.textContent = (typeof res.queued !== 'undefined')
						? (res.queued + ' ' + (A.strings.queued || 'در صف پردازش'))
						: (res.processed + ' / ' + res.total);
				} else {
					out.textContent = A.strings.failed;
				}
				bulkApply.disabled = false;
			}).catch(function () { out.textContent = A.strings.failed; bulkApply.disabled = false; });
		});
	}

	// CRM lead save (single-lead view).
	var crm = document.querySelector('.swc-crm-panel');
	if (crm) {
		var saveBtn = crm.querySelector('.swc-crm-save');
		var result = crm.querySelector('.swc-crm-result');
		saveBtn.addEventListener('click', function () {
			saveBtn.disabled = true; result.textContent = A.strings.working;
			var branchSel = crm.querySelector('.swc-crm-branch');
			var data = {
				lead_id: crm.getAttribute('data-lead'),
				nonce: crm.getAttribute('data-nonce'),
				lead_status: crm.querySelector('.swc-crm-status').value,
				email: crm.querySelector('.swc-crm-email').value,
				tags: crm.querySelector('.swc-crm-tags').value,
				notes: crm.querySelector('.swc-crm-notes').value
			};
			if (branchSel) { data.branch_id = branchSel.value; }
			post('swc_lead_update', data).then(function (res) {
				result.textContent = (res && res.ok) ? '✓ ' + A.strings.ok : '✕ ' + (res && res.error ? res.error : A.strings.failed);
				result.style.color = (res && res.ok) ? '#1a7f37' : '#d63638';
				saveBtn.disabled = false;
			}).catch(function () { result.textContent = '✕'; saveBtn.disabled = false; });
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
