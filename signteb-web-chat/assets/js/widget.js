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

	// One page can host several widgets (the floating launcher plus any number
	// of [medora_chat] shortcodes), so initialise every instance independently.
	var roots = document.querySelectorAll('.swc-root');
	if (!roots.length) { return; }
	Array.prototype.forEach.call(roots, initWidget);

	/* ---------- notification chime (Web Audio, no asset) ---------- */
	// Browsers block audio until the visitor interacts with the page, so a
	// chime requested before any gesture is queued and flushed on first input.
	var audioCtx = null, audioReady = false, pendingChime = false;

	function ensureAudio() {
		try {
			var AC = window.AudioContext || window.webkitAudioContext;
			if (!AC) { return; }
			if (!audioCtx) { audioCtx = new AC(); }
			if (audioCtx.state === 'suspended' && audioCtx.resume) { audioCtx.resume(); }
			audioReady = audioCtx.state === 'running';
		} catch (e) {}
	}

	function actuallyChime() {
		if (!audioCtx) { return; }
		try {
			var now = audioCtx.currentTime;
			// A soft two-note arpeggio (E5 → A5): pleasant, brief, non-intrusive.
			[[659.25, 0], [880.0, 0.13]].forEach(function (pair) {
				var osc = audioCtx.createOscillator();
				var gain = audioCtx.createGain();
				osc.type = 'sine';
				osc.frequency.value = pair[0];
				var t = now + pair[1];
				gain.gain.setValueAtTime(0.0001, t);
				gain.gain.exponentialRampToValueAtTime(0.12, t + 0.02);
				gain.gain.exponentialRampToValueAtTime(0.0001, t + 0.34);
				osc.connect(gain);
				gain.connect(audioCtx.destination);
				osc.start(t);
				osc.stop(t + 0.4);
			});
		} catch (e) {}
	}

	function playChime() {
		ensureAudio();
		if (audioReady) { actuallyChime(); } else { pendingChime = true; }
	}

	function onGesture() {
		ensureAudio();
		if (!audioReady) { return; }
		if (pendingChime) { pendingChime = false; actuallyChime(); }
		['pointerdown', 'keydown', 'touchstart', 'scroll'].forEach(function (e) {
			window.removeEventListener(e, onGesture);
		});
	}
	['pointerdown', 'keydown', 'touchstart', 'scroll'].forEach(function (e) {
		window.addEventListener(e, onGesture, { passive: true });
	});

	function initWidget(root) {

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

	// Opt-in debug logging: enable with localStorage.setItem('swc_debug','1')
	// or by setting SWC_CONFIG.debug = true. Traces the full flow so the source
	// of any issue is visible in the console.
	var DEBUG = !!cfg.debug || (function () {
		try { return window.localStorage.getItem('swc_debug') === '1'; } catch (e) { return false; }
	})();
	function log() {
		if (!DEBUG || !window.console) { return; }
		try { console.log.apply(console, ['[Medora]'].concat([].slice.call(arguments))); } catch (e) {}
	}

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

	var savedScrollY = 0;
	var lastVvHeight = 0;

	function isMobile() { return window.innerWidth <= 480; }
	function scrollToLatest() { messages.scrollTop = messages.scrollHeight; }

	// Size + position the panel to the *visual* viewport so the input row sits
	// just above the keyboard and nothing overlaps or jumps. On desktop the
	// inline styles are cleared and CSS takes over.
	function syncViewport() {
		var vv = window.visualViewport;
		if (!vv || !isMobile()) {
			panel.style.height = '';
			panel.style.transform = '';
			return;
		}
		panel.style.height = vv.height + 'px';
		// Only translate when the visual viewport is actually offset (e.g. the
		// page scrolled under an iOS keyboard); keep '' otherwise so the CSS
		// entrance animation is preserved.
		panel.style.transform = vv.offsetTop ? 'translateY(' + vv.offsetTop + 'px)' : '';
	}

	function onViewportChange() {
		if (panel.hidden) { return; }
		syncViewport();
		var vv = window.visualViewport;
		if (vv) {
			// Keyboard just opened -> bring the latest message back into view
			// (once). Never on plain typing, where the height is stable.
			if (vv.height < lastVvHeight - 80) { scrollToLatest(); }
			lastVvHeight = vv.height;
		}
	}
	if (window.visualViewport) {
		window.visualViewport.addEventListener('resize', onViewportChange);
		window.visualViewport.addEventListener('scroll', onViewportChange);
	}

	// Lock the page behind the full-screen mobile panel so focusing the input
	// can never scroll or jump the underlying document (iOS Safari fix).
	function lockBody() {
		if (!isMobile() || document.body.classList.contains('swc-body-lock')) { return; }
		savedScrollY = window.scrollY || window.pageYOffset || 0;
		document.body.style.top = '-' + savedScrollY + 'px';
		document.body.classList.add('swc-body-lock');
	}
	function unlockBody() {
		if (!document.body.classList.contains('swc-body-lock')) { return; }
		document.body.classList.remove('swc-body-lock');
		document.body.style.top = '';
		window.scrollTo(0, savedScrollY);
	}

	function openPanel() {
		panel.hidden = false;
		root.classList.add('swc-open');
		lockBody();
		lastVvHeight = window.visualViewport ? window.visualViewport.height : 0;
		syncViewport();
		maybeShowLead();
		setTimeout(function () {
			if (started) { input.focus(); }
			scrollToLatest();
		}, 150);
	}
	function closePanel() {
		root.classList.remove('swc-open');
		panel.hidden = true;
		panel.style.height = '';
		panel.style.transform = '';
		unlockBody();
	}
	var isInline = root.dataset.inline === '1';

	if (launcher) {
		launcher.addEventListener('click', function () {
			if (panel.hidden) { openPanel(); } else { closePanel(); }
		});
	}
	if (closeBtn) {
		// Embedded chat has nothing to close; the button is hidden by CSS but
		// guard the handler too.
		closeBtn.addEventListener('click', function () { if (!isInline) { closePanel(); } });
	}

	// Embedded/inline chat is open from the start: prime lead capture + focus
	// without waiting for a launcher click.
	if (isInline) {
		maybeShowLead();
		setTimeout(function () { if (started) { input.focus(); } scrollToLatest(); }, 150);
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
		if (saveProfile) {
			store('swc_profile', JSON.stringify(profile));
			log('Lead saved', profile);
		}
		// Move from Lead Form -> Chat Mode: hide the overlay and reveal the chat.
		if (lead) { lead.hidden = true; }
		root.classList.remove('swc-lead-active');
		started = true;
		log('Chat started (session ' + sessionId + ')');
		scrollToLatest();
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
		var consultUrl = (card && card.consult_url) || root.dataset.consultUrl || '';
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

		// Online consultation sits directly beside booking: same prominence when
		// it is the only action configured, secondary when booking is present.
		if (root.dataset.chConsult === '1' && consultUrl) {
			wrap.appendChild(channelBtn(cfg.strings.consult, consultUrl, 'consult', '🩺', !wrap.children.length));
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
		// 1) Render the user's message instantly (before the AI responds).
		appendMessage(text, 'user');
		if (quickWrap) { quickWrap.style.display = 'none'; }
		// 2) Show the typing indicator so the user knows we're processing.
		var typing = showTyping();
		log('Message sent', text);

		request(text)
			.then(function (data) {
				typing.remove();
				log('Response received', data);
				if (data && data.conversation_id) {
					conversationId = data.conversation_id;
					log('Conversation id', conversationId);
				}
				if (data && data.ok && data.reply) {
					// 3) Render the AI reply (no refresh/reopen needed).
					var el = appendMessage('', 'bot');
					typeInto(el, data.reply);
					if (data.cta_card) {
						setTimeout(function () { renderCtaCard(data.cta_card); }, 450);
					}
					log('UI rendered');
				} else {
					appendMessage((data && data.error) || cfg.strings.error, 'bot');
					log('UI rendered (error state)', data && data.error);
				}
			})
			.catch(function (err) {
				typing.remove();
				appendMessage(cfg.strings.error, 'bot');
				log('Request failed', err);
			});
	}

	function payload(text) {
		return {
			message: text,
			session_id: sessionId,
			page_url: cfg.pageUrl,
			branch: root.dataset.branch || '0',
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

	/* ---------- teaser greeting bubble ---------- */

	(function initTeaser() {
		var teaser = root.querySelector('.swc-teaser');
		if (!teaser || root.dataset.inline === '1') { return; }
		// Respect a per-visitor dismissal so it never nags on every page view.
		if (load('swc_teaser_dismissed') === '1') { return; }

		var delay = parseInt(root.dataset.teaserDelay, 10);
		if (isNaN(delay)) { delay = 3; }

		var soundOn = root.dataset.teaserSound === '1';
		var chimed = false;

		function reveal() {
			if (!panel.hidden) { return; } // already chatting
			teaser.hidden = false;
			root.classList.add('swc-teaser-on');
			// Remember that this visitor has seen the bubble, so the automatic
			// open happens once per browser — not again on every page view.
			store('swc_teaser_shown', '1');
			if (soundOn && !chimed) { chimed = true; playChime(); }
		}

		// Auto-open only on the visitor's first page; hovering the icon can
		// still bring the bubble back intentionally on later pages.
		var timer = null;
		if (load('swc_teaser_shown') !== '1') {
			timer = setTimeout(reveal, delay * 1000);
		}

		// Also surface it the moment the visitor hovers the chat icon.
		if (launcher) {
			launcher.addEventListener('mouseenter', reveal);
		}

		function dismiss(persist) {
			clearTimeout(timer);
			teaser.hidden = true;
			root.classList.remove('swc-teaser-on');
			if (persist) { store('swc_teaser_dismissed', '1'); }
		}

		var closeBubble = teaser.querySelector('.swc-teaser-close');
		if (closeBubble) {
			closeBubble.addEventListener('click', function (e) {
				e.stopPropagation();
				dismiss(true);
			});
		}
		// Clicking the bubble body opens the chat.
		teaser.addEventListener('click', function () { dismiss(true); openPanel(); });
		// Opening the panel any other way also clears the teaser.
		launcher.addEventListener('click', function () { dismiss(false); });
	})();

	} // end initWidget
})();
