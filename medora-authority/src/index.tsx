import { createRoot } from '@wordpress/element';
import { App } from './app/App';
import './style.css';

/**
 * Mounts the dashboard.
 *
 * The container is rendered by PHP with a loading message inside it, so a
 * failed bundle load leaves a readable page rather than a blank one.
 */
const container = document.getElementById( 'medora-root' );

if ( container ) {
	container.innerHTML = '';
	createRoot( container ).render( <App /> );
}
