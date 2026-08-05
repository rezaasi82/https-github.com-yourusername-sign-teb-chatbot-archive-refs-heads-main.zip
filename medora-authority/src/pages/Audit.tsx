import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { api } from '../api/client';
import { useAsync } from '../hooks/useAsync';
import type { AuditEntry, AuditReport } from '../types';

const PER_PAGE = 50;

/**
 * What the plugin has done to this site, and when.
 *
 * The log existed and was written to from the first release; nothing read it.
 * That matters most for the AI Writer: every generated sentence and every
 * rejected one is recorded, and "which words here did a model write?" is
 * exactly the question a clinical reviewer asks. An answer sitting in a table
 * with no reader is not an answer.
 *
 * Entries are rendered as sentences rather than as a slug plus a JSON blob,
 * because the person who needs this is a compliance reviewer, not a developer.
 */
export function Audit(): JSX.Element {
	const [ page, setPage ] = useState( 1 );

	const { data, loading, error } = useAsync< AuditReport >(
		() => api.auditLog( page, PER_PAGE ),
		[ page ]
	);

	const pages = data ? Math.max( 1, Math.ceil( data.total / PER_PAGE ) ) : 1;

	return (
		<div className="medora-page">
			<header className="medora-page__head">
				<h1>{ __( 'Audit log', 'medora-authority' ) }</h1>
				<p className="medora-muted">
					{ __(
						'Every change Medora made, and every generated sentence it accepted or threw away. Kept for the retention period set in Settings.',
						'medora-authority'
					) }
				</p>
			</header>

			{ error && <p className="medora-error">{ error.message }</p> }

			{ loading && ! data && (
				<p className="medora-loading">
					{ __( 'Loading…', 'medora-authority' ) }
				</p>
			) }

			{ data && data.items.length === 0 && (
				<p className="medora-empty">
					{ __( 'Nothing recorded yet.', 'medora-authority' ) }
				</p>
			) }

			{ data && data.items.length > 0 && (
				<>
					<table className="medora-table medora-table--audit">
						<thead>
							<tr>
								<th>{ __( 'When', 'medora-authority' ) }</th>
								<th>{ __( 'Who', 'medora-authority' ) }</th>
								<th>{ __( 'What happened', 'medora-authority' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ data.items.map( ( entry ) => (
								<Row key={ entry.id } entry={ entry } />
							) ) }
						</tbody>
					</table>

					{ pages > 1 && (
						<nav className="medora-actions" aria-label={ __( 'Pages', 'medora-authority' ) }>
							<button
								type="button"
								className="button"
								disabled={ page <= 1 }
								onClick={ () => setPage( ( p ) => p - 1 ) }
							>
								{ __( 'Newer', 'medora-authority' ) }
							</button>

							<span className="medora-muted">
								{ sprintf(
									/* translators: 1: current page, 2: total pages. */
									__( 'Page %1$d of %2$d', 'medora-authority' ),
									page,
									pages
								) }
							</span>

							<button
								type="button"
								className="button"
								disabled={ page >= pages }
								onClick={ () => setPage( ( p ) => p + 1 ) }
							>
								{ __( 'Older', 'medora-authority' ) }
							</button>
						</nav>
					) }
				</>
			) }
		</div>
	);
}

function Row( { entry }: { entry: AuditEntry } ): JSX.Element {
	const { label, tone } = describe( entry );

	return (
		<tr>
			<td>
				<time dateTime={ entry.created_at }>
					{ formatTime( entry.created_at ) }
				</time>
			</td>
			<td>{ entry.user_name }</td>
			<td>
				<span
					className={
						tone
							? `medora-pill medora-pill--${ tone }`
							: 'medora-pill'
					}
				>
					{ entry.action }
				</span>{ ' ' }
				{ label }
				<Detail entry={ entry } />
			</td>
		</tr>
	);
}

/**
 * The one-line human reading of an entry.
 *
 * Unknown actions fall through to the raw slug rather than being hidden: a
 * third-party module recording its own events should still appear in the log,
 * and an audit log that silently omits entries is worse than a slightly ugly
 * one.
 */
function describe( entry: AuditEntry ): { label: string; tone: string } {
	const target =
		entry.object_id > 0
			? sprintf(
					/* translators: 1: object type, 2: object id. */
					__( '%1$s #%2$d', 'medora-authority' ),
					entry.object_type,
					entry.object_id
			  )
			: '';

	switch ( entry.action ) {
		case 'llm.generated':
			return {
				tone: 'ok',
				label: sprintf(
					/* translators: 1: field list, 2: object. */
					__( 'A model rewrote %1$s on %2$s.', 'medora-authority' ),
					( ( entry.context.fields as string[] ) ?? [] ).join( ', ' ) ||
						__( 'nothing', 'medora-authority' ),
					target
				),
			};

		case 'llm.rejected':
			return {
				tone: 'warn',
				label: sprintf(
					/* translators: 1: field name, 2: object. */
					__(
						'Generated %1$s for %2$s was discarded — the page did not support it.',
						'medora-authority'
					),
					String( entry.context.field ?? '' ),
					target
				),
			};

		case 'llm.refused':
			return {
				tone: 'warn',
				label: sprintf(
					/* translators: %s: object. */
					__( 'The model declined to write for %s.', 'medora-authority' ),
					target
				),
			};

		case 'llm.failed':
			return {
				tone: 'error',
				label: sprintf(
					/* translators: %s: object. */
					__( 'The model request failed for %s.', 'medora-authority' ),
					target
				),
			};

		case 'llm.unusable':
			return {
				tone: 'warn',
				label: sprintf(
					/* translators: %s: object. */
					__(
						'The model reply could not be used for %s.',
						'medora-authority'
					),
					target
				),
			};

		case 'link.applied':
			return {
				tone: 'ok',
				label: sprintf(
					/* translators: %s: object. */
					__( 'An internal link was added to %s.', 'medora-authority' ),
					target
				),
			};

		case 'link.reverted':
			return {
				tone: 'ok',
				label: sprintf(
					/* translators: %s: object. */
					__( 'Medora links were removed from %s.', 'medora-authority' ),
					target
				),
			};

		case 'settings.updated':
			return { tone: '', label: __( 'Settings were saved.', 'medora-authority' ) };

		case 'module.toggled':
			return { tone: '', label: __( 'A module was turned on or off.', 'medora-authority' ) };

		case 'crawler.policy_changed':
			return { tone: '', label: __( 'The AI crawler policy changed.', 'medora-authority' ) };

		case 'license.status_changed':
			return { tone: '', label: __( 'The licence status changed.', 'medora-authority' ) };

		default:
			return { tone: '', label: target };
	}
}

/**
 * The parts of the context worth showing inline.
 *
 * Deliberately not a JSON dump: context is assembled from request payloads and
 * is redacted server-side, but a raw dump would still put arbitrary stored text
 * in front of the reader with no shape to it.
 */
function Detail( { entry }: { entry: AuditEntry } ): JSX.Element {
	const bits: string[] = [];

	if ( typeof entry.context.model === 'string' ) {
		bits.push( entry.context.model );
	}

	if ( typeof entry.context.support_ratio === 'number' ) {
		bits.push(
			sprintf(
				/* translators: %s: percentage of the text supported by the page. */
				__( '%s%% supported', 'medora-authority' ),
				Math.round( entry.context.support_ratio * 100 ).toString()
			)
		);
	}

	const invented = entry.context.invented_numbers;

	if ( Array.isArray( invented ) && invented.length > 0 ) {
		bits.push(
			sprintf(
				/* translators: %s: comma-separated list of figures. */
				__( 'figures not in the page: %s', 'medora-authority' ),
				invented.join( ', ' )
			)
		);
	}

	if ( typeof entry.context.error === 'string' ) {
		bits.push( entry.context.error );
	}

	if ( bits.length === 0 ) {
		return <></>;
	}

	return <p className="medora-muted">{ bits.join( ' · ' ) }</p>;
}

function formatTime( value: string ): string {
	// Stored UTC without a zone marker; without the Z the browser reads it as
	// local and every entry is off by the site's offset.
	const date = new Date( value.replace( ' ', 'T' ) + 'Z' );

	return Number.isNaN( date.getTime() ) ? value : date.toLocaleString();
}
