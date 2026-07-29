/**
 * Medora AI Web Chat — admin settings (Vanilla JS).
 * Shows only the API-key + model fields for the currently selected provider,
 * updated live without a page refresh.
 */
(function () {
	'use strict';

	var select = document.getElementById('mdr-provider');
	if (!select) {
		return;
	}

	var blocks = document.querySelectorAll('.mdr-provider-block');

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
	if (typeof window.MDR_ADMIN === 'undefined') { return; }
	var A = window.MDR_ADMIN;

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
	document.querySelectorAll('.mdr-act').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var op = btn.getAttribute('data-op');
			var lead = btn.getAttribute('data-lead');
			var original = btn.textContent;
			btn.disabled = true; btn.textContent = A.strings.working;
			var action = op === 'webhook' ? 'mdr_export_webhook' : 'mdr_export_gsheet';
			post(action, { lead_id: lead }).then(function (res) {
				btn.textContent = res && res.ok ? '✓ ' + A.strings.ok : '✕ ' + A.strings.failed;
				setTimeout(function () { btn.disabled = false; btn.textContent = original; }, 2500);
			}).catch(function () {
				btn.textContent = '✕'; btn.disabled = false;
			});
		});
	});

	// Select-all + bulk actions.
	var checkAll = document.getElementById('mdr-check-all');
	if (checkAll) {
		checkAll.addEventListener('change', function () {
			document.querySelectorAll('.mdr-check').forEach(function (c) { c.checked = checkAll.checked; });
		});
	}
	var bulkApply = document.getElementById('mdr-bulk-apply');
	if (bulkApply) {
		bulkApply.addEventListener('click', function () {
			var op = document.getElementById('mdr-bulk-op').value;
			var ids = [];
			document.querySelectorAll('.mdr-check:checked').forEach(function (c) { ids.push(c.value); });
			var out = document.getElementById('mdr-bulk-result');
			if (!op || ids.length === 0) { out.textContent = A.strings.noSel; return; }
			bulkApply.disabled = true; out.textContent = A.strings.working;
			post('mdr_export_bulk', { op: op, ids: ids }).then(function (res) {
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
	var crm = document.querySelector('.mdr-crm-panel');
	if (crm) {
		var saveBtn = crm.querySelector('.mdr-crm-save');
		var result = crm.querySelector('.mdr-crm-result');
		saveBtn.addEventListener('click', function () {
			saveBtn.disabled = true; result.textContent = A.strings.working;
			var branchSel = crm.querySelector('.mdr-crm-branch');
			var data = {
				lead_id: crm.getAttribute('data-lead'),
				nonce: crm.getAttribute('data-nonce'),
				lead_status: crm.querySelector('.mdr-crm-status').value,
				email: crm.querySelector('.mdr-crm-email').value,
				tags: crm.querySelector('.mdr-crm-tags').value,
				notes: crm.querySelector('.mdr-crm-notes').value
			};
			if (branchSel) { data.branch_id = branchSel.value; }
			post('mdr_lead_update', data).then(function (res) {
				result.textContent = (res && res.ok) ? '✓ ' + A.strings.ok : '✕ ' + (res && res.error ? res.error : A.strings.failed);
				result.style.color = (res && res.ok) ? '#1a7f37' : '#d63638';
				saveBtn.disabled = false;
			}).catch(function () { result.textContent = '✕'; saveBtn.disabled = false; });
		});
	}

	// SMS panel settings — provider-specific field toggling + test send.
	var smsProvider = document.querySelector('.mdr-sms-provider');
	if (smsProvider) {
		var customBox = document.querySelector('.mdr-sms-custom');
		var secretRow = document.querySelector('.mdr-sms-secret-row');
		var meliHint = document.querySelector('.mdr-sms-hint[data-for="melipayamak"]');
		var syncProvider = function () {
			var v = smsProvider.value;
			if (customBox) { customBox.style.display = v === 'custom' ? '' : 'none'; }
			// MeliPayamak accepts the classic username/password pair too.
			if (secretRow) { secretRow.style.display = v === 'melipayamak' ? '' : 'none'; }
			if (meliHint) { meliHint.style.display = v === 'melipayamak' ? '' : 'none'; }
		};
		smsProvider.addEventListener('change', syncProvider);
		syncProvider();
	}
	// SMS connection diagnosis — shows stored config + the panel's raw verdict.
	var smsDiagBtn = document.querySelector('.mdr-sms-diag-btn');
	if (smsDiagBtn) {
		smsDiagBtn.addEventListener('click', function () {
			var out = document.querySelector('.mdr-sms-diag-out');
			smsDiagBtn.disabled = true;
			if (out) { out.style.display = ''; out.textContent = A.strings.working; }
			post('mdr_sms_diag', {}).then(function (res) {
				if (out) {
					out.textContent = (res && res.report) ? res.report : A.strings.failed;
					out.style.borderColor = (res && res.ok) ? '#1a7f37' : '#d63638';
				}
				smsDiagBtn.disabled = false;
			}).catch(function () { if (out) { out.textContent = A.strings.failed; } smsDiagBtn.disabled = false; });
		});
	}

	var smsTestBtn = document.querySelector('.mdr-sms-test-btn');
	if (smsTestBtn) {
		smsTestBtn.addEventListener('click', function () {
			var toEl = document.querySelector('.mdr-sms-test-to');
			var out = document.querySelector('.mdr-test-result[data-for="sms"]');
			var to = toEl ? toEl.value : '';
			if (!to) { if (out) { out.textContent = A.strings.noSel; } return; }
			smsTestBtn.disabled = true; if (out) { out.textContent = A.strings.working; }
			post('mdr_test_sms', { to: to }).then(function (res) {
				if (out) {
					out.textContent = (res && res.ok) ? ('✓ ' + A.strings.ok) : ('✕ ' + ((res && res.error) || A.strings.failed));
					out.style.color = (res && res.ok) ? '#1a7f37' : '#d63638';
				}
				smsTestBtn.disabled = false;
			}).catch(function () { if (out) { out.textContent = '✕'; } smsTestBtn.disabled = false; });
		});
	}

	// Messenger lead-alert test buttons (Bale / Telegram bot).
	document.querySelectorAll('.mdr-msgr-test-btn').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var ch = btn.getAttribute('data-channel');
			var out = document.querySelector('.mdr-test-result[data-for="msgr-' + ch + '"]');
			btn.disabled = true; if (out) { out.textContent = A.strings.working; }
			post('mdr_test_messenger', { channel: ch }).then(function (res) {
				if (out) {
					out.textContent = (res && res.ok) ? ('✓ ' + A.strings.ok) : ('✕ ' + ((res && res.error) || A.strings.failed));
					out.style.color = (res && res.ok) ? '#1a7f37' : '#d63638';
				}
				btn.disabled = false;
			}).catch(function () { if (out) { out.textContent = '✕'; } btn.disabled = false; });
		});
	});

	// Lead referral — email (server wp_mail) + SMS deep-link.
	var refer = document.querySelector('.mdr-refer');
	if (refer) {
		var refText = refer.getAttribute('data-text') || '';
		var refResult = refer.querySelector('.mdr-refer-result');
		var origResult = refResult ? refResult.textContent : '';

		var mailBtn = refer.querySelector('.mdr-refer-mail');
		if (mailBtn) {
			mailBtn.addEventListener('click', function () {
				var to = (refer.querySelector('.mdr-refer-email') || {}).value || '';
				if (!to) { refResult.textContent = A.strings.noSel; return; }
				mailBtn.disabled = true; refResult.textContent = A.strings.working;
				post('mdr_lead_refer', { lead_id: refer.getAttribute('data-lead'), to: to }).then(function (res) {
					refResult.textContent = (res && res.ok) ? ('✓ ' + A.strings.ok) : ('✕ ' + ((res && res.error) || A.strings.failed));
					refResult.style.color = (res && res.ok) ? '#1a7f37' : '#d63638';
					mailBtn.disabled = false;
				}).catch(function () { refResult.textContent = '✕'; mailBtn.disabled = false; });
			});
		}

		// Picking a saved colleague fills both destinations (phone + email).
		var staffSel = refer.querySelector('.mdr-refer-staff');
		var phoneField = refer.querySelector('.mdr-refer-phone');
		var emailField = refer.querySelector('.mdr-refer-email');
		if (staffSel) {
			staffSel.addEventListener('change', function () {
				var opt = staffSel.options[staffSel.selectedIndex];
				if (!opt || !staffSel.value) { return; }
				if (phoneField) { phoneField.value = staffSel.value; }
				var mail = opt.getAttribute('data-email') || '';
				if (emailField && mail) { emailField.value = mail; }
			});
		}

		// Server-side panel send (only present when the SMS panel is configured).
		var panelBtn = refer.querySelector('.mdr-refer-panel');
		if (panelBtn) {
			panelBtn.addEventListener('click', function () {
				var phone = (refer.querySelector('.mdr-refer-phone') || {}).value || '';
				if (!phone) { refResult.textContent = A.strings.noSel; return; }
				var tpl = (refer.querySelector('.mdr-refer-template') || {}).value || 'referral';
				panelBtn.disabled = true; refResult.textContent = A.strings.working;
				post('mdr_send_sms', { lead_id: refer.getAttribute('data-lead'), to: phone, template: tpl }).then(function (res) {
					refResult.textContent = (res && res.ok) ? ('✓ ' + A.strings.ok) : ('✕ ' + ((res && res.error) || A.strings.failed));
					refResult.style.color = (res && res.ok) ? '#1a7f37' : '#d63638';
					panelBtn.disabled = false;
				}).catch(function () { refResult.textContent = '✕'; panelBtn.disabled = false; });
			});
		}
	}

	// SEO Intelligence — AI idea generation.
	var seoGen = document.getElementById('mdr-seo-gen');
	if (seoGen) {
		seoGen.addEventListener('click', function () {
			var wrap = document.querySelector('.mdr-seo-ai');
			var out = document.getElementById('mdr-seo-ideas');
			seoGen.disabled = true;
			var original = seoGen.textContent;
			seoGen.textContent = A.strings.working;
			out.textContent = '…';
			post('mdr_seo_generate', { nonce: wrap.getAttribute('data-nonce') }).then(function (res) {
				out.textContent = (res && res.ok) ? res.ideas : ((res && res.error) || A.strings.failed);
				seoGen.disabled = false; seoGen.textContent = original;
			}).catch(function () { out.textContent = A.strings.failed; seoGen.disabled = false; seoGen.textContent = original; });
		});
	}

	// Connection tests (Integrations tab).
	document.querySelectorAll('.mdr-test-btn').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var target = btn.getAttribute('data-target');
			var out = document.querySelector('.mdr-test-result[data-for="' + target + '"]');
			btn.disabled = true; if (out) { out.textContent = A.strings.working; }
			post(target === 'webhook' ? 'mdr_test_webhook' : 'mdr_test_gsheet', {}).then(function (res) {
				if (out) {
					out.textContent = (res && res.ok) ? ('✓ ' + A.strings.ok + (res.code ? ' (HTTP ' + res.code + ')' : '')) : ('✕ ' + (res && res.error ? res.error : A.strings.failed));
					out.style.color = (res && res.ok) ? '#1a7f37' : '#d63638';
				}
				btn.disabled = false;
			}).catch(function () { if (out) { out.textContent = '✕'; } btn.disabled = false; });
		});
	});
})();
