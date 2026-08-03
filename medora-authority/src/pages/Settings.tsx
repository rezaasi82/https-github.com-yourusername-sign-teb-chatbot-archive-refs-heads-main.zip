import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { api, boot } from '../api/client';
import { useAsync } from '../hooks/useAsync';
import type { LicenseState, ModuleInfo, SecurityScan } from '../types';

function Modules(): JSX.Element {
	const { data, loading, reload } = useAsync( () => api.overview( 30 ), [] );
	const [ message, setMessage ] = useState( '' );
	const [ busy, setBusy ] = useState< string | null >( null );

	const toggle = async ( module: ModuleInfo ) => {
		setBusy( module.id );
		setMessage( '' );

		try {
			await api.toggleModule( module.id, ! module.enabled );
			setMessage(
				__(
					'Saved. Reload the page for the change to take full effect.',
					'medora-authority'
				)
			);
			reload();
		} catch ( error ) {
			setMessage( ( error as Error ).message );
		} finally {
			setBusy( null );
		}
	};

	if ( loading && ! data ) {
		return <p className="medora-loading">{ __( 'Loading…', 'medora-authority' ) }</p>;
	}

	return (
		<section className="medora-card">
			<h2>{ __( 'Modules', 'medora-authority' ) }</h2>
			<p className="medora-muted">
				{ __(
					'Every module is independent. Turn off what you do not need — disabled modules add no queries and no page weight.',
					'medora-authority'
				) }
			</p>

			{ message && <div className="medora-notice">{ message }</div> }

			<ul className="medora-modules">
				{ ( data?.modules ?? [] ).map( ( module ) => (
					<li key={ module.id }>
						<div>
							<strong>{ module.title }</strong>
							{ module.required_tier !== 'free' && (
								<span className="medora-pill">
									{ module.required_tier }
								</span>
							) }
							{ module.enabled && ! module.booted && (
								<span className="medora-pill medora-pill--warn">
									{ module.skip_reason ||
										__( 'not running', 'medora-authority' ) }
								</span>
							) }
							<p className="medora-muted">{ module.description }</p>
						</div>

						<label className="medora-switch">
							<input
								type="checkbox"
								checked={ module.enabled }
								disabled={
									busy === module.id || ! boot.capabilities.manage
								}
								onChange={ () => toggle( module ) }
							/>
							<span />
						</label>
					</li>
				) ) }
			</ul>
		</section>
	);
}

function License(): JSX.Element {
	const { data, reload } = useAsync< LicenseState >( () => api.license(), [] );
	const [ key, setKey ] = useState( '' );
	const [ message, setMessage ] = useState( '' );
	const [ busy, setBusy ] = useState( false );

	const run = async ( action: string ) => {
		setBusy( true );
		setMessage( '' );

		try {
			const result = await api.licenseAction( action, key );
			setMessage( result.message );
			setKey( '' );
			reload();
		} catch ( error ) {
			setMessage( ( error as Error ).message );
		} finally {
			setBusy( false );
		}
	};

	return (
		<section className="medora-card">
			<h2>{ __( 'Licence', 'medora-authority' ) }</h2>

			<p>
				<strong>{ data?.tier_label ?? '—' }</strong>
				{ data?.status && (
					<span className={ `medora-pill medora-pill--${ data.status }` }>
						{ data.status }
					</span>
				) }
			</p>

			{ data?.grace_days_left !== null && data?.grace_days_left !== undefined && (
				<div className="medora-notice medora-notice--warning">
					{ sprintf(
						/* translators: %d: days remaining. */
						__(
							'Licence expired. Everything keeps working for %d more day(s).',
							'medora-authority'
						),
						data.grace_days_left
					) }
				</div>
			) }

			{ data?.masked_key ? (
				<>
					<p className="medora-muted">{ data.masked_key }</p>
					<div className="medora-actions">
						<button
							type="button"
							className="button"
							disabled={ busy }
							onClick={ () => run( 'refresh' ) }
						>
							{ __( 'Refresh status', 'medora-authority' ) }
						</button>

						{ ! data.domain_bound && (
							<button
								type="button"
								className="button button-primary"
								disabled={ busy }
								onClick={ () => run( 'transfer' ) }
							>
								{ __( 'Move licence to this domain', 'medora-authority' ) }
							</button>
						) }

						<button
							type="button"
							className="button"
							disabled={ busy }
							onClick={ () => run( 'deactivate' ) }
						>
							{ __( 'Deactivate', 'medora-authority' ) }
						</button>
					</div>

					{ ! data.domain_bound && (
						<p className="medora-muted">
							{ __(
								'This licence is bound to a different domain. You can move it yourself — no support ticket needed. Once a month.',
								'medora-authority'
							) }
						</p>
					) }
				</>
			) : (
				<div className="medora-actions">
					<input
						type="text"
						value={ key }
						placeholder={ __( 'Licence key', 'medora-authority' ) }
						onChange={ ( event ) => setKey( event.target.value ) }
					/>
					<button
						type="button"
						className="button button-primary"
						disabled={ busy || key.trim() === '' }
						onClick={ () => run( 'activate' ) }
					>
						{ __( 'Activate', 'medora-authority' ) }
					</button>
				</div>
			) }

			{ message && <p className="medora-muted">{ message }</p> }
		</section>
	);
}

