import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { api } from '../api/client';
import { useAsync } from '../hooks/useAsync';
import { GraphCanvas } from '../components/GraphCanvas';
import type { EntityDetail } from '../types';

export function Graph(): JSX.Element {
	const [ limit, setLimit ] = useState( 150 );
	const [ selected, setSelected ] = useState< number | null >( null );

	const { data, loading, error } = useAsync( () => api.graph( limit ), [ limit ] );
	const detail = useAsync< EntityDetail | null >(
		() => ( selected === null ? Promise.resolve( null ) : api.entity( selected ) ),
		[ selected ]
	);

	return (
		<div className="medora-page">
			<header className="medora-page__head">
				<div>
					<h1>{ __( 'Knowledge Graph', 'medora-authority' ) }</h1>
					{ data && (
						<p className="medora-muted">
							{ sprintf(
								/* translators: 1: entity count, 2: relationship count. */
								__(
									'%1$d entities, %2$d relationships across the site.',
									'medora-authority'
								),
								data.stats.entities,
								data.stats.relations
							) }
						</p>
					) }
				</div>

				<select
					value={ limit }
					onChange={ ( event ) => setLimit( Number( event.target.value ) ) }
					aria-label={ __( 'Nodes to display', 'medora-authority' ) }
				>
					<option value={ 60 }>{ __( 'Top 60 entities', 'medora-authority' ) }</option>
					<option value={ 150 }>{ __( 'Top 150 entities', 'medora-authority' ) }</option>
					<option value={ 300 }>{ __( 'Top 300 entities', 'medora-authority' ) }</option>
				</select>
			</header>

			{ error && <p className="medora-error">{ error.message }</p> }
			{ loading && ! data && (
				<p className="medora-loading">{ __( 'Building layout…', 'medora-authority' ) }</p>
			) }

			{ data && (
				<section className="medora-card">
					<GraphCanvas data={ data } onSelect={ setSelected } />
				</section>
			) }

			{ detail.data && (
				<section className="medora-card">
					<h2>{ detail.data.name }</h2>
					<p className="medora-muted">
						{ detail.data.type_label }
						{ detail.data.description
							? ` — ${ detail.data.description }`
							: '' }
					</p>

					{ detail.data.permalink && (
						<p>
							<a href={ detail.data.permalink }>
								{ __( 'Open entity page', 'medora-authority' ) }
							</a>
						</p>
					) }

					<ul className="medora-list">
						{ ( detail.data.relations ?? [] )
							.slice( 0, 15 )
							.map( ( relation, index ) => (
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

			<section className="medora-card">
				<h2>{ __( 'Machine-readable endpoints', 'medora-authority' ) }</h2>
				<p className="medora-muted">
					{ __(
						'These are what AI systems consume. They are public and require no key.',
						'medora-authority'
					) }
				</p>
				<ul className="medora-list medora-list--linked">
					<li>
						<a href="/llms.txt">/llms.txt</a>
						<span>{ __( 'Curated site map for LLMs', 'medora-authority' ) }</span>
					</li>
					<li>
						<a href="/llms-full.txt">/llms-full.txt</a>
						<span>{ __( 'Full content bundle', 'medora-authority' ) }</span>
					</li>
					<li>
						<a href="/medora-sitemap.xml">/medora-sitemap.xml</a>
						<span>{ __( 'Annotated AI sitemap index', 'medora-authority' ) }</span>
					</li>
					<li>
						<a href="/wp-json/medora/v1/graph">/wp-json/medora/v1/graph</a>
						<span>{ __( 'Knowledge graph as JSON-LD', 'medora-authority' ) }</span>
					</li>
				</ul>
			</section>
		</div>
	);
}
