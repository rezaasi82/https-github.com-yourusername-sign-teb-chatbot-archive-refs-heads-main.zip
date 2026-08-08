import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { api, boot } from '../api/client';
import { Overview } from '../pages/Overview';
import { Entities } from '../pages/Entities';
import { Graph } from '../pages/Graph';
import { Content } from '../pages/Content';
import { Crawlers } from '../pages/Crawlers';
import { Analytics } from '../pages/Analytics';
import { Settings } from '../pages/Settings';
import { Audit } from '../pages/Audit';
import { Wizard } from '../pages/Wizard';

const ROUTES: Record< string, () => JSX.Element > = {
	overview: Overview,
	entities: Entities,
	graph: Graph,
	content: Content,
	crawlers: Crawlers,
	analytics: Analytics,
	settings: Settings,
	audit: Audit,
};

/**
 * Root component.
 *
 * Routing is driven by the WordPress admin page slug rather than by a client
 * router: each submenu entry is a real URL that survives a reload, a bookmark
 * and a browser back button, which a hash router inside an admin page does not.
 * The setup wizard takes over the whole surface until it is completed.
 */
export function App(): JSX.Element {
	const [ route, setRoute ] = useState( boot.screen || 'overview' );
	const [ needsWizard, setNeedsWizard ] = useState< boolean | null >( null );

	useEffect( () => {
		let cancelled = false;

		api.wizard()
			.then( ( state ) => {
				if ( ! cancelled ) {
					setNeedsWizard( ! state.completed );
				}
			} )
			// Without manage_settings the wizard endpoint 403s; that is a
			// normal state for an editor, not an error worth surfacing.
			.catch( () => {
				if ( ! cancelled ) {
					setNeedsWizard( false );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [] );

	if ( needsWizard === null ) {
		return (
			<p className="medora-loading">
				{ __( 'Loading Medora…', 'medora-authority' ) }
			</p>
		);
	}

	if ( needsWizard ) {
		return (
			<Wizard
				onComplete={ () => {
					setNeedsWizard( false );
					setRoute( 'overview' );
				} }
			/>
		);
	}

	const Page = ROUTES[ route ] ?? Overview;

	return (
		<div className="medora-app" dir={ boot.isRtl ? 'rtl' : 'ltr' }>
			<nav
				className="medora-tabs"
				aria-label={ __( 'Medora sections', 'medora-authority' ) }
			>
				{ (
					[
						[ 'overview', __( 'Overview', 'medora-authority' ) ],
						[ 'entities', __( 'Entities', 'medora-authority' ) ],
						[
							'graph',
							__( 'Knowledge Graph', 'medora-authority' ),
						],
						[ 'content', __( 'Content', 'medora-authority' ) ],
						[ 'crawlers', __( 'AI Crawlers', 'medora-authority' ) ],
						[
							'analytics',
							__( 'AI Analytics', 'medora-authority' ),
						],
						[ 'settings', __( 'Settings', 'medora-authority' ) ],
					] as const
				 ).map( ( [ id, label ] ) => (
					<button
						key={ id }
						type="button"
						className={ route === id ? 'is-active' : '' }
						aria-current={ route === id ? 'page' : undefined }
						onClick={ () => setRoute( id ) }
					>
						{ label }
					</button>
				) ) }
			</nav>

			<Page />
		</div>
	);
}
