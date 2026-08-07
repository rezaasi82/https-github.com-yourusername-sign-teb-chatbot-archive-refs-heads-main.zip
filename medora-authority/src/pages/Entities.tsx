import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { api, boot } from '../api/client';
import { useAsync } from '../hooks/useAsync';
import { percentOf } from '../utils/format';
import type { EntityDetail, EntitySummary } from '../types';

function EntityPanel( {
	id,
	onClose,
	onSaved,
	onDeleted,
}: {
	id: number;
	onClose: () => void;
	onSaved: () => void;
	onDeleted: () => void;
} ): JSX.Element {
	const { data, loading, error } = useAsync< EntityDetail >(
		() => api.entity( id ),
		[ id ]
	);

	const [ sameAs, setSameAs ] = useState< string | null >( null );
	const [ description, setDescription ] = useState< string | null >( null );
	const [ saving, setSaving ] = useState( false );
	const [ message, setMessage ] = useState( '' );
	const [ confirming, setConfirming ] = useState( false );

	if ( loading && ! data ) {
		return <aside className="medora-panel">{ __( 'Loading…', 'medora-authority' ) }</aside>;
	}

	if ( error || ! data ) {
		return (
			<aside className="medora-panel">
				<p className="medora-error">{ error?.message }</p>
			</aside>
		);
	}

	const save = async () => {
		setSaving( true );
		setMessage( '' );

		try {
			await api.updateEntity( data.id, {
				description: description ?? data.description,
				same_as: ( sameAs ?? data.same_as.join( '\n' ) )
					.split( /\s*\n\s*/ )
					.map( ( line ) => line.trim() )
					.filter( Boolean ),
			} );

			setMessage( __( 'Saved.', 'medora-authority' ) );
			onSaved();
		} catch ( saveError ) {
			setMessage( ( saveError as Error ).message );
		} finally {
			setSaving( false );
		}
	};

	const remove = async () => {
		setSaving( true );
		setMessage( '' );

		try {
			await api.deleteEntity( data.id );

			onDeleted();
		} catch ( deleteError ) {
			setMessage( ( deleteError as Error ).message );
			setSaving( false );
			setConfirming( false );
		}
	};

	return (
		<aside className="medora-panel">
			<header className="medora-panel__head">
				<div>
					<h2>{ data.name }</h2>
					<span className="medora-pill">{ data.type_label }</span>
				</div>
				<button type="button" className="button-link" onClick={ onClose }>
					{ __( 'Close', 'medora-authority' ) }
				</button>
			</header>

			{ data.authority && (
				<section>
					<h3>
						{ sprintf(
							/* translators: %s: score out of 100. */
							__( 'Entity authority: %s / 100', 'medora-authority' ),
							data.authority.score.toFixed( 1 )
						) }
					</h3>
					<ul className="medora-breakdown">
						{ data.authority.components.map( ( component ) => (
							<li key={ component.id }>
								<div className="medora-breakdown__head">
									<span>{ component.label }</span>
									<strong>
										{ component.points } / { component.max }
									</strong>
								</div>
								<div
									className="medora-meter"
									role="presentation"
								>
									<span
										style={ {
											inlineSize: `${ percentOf(
												component.points,
												component.max
											) }%`,
										} }
									/>
								</div>
								<p className="medora-muted">{ component.note }</p>
							</li>
						) ) }
					</ul>
				</section>
			) }

			{ boot.capabilities.entities && (
				<section>
					<h3>{ __( 'Edit', 'medora-authority' ) }</h3>

					<label htmlFor="medora-entity-description">
						{ __( 'Description', 'medora-authority' ) }
					</label>
					<textarea
						id="medora-entity-description"
						rows={ 3 }
						value={ description ?? data.description }
						onChange={ ( event ) => setDescription( event.target.value ) }
					/>

					<label htmlFor="medora-entity-sameas">
						{ __( 'sameAs identifiers (one per line)', 'medora-authority' ) }
					</label>
					<textarea
						id="medora-entity-sameas"
						rows={ 3 }
						placeholder="https://www.wikidata.org/wiki/Q…"
						value={ sameAs ?? data.same_as.join( '\n' ) }
						onChange={ ( event ) => setSameAs( event.target.value ) }
					/>
					<p className="medora-muted">
						{ __(
							'These are what let an AI system match this entity to the one in its own knowledge base. It is the single highest-value field here.',
							'medora-authority'
						) }
					</p>

					<button
						type="button"
						className="button button-primary"
						onClick={ save }
						disabled={ saving }
					>
						{ saving
							? __( 'Saving…', 'medora-authority' )
							: __( 'Save entity', 'medora-authority' ) }
					</button>

					{ message && <p className="medora-muted">{ message }</p> }
				</section>
			) }

			{ boot.capabilities.entities && (
				<section className="medora-danger">
					<h3>{ __( 'Remove this entity', 'medora-authority' ) }</h3>
					<p className="medora-muted">
						{ __(
							'Use this for something the extractor got wrong — a navigation label or a stray phrase read as a subject. It is deleted from the graph and from the structured data you publish, and it will not be extracted again.',
							'medora-authority'
						) }
					</p>

					{ confirming ? (
						<div className="medora-actions">
							<button
								type="button"
								className="button button-primary"
								disabled={ saving }
								onClick={ remove }
							>
								{ __( 'Yes, remove it', 'medora-authority' ) }
							</button>
							<button
								type="button"
								className="button"
								disabled={ saving }
								onClick={ () => setConfirming( false ) }
							>
								{ __( 'Cancel', 'medora-authority' ) }
							</button>
						</div>
					) : (
						<button
							type="button"
							className="button"
							onClick={ () => setConfirming( true ) }
						>
							{ __( 'Remove entity', 'medora-authority' ) }
						</button>
					) }
				</section>
			) }

			{ data.relations && data.relations.length > 0 && (
				<section>
					<h3>{ __( 'Relationships', 'medora-authority' ) }</h3>
					<ul className="medora-list">
						{ data.relations.slice( 0, 20 ).map( ( relation, index ) => (
							<li key={ `${ relation.entity.id }-${ index }` }>
								<span>
									<em className="medora-muted">
										{ relation.predicate_label }
									</em>{ ' ' }
									{ relation.entity.name }
								</span>
								<strong>{ relation.weight.toFixed( 2 ) }</strong>
							</li>
						) ) }
					</ul>
				</section>
			) }
		</aside>
	);
}

