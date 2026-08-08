import { useMemo } from '@wordpress/element';

interface SparklineProps {
	series: Array< { day: string; value: number } >;
	height?: number;
	label: string;
}

/**
 * A minimal time-series chart.
 *
 * Deliberately unlabelled on the axes: at dashboard-tile size, axis text is
 * unreadable and the number that matters is already shown in the stat tile
 * above it. The shape is the information here.
 */
export function Sparkline( {
	series,
	height = 64,
	label,
}: SparklineProps ): JSX.Element {
	const width = 320;

	const { path, area, max } = useMemo( () => {
		if ( series.length === 0 ) {
			return { path: '', area: '', max: 0 };
		}

		const values = series.map( ( point ) => point.value );
		const maximum = Math.max( ...values, 1 );
		const step = series.length > 1 ? width / ( series.length - 1 ) : width;

		const points = series.map( ( point, index ) => {
			const x = index * step;
			// Inset by 4px top and bottom so the stroke is never clipped.
			const y = height - 4 - ( point.value / maximum ) * ( height - 8 );

			return `${ x.toFixed( 1 ) },${ y.toFixed( 1 ) }`;
		} );

		return {
			path: `M ${ points.join( ' L ' ) }`,
			area: `M 0,${ height } L ${ points.join(
				' L '
			) } L ${ width },${ height } Z`,
			max: maximum,
		};
	}, [ series, height ] );

	if ( series.length === 0 ) {
		return <div className="medora-sparkline medora-sparkline--empty" />;
	}

	return (
		<svg
			className="medora-sparkline"
			viewBox={ `0 0 ${ width } ${ height }` }
			preserveAspectRatio="none"
			role="img"
			aria-label={ `${ label } — peak ${ max }` }
		>
			<path d={ area } className="medora-sparkline__area" />
			<path d={ path } className="medora-sparkline__line" fill="none" />
		</svg>
	);
}
