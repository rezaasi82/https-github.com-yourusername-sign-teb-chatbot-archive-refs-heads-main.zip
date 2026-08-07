import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { api, shows } from '../api/client';
import { useAsync } from '../hooks/useAsync';
import { ScoreRing } from '../components/ScoreRing';
import { StatCard } from '../components/StatCard';
import { Sparkline } from '../components/Sparkline';
import { collapseSeries } from '../utils/format';
import type { Overview as OverviewData } from '../types';

export function Overview(): JSX.Element {
	const [ days, setDays ] = useState( 30 );
	const { data, loading, error, reload } = useAsync< OverviewData >(
		() => api.overview( days ),
		[ days ]
	);

	if ( loading && ! data ) {
		return <p className="medora-loading">{ __( 'Loading…', 'medora-authority' ) }</p>;
	}

	if ( error ) {
		return (
			<div className="medora-error">
				<p>{ error.message }</p>
				<button type="button" className="button" onClick={ reload }>
					{ __( 'Retry', 'medora-authority' ) }
				</button>
			</div>
		);
	}

	if ( ! data ) {
		return <p />;
	}

	const authority = data.authority;
	const referrals = data.referrals;
	const crawlers = data.crawlers;

	return (
		<div className="medora-page">
			<header className="medora-page__head">
				<div>
					<h1>{ __( 'AI Authority', 'medora-authority' ) }</h1>
					<p className="medora-muted">{ data.site.name }</p>
				</div>

				<select
					value={ days }
					onChange={ ( event ) =>
						setDays( Number( event.target.value ) )
					}
					aria-label={ __( 'Reporting window', 'medora-authority' ) }
				>
					<option value={ 7 }>{ __( 'Last 7 days', 'medora-authority' ) }</option>
					<option value={ 30 }>{ __( 'Last 30 days', 'medora-authority' ) }</option>
					<option value={ 90 }>{ __( 'Last 90 days', 'medora-authority' ) }</option>
				</select>
			</header>

			<section className="medora-hero">
				<ScoreRing
					score={ authority?.average ?? 0 }
					grade={ authority?.grade }
					label={ __( 'Site AI Authority Score', 'medora-authority' ) }
					size={ 152 }
				/>

				<div className="medora-hero__stats">
					<StatCard
						label={ __( 'Pages analysed', 'medora-authority' ) }
						value={ authority?.analyzed ?? 0 }
					/>
					<StatCard
						label={ __( 'Entities', 'medora-authority' ) }
						value={ data.entities?.total ?? 0 }
						hint={ sprintf(
							/* translators: %d: number of relationships. */
							__( '%d relationships', 'medora-authority' ),
							data.graph?.relations ?? 0
						) }
					/>
					<StatCard
						label={ __( 'AI crawler visits', 'medora-authority' ) }
						value={ crawlers?.total_hits ?? 0 }
						hint={
							crawlers
								? sprintf(
										/* translators: 1: crawlers seen, 2: crawlers known. */
										__( '%1$d of %2$d known crawlers', 'medora-authority' ),
										crawlers.coverage.seen,
										crawlers.coverage.known
								  )
								: undefined
						}
					/>
					<StatCard
						label={ __( 'Visits from assistants', 'medora-authority' ) }
						value={ referrals?.total_visits ?? 0 }
						trend={ referrals?.change_percent ?? null }
						tone={
							( referrals?.change_percent ?? 0 ) < 0
								? 'warning'
								: 'positive'
						}
					/>
				</div>
			</section>

			{ data.queue.failed > 0 && (
				<div className="medora-notice medora-notice--warning">
					{ sprintf(
						/* translators: %d: number of failed jobs. */
						__(
							'%d background job(s) failed. Check the site health log — analysis may be incomplete.',
							'medora-authority'
						),
						data.queue.failed
					) }
				</div>
			) }

			{ shows( 'queue_health' ) && <QueueHealth queue={ data.queue } /> }

			<div className="medora-grid medora-grid--2">
				{ referrals && (
					<section className="medora-card">
						<h2>{ __( 'AI referral traffic', 'medora-authority' ) }</h2>
						<Sparkline
							series={ collapseSeries( referrals.series ) }
							label={ __( 'AI referrals', 'medora-authority' ) }
						/>
						<ul className="medora-list">
							{ referrals.sources.slice( 0, 6 ).map( ( source ) => (
								<li key={ source.source_slug }>
									<span>{ source.label }</span>
									<strong>{ source.visits }</strong>
								</li>
							) ) }
							{ referrals.sources.length === 0 && (
								<li className="medora-empty">
									{ __(
										'No assistant referrals observed yet. Note that several assistants strip the referrer, so this is a floor, not a total.',
										'medora-authority'
									) }
								</li>
							) }
						</ul>
					</section>
				) }

				{ crawlers && (
					<section className="medora-card">
						<h2>{ __( 'AI crawler activity', 'medora-authority' ) }</h2>
						<Sparkline
							series={ collapseSeries( crawlers.series ) }
							label={ __( 'Crawler hits', 'medora-authority' ) }
						/>
						<ul className="medora-list">
							{ crawlers.crawlers.slice( 0, 6 ).map( ( crawler ) => (
								<li key={ crawler.slug }>
									<span>
										{ crawler.name }
										<em className="medora-muted"> · { crawler.vendor }</em>
									</span>
									<strong>{ crawler.hits }</strong>
								</li>
							) ) }
						</ul>

						{ crawlers.missing.length > 0 && (
							<p className="medora-muted">
								{ sprintf(
									/* translators: %s: comma-separated crawler names. */
									__(
										'Allowed but never seen: %s. Usually a robots, DNS or firewall issue rather than disinterest.',
										'medora-authority'
									),
									crawlers.missing
										.slice( 0, 4 )
										.map( ( c ) => c.name )
										.join( ', ' )
								) }
							</p>
						) }
					</section>
				) }
			</div>

			{ authority && (
				<div className="medora-grid medora-grid--2">
					<section className="medora-card">
						<h2>{ __( 'Biggest wins available', 'medora-authority' ) }</h2>
						<p className="medora-muted">
							{ __(
								'The issues that recur most across your site — one fix each, applied broadly.',
								'medora-authority'
							) }
						</p>
						<ul className="medora-issues">
							{ authority.top_issues.map( ( issue ) => (
								<li key={ issue.code }>
									<div>
										<strong>{ issue.label }</strong>
										<span className="medora-pill">
											{ sprintf(
												/* translators: %d: number of pages. */
												__( '%d pages', 'medora-authority' ),
												issue.count
											) }
										</span>
									</div>
									<p>{ issue.recommendation }</p>
								</li>
							) ) }
						</ul>
					</section>

					<section className="medora-card">
						<h2>{ __( 'Weakest pages', 'medora-authority' ) }</h2>
						<ul className="medora-list medora-list--linked">
							{ authority.weakest.map( ( page ) => (
								<li key={ page.object_id }>
									<a href={ page.url }>{ page.title }</a>
									<strong>{ page.score.toFixed( 0 ) }</strong>
								</li>
							) ) }
						</ul>
					</section>
				</div>
			) }
		</div>
	);
}


