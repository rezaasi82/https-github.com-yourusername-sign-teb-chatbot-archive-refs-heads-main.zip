import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { api, boot } from '../api/client';
import { useAsync } from '../hooks/useAsync';
import type { LinkReport, LinkSuggestion } from '../types';

interface LinkPanelProps {
	postId: number;
}

/**
 * Internal link suggestions, with one-at-a-time application.
 *
 * The UI mirrors the server's constraints rather than hiding them: a
 * suggestion with no usable anchor cannot be applied and says why, and there is
 * no bulk action — applying links is an editorial decision per link, and a
 * button that rewrites twelve published pages at once is not a feature anyone
 * should be offered.
 */
export function LinkPanel( { postId }: LinkPanelProps ): JSX.Element {
	const { data, loading, error, reload } = useAsync< LinkReport >(
		() => api.links( postId ),
		[ postId ]
	);

	const [ busy, setBusy ] = useState< number | null >( null );
	const [ message, setMessage ] = useState( '' );
	const [ failure, setFailure ] = useState( '' );
	const [ anchorChoice, setAnchorChoice ] = useState<
		Record< number, string >
	>( {} );

	const canEdit = boot.capabilities.analyze;

	const applyLink = async ( suggestion: LinkSuggestion ) => {
		const anchor =
			anchorChoice[ suggestion.target_id ] ?? suggestion.anchors[ 0 ];

		if ( ! anchor ) {
			return;
		}

		setBusy( suggestion.target_id );
		setMessage( '' );
		setFailure( '' );

		try {
			await api.applyLink( postId, suggestion.target_id, anchor );

			setMessage(
				sprintf(
					/* translators: 1: anchor text, 2: target page title. */
					__( 'Linked "%1$s" to %2$s.', 'medora-authority' ),
					anchor,
					suggestion.title
				)
			);

			reload();
		} catch ( applyError ) {
			setFailure( ( applyError as Error ).message );
		} finally {
			setBusy( null );
		}
	};

	const revert = async () => {
		setBusy( -1 );
		setMessage( '' );
		setFailure( '' );

		try {
			const result = await api.revertLinks( postId );

			setMessage(
				sprintf(
					/* translators: %d: number of links removed. */
					__(
						'Removed %d link(s) added by Medora.',
						'medora-authority'
					),
					result.reverted
				)
			);

			reload();
		} catch ( revertError ) {
			setFailure( ( revertError as Error ).message );
		} finally {
			setBusy( null );
		}
	};

	if ( loading && ! data ) {
		return (
			<p className="medora-loading">
				{ __( 'Finding related pages…', 'medora-authority' ) }
			</p>
		);
	}

	if ( error ) {
		return <p className="medora-error">{ error.message }</p>;
	}

	if ( ! data ) {
		return <></>;
	}

	return (
		<div className="medora-links">
			<header className="medora-brief__head">
				<div>
					<h3>{ __( 'Internal links', 'medora-authority' ) }</h3>
					<p className="medora-muted">
						{ __(
							'Suggested from semantic similarity and shared entities. Anchors are phrases already in your text — nothing is inserted.',
							'medora-authority'
						) }
					</p>
				</div>

				{ data.applied > 0 && canEdit && (
					<button
						type="button"
						className="button"
						disabled={ busy !== null }
						onClick={ revert }
					>
						{ sprintf(
							/* translators: %d: number of links. */
							__( 'Undo %d Medora link(s)', 'medora-authority' ),
							data.applied
						) }
					</button>
				) }
			</header>

			{ message && <div className="medora-notice">{ message }</div> }
			{ failure && <p className="medora-error">{ failure }</p> }

			<h4>
				{ __( 'Pages this one should link to', 'medora-authority' ) }
			</h4>

			{ data.outbound.length === 0 && (
				<p className="medora-empty">
					{ __(
						'No related pages found yet. Publish more on this subject, or check the Vector Engine is running.',
						'medora-authority'
					) }
				</p>
			) }

			<ul className="medora-suggestions">
				{ data.outbound.map( ( suggestion ) => {
					const hasAnchor = suggestion.anchors.length > 0;

					return (
						<li
							key={ suggestion.target_id }
							className={
								suggestion.already_linked
									? 'medora-suggestion is-linked'
									: 'medora-suggestion'
							}
						>
							<div className="medora-suggestion__body">
								<a href={ suggestion.url }>
									<strong>{ suggestion.title }</strong>
								</a>
								<span className="medora-pill">
									{ suggestion.score.toFixed( 2 ) }
								</span>

								{ suggestion.already_linked && (
									<span className="medora-pill medora-pill--ok">
										{ __(
											'already linked',
											'medora-authority'
										) }
									</span>
								) }

								<p className="medora-muted">
									{ suggestion.reason }
								</p>
							</div>

							{ ! suggestion.already_linked && canEdit && (
								<div className="medora-suggestion__action">
									{ hasAnchor ? (
										<>
											{ suggestion.anchors.length > 1 && (
												<select
													aria-label={ __(
														'Anchor text',
														'medora-authority'
													) }
													value={
														anchorChoice[
															suggestion.target_id
														] ??
														suggestion.anchors[ 0 ]
													}
													onChange={ ( event ) =>
														setAnchorChoice(
															( previous ) => ( {
																...previous,
																[ suggestion.target_id ]:
																	event.target
																		.value,
															} )
														)
													}
												>
													{ suggestion.anchors.map(
														( anchor ) => (
															<option
																key={ anchor }
																value={ anchor }
															>
																{ anchor }
															</option>
														)
													) }
												</select>
											) }

											<button
												type="button"
												className="button button-primary"
												disabled={
													busy ===
													suggestion.target_id
												}
												onClick={ () =>
													applyLink( suggestion )
												}
											>
												{ busy === suggestion.target_id
													? __(
															'Linking…',
															'medora-authority'
													  )
													: __(
															'Add link',
															'medora-authority'
													  ) }
											</button>
										</>
									) : (
										<span className="medora-muted">
											{ __(
												'No shared phrase in this page to use as an anchor.',
												'medora-authority'
											) }
										</span>
									) }
								</div>
							) }
						</li>
					);
				} ) }
			</ul>

			{ data.inbound.length > 0 && (
				<>
					<h4>
						{ __(
							'Pages that should link here',
							'medora-authority'
						) }
					</h4>
					<p className="medora-muted">
						{ __(
							'Inbound links are what actually move authority toward a page. Open each and add the link there.',
							'medora-authority'
						) }
					</p>
					<ul className="medora-list medora-list--linked">
						{ data.inbound.map( ( source ) => (
							<li key={ source.source_id }>
								<a
									href={ `post.php?post=${ source.source_id }&action=edit` }
								>
									{ source.title }
								</a>
								<strong>{ source.score.toFixed( 2 ) }</strong>
							</li>
						) ) }
					</ul>
				</>
			) }
		</div>
	);
}
