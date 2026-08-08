import { __, sprintf } from '@wordpress/i18n';
import { api } from '../api/client';
import { gradeFor } from '../utils/format';
import { useAsync } from '../hooks/useAsync';
import type { GeoReport, Passage } from '../types';

interface PassagePanelProps {
	postId: number;
}

/**
 * The page as a retriever sees it: a list of passages, each judged alone.
 *
 * Passages with problems come first, because a clean passage needs nothing
 * from the reader. Each one shows the excerpt it was judged on — without that
 * the verdict is unarguable, and a lint the author cannot argue with is a lint
 * they cannot trust.
 */
export function PassagePanel( { postId }: PassagePanelProps ): JSX.Element {
	const { data, loading, error } = useAsync< GeoReport >(
		() => api.geo( postId ),
		[ postId ]
	);

	if ( loading && ! data ) {
		return (
			<p className="medora-loading">
				{ __( 'Reading passages…', 'medora-authority' ) }
			</p>
		);
	}

	if ( error ) {
		return <p className="medora-error">{ error.message }</p>;
	}

	if ( ! data ) {
		return <></>;
	}

	if ( data.total === 0 ) {
		return (
			<p className="medora-empty">
				{ __(
					'Nothing here to retrieve. This page has no body copy a model could be handed.',
					'medora-authority'
				) }
			</p>
		);
	}

	// Worst first, and within a score, in document order so the reader works
	// down the page rather than jumping around it.
	const ordered = [ ...data.passages ].sort(
		( a, b ) => a.score - b.score || a.index - b.index
	);

	const percent = Math.round( data.ratio * 100 );

	return (
		<div className="medora-passages">
			<header className="medora-brief__head">
				<div>
					<h3>{ __( 'Passages', 'medora-authority' ) }</h3>
					<p className="medora-muted">
						{ sprintf(
							/* translators: 1: self-contained passages, 2: total, 3: percentage. */
							__(
								'%1$d of %2$d passages (%3$d%%) stand on their own when retrieved without the rest of the page.',
								'medora-authority'
							),
							data.clean,
							data.total,
							percent
						) }
					</p>
				</div>

				<span
					className={ `medora-grade medora-grade--${ gradeFor(
						percent
					).toLowerCase() }` }
				>
					{ percent }%
				</span>
			</header>

			<Structure structure={ data.structure } />

			<ol className="medora-passage-list">
				{ ordered.map( ( passage ) => (
					<PassageRow key={ passage.index } passage={ passage } />
				) ) }
			</ol>
		</div>
	);
}

function PassageRow( { passage }: { passage: Passage } ): JSX.Element {
	return (
		<li
			className={
				passage.issues.length === 0
					? 'medora-passage is-clean'
					: 'medora-passage'
			}
		>
			<div className="medora-passage__head">
				<strong>
					{ sprintf(
						/* translators: %d: passage number. */
						__( 'Passage %d', 'medora-authority' ),
						passage.index + 1
					) }
				</strong>

				<span className="medora-muted">
					{ passage.heading !== ''
						? passage.heading
						: __( 'no heading', 'medora-authority' ) }
				</span>

				<span className="medora-pill">
					{ sprintf(
						/* translators: %d: word count. */
						__( '%d words', 'medora-authority' ),
						passage.words
					) }
				</span>
			</div>

			<blockquote className="medora-quote">
				{ passage.excerpt }
			</blockquote>

			{ passage.issues.length === 0 ? (
				<p className="medora-muted">
					{ __( 'Reads on its own.', 'medora-authority' ) }
				</p>
			) : (
				<ul className="medora-hints">
					{ passage.issues.map( ( issue ) => (
						<li key={ issue.code }>
							<strong>{ issue.label }</strong> — { issue.fix }
						</li>
					) ) }
				</ul>
			) }
		</li>
	);
}

function Structure( {
	structure,
}: {
	structure: GeoReport[ 'structure' ];
} ): JSX.Element {
	const notes: string[] = [];

	if ( structure.is_long_form && structure.surfaces === 0 ) {
		notes.push(
			__(
				'Long page with no table, procedure or list. Structure gets quoted intact; prose gets paraphrased.',
				'medora-authority'
			)
		);
	}

	if ( structure.headings === 0 ) {
		notes.push(
			__(
				'No H2 or H3 headings, so passage boundaries fall mid-argument.',
				'medora-authority'
			)
		);
	} else if ( structure.question_headings === 0 ) {
		notes.push(
			__(
				'No heading is phrased as a question. A question heading makes the passage under it a direct answer.',
				'medora-authority'
			)
		);
	}

	if ( notes.length === 0 ) {
		return <></>;
	}

	return (
		<div className="medora-notice medora-notice--warning">
			<ul className="medora-hints">
				{ notes.map( ( note ) => (
					<li key={ note }>{ note }</li>
				) ) }
			</ul>
		</div>
	);
}