/**
 * Background work, broken down by queue.
 *
 * The totals alone are ambiguous: 400 pending jobs is a backlog, 400 pending
 * jobs all sitting in the `llm` queue is a model provider that stopped
 * answering. The server has reported the split since 0.4.0 and nothing showed
 * it, so the two diagnoses looked identical.
 */
function QueueHealth( {
	queue,
}: {
	queue: import('../types').Overview[ 'queue' ];
} ): JSX.Element {
	const rows = Object.entries( queue.by_queue ?? {} );

	if ( rows.length === 0 ) {
		return <></>;
	}

	return (
		<section className="medora-card">
			<h2>{ __( 'Background work', 'medora-authority' ) }</h2>

			<table className="medora-table">
				<thead>
					<tr>
						<th>{ __( 'Queue', 'medora-authority' ) }</th>
						<th>{ __( 'Waiting', 'medora-authority' ) }</th>
						<th>{ __( 'Running', 'medora-authority' ) }</th>
						<th>{ __( 'Failed', 'medora-authority' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ rows.map( ( [ name, counts ] ) => (
						<tr key={ name }>
							<td>{ name }</td>
							<td>{ counts.pending }</td>
							<td>{ counts.running }</td>
							<td
								className={
									counts.failed > 0 ? 'medora-error' : undefined
								}
							>
								{ counts.failed }
							</td>
						</tr>
					) ) }
				</tbody>
			</table>
		</section>
	);
}
