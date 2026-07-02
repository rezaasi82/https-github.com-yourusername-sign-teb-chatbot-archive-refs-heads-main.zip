( function () {
	'use strict';
	document.querySelectorAll('.stmb-video').forEach(function(el) {
		function playVideo() {
			const embed = el.dataset.embed;
			if (!embed) return;
			const iframe = document.createElement('iframe');
			iframe.src            = embed;
			iframe.allowFullscreen = true;
			iframe.allow           = 'autoplay; encrypted-media';
			iframe.loading         = 'lazy';
			el.innerHTML = '';
			el.appendChild(iframe);
		}
		el.addEventListener('click',   playVideo);
		el.addEventListener('keydown', function(e) { if (e.key==='Enter'||e.key===' ') { e.preventDefault(); playVideo(); } });
	});
} )();
