/**
 * SignTeb AI Web Chat — frontend widget (Vanilla JS, no jQuery).
 *
 * Talks to the REST endpoint first and falls back to admin-ajax automatically
 * if REST is blocked. Renders a natural typing effect, localized digits, and
 * inline CTA cards when booking/contact intent is detected. RTL/LTR aware and
 * fully white-label (all visuals come from CSS variables set inline).
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

	var sessionId = getSession();

	function getSession() {
		var key = 'swc_session';
		try {
			var existing = window.localStorage.getItem(key);
			if (existing) {
				return existing;
			}
			var id = 'sess_' + Math.random().toString(36).slice(2) + Date.now().toString(36);
			window.localStorage.setItem(key, id);
			return id;
		} catch (e) {
			return 'sess_' + Date.now().toString(36);
		}
	}

	function localizeDigits(str) {
		// Persian digits only for RTL widgets; keep Latin digits for LTR sites.
		if (!isRtl) {
			return String(str);
		}
		var fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
		return String(str).replace(/[0-9]/g, function (d) {
			return fa[d];
		});
	}

	function openPanel() {
		panel.hidden = false;
		root.classList.add('swc-open');
		setTimeout(function () {
			input.focus();
		}, 120);
	}

	function closePanel() {
		root.classList.remove('swc-open');
		panel.hidden = true;
	}

	launcher.addEventListener('click', function () {
		if (panel.hidden) {
			openPanel();
		} else {
			closePanel();
		}
	});
	closeBtn.addEventListener('click', closePanel);

	function appendMessage(text, who) {
		var el = document.createElement('div');
		el.className = 'swc-msg ' + (who === 'user' ? 'swc-msg-user' : 'swc-msg-bot');
		el.textContent = localizeDigits(text);
		messages.appendChild(el);
		messages.scrollTop = messages.scrollHeight;
		return el;
	}

	/** Natural typing effect (simulated streaming). */
	function typeInto(el, text) {
		el.textContent = '';
		var chars = localizeDigits(text).split('');
		var i = 0;
		(function step() {
			if (i >= chars.length) {
				return;
			}
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

	function renderCtaCard(card) {
		if (!card || !card.type) {
			return;
		}
		var wrap = document.createElement('div');
		wrap.className = 'swc-cta-card';

		var bookingUrl = card.booking_url || root.dataset.bookingUrl || '';
		var whatsapp = card.whatsapp || root.dataset.whatsapp || '';
		var phone = card.phone || root.dataset.phone || '';

		if (bookingUrl) {
			wrap.appendChild(makeCtaButton(cfg.strings.book, bookingUrl, 'book'));
		}
		if (whatsapp) {
			var wa = whatsapp.replace(/[^0-9]/g, '');
			wrap.appendChild(makeCtaButton(cfg.strings.whatsapp, 'https://wa.me/' + wa, 'wa'));
		}
		if (phone) {
			wrap.appendChild(makeCtaButton(cfg.strings.call, 'tel:' + phone.replace(/[^0-9+]/g, ''), 'call'));
		}
		if (wrap.children.length) {
			messages.appendChild(wrap);
			messages.scrollTop = messages.scrollHeight;
		}
	}

	function makeCtaButton(label, href, kind) {
		var a = document.createElement('a');
		a.className = 'swc-cta-btn swc-cta-' + kind;
		a.href = href;
		a.target = '_blank';
		a.rel = 'noopener';
		a.textContent = label;
		return a;
	}

	function send(text) {
		appendMessage(text, 'user');
		if (quickWrap) {
			quickWrap.style.display = 'none';
		}
		var typing = showTyping();

		request(text)
			.then(function (data) {
				typing.remove();
				if (data && data.ok && data.reply) {
					var el = appendMessage('', 'bot');
					typeInto(el, data.reply);
					if (data.cta_card) {
						setTimeout(function () {
							renderCtaCard(data.cta_card);
						}, 400);
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

	/** Try REST first; on network/HTTP failure, retry through admin-ajax. */
	function request(text) {
		return fetch(cfg.restUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg.restNonce
			},
			body: JSON.stringify({
				message: text,
				session_id: sessionId,
				page_url: cfg.pageUrl
			})
		})
			.then(function (r) {
				return r.json();
			})
			.catch(function () {
				return ajaxFallback(text);
			});
	}

	function ajaxFallback(text) {
		var body = new URLSearchParams();
		body.append('action', 'swc_chat_message');
		body.append('nonce', cfg.ajaxNonce);
		body.append('message', text);
		body.append('session_id', sessionId);
		body.append('page_url', cfg.pageUrl);

		return fetch(cfg.ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		}).then(function (r) {
			return r.json();
		});
	}

	form.addEventListener('submit', function (e) {
		e.preventDefault();
		var text = input.value.trim();
		if (!text) {
			return;
		}
		input.value = '';
		send(text);
	});

	if (quickWrap) {
		quickWrap.addEventListener('click', function (e) {
			var btn = e.target.closest('.swc-quick-reply');
			if (btn) {
				send(btn.textContent.trim());
			}
		});
	}
})();
