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
			const dur    = 2000;
			const start  = performance.now();

			function ease(t) { return 1 - Math.pow(1-t,3); }

			( function tick(now) {
				const p   = Math.min((now-start)/dur, 1);
				const cur = Math.floor( ease(p) * target );
				el.textContent = toFa(cur) + suffix;
				if ( p < 1 ) requestAnimationFrame(tick);
				else el.textContent = toFa(target) + suffix;
			} )( start );

			obs.unobserve(el);
		} );
	}, { threshold: 0.5 } );

	document.querySelectorAll('[data-counter]').forEach( el => obs.observe(el) );
} )();
