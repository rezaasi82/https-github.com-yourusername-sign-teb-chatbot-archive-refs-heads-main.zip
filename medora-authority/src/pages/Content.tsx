import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { api, shows } from '../api/client';
import { useAsync } from '../hooks/useAsync';
import { ScoreRing } from '../components/ScoreRing';
import { DeductionList } from '../components/DeductionList';
import { BriefPanel } from '../components/BriefPanel';
import { LinkPanel } from '../components/LinkPanel';
import { PassagePanel } from '../components/PassagePanel';
import type { Deduction, SiteReport } from '../types';

type PanelTab = 'fixes' | 'brief' | 'links' | 'passages';

/**
 * Tab ids double as experience-mode feature names, so a mode that hides
 * "passages" hides both the menu entry and this tab without a second list to
 * keep in step.
 */
const TABS: ReadonlyArray< readonly [ PanelTab, string ] > = [
	[ 'fixes', __( 'Fixes', 'medora-authority' ) ],
	[ 'brief', __( 'Brief', 'medora-authority' ) ],
	[ 'links', __( 'Links', 'medora-authority' ) ],
	[ 'passages', __( 'Passages', 'medora-authority' ) ],
];

interface Recommendations {
	post_id: number;
	score: number;
	grade: 'A' | 'B' | 'C' | 'D' | 'F';
	potential_score: number;
	actions: Array<
		Deduction & { title: string; effort: number; impact_ratio: number }
	>;
	missing_concepts: string[];
	answer_first: {
		needs_rewrite: boolean;
		current_opening: string;
		guidance: string;
	};
}

export function Content(): JSX.Element {
	const [ postId, setPostId ] = useState< number | null >( null );
	const [ tab, setTab ] = useState< PanelTab >( 'fixes' );

	const report = useAsync< SiteReport >( () => api.siteScore(), [] );
	const detail = useAsync< Recommendations | null >(
		() =>
			postId === null
				? Promise.resolve( null )
				: ( api.recommendations( postId ) as Promise< Recommendations > ),
		[ postId ]
	);

	return (
		<div className="medora-page medora-page--split">
			<div>
				<header className="medora-page__head">
					<h1>{ __( 'Content', 'medora-authority' ) }</h1>
					<p className="medora-muted">
						{ __(
							'Lowest-scoring pages first. Pick one to see its ordered fix list.',
							'medora-authority'
						) }
					</p>
				</header>

				{ report.error && (
					<p className="medora-error">{ report.error.message }</p>
				) }

				<table className="medora-table">
					<thead>
						<tr>
							<th>{ __( 'Page', 'medora-authority' ) }</th>
							<th>{ __( 'Score', 'medora-authority' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ ( report.data?.weakest ?? [] ).map( ( page ) => (
							<tr
								key={ page.object_id }
								onClick={ () => setPostId( page.object_id ) }
								className={
									postId === page.object_id ? 'is-selected' : ''
								}
							>
								<td>
									<strong>{ page.title }</strong>
									<p className="medora-muted">{ page.url }</p>
								</td>
								<td>
									<span
										className={ `medora-grade medora-grade--${
											page.score >= 65 ? 'c' : 'f'
										}` }
									>
										{ page.score.toFixed( 0 ) }
									</span>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			</div>

			{ postId !== null && (
				<aside className="medora-panel">
					<header className="medora-panel__head">
						{ detail.data && (
							<ScoreRing
								score={ detail.data.score }
								grade={ detail.data.grade }
								size={ 96 }
							/>
						) }
						<button
							type="button"
							className="button-link"
							onClick={ () => setPostId( null ) }
						>
							{ __( 'Close', 'medora-authority' ) }
						</button>
					</header>

					<nav
						className="medora-subtabs"
						aria-label={ __( 'Page tools', 'medora-authority' ) }
					>
						{ TABS.filter( ( [ id ] ) => shows( id ) ).map(
							( [ id, label ] ) => (
								<button
									key={ id }
									type="button"
									className={ tab === id ? 'is-active' : '' }
									aria-current={ tab === id ? 'true' : undefined }
									onClick={ () => setTab( id ) }
								>
									{ label }
								</button>
							)
						) }
					</nav>

					{ tab === 'fixes' && detail.data && (
						<>
							<p className="medora-muted">
								{ sprintf(
									/* translators: %s: potential score. */
									__(
										'Completing every action below would take this page to roughly %s / 100.',
										'medora-authority'
									),
									detail.data.potential_score.toFixed( 0 )
								) }
							</p>

							{ detail.data.answer_first.needs_rewrite && (
								<div className="medora-notice medora-notice--warning">
									<strong>
										{ __(
											'Answer-first rewrite',
											'medora-authority'
										) }
									</strong>
									<p>{ detail.data.answer_first.guidance }</p>
								</div>
							) }

							<h3>
								{ __(
									'Do these, in this order',
									'medora-authority'
								) }
							</h3>
							<p className="medora-muted">
								{ __(
									'Ordered by points recovered per unit of effort, not by severity alone.',
									'medora-authority'
								) }
							</p>

							<DeductionList
								deductions={ detail.data.actions.map(
									( action ) => ( {
										...action,
										label: action.title,
									} )
								) }
							/>
						</>
					) }

					{ tab === 'brief' && <BriefPanel postId={ postId } /> }
					{ tab === 'links' && <LinkPanel postId={ postId } /> }
					{ tab === 'passages' && <PassagePanel postId={ postId } /> }

					{ tab === 'fixes' && detail.loading && ! detail.data && (
						<p className="medora-loading">
							{ __( 'Loading…', 'medora-authority' ) }
						</p>
					) }
				</aside>
			) }
		</div>
	);
}
