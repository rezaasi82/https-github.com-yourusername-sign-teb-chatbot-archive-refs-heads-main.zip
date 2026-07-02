/**
 * SignTeb Setup Wizard — JavaScript
 * AJAX step saving + navigation
 */

( function () {
	'use strict';

	// ── Save step on "Next" click ──────────────────────────────────────────────

	const nextBtn = document.querySelector( '.stwiz-next-btn' );
	if ( nextBtn ) {
		nextBtn.addEventListener( 'click', function () {
			const step     = nextBtn.dataset.step;
			const nextUrl  = nextBtn.dataset.next;
			const form     = document.querySelector( '.stwiz-step-form[data-step="' + step + '"]' );

			if ( ! form ) {
				// Steps without form (welcome) — just navigate
				location.href = nextUrl;
				return;
			}

			// Collect form data
			const formData  = new FormData();
			const inputs    = form.querySelectorAll( 'input, select, textarea' );

			inputs.forEach( function ( el ) {
				if ( el.name ) formData.append( 'data[' + el.name + ']', el.value );
			} );

			formData.append( 'action', 'stwiz_save_step' );
			formData.append( 'nonce',  stWizData.nonce );
			formData.append( 'step',   step );

			// Loading state
			const origText  = nextBtn.innerHTML;
			nextBtn.innerHTML = '<span class="stwiz-spinner-sm"></span> در حال ذخیره...';
			nextBtn.disabled  = true;

			fetch( stWizData.ajaxUrl, {
				method:  'POST',
				body:    formData,
			} )
			.then( function ( r ) { return r.json(); } )
			.then( function ( data ) {
				if ( data.success ) {
					location.href = nextUrl;
				} else {
					nextBtn.innerHTML = origText;
					nextBtn.disabled  = false;
					showToast( data.data?.message || 'خطا در ذخیره.', 'error' );
				}
			} )
			.catch( function () {
				nextBtn.innerHTML = origText;
				nextBtn.disabled  = false;
				showToast( 'خطای شبکه. لطفاً مجدداً تلاش کنید.', 'error' );
			} );
		} );
	}

	// ── Toast ─────────────────────────────────────────────────────────────────

	function showToast( msg, type = 'info' ) {
		const toast = document.createElement( 'div' );
		toast.className = 'stwiz-toast stwiz-toast--' + type;
		toast.textContent = msg;
		document.body.appendChild( toast );

		setTimeout( function () { toast.classList.add( 'is-visible' ); }, 10 );
		setTimeout( function () {
			toast.classList.remove( 'is-visible' );
			setTimeout( function () { toast.remove(); }, 300 );
		}, 3500 );
	}

	// ── Spinner CSS (inline for zero-dependency) ──────────────────────────────

	const style = document.createElement( 'style' );
	style.textContent = `
		.stwiz-spinner-sm {
			display: inline-block;
			width: 14px;
			height: 14px;
			border: 2px solid rgba(255,255,255,0.2);
			border-top-color: #fff;
			border-radius: 50%;
			animation: stwiz-spin 0.7s linear infinite;
		}
		.stwiz-toast {
			position: fixed;
			bottom: 1.5rem;
			right: 1.5rem;
			padding: 0.75rem 1.25rem;
			border-radius: 10px;
			font-size: 0.875rem;
			font-weight: 600;
			background: #0f172a;
			color: #e8edf5;
			border: 1px solid rgba(255,255,255,0.1);
			box-shadow: 0 8px 32px rgba(0,0,0,0.4);
			opacity: 0;
			transform: translateY(10px);
			transition: all 0.3s ease;
			z-index: 9999;
		}
		.stwiz-toast.is-visible { opacity: 1; transform: translateY(0); }
		.stwiz-toast--error { border-color: rgba(239,68,68,0.3); background: rgba(239,68,68,0.1); color: #fca5a5; }
	`;
	document.head.appendChild( style );

} )();