export function Entities(): JSX.Element {
	const [ search, setSearch ] = useState( '' );
	const [ type, setType ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const [ selected, setSelected ] = useState< number | null >( null );

	const {
		data: suppressed,
		reload: reloadSuppressed,
	} = useAsync< import('../types').SuppressionReport >(
		() =>
			boot.capabilities.entities
				? api.suppressedEntities()
				: Promise.resolve( { items: [], limit: 0 } ),
		[]
	);

	const { data, loading, error, reload } = useAsync(
		() =>
			api.entities( {
				search,
				type,
				page,
				per_page: 40,
				orderby: 'authority',
			} ),
		[ search, type, page ]
	);

	return (
		<div className="medora-page medora-page--split">
			<div>
				<header className="medora-page__head">
					<h1>{ __( 'Entities', 'medora-authority' ) }</h1>
					<div className="medora-filters">
						<input
							type="search"
							placeholder={ __( 'Search entities…', 'medora-authority' ) }
							value={ search }
							onChange={ ( event ) => {
								setSearch( event.target.value );
								setPage( 1 );
							} }
						/>
						<select
							value={ type }
							onChange={ ( event ) => {
								setType( event.target.value );
								setPage( 1 );
							} }
							aria-label={ __( 'Entity type', 'medora-authority' ) }
						>
							<option value="">
								{ __( 'All types', 'medora-authority' ) }
							</option>
							<option value="Person">Person</option>
							<option value="Organization">Organization</option>
							<option value="Physician">Physician</option>
							<option value="MedicalCondition">MedicalCondition</option>
							<option value="MedicalProcedure">MedicalProcedure</option>
							<option value="DefinedTerm">Topic</option>
							<option value="Place">Place</option>
							<option value="Product">Product</option>
						</select>
					</div>
				</header>

				{ error && <p className="medora-error">{ error.message }</p> }

				<table className="medora-table">
					<thead>
						<tr>
							<th>{ __( 'Entity', 'medora-authority' ) }</th>
							<th>{ __( 'Type', 'medora-authority' ) }</th>
							<th>{ __( 'Authority', 'medora-authority' ) }</th>
							<th>{ __( 'sameAs', 'medora-authority' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ ( data?.items ?? [] ).map( ( entity: EntitySummary ) => (
							<tr
								key={ entity.id }
								onClick={ () => setSelected( entity.id ) }
								className={
									selected === entity.id ? 'is-selected' : ''
								}
							>
								<td>
									<strong>{ entity.name }</strong>
									{ entity.description && (
										<p className="medora-muted">
											{ entity.description.slice( 0, 90 ) }
										</p>
									) }
								</td>
								<td>{ entity.type_label }</td>
								<td>
									<div className="medora-meter" role="presentation">
										<span
											style={ {
												inlineSize: `${ entity.authority_score }%`,
											} }
										/>
									</div>
									<small>{ entity.authority_score.toFixed( 0 ) }</small>
								</td>
								<td>
									{ entity.same_as.length > 0 ? (
										<span className="medora-pill medora-pill--ok">
											{ entity.same_as.length }
										</span>
									) : (
										<span className="medora-pill medora-pill--warn">
											{ __( 'none', 'medora-authority' ) }
										</span>
									) }
								</td>
							</tr>
						) ) }
					</tbody>
				</table>

				{ loading && <p className="medora-loading">{ __( 'Loading…', 'medora-authority' ) }</p> }

				{ data && data.total > 40 && (
					<nav className="medora-pagination">
						<button
							type="button"
							className="button"
							disabled={ page <= 1 }
							onClick={ () => setPage( ( p ) => p - 1 ) }
						>
							{ __( 'Previous', 'medora-authority' ) }
						</button>
						<span>
							{ sprintf(
								/* translators: 1: current page, 2: total entities. */
								__( 'Page %1$d — %2$d entities', 'medora-authority' ),
								page,
								data.total
							) }
						</span>
						<button
							type="button"
							className="button"
							disabled={ page * 40 >= data.total }
							onClick={ () => setPage( ( p ) => p + 1 ) }
						>
							{ __( 'Next', 'medora-authority' ) }
						</button>
					</nav>
				) }
			</div>

			{ suppressed && suppressed.items.length > 0 && (
				<RemovedEntities
					report={ suppressed }
					onChanged={ () => {
						reloadSuppressed();
						reload();
					} }
				/>
			) }

			{ selected !== null && (
				<EntityPanel
					id={ selected }
					onClose={ () => setSelected( null ) }
					onSaved={ reload }
					onDeleted={ () => {
						setSelected( null );
						reload();
						reloadSuppressed();
					} }
				/>
			) }
		</div>
	);
}


/**
 * Entities the publisher removed, and the way back.
 *
 * Removal has to be reversible and inspectable, because it is not only a
 * deletion: it also stops the entity being extracted again, which is a standing
 * instruction rather than a one-off act. A standing instruction nobody can see
 * or undo is how a knowledge graph quietly loses a subject and nobody works out
 * why.
 */
function RemovedEntities( {
	report,
	onChanged,
}: {
	report: import('../types').SuppressionReport;
	onChanged: () => void;
} ): JSX.Element {
	const [ busy, setBusy ] = useState( false );

	const restore = async ( uid?: string ) => {
		setBusy( true );

		try {
			await api.restoreEntity( uid );
			onChanged();
		} finally {
			setBusy( false );
		}
	};

	return (
		<section className="medora-card">
			<h2>
				{ sprintf(
					/* translators: 1: number removed, 2: maximum. */
					__( 'Removed entities — %1$d of %2$d', 'medora-authority' ),
					report.items.length,
					report.limit
				) }
			</h2>
			<p className="medora-muted">
				{ __(
					'These are skipped during extraction. Restoring one brings it back the next time the page it came from is analysed.',
					'medora-authority'
				) }
			</p>

			<ul className="medora-list">
				{ report.items.map( ( item ) => (
					<li key={ item.uid }>
						<span>
							<strong>{ item.name }</strong>
							<em className="medora-muted"> · { item.type }</em>
						</span>
						<button
							type="button"
							className="button-link"
							disabled={ busy }
							onClick={ () => restore( item.uid ) }
						>
							{ __( 'Restore', 'medora-authority' ) }
						</button>
					</li>
				) ) }
			</ul>

			<div className="medora-actions">
				<button
					type="button"
					className="button"
					disabled={ busy }
					onClick={ () => restore() }
				>
					{ __( 'Restore all', 'medora-authority' ) }
				</button>
			</div>
		</section>
	);
}
