/**
 * SignTeb AI Chat — frontend widget (Vanilla JS, no jQuery).
 *
 * Talks to the REST endpoint first and falls back to admin-ajax automatically
 * if REST is blocked. Renders a typing effect, Persian digits, and inline CTA
 * cards when booking/contact intent is detected.
 */
(function () {
	'use strict';

	if (typeof window.STMC_CHAT === 'undefined') {
		return;
	}

	var cfg = window.STMC_CHAT;
	var root = document.getElementById('stmc-chat-root');
	if (!root) {
		return;
	}

	var panel = root.querySelector('.stmc-chat-panel');
	var launcher = root.querySelector('.stmc-chat-launcher');
	var closeBtn = root.querySelector('.stmc-chat-close');
	var form = root.querySelector('.stmc-chat-form');
	var input = root.querySelector('.stmc-chat-input');
	var messages = root.querySelector('.stmc-chat-messages');
	var quickWrap = root.querySelector('.stmc-chat-quick');

	// Stable per-visitor session id (kept in localStorage).
	var sessionId = getSession();

	function getSession() {
		var key = 'stmc_chat_session';
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

	function toPersianDigits(str) {
		var fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
		return String(str).replace(/[0-9]/g, function (d) {
			return fa[d];
		});
	}

	function openPanel() {
		panel.hidden = false;
		root.classList.add('stmc-open');
		setTimeout(function () {
			input.focus();
		}, 120);
	}

	function closePanel() {
		root.classList.remove('stmc-open');
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
		el.className = 'stmc-msg ' + (who === 'user' ? 'stmc-msg-user' : 'stmc-msg-bot');
		el.textContent = toPersianDigits(text);
		messages.appendChild(el);
		messages.scrollTop = messages.scrollHeight;
		return el;
	}

	function typeInto(el, text) {
		el.textContent = '';
		var chars = toPersianDigits(text).split('');
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
		el.className = 'stmc-msg stmc-msg-bot stmc-typing';
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
		wrap.className = 'stmc-cta-card';

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
			wrap.appendChild(makeCtaButton(cfg.strings.call, 'tel:' + phone, 'call'));
		}
		if (wrap.children.length) {
			messages.appendChild(wrap);
			messages.scrollTop = messages.scrollHeight;
		}
	}

	function makeCtaButton(label, href, kind) {
		var a = document.createElement('a');
		a.className = 'stmc-cta-btn stmc-cta-' + kind;
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

	/**
	 * Try REST first; on network/HTTP failure, retry through admin-ajax.
	 */
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
		body.append('action', 'stmc_chat_message');
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
			var btn = e.target.closest('.stmc-quick-reply');
			if (btn) {
				send(btn.textContent.trim());
			}
		});
	}
})();
