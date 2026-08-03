import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { api } from '../api/client';
import { useAsync } from '../hooks/useAsync';
import type { Brief } from '../types';

interface BriefPanelProps {
	postId: number;
}

/**
 * The writing brief.
 *
 * Ordered the way the work is actually done: what the opening must achieve,
 * then the outline, then supporting material. The checklist sits at the top
 * because it is the only part that answers "am I finished?".
 */
export function BriefPanel( { postId }: BriefPanelProps ): JSX.Element {
	const { data, loading, error } = useAsync< Brief >(
		() => api.brief( postId ),
		[ postId ]
	);

	const [ copied, setCopied ] = useState( false );
	const [ copyError, setCopyError ] = useState( '' );

	const copyMarkdown = async () => {
		setCopyError( '' );

		try {
			const result = await api.briefMarkdown( postId );

			// The clipboard API needs a secure context and, in some browsers,
			// a permission the admin does not have. Failing loudly beats a
			// button that silently does nothing.
			await navigator.clipboard.writeText( result.markdown );

			setCopied( true );
			window.setTimeout( () => setCopied( false ), 2500 );
		} catch {
			setCopyError(
				__(
					'Could not copy. Open the brief endpoint directly to grab the Markdown.',
					'medora-authority'
				)
			);
		}
	};

	if ( loading && ! data ) {
		return (
			<p className="medora-loading">
				{ __( 'Building brief…', 'medora-authority' ) }
			</p>
		);
	}

	if ( error ) {
		return <p className="medora-error">{ error.message }</p>;
	}

	if ( ! data ) {
		return <></>;
	}

	const outstanding = data.checklist.filter( ( item ) => ! item.done );
	const unanswered = data.questions.filter(
		( question ) => question.status === 'unanswered'
	);

	return (
		<div className="medora-brief">
			<header className="medora-brief__head">
				<div>
					<h3>{ __( 'Writing brief', 'medora-authority' ) }</h3>
					<p className="medora-muted">
						{ sprintf(
							/* translators: 1: current score, 2: achievable score. */
							__(
								'%1$s now → about %2$s if everything below is done.',
								'medora-authority'
							),
							data.score.toFixed( 0 ),
							data.potential_score.toFixed( 0 )
						) }
					</p>
				</div>

				<button
					type="button"
					className="button"
					onClick={ copyMarkdown }
				>
					{ copied
						? __( 'Copied', 'medora-authority' )
						: __( 'Copy as Markdown', 'medora-authority' ) }
				</button>
			</header>

			{ copyError && <p className="medora-error">{ copyError }</p> }

			<section>
				<h4>
					{ outstanding.length === 0
						? __( 'Nothing outstanding', 'medora-authority' )
						: sprintf(
								/* translators: %d: number of outstanding tasks. */
								__( '%d thing(s) left', 'medora-authority' ),
								outstanding.length
						  ) }
				</h4>

				<ul className="medora-checklist">
					{ data.checklist.map( ( item ) => (
						<li
							key={ item.task }
							className={
								item.done
									? 'medora-checklist__item is-done'
									: 'medora-checklist__item'
							}
						>
							<span aria-hidden="true">
								{ item.done ? '✓' : '○' }
							</span>
							{ item.task }
						</li>
					) ) }
				</ul>
			</section>

			<section>
				<h4>{ __( 'Opening', 'medora-authority' ) }</h4>
				<p
					className={
						data.opening.needs_rewrite
							? 'medora-notice medora-notice--warning'
							: 'medora-muted'
					}
				>
					{ data.opening.spec }
				</p>

				{ data.opening.current && (
					<blockquote className="medora-quote">
						{ data.opening.current }
					</blockquote>
				) }
			</section>

			<section>
				<h4>
					{ sprintf(
						/* translators: 1: current word count, 2: target. */
						__( 'Outline — %1$d words now, ~%2$d target', 'medora-authority' ),
						data.word_count.current,
						data.word_count.target
					) }
				</h4>

				<ol className="medora-outline">
					{ data.sections.map( ( section, index ) => (
						<li
							key={ `${ section.heading }-${ index }` }
							className={ `medora-outline__item is-${ section.status }` }
						>
							<strong>{ section.heading }</strong>

							{ section.status === 'missing' && (
								<span className="medora-pill medora-pill--warn">
									{ __( 'add', 'medora-authority' ) }
								</span>
							) }

							{ section.why && (
								<p className="medora-muted">{ section.why }</p>
							) }

							{ section.cover.length > 0 && (
								<ul className="medora-hints">
									{ section.cover.map( ( hint ) => (
										<li key={ hint }>{ hint }</li>
									) ) }
								</ul>
							) }
						</li>
					) ) }
				</ol>
			</section>

			{ unanswered.length > 0 && (
				<section>
					<h4>{ __( 'Questions not yet answered', 'medora-authority' ) }</h4>
					<ul className="medora-list">
						{ unanswered.map( ( question ) => (
							<li key={ question.question }>
								<span>{ question.question }</span>
							</li>
						) ) }
					</ul>
				</section>
			) }

			{ data.entities.add.length > 0 && (
				<section>
					<h4>{ __( 'Entities to introduce', 'medora-authority' ) }</h4>
					<p className="medora-muted">
						{ __(
							'Drawn from your own knowledge graph — concepts this subject connects to elsewhere on the site.',
							'medora-authority'
						) }
					</p>
					<ul className="medora-list">
						{ data.entities.add.map( ( entity ) => (
							<li key={ entity.name }>
								<span>
									<strong>{ entity.name }</strong>
									<em className="medora-muted">
										{ ' ' }
										· { entity.type } — { entity.why }
									</em>
								</span>
							</li>
						) ) }
					</ul>
				</section>
			) }

			<section>
				<h4>{ __( 'Evidence', 'medora-authority' ) }</h4>
				<p
					className={
						data.evidence.current < data.evidence.target
							? 'medora-notice medora-notice--warning'
							: 'medora-muted'
					}
				>
					{ sprintf(
						/* translators: 1: citations attached, 2: target. */
						__( '%1$d of %2$d sources attached. ', 'medora-authority' ),
						data.evidence.current,
						data.evidence.target
					) }
					{ data.evidence.note }
				</p>
			</section>
		</div>
	);
}
