import type { ReactNode } from 'react';

interface StatCardProps {
	label: string;
	value: ReactNode;
	hint?: string;
	trend?: number | null;
	tone?: 'default' | 'positive' | 'warning' | 'critical';
}

/**
 * A single KPI tile.
 *
 * `trend` is nullable on purpose: the analytics layer returns null rather than
 * a percentage when there is no prior period to compare against, and rendering
 * "+100%" from a base of zero would be a lie the UI tells on the API's behalf.
 */
export function StatCard( {
	label,
	value,
	hint,
	trend,
	tone = 'default',
}: StatCardProps ): JSX.Element {
	return (
		<div className={ `medora-stat medora-stat--${ tone }` }>
			<span className="medora-stat__label">{ label }</span>
			<strong className="medora-stat__value">{ value }</strong>

			{ typeof trend === 'number' && (
				<span
					className={ `medora-stat__trend medora-stat__trend--${
						trend >= 0 ? 'up' : 'down'
					}` }
				>
					{ trend >= 0 ? '▲' : '▼' }{ ' ' }
					{ Math.abs( trend ).toFixed( 1 ) }%
				</span>
			) }

			{ hint && <span className="medora-stat__hint">{ hint }</span> }
		</div>
	);
}
