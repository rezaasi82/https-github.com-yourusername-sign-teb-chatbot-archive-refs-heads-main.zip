import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { GraphData } from '../types';

interface GraphCanvasProps {
	data: GraphData;
	height?: number;
	onSelect?: ( entityId: number ) => void;
}

interface Positioned {
	id: number;
	label: string;
	type: string;
	score: number;
	degree: number;
	x: number;
	y: number;
	vx: number;
	vy: number;
}

/**
 * Force-directed knowledge graph, rendered as SVG.
 *
 * Implemented directly rather than pulling in D3: the layout is ~60 lines of
 * Verlet-style integration, and a charting dependency would add several
 * hundred kilobytes to an admin bundle for one screen.
 *
 * The simulation runs for a fixed number of ticks inside a `requestAnimationFrame`
 * loop and then stops — an always-running physics loop in an admin tab is a
 * battery and CPU cost users notice, and the layout has converged long before
 * then anyway.
 */
export function GraphCanvas( {
	data,
	height = 520,
	onSelect,
}: GraphCanvasProps ): JSX.Element {
	const width = 900;
	const [ nodes, setNodes ] = useState< Positioned[] >( [] );
	const [ hovered, setHovered ] = useState< number | null >( null );
	const frameRef = useRef< number | null >( null );

	const links = useMemo(
		() =>
			data.links.filter(
				( link ) => link.source !== link.target
			),
		[ data.links ]
	);

	useEffect( () => {
		if ( data.nodes.length === 0 ) {
			setNodes( [] );
			return;
		}

		// Seed on a circle so the first tick has no degenerate zero-distance
		// pairs, which would produce infinite repulsion.
		const seeded: Positioned[] = data.nodes.map( ( node, index ) => {
			const angle = ( index / data.nodes.length ) * Math.PI * 2;
			const radius = Math.min( width, height ) * 0.35;

			return {
				id: node.id,
				label: node.label,
				type: node.type,
				score: node.score,
				degree: node.degree,
				x: width / 2 + Math.cos( angle ) * radius,
				y: height / 2 + Math.sin( angle ) * radius,
				vx: 0,
				vy: 0,
			};
		} );

		const index = new Map( seeded.map( ( node ) => [ node.id, node ] ) );
		let tick = 0;
		const maxTicks = 220;

		const step = () => {
			const alpha = 1 - tick / maxTicks;

			// Repulsion between every pair. O(n²), which is fine because the
			// API caps the exported node set at a few hundred.
			for ( let i = 0; i < seeded.length; i++ ) {
				const a = seeded[ i ]!;

				for ( let j = i + 1; j < seeded.length; j++ ) {
					const b = seeded[ j ]!;
					const dx = b.x - a.x;
					const dy = b.y - a.y;
					const distanceSq = dx * dx + dy * dy || 0.01;
					const distance = Math.sqrt( distanceSq );
					const force = ( 3200 * alpha ) / distanceSq;
					const fx = ( dx / distance ) * force;
					const fy = ( dy / distance ) * force;

					a.vx -= fx;
					a.vy -= fy;
					b.vx += fx;
					b.vy += fy;
				}

				// Gentle pull toward the centre keeps disconnected components
				// from drifting off-canvas.
				a.vx += ( width / 2 - a.x ) * 0.002 * alpha;
				a.vy += ( height / 2 - a.y ) * 0.002 * alpha;
			}

			// Attraction along edges, weighted by relationship strength.
			for ( const link of links ) {
				const a = index.get( link.source );
				const b = index.get( link.target );

				if ( ! a || ! b ) {
					continue;
				}

				const dx = b.x - a.x;
				const dy = b.y - a.y;
				const distance = Math.sqrt( dx * dx + dy * dy ) || 0.01;
				const force =
					( distance - 90 ) * 0.008 * alpha * ( 0.4 + link.weight );

				const fx = ( dx / distance ) * force;
				const fy = ( dy / distance ) * force;

				a.vx += fx;
				a.vy += fy;
				b.vx -= fx;
				b.vy -= fy;
			}

			for ( const node of seeded ) {
				node.vx *= 0.82;
				node.vy *= 0.82;
				node.x = Math.max( 30, Math.min( width - 30, node.x + node.vx ) );
				node.y = Math.max( 30, Math.min( height - 30, node.y + node.vy ) );
			}

			setNodes( seeded.map( ( node ) => ( { ...node } ) ) );

			tick += 1;

			if ( tick < maxTicks ) {
				frameRef.current = requestAnimationFrame( step );
			}
		};

		frameRef.current = requestAnimationFrame( step );

		return () => {
			if ( frameRef.current !== null ) {
				cancelAnimationFrame( frameRef.current );
			}
		};
	}, [ data.nodes, links, height ] );

	if ( data.nodes.length === 0 ) {
		return (
			<p className="medora-empty">
				{ __(
					'No entities yet. Publish content and run an analysis to build the graph.',
					'medora-authority'
				) }
			</p>
		);
	}

	const positions = new Map( nodes.map( ( node ) => [ node.id, node ] ) );

	return (
		<div className="medora-graph">
			<svg
				viewBox={ `0 0 ${ width } ${ height }` }
				className="medora-graph__canvas"
				role="img"
				aria-label={ __( 'Knowledge graph', 'medora-authority' ) }
			>
				<g className="medora-graph__links">
					{ links.map( ( link, i ) => {
						const a = positions.get( link.source );
						const b = positions.get( link.target );

						if ( ! a || ! b ) {
							return null;
						}

						const isActive =
							hovered === link.source || hovered === link.target;

						return (
							<line
								key={ `${ link.source }-${ link.target }-${ i }` }
								x1={ a.x }
								y1={ a.y }
								x2={ b.x }
								y2={ b.y }
								strokeWidth={ 0.5 + link.weight * 2 }
								className={
									isActive
										? 'medora-graph__link is-active'
										: 'medora-graph__link'
								}
							/>
						);
					} ) }
				</g>

				<g className="medora-graph__nodes">
					{ nodes.map( ( node ) => {
						// Radius encodes connectedness, fill encodes authority.
						const radius = 6 + Math.min( 16, node.degree * 1.5 );

						return (
							<g
								key={ node.id }
								transform={ `translate(${ node.x }, ${ node.y })` }
								onMouseEnter={ () => setHovered( node.id ) }
								onMouseLeave={ () => setHovered( null ) }
								onClick={ () => onSelect?.( node.id ) }
								onKeyDown={ ( event ) => {
									if ( event.key === 'Enter' ) {
										onSelect?.( node.id );
									}
								} }
								tabIndex={ 0 }
								role="button"
								aria-label={ `${ node.label } — ${ node.score.toFixed(
									0
								) }/100` }
								className="medora-graph__node"
							>
								<circle
									r={ radius }
									style={ {
										fill: `color-mix(in srgb, var(--medora-accent) ${ Math.max(
											18,
											node.score
										) }%, var(--medora-surface-2))`,
									} }
								/>
								{ ( hovered === node.id || node.degree > 4 ) && (
									<text
										y={ radius + 13 }
										textAnchor="middle"
										className="medora-graph__label"
									>
										{ node.label.length > 22
											? `${ node.label.slice( 0, 21 ) }…`
											: node.label }
									</text>
								) }
							</g>
						);
					} ) }
				</g>
			</svg>

			<p className="medora-graph__legend">
				{ __(
					'Circle size = number of relationships. Fill = authority score. Click a node to open it.',
					'medora-authority'
				) }
			</p>
		</div>
	);
}
