( function () {
	'use strict';
	const isRtl = document.documentElement.dir === 'rtl';
	const FA = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];

	function toFa( n ) {
		return isRtl ? String(n).replace(/[0-9]/g, d => FA[d]) : String(n);
	}

	if ( ! ('IntersectionObserver' in window) ) return;

	const obs = new IntersectionObserver( function(entries) {
		entries.forEach( function(entry) {
			if ( ! entry.isIntersecting ) return;
			const el     = entry.target;
			const target = parseInt( el.dataset.counter, 10 );
			const suffix = el.dataset.suffix || '';

			obs.unobserve(el);

			// اگر data-counter عدد معتبری نبود، متنِ سرور (که همان مقدار نهایی
			// است) را دست‌نخورده بگذار — هرگز عدد خراب نشان نده.
			if ( Number.isNaN( target ) ) return;

			const dur    = 2000;
			const start  = performance.now();

			function ease(t) { return 1 - Math.pow(1-t,3); }

			function tick(now) {
				// p در بازه‌ی [0,1] کلمپ می‌شود. کلمپِ کفِ صفر حیاتی است: تایم‌استمپِ
				// اولین requestAnimationFrame می‌تواند اندکی قبل از start باشد، که
				// بدون این کلمپ p منفی و ease(p) منفی می‌شد و لحظه‌ای اعداد منفی
				// (مثل ‎-۱۹۰) روی شمارنده چشمک می‌زد.
				const p   = Math.min( Math.max( (now-start)/dur, 0 ), 1 );
				el.textContent = toFa( Math.floor( ease(p) * target ) ) + suffix;
				if ( p < 1 ) requestAnimationFrame(tick);
				else el.textContent = toFa(target) + suffix;
			}

			// شروع با requestAnimationFrame (نه فراخوانیِ همگامِ فوری). به‌این‌ترتیب
			// هرگز مقدارِ نهاییِ رندرشده توسط سرور را با یک «۰»ِ همگام بازنویسی
			// نمی‌کنیم؛ اگر به هر دلیلی rAF اجرا نشد، همان عددِ درستِ سرور می‌ماند.
			requestAnimationFrame(tick);
		} );
	}, { threshold: 0.5 } );

	document.querySelectorAll('[data-counter]').forEach( el => obs.observe(el) );
} )();
