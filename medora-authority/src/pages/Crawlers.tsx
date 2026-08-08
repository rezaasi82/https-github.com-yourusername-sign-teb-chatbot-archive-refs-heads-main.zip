import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { api, boot } from '../api/client';
import { useAsync } from '../hooks/useAsync';
import type { CrawlerRow } from '../types';

const PRESETS = [
	{
		value: 'allow',
		label: __( 'Allow all', 'medora-authority' ),
		hint: __(
			'Maximum AI visibility, including model training.',
			'medora-authority'
		),
	},
	{
		value: 'selective',
		label: __( 'Selective', 'medora-authority' ),
		hint: __(
			'Stay citable in AI answers, opt out of corpus collection. This is what most publishers want.',
			'medora-authority'
		),
	},
	{
		value: 'block',
		label: __( 'Block AI crawlers', 'medora-authority' ),
		hint: __(
			'Classic search crawlers still allowed.',
			'medora-authority'
		),
	},
];

export function Crawlers(): JSX.Element {
	const [ busy, setBusy ] = useState( false );
	const [ showRobots, setShowRobots ] = useState( false );

	const { data, loading, error, reload } = useAsync(
		() => api.crawlers(),
		[]
	);
	const activity = useAsync( () => api.crawlerActivity( 30 ), [] );

	const hits = new Map(
		( activity.data?.crawlers ?? [] ).map( ( row ) => [
			row.slug,
			row.hits,
		] )
	);

	const update = async (
		payload: Parameters< typeof api.updateCrawler >[ 0 ]
	) => {
		setBusy( true );

		try {
			await api.updateCrawler( payload );
			reload();
		} finally {
			setBusy( false );
		}
	};

	if ( loading && ! data ) {
		return (
			<p className="medora-loading">
				{ __( 'Loading…', 'medora-authority' ) }
			</p>
		);
	}

	if ( error ) {
		return <p className="medora-error">{ error.message }</p>;
	}

	const grouped = ( data?.crawlers ?? [] ).reduce<
		Record< string, CrawlerRow[] >
	>( ( accumulator, crawler ) => {
		( accumulator[ crawler.vendor ] ??= [] ).push( crawler );
		return accumulator;
	}, {} );

	return (
		<div className="medora-page">
			<header className="medora-page__head">
				<h1>{ __( 'AI Crawlers', 'medora-authority' ) }</h1>
			</header>

			{ data?.robots.physical_file_present && (
				<div className="medora-notice medora-notice--critical">
					{ __(
						'A physical robots.txt file exists in your site root. It overrides the virtual one, so none of the policy below is actually being served. Delete or merge that file.',
						'medora-authority'
					) }
				</div>
			) }

			<section className="medora-card">
				<h2>{ __( 'Site policy', 'medora-authority' ) }</h2>
				<div className="medora-presets">
					{ PRESETS.map( ( preset ) => (
						<label
							key={ preset.value }
							htmlFor={ `medora-preset-${ preset.value }` }
							className={
								data?.preset === preset.value
									? 'medora-preset is-active'
									: 'medora-preset'
							}
						>
							<input
								id={ `medora-preset-${ preset.value }` }
								type="radio"
								name="medora-preset"
								value={ preset.value }
								checked={ data?.preset === preset.value }
								disabled={ busy || ! boot.capabilities.manage }
								onChange={ () =>
									update( { preset: preset.value } )
								}
							/>
							<strong>{ preset.label }</strong>
							<span className="medora-muted">
								{ preset.hint }
							</span>
						</label>
					) ) }
				</div>
			</section>

			{ Object.entries( grouped ).map( ( [ vendor, crawlers ] ) => (
				<section className="medora-card" key={ vendor }>
					<h2>{ vendor }</h2>
					<table className="medora-table">
						<thead>
							<tr>
								<th>{ __( 'Crawler', 'medora-authority' ) }</th>
								<th>
									{ __( 'Used for', 'medora-authority' ) }
								</th>
								<th>
									{ __( 'Visits (30d)', 'medora-authority' ) }
								</th>
								<th>{ __( 'Access', 'medora-authority' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ crawlers.map( ( crawler ) => (
								<tr key={ crawler.slug }>
									<td>
										<strong>{ crawler.name }</strong>
										{ crawler.is_override && (
											<span className="medora-pill">
												{ __(
													'override',
													'medora-authority'
												) }
											</span>
										) }
									</td>
									<td>{ crawler.purpose_label }</td>
									<td>{ hits.get( crawler.slug ) ?? 0 }</td>
									<td>
										<select
											value={ crawler.decision }
											disabled={
												busy ||
												! boot.capabilities.manage
											}
											onChange={ ( event ) =>
												update( {
													slug: crawler.slug,
													decision:
														event.target.value,
												} )
											}
										>
											<option value="allow">
												{ __(
													'Allow',
													'medora-authority'
												) }
											</option>
											<option value="delay">
												{ __(
													'Allow, throttled',
													'medora-authority'
												) }
											</option>
											<option value="block">
												{ __(
													'Block',
													'medora-authority'
												) }
											</option>
											{ crawler.is_override && (
												<option value="reset">
													{ __(
														'Use site policy',
														'medora-authority'
													) }
												</option>
											) }
										</select>
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				</section>
			) ) }

			<section className="medora-card">
				<h2>{ __( 'robots.txt preview', 'medora-authority' ) }</h2>
				<button
					type="button"
					className="button"
					onClick={ () => setShowRobots( ( value ) => ! value ) }
					aria-expanded={ showRobots }
				>
					{ showRobots
						? __( 'Hide', 'medora-authority' )
						: __( 'Show what is served', 'medora-authority' ) }
				</button>

				{ showRobots && (
					<pre className="medora-code">{ data?.robots.preview }</pre>
				) }
			</section>

			{ activity.data && activity.data.top_paths.length > 0 && (
				<section className="medora-card">
					<h2>{ __( 'Most-crawled URLs', 'medora-authority' ) }</h2>
					<p className="medora-muted">
						{ sprintf(
							/* translators: %d: number of days. */
							__(
								'What AI crawlers fetched most over the last %d days — the pages most likely to end up cited.',
								'medora-authority'
							),
							activity.data.window_days
						) }
					</p>
					<ul className="medora-list">
						{ activity.data.top_paths
							.slice( 0, 12 )
							.map( ( path ) => (
								<li key={ path.request_uri }>
									<span>{ path.request_uri }</span>
									<strong>{ path.hits }</strong>
								</li>
							) ) }
					</ul>
				</section>
			) }
		</div>
	);
}
