/**
 * Medora AI — frontend widget (Vanilla JS, no jQuery).
 *
 * REST-first with automatic admin-ajax fallback. Lead capture (name + phone),
 * natural typing effect, professional booking CTA + communication channels,
 * click tracking for analytics, and mobile keyboard handling (visualViewport).
 */
(function () {
	'use strict';

	if (typeof window.SWC_CONFIG === 'undefined') {
		return;
	}

	var cfg = window.SWC_CONFIG;
	var root = document.getElementById('swc-root');
	if (!root) {
		return;
	}

	var isRtl = (root.getAttribute('dir') || 'rtl') === 'rtl';
	var panel = root.querySelector('.swc-panel');
	var launcher = root.querySelector('.swc-launcher');
	var closeBtn = root.querySelector('.swc-close');
	var form = root.querySelector('.swc-form');
	var input = root.querySelector('.swc-input');
	var messages = root.querySelector('.swc-messages');
	var quickWrap = root.querySelector('.swc-quick');
	var lead = root.querySelector('.swc-lead');

	var sessionId = getSession();
	var profile = getProfile();
	var conversationId = 0;
	var started = false;

	function store(key, val) {
		try { window.localStorage.setItem(key, val); } catch (e) {}
	}
	function load(key) {
		try { return window.localStorage.getItem(key); } catch (e) { return null; }
	}

	function getSession() {
		var existing = load('swc_session');
		if (existing) { return existing; }
		var id = 'sess_' + Math.random().toString(36).slice(2) + Date.now().toString(36);
		store('swc_session', id);
		return id;
	}

	function getProfile() {
		try { return JSON.parse(load('swc_profile') || '{}') || {}; } catch (e) { return {}; }
	}

	function localizeDigits(str) {
		if (!isRtl) { return String(str); }
		var fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
		return String(str).replace(/[0-9]/g, function (d) { return fa[d]; });
	}

	/* ---------- open / close + mobile keyboard ---------- */

	function openPanel() {
		panel.hidden = false;
		root.classList.add('swc-open');
		applyViewport();
		maybeShowLead();
		setTimeout(function () {
			if (started) { input.focus(); }
		}, 150);
	}
	function closePanel() {
		root.classList.remove('swc-open');
		panel.hidden = true;
	}
	launcher.addEventListener('click', function () {
		if (panel.hidden) { openPanel(); } else { closePanel(); }
	});
	closeBtn.addEventListener('click', closePanel);

	// Keep the panel glued to the visible viewport when the mobile keyboard opens.
	function applyViewport() {
		if (!window.visualViewport || window.innerWidth > 480) {
			panel.style.height = '';
			return;
		}
		panel.style.height = window.visualViewport.height + 'px';
	}
	if (window.visualViewport) {
		window.visualViewport.addEventListener('resize', function () {
			if (!panel.hidden) { applyViewport(); }
		});
	}

	/* ---------- lead capture ---------- */

	function maybeShowLead() {
		var enabled = root.dataset.leadCapture === '1';
		if (!enabled || (profile && profile.name)) {
			started = true;
			return;
		}
		if (lead) {
			lead.hidden = false;
			root.classList.add('swc-lead-active');
			var nameField = lead.querySelector('.swc-lead-name');
			if (nameField) { setTimeout(function () { nameField.focus(); }, 200); }
		} else {
			started = true;
		}
	}

	function finishLead(saveProfile) {
		if (lead) { lead.hidden = true; }
		root.classList.remove('swc-lead-active');
		started = true;
		if (saveProfile) { store('swc_profile', JSON.stringify(profile)); }
		setTimeout(function () { input.focus(); }, 100);
	}

	if (lead) {
		var startBtn = lead.querySelector('.swc-lead-start');
		var skipBtn = lead.querySelector('.swc-lead-skip');
		var errBox = lead.querySelector('.swc-lead-error');

		startBtn.addEventListener('click', function () {
			var name = (lead.querySelector('.swc-lead-name').value || '').trim();
			var phone = (lead.querySelector('.swc-lead-phone').value || '').trim();
			var digits = phone.replace(/[^\d]/g, '');
			if (name.length < 2) {
				return showLeadError('لطفاً نام خود را وارد کنید.');
			}
			if (digits.length < 10) {
				return showLeadError('لطفاً شماره موبایل معتبر وارد کنید.');
			}
			profile = { name: name, phone: phone };
			finishLead(true);
		});
		if (skipBtn) {
			skipBtn.addEventListener('click', function () { finishLead(false); });
		}
		function showLeadError(msg) {
			if (errBox) { errBox.textContent = msg; errBox.hidden = false; }
		}
	}

	/* ---------- messages ---------- */

	function appendMessage(text, who) {
		var el = document.createElement('div');
		el.className = 'swc-msg ' + (who === 'user' ? 'swc-msg-user' : 'swc-msg-bot');
		el.textContent = localizeDigits(text);
		messages.appendChild(el);
		messages.scrollTop = messages.scrollHeight;
		return el;
	}

	function typeInto(el, text) {
		el.textContent = '';
		var chars = localizeDigits(text).split('');
		var i = 0;
		(function step() {
			if (i >= chars.length) { return; }
			el.textContent += chars[i++];
			messages.scrollTop = messages.scrollHeight;
			setTimeout(step, 12);
		})();
	}

	function showTyping() {
		var el = document.createElement('div');
		el.className = 'swc-msg swc-msg-bot swc-typing';
		el.innerHTML = '<span></span><span></span><span></span>';
		messages.appendChild(el);
		messages.scrollTop = messages.scrollHeight;
		return el;
	}

	/* ---------- CTA card + channels ---------- */

	function renderCtaCard(card) {
		var bookingUrl = (card && card.booking_url) || root.dataset.bookingUrl || '';
		var whatsapp = (card && card.whatsapp) || root.dataset.whatsapp || '';
		var phone = (card && card.phone) || root.dataset.phone || '';
		var baleUrl = root.dataset.baleUrl || '';

		var wrap = document.createElement('div');
		wrap.className = 'swc-cta-card';

		if (root.dataset.chBooking === '1' && bookingUrl) {
			var head = document.createElement('div');
			head.className = 'swc-cta-head';
			head.innerHTML = '<div class="swc-cta-title"></div><div class="swc-cta-text"></div>';
			head.querySelector('.swc-cta-title').textContent = cfg.strings.ctaTitle;
			head.querySelector('.swc-cta-text').textContent = cfg.strings.ctaText;
			wrap.appendChild(head);
			wrap.appendChild(channelBtn(cfg.strings.book, bookingUrl, 'booking', '📅', true));
		}

		var row = document.createElement('div');
		row.className = 'swc-channel-row';
		if (root.dataset.chWhatsapp === '1' && whatsapp) {
			row.appendChild(channelBtn(cfg.strings.whatsapp, 'https://wa.me/' + whatsapp.replace(/[^0-9]/g, ''), 'whatsapp', '💬', false));
		}
		if (root.dataset.chCall === '1' && phone) {
			row.appendChild(channelBtn(cfg.strings.call, 'tel:' + phone.replace(/[^0-9+]/g, ''), 'call', '📞', false));
		}
		if (root.dataset.chBale === '1' && baleUrl) {
			row.appendChild(channelBtn(cfg.strings.bale, baleUrl, 'bale', '🟦', false));
		}
		if (row.children.length) { wrap.appendChild(row); }

		if (wrap.children.length) {
			messages.appendChild(wrap);
			messages.scrollTop = messages.scrollHeight;
		}
	}

	function channelBtn(label, href, type, emoji, primary) {
		var a = document.createElement('a');
		a.className = 'swc-cta-btn swc-cta-' + type + (primary ? ' swc-cta-primary' : '');
		a.href = href;
		a.target = '_blank';
		a.rel = 'noopener';
		a.innerHTML = '<span class="swc-cta-emoji" aria-hidden="true"></span><span class="swc-cta-label"></span>';
		a.querySelector('.swc-cta-emoji').textContent = emoji;
		a.querySelector('.swc-cta-label').textContent = label;
		a.addEventListener('click', function () { trackEvent(type); });
		return a;
	}

	/* ---------- transport ---------- */

	function send(text) {
		appendMessage(text, 'user');
		if (quickWrap) { quickWrap.style.display = 'none'; }
		var typing = showTyping();

		request(text)
			.then(function (data) {
				typing.remove();
				if (data && data.conversation_id) { conversationId = data.conversation_id; }
				if (data && data.ok && data.reply) {
					var el = appendMessage('', 'bot');
					typeInto(el, data.reply);
					if (data.cta_card) {
						setTimeout(function () { renderCtaCard(data.cta_card); }, 450);
					}
				} else {
					appendMessage((data && data.error) || cfg.strings.error, 'bot');
				}
			})
			.catch(function () {
				typing.remove();
				appendMessage(cfg.strings.error, 'bot');
			});
	}

	function payload(text) {
		return {
			message: text,
			session_id: sessionId,
			page_url: cfg.pageUrl,
			name: (profile && profile.name) || '',
			phone: (profile && profile.phone) || ''
		};
	}

	function request(text) {
		return fetch(cfg.restUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.restNonce },
			body: JSON.stringify(payload(text))
		})
			.then(function (r) { return r.json(); })
			.catch(function () { return ajaxFallback(text); });
	}

	function ajaxFallback(text) {
		var body = new URLSearchParams();
		var p = payload(text);
		body.append('action', 'swc_chat_message');
		body.append('nonce', cfg.ajaxNonce);
		Object.keys(p).forEach(function (k) { body.append(k, p[k]); });
		return fetch(cfg.ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		}).then(function (r) { return r.json(); });
	}

	function trackEvent(type) {
		// Fire-and-forget; REST first, admin-ajax fallback.
		fetch(cfg.eventUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.restNonce },
			body: JSON.stringify({ type: type, conversation_id: conversationId })
		}).catch(function () {
			var body = new URLSearchParams();
			body.append('action', 'swc_track_event');
			body.append('nonce', cfg.ajaxNonce);
			body.append('type', type);
			body.append('conversation_id', conversationId);
			fetch(cfg.ajaxUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString()
			}).catch(function () {});
		});
	}

	/* ---------- events ---------- */

	form.addEventListener('submit', function (e) {
		e.preventDefault();
		if (!started) { return; }
		var text = input.value.trim();
		if (!text) { return; }
		input.value = '';
		send(text);
	});

	if (quickWrap) {
		quickWrap.addEventListener('click', function (e) {
			var btn = e.target.closest('.swc-quick-reply');
			if (btn && started) { send(btn.textContent.trim()); }
		});
	}
})();
