import type { Grade } from '../types';

/**
 * Pure helpers shared by the dashboard views.
 *
 * These live outside the components so they can be tested without rendering,
 * and so the grade thresholds exist in exactly one place on the client — they
 * must agree with `AuthorityScoreCalculator::gradeFor()` on the server, and two
 * copies would eventually disagree.
 */

/**
 * Must mirror `AuthorityScoreCalculator::gradeFor()`.
 */
export function gradeFor( score: number ): Grade {
	if ( score >= 90 ) {
		return 'A';
	}
	if ( score >= 80 ) {
		return 'B';
	}
	if ( score >= 65 ) {
		return 'C';
	}
	if ( score >= 50 ) {
		return 'D';
	}
	return 'F';
}

export interface DayPoint {
	day: string;
	value: number;
}

/**
 * Collapse a per-source daily series into one total per day.
 *
 * The API returns one row per (day, source) pair because the breakdown is also
 * needed; a single-line chart wants them summed. Days with no rows stay absent
 * rather than being zero-filled — a gap in observed data is not the same claim
 * as "zero visits that day", and the analytics module is explicit that its
 * numbers are a floor.
 */
export function collapseSeries(
	series: Array< { day: string; visits?: number; hits?: number } >
): DayPoint[] {
	const byDay = new Map< string, number >();

	for ( const point of series ) {
		const value = point.visits ?? point.hits ?? 0;
		byDay.set( point.day, ( byDay.get( point.day ) ?? 0 ) + value );
	}

	return [ ...byDay.entries() ]
		.sort( ( a, b ) => a[ 0 ].localeCompare( b[ 0 ] ) )
		.map( ( [ day, value ] ) => ( { day, value } ) );
}

/**
 * Format a score for display.
 *
 * Whole numbers render without a decimal point; anything else keeps one place.
 * "72" reads as a score, "72.0" reads as a measurement.
 */
export function formatScore( score: number ): string {
	const clamped = Math.max( 0, Math.min( 100, score ) );

	return clamped % 1 === 0 ? clamped.toFixed( 0 ) : clamped.toFixed( 1 );
}

/**
 * Percentage of a component's available points.
 *
 * Guards a zero denominator, which would otherwise render `NaN%` in the UI.
 */
export function percentOf( points: number, max: number ): number {
	if ( max <= 0 ) {
		return 0;
	}

	return Math.max( 0, Math.min( 100, ( points / max ) * 100 ) );
}

/**
 * Truncate a label for a fixed-width slot, on a character boundary.
 */
export function truncate( value: string, length: number ): string {
	return value.length > length ? `${ value.slice( 0, length - 1 ) }…` : value;
}