function Security(): JSX.Element {
	const { data } = useAsync< SecurityScan >( () => api.securityScan(), [] );

	if ( ! data ) {
		return <></>;
	}

	return (
		<section className="medora-card">
			<h2>
				{ sprintf(
					/* translators: 1: checks passed, 2: total checks. */
					__( 'Configuration check — %1$d of %2$d passing', 'medora-authority' ),
					data.passed,
					data.passed + data.failed
				) }
			</h2>

			<ul className="medora-checks">
				{ data.checks.map( ( check ) => (
					<li
						key={ check.id }
						className={ `medora-check medora-check--${ check.status }` }
					>
						<span aria-hidden="true">
							{ check.status === 'pass' ? '✓' : '✕' }
						</span>
						<div>
							<strong>{ check.label }</strong>
							{ check.detail && <p>{ check.detail }</p> }
						</div>
					</li>
				) ) }
			</ul>
		</section>
	);
}

export function Settings(): JSX.Element {
	const { data, reload } = useAsync( () => api.settings(), [] );
	const [ draft, setDraft ] = useState< Record< string, unknown > >( {} );
	const [ status, setStatus ] = useState( '' );

	useEffect( () => {
		if ( data ) {
			setDraft( data.settings );
		}
	}, [ data ] );

	const set = ( key: string, value: unknown ) =>
		setDraft( ( previous ) => ( { ...previous, [ key ]: value } ) );

	const save = async () => {
		setStatus( __( 'Saving…', 'medora-authority' ) );

		try {
			await api.saveSettings( draft );
			setStatus( __( 'Settings saved.', 'medora-authority' ) );
			reload();
		} catch ( error ) {
			setStatus( ( error as Error ).message );
		}
	};

	return (
		<div className="medora-page">
			<header className="medora-page__head">
				<h1>{ __( 'Settings', 'medora-authority' ) }</h1>
			</header>

			<section className="medora-card">
				<h2>{ __( 'Publisher', 'medora-authority' ) }</h2>

				<label htmlFor="medora-org-name">
					{ __( 'Organisation name', 'medora-authority' ) }
				</label>
				<input
					id="medora-org-name"
					type="text"
					value={ String( draft.organization_name ?? '' ) }
					onChange={ ( event ) =>
						set( 'organization_name', event.target.value )
					}
				/>

				<label htmlFor="medora-site-mode">
					{ __( 'Site mode', 'medora-authority' ) }
				</label>
				<select
					id="medora-site-mode"
					value={ String( draft.site_mode ?? 'general' ) }
					onChange={ ( event ) => set( 'site_mode', event.target.value ) }
				>
					<option value="general">
						{ __( 'General', 'medora-authority' ) }
					</option>
					<option value="medical">
						{ __( 'Health / medical (YMYL)', 'medora-authority' ) }
					</option>
				</select>

				<label htmlFor="medora-retention">
					{ __( 'Log retention (days)', 'medora-authority' ) }
				</label>
				<input
					id="medora-retention"
					type="number"
					min={ 7 }
					max={ 400 }
					value={ Number( draft.retention_days ?? 180 ) }
					onChange={ ( event ) =>
						set( 'retention_days', Number( event.target.value ) )
					}
				/>

				<h3>{ __( 'Published artefacts', 'medora-authority' ) }</h3>

				{ (
					[
						[ 'llms_txt_enabled', __( 'Publish llms.txt', 'medora-authority' ) ],
						[ 'sitemap_enabled', __( 'Publish AI sitemaps', 'medora-authority' ) ],
						[ 'schema_enabled', __( 'Output JSON-LD schema', 'medora-authority' ) ],
						[ 'analytics_enabled', __( 'Track AI referrals', 'medora-authority' ) ],
					] as const
				 ).map( ( [ key, label ] ) => (
					<label key={ key } className="medora-checkbox">
						<input
							type="checkbox"
							checked={ Boolean( draft[ key ] ) }
							onChange={ ( event ) => set( key, event.target.checked ) }
						/>
						{ label }
					</label>
				) ) }

				<h3>{ __( 'Embeddings', 'medora-authority' ) }</h3>

				<label htmlFor="medora-embedding-provider">
					{ __( 'Provider', 'medora-authority' ) }
				</label>
				<select
					id="medora-embedding-provider"
					value={ String( draft.embedding_provider ?? 'hashing' ) }
					onChange={ ( event ) =>
						set( 'embedding_provider', event.target.value )
					}
				>
					<option value="hashing">
						{ __( 'Built-in (offline, no API key)', 'medora-authority' ) }
					</option>
					<option value="openai">
						{ __( 'OpenAI-compatible API', 'medora-authority' ) }
					</option>
				</select>
				<p className="medora-muted">
					{ data?.has_embedding_key
						? __( 'An API key is configured.', 'medora-authority' )
						: __(
								'No API key configured. Set MEDORA_EMBEDDING_API_KEY in wp-config.php rather than storing it in the database.',
								'medora-authority'
						  ) }
				</p>
				<p className="medora-muted">
					{ __(
						'Switching provider clears the existing index — the two vector spaces are not comparable — and content is re-embedded in the background.',
						'medora-authority'
					) }
				</p>

				<div className="medora-actions">
					<button
						type="button"
						className="button button-primary"
						onClick={ save }
						disabled={ ! boot.capabilities.manage }
					>
						{ __( 'Save settings', 'medora-authority' ) }
					</button>
					{ status && <span className="medora-muted">{ status }</span> }
				</div>
			</section>

			<Modules />
			<License />
			<Security />
		</div>
	);
}
