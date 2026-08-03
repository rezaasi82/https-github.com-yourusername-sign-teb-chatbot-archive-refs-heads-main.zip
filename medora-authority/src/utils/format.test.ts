import {
	collapseSeries,
	formatScore,
	gradeFor,
	percentOf,
	truncate,
} from './format';

describe( 'gradeFor', () => {
	it.each( [
		[ 100, 'A' ],
		[ 90, 'A' ],
		[ 89.9, 'B' ],
		[ 80, 'B' ],
		[ 79.9, 'C' ],
		[ 65, 'C' ],
		[ 64.9, 'D' ],
		[ 50, 'D' ],
		[ 49.9, 'F' ],
		[ 0, 'F' ],
	] )( 'maps %s to %s', ( score, expected ) => {
		expect( gradeFor( score as number ) ).toBe( expected );
	} );

	// The boundaries are the whole point of the function; an off-by-one here
	// silently mislabels every page sitting on a threshold.
	it( 'treats each threshold as inclusive', () => {
		expect( gradeFor( 90 ) ).toBe( 'A' );
		expect( gradeFor( 80 ) ).toBe( 'B' );
		expect( gradeFor( 65 ) ).toBe( 'C' );
		expect( gradeFor( 50 ) ).toBe( 'D' );
	} );
} );

describe( 'collapseSeries', () => {
	it( 'sums rows sharing a day', () => {
		expect(
			collapseSeries( [
				{ day: '2026-01-01', visits: 3 },
				{ day: '2026-01-01', visits: 4 },
				{ day: '2026-01-02', visits: 1 },
			] )
		).toEqual( [
			{ day: '2026-01-01', value: 7 },
			{ day: '2026-01-02', value: 1 },
		] );
	} );

	it( 'accepts either the visits or hits field', () => {
		expect(
			collapseSeries( [
				{ day: '2026-01-01', hits: 5 },
				{ day: '2026-01-01', visits: 2 },
			] )
		).toEqual( [ { day: '2026-01-01', value: 7 } ] );
	} );

	it( 'sorts chronologically regardless of input order', () => {
		const result = collapseSeries( [
			{ day: '2026-03-01', visits: 1 },
			{ day: '2026-01-01', visits: 1 },
			{ day: '2026-02-01', visits: 1 },
		] );

		expect( result.map( ( p ) => p.day ) ).toEqual( [
			'2026-01-01',
			'2026-02-01',
			'2026-03-01',
		] );
	} );

	it( 'does not zero-fill missing days', () => {
		// A gap in observed data is not a claim of zero traffic.
		const result = collapseSeries( [
			{ day: '2026-01-01', visits: 1 },
			{ day: '2026-01-05', visits: 1 },
		] );

		expect( result ).toHaveLength( 2 );
	} );

	it( 'returns an empty array for no input', () => {
		expect( collapseSeries( [] ) ).toEqual( [] );
	} );

	it( 'treats a row with neither field as zero', () => {
		expect( collapseSeries( [ { day: '2026-01-01' } ] ) ).toEqual( [
			{ day: '2026-01-01', value: 0 },
		] );
	} );
} );

describe( 'formatScore', () => {
	it( 'drops the decimal on whole numbers', () => {
		expect( formatScore( 72 ) ).toBe( '72' );
		expect( formatScore( 100 ) ).toBe( '100' );
	} );

	it( 'keeps one place otherwise', () => {
		expect( formatScore( 72.45 ) ).toBe( '72.5' );
	} );

	it( 'clamps out-of-range input', () => {
		expect( formatScore( -10 ) ).toBe( '0' );
		expect( formatScore( 250 ) ).toBe( '100' );
	} );
} );

describe( 'percentOf', () => {
	it( 'computes a share of the available points', () => {
		expect( percentOf( 5, 20 ) ).toBe( 25 );
	} );

	it( 'returns zero rather than NaN when nothing is available', () => {
		expect( percentOf( 5, 0 ) ).toBe( 0 );
		expect( percentOf( 0, 0 ) ).toBe( 0 );
	} );

	it( 'clamps beyond the maximum', () => {
		expect( percentOf( 30, 20 ) ).toBe( 100 );
		expect( percentOf( -5, 20 ) ).toBe( 0 );
	} );
} );

describe( 'truncate', () => {
	it( 'leaves short strings alone', () => {
		expect( truncate( 'short', 10 ) ).toBe( 'short' );
	} );

	it( 'appends an ellipsis when cutting', () => {
		expect( truncate( 'a much longer label', 10 ) ).toBe( 'a much lo…' );
		expect( truncate( 'a much longer label', 10 ) ).toHaveLength( 10 );
	} );
} );
