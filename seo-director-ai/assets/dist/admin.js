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

	function SetupGuide( { goToConnections } ) {
		return h( Card, { title: __( 'Welcome — let’s get you set up', 'seo-director-ai' ) },
			h( 'ol', { className: 'sda-setup' },
				h( 'li', null, __( 'Add your Google OAuth Client ID under Settings.', 'seo-director-ai' ) ),
				h( 'li', null, __( 'Connect Google Search Console under Connections.', 'seo-director-ai' ) ),
				h( 'li', null, __( 'Pick your property, then run the first sync.', 'seo-director-ai' ) ),
				h( 'li', null, __( '(Optional) Add an AI provider key for explanations and roadmaps.', 'seo-director-ai' ) )
			),
			config.canManage
				? h( 'button', { className: 'button button-primary', onClick: goToConnections }, __( 'Open Connections', 'seo-director-ai' ) )
				: null
		);
	}

	function OverviewScreen( { data, onRescan, goToConnections, aiEnabled, goToLicense } ) {
		if ( ! data ) {
			return h( 'p', { className: 'sda-loading' }, __( 'Loading…', 'seo-director-ai' ) );
		}
		if ( data.connected === false ) {
			return h( SetupGuide, { goToConnections: goToConnections } );
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
			h( InsightPanel, { canUse: aiEnabled, goToLicense: goToLicense } ),
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

	function PropertyPicker( { service } ) {
		const [ properties, setProperties ] = useState( null );
		const [ error, setError ] = useState( '' );

		const load = () =>
			apiFetch( { path: 'properties?service=' + service } )
				.then( ( r ) => setProperties( r.properties || [] ) )
				.catch( ( e ) => setError( ( e && e.message ) || __( 'Could not load properties.', 'seo-director-ai' ) ) );
		useEffect( () => { load(); }, [] );

		const activate = ( id ) =>
			apiFetch( { path: 'properties/' + id + '/activate', method: 'POST', data: { service: service } } ).then( load );

		if ( error ) return h( 'p', { className: 'sda-empty' }, error );
		if ( properties === null ) return h( 'p', { className: 'sda-loading' }, __( 'Discovering properties…', 'seo-director-ai' ) );
		if ( ! properties.length ) return h( 'p', { className: 'sda-empty' }, __( 'No properties found on this account.', 'seo-director-ai' ) );

		return h( 'ul', { className: 'sda-props' }, properties.map( ( p ) =>
			h( 'li', { key: p.id },
				h( 'label', null,
					h( 'input', {
						type: 'radio',
						name: 'sda-prop-' + service,
						checked: p.is_active,
						onChange: () => activate( p.id ),
					} ),
					' ', p.display_name || p.external_id
				)
			)
		) );
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
				return h( 'li', { key: svc.slug, className: 'sda-list__item sda-list__item--stack' },
					h( 'div', { className: 'sda-list__row' },
						h( 'span', { className: 'sda-list__label' }, svc.name ),
						row
							? h( Fragment, null,
								h( 'span', { className: 'sda-badge sda-badge--sev-' + ( row.status === 'connected' ? 'low' : 'high' ) }, row.status ),
								h( 'button', { className: 'button-link sda-danger', onClick: () => disconnect( svc.slug ) }, __( 'Disconnect', 'seo-director-ai' ) ) )
							: h( 'button', { className: 'button', disabled: busy === svc.slug, onClick: () => connect( svc ) },
								busy === svc.slug ? __( 'Working…', 'seo-director-ai' ) : __( 'Connect', 'seo-director-ai' ) )
					),
					svc.oauth && row && row.status === 'connected'
						? h( PropertyPicker, { service: svc.slug } )
						: null
				);
			} ) )
		);
	}

	function InsightPanel( { canUse, goToLicense } ) {
		const [ state, setState ] = useState( 'idle' ); // idle | loading | done | error
		const [ insight, setInsight ] = useState( null );
		const [ error, setError ] = useState( '' );

		if ( ! config.canManage ) return null;

		if ( canUse === false ) {
			return h( Card, { title: __( 'AI explanation', 'seo-director-ai' ) },
				h( 'p', { className: 'sda-empty' }, __( 'AI insights are a Pro feature.', 'seo-director-ai' ) ),
				h( 'button', { className: 'button', onClick: goToLicense }, __( 'Upgrade', 'seo-director-ai' ) )
			);
		}

		const explain = () => {
			setState( 'loading' );
			setError( '' );
			apiFetch( { path: 'insights/explain', method: 'POST', data: { scope: 'site' } } )
				.then( ( r ) => { setInsight( r.insight ); setState( 'done' ); } )
				.catch( ( e ) => { setError( ( e && e.message ) || __( 'Could not generate insight.', 'seo-director-ai' ) ); setState( 'error' ); } );
		};

		return h( Card, { title: __( 'AI explanation', 'seo-director-ai' ) },
			state === 'idle' || state === 'error'
				? h( Fragment, null,
					h( 'p', { className: 'sda-empty' }, __( 'Ask the AI to explain your recent trend and recommend next actions.', 'seo-director-ai' ) ),
					error ? h( 'p', { className: 'sda-error' }, error ) : null,
					h( 'button', { className: 'button', onClick: explain }, __( 'Explain my trend', 'seo-director-ai' ) )
				)
				: null,
			state === 'loading' ? h( 'p', { className: 'sda-loading' }, __( 'Analyzing…', 'seo-director-ai' ) ) : null,
			state === 'done' && insight ? h( InsightBody, { insight: insight } ) : null
		);
	}

	function InsightBody( { insight } ) {
		return h( 'div', { className: 'sda-insight' },
			insight.headline ? h( 'h4', null, insight.headline ) : null,
			insight.explanation ? h( 'p', null, insight.explanation ) : null,
			insight.causes && insight.causes.length
				? h( Fragment, null,
					h( 'h5', null, __( 'Likely causes', 'seo-director-ai' ) ),
					h( 'ul', { className: 'sda-list' }, insight.causes.map( ( c, i ) =>
						h( 'li', { key: i, className: 'sda-list__item' },
							h( 'span', { className: 'sda-badge sda-badge--conf-' + c.confidence }, c.confidence ),
							h( 'span', { className: 'sda-list__label' }, c.cause )
						) ) ) )
				: null,
			insight.actions && insight.actions.length
				? h( Fragment, null,
					h( 'h5', null, __( 'Recommended actions', 'seo-director-ai' ) ),
					h( 'ul', { className: 'sda-list' }, insight.actions.map( ( a, i ) =>
						h( 'li', { key: i, className: 'sda-list__item' },
							h( 'span', { className: 'sda-list__label' }, a.title ),
							h( 'span', { className: 'sda-list__meta' },
								__( 'impact', 'seo-director-ai' ) + ': ' + a.impact + ' · ' + __( 'effort', 'seo-director-ai' ) + ': ' + a.effort )
						) ) ) )
				: null
		);
	}

	function MoversScreen() {
		const [ dimension, setDimension ] = useState( 'query' );
		const [ winners, setWinners ] = useState( null );
		const [ losers, setLosers ] = useState( null );

		useEffect( () => {
			setWinners( null );
			setLosers( null );
			apiFetch( { path: 'winners?dimension=' + dimension } ).then( ( r ) => setWinners( r.winners || [] ) ).catch( () => setWinners( [] ) );
			apiFetch( { path: 'losers?dimension=' + dimension } ).then( ( r ) => setLosers( r.losers || [] ) ).catch( () => setLosers( [] ) );
		}, [ dimension ] );

		return h( Fragment, null,
			h( 'div', { className: 'sda-toolbar' },
				h( 'label', null, __( 'Dimension:', 'seo-director-ai' ), ' ',
					h( 'select', { value: dimension, onChange: ( e ) => setDimension( e.target.value ) },
						h( 'option', { value: 'query' }, __( 'Queries', 'seo-director-ai' ) ),
						h( 'option', { value: 'page' }, __( 'Pages', 'seo-director-ai' ) )
					)
				)
			),
			h( 'div', { className: 'sda-grid sda-grid--two' },
				h( Card, { title: __( 'Winners (week over week)', 'seo-director-ai' ) }, h( MoverTable, { rows: winners, positive: true } ) ),
				h( Card, { title: __( 'Losers (week over week)', 'seo-director-ai' ) }, h( MoverTable, { rows: losers, positive: false } ) )
			)
		);
	}

	function MoverTable( { rows, positive } ) {
		if ( rows === null ) return h( 'p', { className: 'sda-loading' }, __( 'Loading…', 'seo-director-ai' ) );
		if ( ! rows.length ) return h( 'p', { className: 'sda-empty' }, __( 'No movers in this period.', 'seo-director-ai' ) );
		return h( 'div', { className: 'sda-table-wrap' }, h( 'table', { className: 'sda-table' },
			h( 'thead', null, h( 'tr', null,
				h( 'th', null, __( 'Entity', 'seo-director-ai' ) ),
				h( 'th', null, __( 'Clicks', 'seo-director-ai' ) ),
				h( 'th', null, 'Δ' ),
				h( 'th', null, __( 'Position', 'seo-director-ai' ) )
			) ),
			h( 'tbody', null, rows.map( ( r, i ) => h( 'tr', { key: i },
				h( 'td', { className: 'sda-cell-path' },
					r.is_new ? h( 'span', { className: 'sda-badge sda-badge--sev-low' }, __( 'new', 'seo-director-ai' ) ) : null,
					r.is_lost ? h( 'span', { className: 'sda-badge sda-badge--sev-critical' }, __( 'lost', 'seo-director-ai' ) ) : null,
					' ', r.label ),
				h( 'td', null, fmt( r.clicks ) ),
				h( 'td', { className: positive ? 'sda-delta-up' : 'sda-delta-down' }, ( r.clicks_delta >= 0 ? '+' : '' ) + fmt( r.clicks_delta ) ),
				h( 'td', null, r.position || '—' )
			) ) )
		) );
	}

	function RoadmapScreen() {
		const [ board, setBoard ] = useState( null );
		const [ busy, setBusy ] = useState( false );

		const load = () => apiFetch( { path: 'roadmap?scope=monthly' } ).then( ( r ) => setBoard( r.board ) );
		useEffect( () => { load().catch( () => setBoard( { todo: [], in_progress: [], done: [], dismissed: [] } ) ); }, [] );

		const generate = () => {
			setBusy( true );
			apiFetch( { path: 'roadmap', method: 'POST', data: { scope: 'monthly' } } )
				.then( ( r ) => setBoard( r.board ) )
				.finally( () => setBusy( false ) );
		};

		const move = ( id, status ) =>
			apiFetch( { path: 'roadmap/tasks/' + id, method: 'PATCH', data: { status: status } } ).then( load );

		if ( ! board ) return h( 'p', { className: 'sda-loading' }, __( 'Loading…', 'seo-director-ai' ) );

		const columns = [
			[ 'todo', __( 'To do', 'seo-director-ai' ) ],
			[ 'in_progress', __( 'In progress', 'seo-director-ai' ) ],
			[ 'done', __( 'Done', 'seo-director-ai' ) ],
		];
		const empty = ! columns.some( ( [ id ] ) => ( board[ id ] || [] ).length );

		return h( Fragment, null,
			h( 'div', { className: 'sda-toolbar' },
				config.canManage
					? h( 'button', { className: 'button button-primary', disabled: busy, onClick: generate },
						busy ? __( 'Generating…', 'seo-director-ai' ) : __( 'Generate from opportunities', 'seo-director-ai' ) )
					: null
			),
			empty
				? h( Card, null, h( 'p', { className: 'sda-empty' }, __( 'No tasks yet. Generate a roadmap from your detected opportunities.', 'seo-director-ai' ) ) )
				: h( 'div', { className: 'sda-kanban' }, columns.map( ( [ id, label ] ) =>
					h( 'div', { key: id, className: 'sda-kanban__col' },
						h( 'h4', { className: 'sda-kanban__title' }, label, ' ', h( 'span', { className: 'sda-kanban__count' }, ( board[ id ] || [] ).length ) ),
						( board[ id ] || [] ).map( ( task ) => h( TaskCard, { key: task.id, task: task, onMove: move } ) )
					)
				) )
		);
	}

	function TaskCard( { task, onMove } ) {
		return h( 'div', { className: 'sda-task sda-task--' + task.category },
			h( 'div', { className: 'sda-task__title' }, task.title ),
			task.expected_result ? h( 'div', { className: 'sda-task__meta' }, task.expected_result ) : null,
			h( 'div', { className: 'sda-task__foot' },
				h( 'span', { className: 'sda-badge sda-badge--' + task.category }, task.category ),
				config.canManage
					? h( 'span', { className: 'sda-task__actions' },
						task.status !== 'in_progress' && task.status !== 'done'
							? h( 'button', { className: 'button-link', onClick: () => onMove( task.id, 'in_progress' ), title: __( 'Start', 'seo-director-ai' ) }, '▶' ) : null,
						task.status !== 'done'
							? h( 'button', { className: 'button-link', onClick: () => onMove( task.id, 'done' ), title: __( 'Done', 'seo-director-ai' ) }, '✓' ) : null,
						h( 'button', { className: 'button-link sda-danger', onClick: () => onMove( task.id, 'dismissed' ), title: __( 'Dismiss', 'seo-director-ai' ) }, '✕' )
					)
					: null
			)
		);
	}

	function LicenseScreen( { onChange } ) {
		const [ data, setData ] = useState( null );
		const [ key, setKey ] = useState( '' );
		const [ busy, setBusy ] = useState( false );
		const [ error, setError ] = useState( '' );

		const load = () => apiFetch( { path: 'license/status' } ).then( ( r ) => { setData( r ); if ( onChange ) onChange( r ); } );
		useEffect( () => { load().catch( () => setData( { license: { status: 'deactivated', edition: 'free', effective_edition: 'free' } } ) ); }, [] );

		const activate = () => {
			if ( ! key.trim() ) return;
			setBusy( true );
			setError( '' );
			apiFetch( { path: 'license/activate', method: 'POST', data: { license_key: key.trim() } } )
				.then( ( r ) => { setData( r ); setKey( '' ); if ( onChange ) onChange( r ); } )
				.catch( ( e ) => setError( ( e && e.message ) || __( 'Activation failed.', 'seo-director-ai' ) ) )
				.finally( () => setBusy( false ) );
		};

		const deactivate = () => {
			if ( ! window.confirm( __( 'Deactivate this license on this site?', 'seo-director-ai' ) ) ) return;
			setBusy( true );
			apiFetch( { path: 'license/deactivate', method: 'POST' } )
				.then( ( r ) => { setData( r ); if ( onChange ) onChange( r ); } )
				.finally( () => setBusy( false ) );
		};

		if ( ! data ) return h( 'p', { className: 'sda-loading' }, __( 'Loading…', 'seo-director-ai' ) );

		const lic = data.license || {};
		const active = lic.status === 'active' || lic.status === 'grace';
		const statusClass = lic.status === 'active' ? 'low' : lic.status === 'grace' ? 'medium' : 'high';

		return h( Fragment, null,
			h( Card, { title: __( 'License', 'seo-director-ai' ) },
				h( 'div', { className: 'sda-license-status' },
					h( 'span', { className: 'sda-license-edition' }, ( lic.edition || 'free' ).toUpperCase() ),
					h( 'span', { className: 'sda-badge sda-badge--sev-' + statusClass }, lic.status )
				),
				lic.status === 'grace'
					? h( 'p', { className: 'sda-grace' }, __( 'Your license expired but you are in the grace period.', 'seo-director-ai' ) +
						( lic.grace_ends_at ? ' ' + __( 'Ends:', 'seo-director-ai' ) + ' ' + lic.grace_ends_at : '' ) )
					: null,
				lic.expires_at ? h( 'p', { className: 'sda-list__meta' }, __( 'Renews / expires:', 'seo-director-ai' ) + ' ' + lic.expires_at ) : null,
				active
					? h( 'button', { className: 'button', disabled: busy, onClick: deactivate }, __( 'Deactivate', 'seo-director-ai' ) )
					: h( 'div', { className: 'sda-form' },
						h( 'label', { className: 'sda-form__row' },
							__( 'License key', 'seo-director-ai' ),
							h( 'input', { type: 'text', className: 'regular-text', value: key, onChange: ( e ) => setKey( e.target.value ), placeholder: 'SDA-XXXX-XXXX-XXXX' } )
						),
						error ? h( 'p', { className: 'sda-error' }, error ) : null,
						h( 'button', { className: 'button button-primary', disabled: busy, onClick: activate },
							busy ? __( 'Activating…', 'seo-director-ai' ) : __( 'Activate', 'seo-director-ai' ) )
					)
			),
			h( Card, { title: __( 'What your plan includes', 'seo-director-ai' ) },
				h( CapabilityList, { caps: data.capabilities || {} } )
			)
		);
	}

	function CapabilityList( { caps } ) {
		const labels = {
			roadmap: __( 'Editorial roadmap', 'seo-director-ai' ),
			ai_insights: __( 'AI insights & explanations', 'seo-director-ai' ),
			advanced_detectors: __( 'Advanced opportunity detectors', 'seo-director-ai' ),
			reports: __( 'PDF / CSV reports', 'seo-director-ai' ),
			scheduled_reports: __( 'Scheduled report delivery', 'seo-director-ai' ),
			agency_hub: __( 'Agency multi-site hub', 'seo-director-ai' ),
			white_label: __( 'White-label branding', 'seo-director-ai' ),
		};
		return h( 'ul', { className: 'sda-list' }, Object.keys( labels ).map( ( slug ) =>
			h( 'li', { key: slug, className: 'sda-list__item' },
				h( 'span', { className: 'sda-cap-mark ' + ( caps[ slug ] ? 'is-on' : 'is-off' ) }, caps[ slug ] ? '✓' : '—' ),
				h( 'span', { className: 'sda-list__label' }, labels[ slug ] )
			)
		) );
	}

	function VitalsScreen() {
		const [ data, setData ] = useState( null );
		const [ queued, setQueued ] = useState( false );

		useEffect( () => {
			apiFetch( { path: 'metrics/vitals' } ).then( setData ).catch( () => setData( { home: [], recent: [] } ) );
		}, [] );

		if ( ! data ) return h( 'p', { className: 'sda-loading' }, __( 'Loading…', 'seo-director-ai' ) );

		const queueAudit = () =>
			apiFetch( { path: 'metrics/vitals', method: 'POST' } ).then( () => setQueued( true ) );

		const metricCell = ( v, unit ) => ( v == null ? '—' : Number( v ).toLocaleString() + ( unit || '' ) );

		return h( Fragment, null,
			h( Card, { title: __( 'Core Web Vitals — home page', 'seo-director-ai' ) },
				data.home && data.home.length
					? h( 'div', { className: 'sda-table-wrap' }, h( 'table', { className: 'sda-table' },
						h( 'thead', null, h( 'tr', null,
							h( 'th', null, __( 'Device', 'seo-director-ai' ) ),
							h( 'th', null, 'LCP' ), h( 'th', null, 'CLS' ), h( 'th', null, 'INP' ), h( 'th', null, 'TTFB' ),
							h( 'th', null, __( 'Score', 'seo-director-ai' ) ),
							h( 'th', null, __( 'Status', 'seo-director-ai' ) )
						) ),
						h( 'tbody', null, data.home.map( ( a, i ) => h( 'tr', { key: i },
							h( 'td', null, a.strategy ),
							h( 'td', null, metricCell( a.lcp_ms, ' ms' ) ),
							h( 'td', null, a.cls == null ? '—' : a.cls ),
							h( 'td', null, metricCell( a.inp_ms, ' ms' ) ),
							h( 'td', null, metricCell( a.ttfb_ms, ' ms' ) ),
							h( 'td', null, a.perf_score == null ? '—' : a.perf_score ),
							h( 'td', null, h( 'span', {
								className: 'sda-badge sda-badge--sev-' + ( a.cwv_status === 'good' ? 'low' : a.cwv_status === 'poor' ? 'critical' : 'medium' ),
							}, a.cwv_status ) )
						) ) )
					) )
					: h( 'p', { className: 'sda-empty' }, __( 'No audits yet — run one below.', 'seo-director-ai' ) ),
				config.canManage
					? h( 'p', null, h( 'button', { className: 'button', disabled: queued, onClick: queueAudit },
						queued ? __( 'Audit queued ✓', 'seo-director-ai' ) : __( 'Run audit round', 'seo-director-ai' ) ) )
					: null
			),
			h( Card, { title: __( 'Recent audits', 'seo-director-ai' ) },
				data.recent && data.recent.length
					? h( 'ul', { className: 'sda-list' }, data.recent.map( ( a, i ) =>
						h( 'li', { key: i, className: 'sda-list__item' },
							h( 'span', { className: 'sda-badge' }, a.strategy ),
							h( 'span', { className: 'sda-list__label' }, a.page_path ),
							h( 'span', { className: 'sda-list__meta' }, ( a.perf_score == null ? '—' : a.perf_score ) + ' / 100' )
						) ) )
					: h( 'p', { className: 'sda-empty' }, __( 'Nothing audited yet.', 'seo-director-ai' ) )
			)
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
				h( 'label', { className: 'sda-form__row' },
					__( 'License server URL', 'seo-director-ai' ),
					h( 'input', { type: 'url', value: settings.license_server || '', onChange: set( 'license_server' ), className: 'regular-text', placeholder: 'https://api.seodirector.app' } )
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
		const [ caps, setCaps ] = useState( {} );

		const loadOverview = () => apiFetch( { path: 'overview' } ).then( setOverview ).catch( () => setOverview( { totals: {}, health: {} } ) );
		useEffect( () => {
			loadOverview();
			if ( config.canManage ) {
				apiFetch( { path: 'license/status' } ).then( ( r ) => setCaps( r.capabilities || {} ) ).catch( () => {} );
			}
		}, [] );

		const rescan = () => apiFetch( { path: 'opportunities', method: 'POST' } ).then( loadOverview );
		const sync = () => apiFetch( { path: 'sync', method: 'POST' } );

		const tabs = [
			[ 'overview', __( 'Overview', 'seo-director-ai' ) ],
			[ 'movers', __( 'Winners & Losers', 'seo-director-ai' ) ],
			[ 'roadmap', __( 'Roadmap', 'seo-director-ai' ) ],
			[ 'vitals', __( 'Web Vitals', 'seo-director-ai' ) ],
			[ 'connections', __( 'Connections', 'seo-director-ai' ) ],
			[ 'license', __( 'License', 'seo-director-ai' ) ],
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
			screen === 'overview' ? h( OverviewScreen, {
				data: overview,
				onRescan: rescan,
				goToConnections: () => setScreen( 'connections' ),
				aiEnabled: caps.ai_insights,
				goToLicense: () => setScreen( 'license' ),
			} ) : null,
			screen === 'movers' ? h( MoversScreen ) : null,
			screen === 'roadmap' ? h( RoadmapScreen ) : null,
			screen === 'vitals' ? h( VitalsScreen ) : null,
			screen === 'connections' ? h( ConnectionsScreen ) : null,
			screen === 'license' ? h( LicenseScreen, { onChange: ( r ) => setCaps( r.capabilities || {} ) } ) : null,
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
