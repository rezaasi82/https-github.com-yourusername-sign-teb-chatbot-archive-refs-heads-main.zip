import { __, sprintf } from '@wordpress/i18n';
import type { Deduction, Severity } from '../types';

interface DeductionListProps {
	deductions: Deduction[];
	limit?: number;
	emptyMessage?: string;
}

const SEVERITY_LABELS: Record< Severity, string > = {
	critical: __( 'Critical', 'medora-authority' ),
	high: __( 'High', 'medora-authority' ),
	medium: __( 'Medium', 'medora-authority' ),
	low: __( 'Low', 'medora-authority' ),
};

/**
 * Renders the "why" behind a score.
 *
 * Every row shows the points lost and the fix. That pairing is the product
 * promise — a score with no derivation is a vanity metric, and a
 * recommendation with no cost attached gives no basis for prioritising.
 */
export function DeductionList( {
	deductions,
	limit,
	emptyMessage,
}: DeductionListProps ): JSX.Element {
	if ( deductions.length === 0 ) {
		return (
			<p className="medora-empty">
				{ emptyMessage ??
					__( 'No issues found on this page.', 'medora-authority' ) }
			</p>
		);
	}

	const rows = limit ? deductions.slice( 0, limit ) : deductions;

	return (
		<ul className="medora-deductions">
			{ rows.map( ( deduction ) => (
				<li
					key={ `${ deduction.component ?? '' }-${ deduction.code }` }
					className={ `medora-deduction medora-deduction--${ deduction.severity }` }
				>
					<div className="medora-deduction__head">
						<span
							className={ `medora-badge medora-badge--${ deduction.severity }` }
						>
							{ SEVERITY_LABELS[ deduction.severity ] }
						</span>
						<strong>{ deduction.label }</strong>
						<span className="medora-deduction__points">
							{ sprintf(
								/* translators: %s: number of points. */
								__( '−%s pts', 'medora-authority' ),
								deduction.points.toFixed( 1 )
							) }
						</span>
					</div>
					<p className="medora-deduction__fix">
						{ deduction.recommendation }
					</p>
				</li>
			) ) }

			{ limit && deductions.length > limit && (
				<li className="medora-deduction medora-deduction--more">
					{ sprintf(
						/* translators: %d: number of remaining issues. */
						__( '%d more issue(s).', 'medora-authority' ),
						deductions.length - limit
					) }
				</li>
			) }
		</ul>
	);
}
