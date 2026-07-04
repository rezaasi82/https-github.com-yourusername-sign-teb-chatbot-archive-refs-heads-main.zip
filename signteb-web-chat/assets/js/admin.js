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
