import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { api } from '../api/client';
import { useAsync } from '../hooks/useAsync';
import { Sparkline } from '../components/Sparkline';
import { StatCard } from '../components/StatCard';

export function Analytics(): JSX.Element {
	const [ days, setDays ] = useState( 30 );
	const { data, loading, error } = useAsync( () => api.analytics( days ), [ days ] );

	if ( error ) {
		return <p className="medora-error">{ error.message }</p>;
	}

	if ( loading && ! data ) {
		return <p className="medora-loading">{ __( 'Loading…', 'medora-authority' ) }</p>;
	}

	const byDay = new Map< string, number >();

	for ( const point of data?.series ?? [] ) {
		byDay.set( point.day, ( byDay.get( point.day ) ?? 0 ) + point.visits );
	}

	const series = [ ...byDay.entries() ]
		.sort( ( a, b ) => a[ 0 ].localeCompare( b[ 0 ] ) )
		.map( ( [ day, value ] ) => ( { day, value } ) );

	return (
		<div className="medora-page">
			<header className="medora-page__head">
				<h1>{ __( 'AI Analytics', 'medora-authority' ) }</h1>
				<select
					value={ days }
					onChange={ ( event ) => setDays( Number( event.target.value ) ) }
					aria-label={ __( 'Reporting window', 'medora-authority' ) }
				>
					<option value={ 7 }>{ __( 'Last 7 days', 'medora-authority' ) }</option>
					<option value={ 30 }>{ __( 'Last 30 days', 'medora-authority' ) }</option>
					<option value={ 90 }>{ __( 'Last 90 days', 'medora-authority' ) }</option>
				</select>
			</header>

			<div className="medora-notice">
				{ __(
					'These are observed referrals. Several assistants strip the referrer header, so treat this as a lower bound on your real AI traffic, not a total.',
					'medora-authority'
				) }
			</div>

			<div className="medora-hero__stats">
				<StatCard
					label={ __( 'Visits from assistants', 'medora-authority' ) }
					value={ data?.total_visits ?? 0 }
					trend={ data?.change_percent ?? null }
				/>
				<StatCard
					label={ __( 'Previous period', 'medora-authority' ) }
					value={ data?.previous_visits ?? 0 }
				/>
				<StatCard
					label={ __( 'Distinct sources', 'medora-authority' ) }
					value={ data?.sources.length ?? 0 }
				/>
			</div>

			<section className="medora-card">
				<h2>{ __( 'Trend', 'medora-authority' ) }</h2>
				<Sparkline
					series={ series }
					height={ 110 }
					label={ __( 'AI referrals', 'medora-authority' ) }
				/>
			</section>

			<div className="medora-grid medora-grid--2">
				<section className="medora-card">
					<h2>{ __( 'By assistant', 'medora-authority' ) }</h2>
					<table className="medora-table">
						<thead>
							<tr>
								<th>{ __( 'Source', 'medora-authority' ) }</th>
								<th>{ __( 'Visits', 'medora-authority' ) }</th>
								<th>{ __( 'Visitors', 'medora-authority' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ ( data?.sources ?? [] ).map( ( source ) => (
								<tr key={ source.source_slug }>
									<td>{ source.label }</td>
									<td>{ source.visits }</td>
									<td>{ source.visitors }</td>
								</tr>
							) ) }
						</tbody>
					</table>
				</section>

				<section className="medora-card">
					<h2>{ __( 'Pages assistants send people to', 'medora-authority' ) }</h2>
					<p className="medora-muted">
						{ __(
							'The closest available proxy for which of your pages are being cited.',
							'medora-authority'
						) }
					</p>
					<ul className="medora-list medora-list--linked">
						{ ( data?.top_pages ?? [] ).map( ( page ) => (
							<li key={ `${ page.object_id }-${ page.url }` }>
								<a href={ page.url }>{ page.title }</a>
								<strong>{ page.visits }</strong>
							</li>
						) ) }
						{ ( data?.top_pages.length ?? 0 ) === 0 && (
							<li className="medora-empty">
								{ sprintf(
									/* translators: %d: number of days. */
									__(
										'No assistant referrals in the last %d days.',
										'medora-authority'
									),
									days
								) }
							</li>
						) }
					</ul>
				</section>
			</div>
		</div>
	);
}
