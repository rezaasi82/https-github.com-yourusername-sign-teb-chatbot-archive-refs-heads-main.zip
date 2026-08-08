import { __ } from '@wordpress/i18n';
import { formatScore, gradeFor } from '../utils/format';
import type { Grade } from '../types';

interface ScoreRingProps {
	score: number;
	grade?: Grade;
	label?: string;
	size?: number;
}

const GRADE_TOKENS: Record< Grade, string > = {
	A: 'var(--medora-grade-a)',
	B: 'var(--medora-grade-b)',
	C: 'var(--medora-grade-c)',
	D: 'var(--medora-grade-d)',
	F: 'var(--medora-grade-f)',
};

/**
 * The headline score dial.
 *
 * Drawn as an SVG arc rather than a canvas or a chart library: it scales
 * cleanly at any DPI, needs no runtime dependency, and inherits the theme's
 * colour tokens so light, dark and white-label accents all work without a
 * second code path.
 */
export function ScoreRing( {
	score,
	grade,
	label,
	size = 132,
}: ScoreRingProps ): JSX.Element {
	const resolved = grade ?? gradeFor( score );
	const stroke = 10;
	const radius = ( size - stroke ) / 2;
	const circumference = 2 * Math.PI * radius;
	const clamped = Math.max( 0, Math.min( 100, score ) );
	const offset = circumference - ( clamped / 100 ) * circumference;

	return (
		<div className="medora-score-ring" style={ { width: size } }>
			<svg
				width={ size }
				height={ size }
				viewBox={ `0 0 ${ size } ${ size }` }
				role="img"
				aria-label={ `${
					label ?? __( 'AI Authority Score', 'medora-authority' )
				}: ${ clamped.toFixed( 1 ) } / 100` }
			>
				<circle
					cx={ size / 2 }
					cy={ size / 2 }
					r={ radius }
					fill="none"
					stroke="var(--medora-track)"
					strokeWidth={ stroke }
				/>
				<circle
					cx={ size / 2 }
					cy={ size / 2 }
					r={ radius }
					fill="none"
					stroke={ GRADE_TOKENS[ resolved ] }
					strokeWidth={ stroke }
					strokeLinecap="round"
					strokeDasharray={ circumference }
					strokeDashoffset={ offset }
					/* Start the arc at twelve o'clock. */
					transform={ `rotate(-90 ${ size / 2 } ${ size / 2 })` }
					className="medora-score-ring__value"
				/>
			</svg>

			<div className="medora-score-ring__center" aria-hidden="true">
				<strong>{ formatScore( clamped ) }</strong>
				<span
					className={ `medora-grade medora-grade--${ resolved.toLowerCase() }` }
				>
					{ resolved }
				</span>
			</div>

			{ label && <p className="medora-score-ring__label">{ label }</p> }
		</div>
	);
}
