/**
 * SEO Director AI — admin SPA.
 * Runs on WordPress-bundled React (wp.element) — no external CDN, no build step required.
 */
( function ( wp ) {
	'use strict';

	const { createElement: h, Fragment, useState, useEffect, render, createRoot } = wp.element;
	const apiFetch = wp.apiFetch;
	const { __ } = wp.i18n;

	const config = window.sdaConfig || { restBase: '', nonce: '', isRtl: false, canManage: false };
	apiFetch.use( apiFetch.createNonceMiddleware( config.nonce ) );
	apiFetch.use( apiFetch.createRootURLMiddleware( config.restBase + '/' ) );

	/* ------------------------------------------------------------------ */
	/* Primitives                                                          */
	/* ------------------------------------------------------------------ */

	function Card( { title, children, className } ) {
		return h(
			'div',
			{ className: 'sda-card' + ( className ? ' ' + className : '' ) },
			title ? h( 'h3', { className: 'sda-card__title' }, title ) : null,
			children
		);
	}

	function Stat( { label, value, delta } ) {
		let deltaEl = null;
		if ( typeof delta === 'number' ) {
			const dir = delta >= 0 ? 'up' : 'down';
			deltaEl = h(
				'span',
				{ className: 'sda-stat__delta sda-stat__delta--' + dir },
				( delta >= 0 ? '▲ ' : '▼ ' ) + Math.abs( delta ).toFixed( 1 ) + '%'
			);
		}
		return h(
			'div',
			{ className: 'sda-stat' },
			h( 'span', { className: 'sda-stat__label' }, label ),
			h( 'span', { className: 'sda-stat__value' }, value ),
			deltaEl
		);
	}

	function ScoreRing( { score } ) {
		const val = score == null ? 0 : score;
		const radius = 52;
		const circumference = 2 * Math.PI * radius;
		const cls = val >= 80 ? 'good' : val >= 50 ? 'mid' : 'poor';
		return h(
			'div',
			{ className: 'sda-ring sda-ring--' + cls },
			h(
				'svg',
				{ viewBox: '0 0 120 120', width: 120, height: 120, role: 'img', 'aria-label': __( 'SEO health score', 'seo-director-ai' ) },
				h( 'circle', { cx: 60, cy: 60, r: radius, className: 'sda-ring__track' } ),
				h( 'circle', {
					cx: 60, cy: 60, r: radius,
					className: 'sda-ring__value',
					strokeDasharray: circumference,
					strokeDashoffset: circumference * ( 1 - val / 100 ),
					transform: 'rotate(-90 60 60)',
				} )
			),
			h( 'div', { className: 'sda-ring__num' }, score == null ? '—' : String( score ) )
		);
	}

	function LineChart( { series, field, label } ) {
		if ( ! series || series.length < 2 ) {
			return h( 'p', { className: 'sda-empty' }, __( 'Not enough data yet — connect Search Console and run a sync.', 'seo-director-ai' ) );
		}
		const W = 640, H = 180, PAD = 8;
		const values = series.map( ( r ) => Number( r[ field ] ) || 0 );
		const max = Math.max.apply( null, values ) || 1;
		const step = ( W - 2 * PAD ) / ( values.length - 1 );
		const points = values
			.map( ( v, i ) => ( PAD + i * step ).toFixed( 1 ) + ',' + ( H - PAD - ( v / max ) * ( H - 2 * PAD ) ).toFixed( 1 ) )
			.join( ' ' );
		return h(
			'svg',
			{ viewBox: '0 0 ' + W + ' ' + H, className: 'sda-chart', preserveAspectRatio: 'none', role: 'img', 'aria-label': label },
			h( 'polyline', { points: points, className: 'sda-chart__line' } )
		);
	}

	/* ------------------------------------------------------------------ */
	/* Screens                                                             */
	/* ------------------------------------------------------------------ */

	function OverviewScreen( { data, onRescan } ) {
		if ( ! data ) {
			return h( 'p', { className: 'sda-loading' }, __( 'Loading…', 'seo-director-ai' ) );
		}
		const t = data.totals || {};
		return h(
			Fragment,
			null,
			h(
				'div',
				{ className: 'sda-grid sda-grid--kpi' },
				h( Card, { className: 'sda-card--score' },
					h( ScoreRing, { score: data.health && data.health.score } ),
					h( 'p', { className: 'sda-score-label' }, __( 'SEO Health Score', 'seo-director-ai' ) )
				),
				h( Card, null,
					h( Stat, { label: __( 'Clicks (28d)', 'seo-director-ai' ), value: fmt( t.clicks ), delta: t.clicks_wow } ),
					h( Stat, { label: __( 'Impressions (28d)', 'seo-director-ai' ), value: fmt( t.impressions ) } )
				),
				h( Card, null,
					h( Stat, { label: __( 'CTR', 'seo-director-ai' ), value: t.ctr != null ? ( t.ctr * 100 ).toFixed( 2 ) + '%' : '—' } ),
					h( Stat, { label: __( 'Avg. position', 'seo-director-ai' ), value: t.position || '—' } )
				)
			),
			h( Card, { title: __( 'Clicks trend', 'seo-director-ai' ) },
				h( LineChart, { series: data.series, field: 'clicks', label: __( 'Clicks over time', 'seo-director-ai' ) } )
			),
			h(
				'div',
				{ className: 'sda-grid sda-grid--two' },
				h( Card, { title: __( 'Top opportunities', 'seo-director-ai' ) },
					h( OpportunityList, { items: data.opportunities, onRescan: onRescan } )
				),
				h( Card, { title: __( 'Active alerts', 'seo-director-ai' ) },
					h( AlertList, { items: data.alerts } )
				)
			),
			h( Card, { title: __( 'Top pages', 'seo-director-ai' ) }, h( TopPagesTable, { rows: data.top_pages } ) )
		);
	}

	function OpportunityList( { items, onRescan } ) {
		if ( ! items || ! items.length ) {
			return h(
				Fragment,
				null,
				h( 'p', { className: 'sda-empty' }, __( 'No opportunities detected yet.', 'seo-director-ai' ) ),
				config.canManage
					? h( 'button', { className: 'button', onClick: onRescan }, __( 'Run detection now', 'seo-director-ai' ) )
					: null
			);
		}
		return h(
			'ul',
			{ className: 'sda-list' },
			items.map( ( o ) =>
				h(
					'li',
					{ key: o.id, className: 'sda-list__item' },
					h( 'span', { className: 'sda-badge sda-badge--' + o.detector }, detectorLabel( o.detector ) ),
					h( 'span', { className: 'sda-list__label' }, o.entity_label ),
					o.est_traffic_gain
						? h( 'span', { className: 'sda-list__meta' }, '+' + fmt( o.est_traffic_gain ) + ' ' + __( 'clicks/mo est.', 'seo-director-ai' ) )
						: null
				)
			)
		);
	}

	function AlertList( { items } ) {
		if ( ! items || ! items.length ) {
			return h( 'p', { className: 'sda-empty' }, __( 'All clear — no active alerts.', 'seo-director-ai' ) );
		}
		return h(
			'ul',
			{ className: 'sda-list' },
			items.map( ( a ) =>
				h(
					'li',
					{ key: a.id, className: 'sda-list__item' },
					h( 'span', { className: 'sda-badge sda-badge--sev-' + a.severity }, a.severity ),
					h( 'span', { className: 'sda-list__label' }, a.message )
				)
			)
		);
	}

	function TopPagesTable( { rows } ) {
		if ( ! rows || ! rows.length ) {
			return h( 'p', { className: 'sda-empty' }, __( 'No page data yet.', 'seo-director-ai' ) );
		}
		return h(
			'div',
			{ className: 'sda-table-wrap' },
			h(
				'table',
				{ className: 'sda-table' },
				h( 'thead', null, h( 'tr', null,
					h( 'th', null, __( 'Page', 'seo-director-ai' ) ),
					h( 'th', null, __( 'Clicks', 'seo-director-ai' ) ),
					h( 'th', null, __( 'Impressions', 'seo-director-ai' ) ),
					h( 'th', null, __( 'CTR', 'seo-director-ai' ) ),
					h( 'th', null, __( 'Position', 'seo-director-ai' ) )
				) ),
				h( 'tbody', null, rows.map( ( r, i ) =>
					h( 'tr', { key: i },
						h( 'td', { className: 'sda-cell-path' }, r.page_path ),
						h( 'td', null, fmt( r.clicks ) ),
						h( 'td', null, fmt( r.impressions ) ),
						h( 'td', null, ( r.ctr * 100 ).toFixed( 2 ) + '%' ),
						h( 'td', null, Number( r.position ).toFixed( 1 ) )
					)
				) )
			)
		);
	}

	function ConnectionsScreen() {
		const [ connections, setConnections ] = useState( null );
		const [ busy, setBusy ] = useState( '' );

		const load = () => apiFetch( { path: 'connections' } ).then( ( r ) => setConnections( r.connections || [] ) );
		useEffect( () => { load().catch( () => setConnections( [] ) ); }, [] );

		const services = [
			{ slug: 'gsc', name: __( 'Google Search Console', 'seo-director-ai' ), oauth: true },
			{ slug: 'ga4', name: __( 'Google Analytics 4', 'seo-director-ai' ), oauth: true },
			{ slug: 'psi', name: __( 'PageSpeed Insights', 'seo-director-ai' ), oauth: false },
			{ slug: 'claude', name: __( 'Anthropic Claude', 'seo-director-ai' ), oauth: false },
			{ slug: 'openai', name: __( 'OpenAI', 'seo-director-ai' ), oauth: false },
			{ slug: 'gemini', name: __( 'Google Gemini', 'seo-director-ai' ), oauth: false },
		];

		const connect = ( svc ) => {
			setBusy( svc.slug );
			const body = {};
			if ( ! svc.oauth ) {
				const key = window.prompt( __( 'Paste the API key for', 'seo-director-ai' ) + ' ' + svc.name );
				if ( ! key ) { setBusy( '' ); return; }
				body.api_key = key;
			}
			apiFetch( { path: 'connections/' + svc.slug, method: 'POST', data: body } )
				.then( ( r ) => {
					if ( r.authorize_url ) { window.location.href = r.authorize_url; return; }
					return load();
				} )
				.finally( () => setBusy( '' ) );
		};

		const disconnect = ( slug ) => {
			if ( ! window.confirm( __( 'Disconnect this service?', 'seo-director-ai' ) ) ) return;
			apiFetch( { path: 'connections/' + slug, method: 'DELETE' } ).then( load );
		};

		if ( connections === null ) {
			return h( 'p', { className: 'sda-loading' }, __( 'Loading…', 'seo-director-ai' ) );
		}

		return h( Card, { title: __( 'Connections', 'seo-director-ai' ) },
			h( 'ul', { className: 'sda-list' }, services.map( ( svc ) => {
				const row = connections.find( ( c ) => c.service === svc.slug );
				return h( 'li', { key: svc.slug, className: 'sda-list__item' },
					h( 'span', { className: 'sda-list__label' }, svc.name ),
					row
						? h( Fragment, null,
							h( 'span', { className: 'sda-badge sda-badge--sev-' + ( row.status === 'connected' ? 'low' : 'high' ) }, row.status ),
							h( 'button', { className: 'button-link sda-danger', onClick: () => disconnect( svc.slug ) }, __( 'Disconnect', 'seo-director-ai' ) ) )
						: h( 'button', { className: 'button', disabled: busy === svc.slug, onClick: () => connect( svc ) },
							busy === svc.slug ? __( 'Working…', 'seo-director-ai' ) : __( 'Connect', 'seo-director-ai' ) )
				);
			} ) )
		);
	}

	function SettingsScreen() {
		const [ settings, setSettings ] = useState( null );
		const [ saved, setSaved ] = useState( false );

		useEffect( () => {
			apiFetch( { path: 'settings' } ).then( ( r ) => setSettings( r.settings || {} ) ).catch( () => setSettings( {} ) );
		}, [] );

		if ( settings === null ) {
			return h( 'p', { className: 'sda-loading' }, __( 'Loading…', 'seo-director-ai' ) );
		}

		const save = () => {
			apiFetch( { path: 'settings', method: 'POST', data: settings } ).then( ( r ) => {
				setSettings( r.settings );
				setSaved( true );
				window.setTimeout( () => setSaved( false ), 2500 );
			} );
		};
		const set = ( key ) => ( e ) => {
			const el = e.target;
			setSettings( Object.assign( {}, settings, { [ key ]: el.type === 'checkbox' ? el.checked : el.value } ) );
		};

		return h( Card, { title: __( 'Settings', 'seo-director-ai' ) },
			h( 'div', { className: 'sda-form' },
				h( 'label', { className: 'sda-form__row' },
					h( 'input', { type: 'checkbox', checked: !! settings.ai_enabled, onChange: set( 'ai_enabled' ) } ),
					' ', __( 'Enable AI insights (explanations, roadmaps)', 'seo-director-ai' )
				),
				h( 'label', { className: 'sda-form__row' },
					__( 'Preferred AI provider', 'seo-director-ai' ),
					h( 'select', { value: settings.ai_provider || 'claude', onChange: set( 'ai_provider' ) },
						h( 'option', { value: 'claude' }, 'Claude' ),
						h( 'option', { value: 'openai' }, 'OpenAI' ),
						h( 'option', { value: 'gemini' }, 'Gemini' )
					)
				),
				h( 'label', { className: 'sda-form__row' },
					__( 'Google OAuth Client ID', 'seo-director-ai' ),
					h( 'input', { type: 'text', value: settings.google_client_id || '', onChange: set( 'google_client_id' ), className: 'regular-text' } )
				),
				h( 'label', { className: 'sda-form__row' },
					__( 'Monthly AI token budget', 'seo-director-ai' ),
					h( 'input', { type: 'number', value: settings.ai_monthly_budget || 0, onChange: set( 'ai_monthly_budget' ) } )
				),
				h( 'p', null,
					h( 'button', { className: 'button button-primary', onClick: save }, __( 'Save settings', 'seo-director-ai' ) ),
					saved ? h( 'span', { className: 'sda-saved' }, ' ✓ ' + __( 'Saved', 'seo-director-ai' ) ) : null
				)
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* App shell                                                           */
	/* ------------------------------------------------------------------ */

	function App() {
		const [ screen, setScreen ] = useState( 'overview' );
		const [ overview, setOverview ] = useState( null );

		const loadOverview = () => apiFetch( { path: 'overview' } ).then( setOverview ).catch( () => setOverview( { totals: {}, health: {} } ) );
		useEffect( () => { loadOverview(); }, [] );

		const rescan = () => apiFetch( { path: 'opportunities', method: 'POST' } ).then( loadOverview );
		const sync = () => apiFetch( { path: 'sync', method: 'POST' } );

		const tabs = [
			[ 'overview', __( 'Overview', 'seo-director-ai' ) ],
			[ 'connections', __( 'Connections', 'seo-director-ai' ) ],
			[ 'settings', __( 'Settings', 'seo-director-ai' ) ],
		];

		return h(
			'div',
			{ className: 'sda-shell' },
			h(
				'header',
				{ className: 'sda-header' },
				h( 'h1', null, __( 'SEO Director AI', 'seo-director-ai' ) ),
				h( 'nav', { className: 'sda-tabs' }, tabs.map( ( [ id, label ] ) =>
					h( 'button', {
						key: id,
						className: 'sda-tab' + ( screen === id ? ' is-active' : '' ),
						onClick: () => setScreen( id ),
					}, label )
				) ),
				config.canManage
					? h( 'button', { className: 'button', onClick: sync, title: __( 'Queue a background data sync', 'seo-director-ai' ) }, __( 'Sync now', 'seo-director-ai' ) )
					: null
			),
			screen === 'overview' ? h( OverviewScreen, { data: overview, onRescan: rescan } ) : null,
			screen === 'connections' ? h( ConnectionsScreen ) : null,
			screen === 'settings' ? h( SettingsScreen ) : null
		);
	}

	function fmt( n ) {
		if ( n == null ) return '—';
		return Number( n ).toLocaleString();
	}

	function detectorLabel( slug ) {
		const map = {
			striking_distance: __( 'Striking distance', 'seo-director-ai' ),
			low_ctr: __( 'Low CTR', 'seo-director-ai' ),
		};
		return map[ slug ] || slug;
	}

	const mount = document.getElementById( 'sda-app' );
	if ( mount ) {
		if ( createRoot ) {
			createRoot( mount ).render( h( App ) );
		} else {
			render( h( App ), mount );
		}
	}
} )( window.wp );
