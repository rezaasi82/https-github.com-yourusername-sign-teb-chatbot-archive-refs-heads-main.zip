/**
 * نصب‌کننده آسان — اسکریپت عمومی
 */
(function () {
	'use strict';

	// جلوگیری از خروج تصادفی از صفحه حین نصب فعال
	window.addEventListener( 'beforeunload', function ( e ) {
		var activeSpinners = document.querySelectorAll( '.ezi-spinner' );
		if ( activeSpinners.length > 0 ) {
			e.preventDefault();
			e.returnValue = '';
		}
	} );
})();
