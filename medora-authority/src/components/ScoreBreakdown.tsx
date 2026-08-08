import { __, sprintf } from '@wordpress/i18n';
import { api, shows } from '../api/client';
import { useAsync } from '../hooks/useAsync';
import { formatScore, percentOf } from '../utils/format';
import type { PostScore, ScoreComponent } from '../types';

interface ScoreBreakdownProps {
	postId: number;
}

/**
 * Why the page scored what it scored.
 *
 * The Fixes tab answers "what do I do next" and is ordered by return on
 * effort, which deliberately scrambles the dimensions. This answers the other
 * question — "why is it 62?" — and so is ordered by how much each dimension is
 * costing, with every deduction sitting under the dimension it came from.
 *
 * The server has always returned this. Nothing rendered it, which left the
 * product's central claim — that every deduction is explained — only half
 * delivered: the deductions were listed, but not what they were deductions
 * *from*.
 */
export function ScoreBreakdown( { postId }: ScoreBreakdownProps ): JSX.Element {
	const { data, loading, error } = useAsync< PostScore >(
		() => api.postScore( postId ),
		[ postId ]
	);

	if ( loading && ! data ) {
		return (
			<p className="medora-loading">
				{ __( 'Loading…', 'medora-authority' ) }
			</p>
		);
	}

	if ( error ) {
		return <p className="medora-error">{ error.message }</p>;
	}

	if ( ! data ) {
		return <></>;
	}

	// Weights are re-normalised server-side when a dimension does not apply, so
	// the shares shown here have to be recomputed against what actually ran
	// rather than against the declared weights.
	const totalWeight = data.components.reduce(
		( sum, component ) => sum + component.weight,
		0
	);

	// Worst contribution first: the dimension costing the most points is the
	// one worth reading about.
	const ordered = [ ...data.components ].sort(
		( a, b ) => ( 100 - a.score ) * a.weight - ( 100 - b.score ) * b.weight
	);

	return (
		<div className="medora-breakdown">
			<h3>{ __( 'Where the score comes from', 'medora-authority' ) }</h3>
			<p className="medora-muted">
				{ __(
					'Each dimension starts at 100 and loses points. Dimensions that do not apply to this site are left out, and the rest are re-weighted to fill the gap.',
					'medora-authority'
				) }
			</p>

			<ul className="medora-dimensions">
				{ ordered.map( ( component ) => (
					<Dimension
						key={ component.id }
						component={ component }
						share={
							totalWeight > 0 ? component.weight / totalWeight : 0
						}
					/>
				) ) }
			</ul>
		</div>
	);
}

function Dimension( {
	component,
	share,
}: {
	component: ScoreComponent;
	share: number;
} ): JSX.Element {
	const cost = ( 100 - component.score ) * share;

	return (
		<li className="medora-dimension">
			<div className="medora-dimension__head">
				<strong>{ component.label }</strong>

				<span
					className={ `medora-grade medora-grade--${ component.grade.toLowerCase() }` }
				>
					{ formatScore( component.score ) }
				</span>

				<span className="medora-muted">
					{ sprintf(
						/* translators: 1: share of the overall score, 2: points this dimension is costing. */
						__(
							'%1$d%% of the score · costing %2$s points',
							'medora-authority'
						),
						Math.round( share * 100 ),
						cost.toFixed( 1 )
					) }
				</span>
			</div>

			<div
				className="medora-meter"
				role="img"
				aria-label={ sprintf(
					/* translators: 1: dimension name, 2: score out of 100. */
					__( '%1$s scored %2$s out of 100', 'medora-authority' ),
					component.label,
					formatScore( component.score )
				) }
			>
				<span
					className={ `medora-meter__fill medora-meter__fill--${ component.grade.toLowerCase() }` }
					style={ {
						inlineSize: `${ percentOf( component.score, 100 ) }%`,
					} }
				/>
			</div>

			{ component.deductions.length === 0 ? (
				<p className="medora-muted">
					{ __( 'Nothing deducted here.', 'medora-authority' ) }
				</p>
			) : (
				<ul className="medora-hints">
					{ component.deductions.map( ( deduction ) => (
						<li key={ deduction.code }>
							<strong>
								{ sprintf(
									/* translators: 1: points lost, 2: what was deducted. */
									__( '−%1$s %2$s', 'medora-authority' ),
									deduction.points.toFixed( 0 ),
									deduction.label
								) }
							</strong>
							<p className="medora-muted">
								{ deduction.recommendation }
							</p>
						</li>
					) ) }
				</ul>
			) }

			{ shows( 'raw_metrics' ) && (
				<Metrics metrics={ component.metrics } />
			) }
		</li>
	);
}

/**
 * The numbers a dimension was computed from.
 *
 * Agency mode and above only. These are the scorer's own working — useful when
 * arguing with a score, meaningless as a to-do list, which is why they are not
 * in front of everyone.
 */
function Metrics( {
	metrics,
}: {
	metrics: Record< string, unknown >;
} ): JSX.Element {
	const rows = Object.entries( metrics );

	if ( rows.length === 0 ) {
		return <></>;
	}

	return (
		<details className="medora-metrics">
			<summary>{ __( 'Measurements', 'medora-authority' ) }</summary>
			<dl>
				{ rows.map( ( [ key, value ] ) => (
					<div key={ key }>
						<dt>{ key.replace( /_/g, ' ' ) }</dt>
						<dd>{ renderValue( value ) }</dd>
					</div>
				) ) }
			</dl>
		</details>
	);
}

function renderValue( value: unknown ): string {
	if ( typeof value === 'boolean' ) {
		return value
			? __( 'yes', 'medora-authority' )
			: __( 'no', 'medora-authority' );
	}

	if ( Array.isArray( value ) ) {
		return value.length > 0
			? value.join( ', ' )
			: __( 'none', 'medora-authority' );
	}

	if ( value === null || value === undefined ) {
		return '—';
	}

	return String( value );
}
