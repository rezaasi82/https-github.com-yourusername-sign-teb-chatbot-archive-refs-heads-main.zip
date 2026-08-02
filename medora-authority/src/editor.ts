import { __ } from '@wordpress/i18n';
import { api } from './api/client';
import './editor.css';

/**
 * Behaviour for the editor meta box.
 *
 * Deliberately not a React island: the panel is rendered server-side so it is
 * useful before any JavaScript runs, and the only interaction is one button.
 * Shipping a second React root into every post edit screen for that would be a
 * meaningful, permanent cost on a screen users open constantly.
 */
function init(): void {
	const box = document.querySelector< HTMLElement >( '.medora-metabox' );
	const button = box?.querySelector< HTMLButtonElement >( '.medora-reanalyze' );

	if ( ! box || ! button ) {
		return;
	}

	const postId = Number( box.dataset.postId ?? 0 );

	if ( ! postId ) {
		return;
	}

	button.addEventListener( 'click', async () => {
		const original = button.textContent ?? '';

		button.disabled = true;
		button.textContent = __( 'Analysing…', 'medora-authority' );

		try {
			// Synchronous so the editor sees a fresh score immediately rather
			// than being told to come back later.
			await api.analyze( postId, true );

			button.textContent = __( 'Done — reloading', 'medora-authority' );
			window.location.reload();
		} catch ( error ) {
			button.textContent = original;
			button.disabled = false;

			const notice = document.createElement( 'p' );
			notice.className = 'medora-error';
			notice.textContent = ( error as Error ).message;
			box.append( notice );
		}
	} );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}
