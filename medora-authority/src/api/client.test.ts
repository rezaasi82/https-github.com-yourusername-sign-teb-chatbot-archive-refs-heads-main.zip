/**
 * The client is the only place errors cross from the server into the UI, so
 * these tests pin the two things that matter: requests go to the right URL,
 * and a WordPress REST error keeps its message instead of becoming
 * "Request failed".
 */

const apiFetchMock = jest.fn();

jest.mock( '@wordpress/api-fetch', () => {
	const mock = ( ...args: unknown[] ) => apiFetchMock( ...args );
	mock.use = jest.fn();
	mock.createNonceMiddleware = jest.fn( () => jest.fn() );

	return { __esModule: true, default: mock };
} );

jest.mock( '@wordpress/i18n', () => ( {
	__: ( text: string ) => text,
	sprintf: ( text: string ) => text,
} ) );

beforeEach( () => {
	apiFetchMock.mockReset();

	( window as unknown as { medoraBoot: unknown } ).medoraBoot = {
		restUrl: 'https://example.test/wp-json/medora/v1',
		nonce: 'abc123',
		adminUrl: '',
		screen: 'overview',
		version: '0.3.0',
		locale: 'en-US',
		isRtl: false,
		postId: 0,
		capabilities: {
			manage: true,
			analyze: true,
			entities: true,
			audit: true,
		},
	};

	jest.resetModules();
} );

describe( 'request', () => {
	it( 'builds a URL from the configured base', async () => {
		const { request } = await import( './client' );

		apiFetchMock.mockResolvedValue( { ok: true } );

		await request( 'entities' );

		expect( apiFetchMock ).toHaveBeenCalledWith(
			expect.objectContaining( {
				url: 'https://example.test/wp-json/medora/v1/entities',
				method: 'GET',
			} )
		);
	} );

	it( 'does not double up slashes', async () => {
		const { request } = await import( './client' );

		apiFetchMock.mockResolvedValue( {} );

		await request( '/entities' );

		expect( apiFetchMock.mock.calls[ 0 ][ 0 ].url ).toBe(
			'https://example.test/wp-json/medora/v1/entities'
		);
	} );

	it( 'appends query parameters', async () => {
		const { request } = await import( './client' );

		apiFetchMock.mockResolvedValue( {} );

		await request( 'entities', {
			params: { search: 'liver', page: 2, min_score: 40 },
		} );

		const url = apiFetchMock.mock.calls[ 0 ][ 0 ].url as string;

		expect( url ).toContain( 'search=liver' );
		expect( url ).toContain( 'page=2' );
		expect( url ).toContain( 'min_score=40' );
	} );

	it( 'omits undefined and empty parameters', async () => {
		const { request } = await import( './client' );

		apiFetchMock.mockResolvedValue( {} );

		await request( 'entities', {
			params: { search: '', type: undefined, page: 1 },
		} );

		const url = apiFetchMock.mock.calls[ 0 ][ 0 ].url as string;

		expect( url ).not.toContain( 'search=' );
		expect( url ).not.toContain( 'type=' );
		expect( url ).toContain( 'page=1' );
	} );

	it( 'encodes parameters that need it', async () => {
		const { request } = await import( './client' );

		apiFetchMock.mockResolvedValue( {} );

		await request( 'search', { params: { q: 'کبد چرب & more' } } );

		const url = apiFetchMock.mock.calls[ 0 ][ 0 ].url as string;

		expect( url ).not.toContain( ' & more' );
		expect( decodeURIComponent( url ) ).toContain( 'کبد چرب & more' );
	} );
} );

describe( 'error handling', () => {
	it( 'preserves the server message rather than a generic one', async () => {
		const { request, ApiError } = await import( './client' );

		apiFetchMock.mockRejectedValue( {
			code: 'medora_upgrade_required',
			message: 'This module requires the Agency plan.',
			data: { status: 402, required_tier: 'agency' },
		} );

		await expect( request( 'modules/white_label' ) ).rejects.toThrow(
			'This module requires the Agency plan.'
		);

		try {
			await request( 'modules/white_label' );
		} catch ( error ) {
			const apiError = error as InstanceType< typeof ApiError >;

			expect( apiError.status ).toBe( 402 );
			expect( apiError.code ).toBe( 'medora_upgrade_required' );
			expect( apiError.isUpgradeRequired ).toBe( true );
			expect( apiError.data.required_tier ).toBe( 'agency' );
		}
	} );

	it( 'flags an upgrade requirement from the status alone', async () => {
		const { request, ApiError } = await import( './client' );

		apiFetchMock.mockRejectedValue( {
			code: 'something_else',
			message: 'Nope.',
			data: { status: 402 },
		} );

		try {
			await request( 'x' );
		} catch ( error ) {
			expect(
				( error as InstanceType< typeof ApiError > ).isUpgradeRequired
			).toBe( true );
		}
	} );

	it( 'does not flag ordinary failures as upgrade requirements', async () => {
		const { request, ApiError } = await import( './client' );

		apiFetchMock.mockRejectedValue( {
			code: 'medora_not_found',
			message: 'Post not found.',
			data: { status: 404 },
		} );

		try {
			await request( 'score/1' );
		} catch ( error ) {
			expect(
				( error as InstanceType< typeof ApiError > ).isUpgradeRequired
			).toBe( false );
		}
	} );

	it( 'rethrows an abort untouched so callers can ignore it', async () => {
		const { request } = await import( './client' );

		apiFetchMock.mockRejectedValue(
			new DOMException( 'Aborted', 'AbortError' )
		);

		await expect( request( 'entities' ) ).rejects.toThrow( DOMException );
	} );

	it( 'copes with an error that carries no shape at all', async () => {
		const { request, ApiError } = await import( './client' );

		apiFetchMock.mockRejectedValue( undefined );

		try {
			await request( 'entities' );
		} catch ( error ) {
			expect( error ).toBeInstanceOf( ApiError );
			expect(
				( error as InstanceType< typeof ApiError > ).status
			).toBe( 0 );
		}
	} );
} );

describe( 'api helpers', () => {
	it( 'posts sync analysis as a body', async () => {
		const { api } = await import( './client' );

		apiFetchMock.mockResolvedValue( {} );

		await api.analyze( 42, true );

		expect( apiFetchMock ).toHaveBeenCalledWith(
			expect.objectContaining( {
				url: 'https://example.test/wp-json/medora/v1/score/42/analyze',
				method: 'POST',
				data: { sync: true },
			} )
		);
	} );

	it( 'uses PATCH for a module toggle', async () => {
		const { api } = await import( './client' );

		apiFetchMock.mockResolvedValue( {} );

		await api.toggleModule( 'vector', false );

		expect( apiFetchMock ).toHaveBeenCalledWith(
			expect.objectContaining( {
				url: 'https://example.test/wp-json/medora/v1/modules/vector',
				method: 'PATCH',
				data: { enabled: false },
			} )
		);
	} );

	it( 'requests the graph in visualisation format', async () => {
		const { api } = await import( './client' );

		apiFetchMock.mockResolvedValue( {} );

		await api.graph( 150 );

		const url = apiFetchMock.mock.calls[ 0 ][ 0 ].url as string;

		expect( url ).toContain( 'format=nodes' );
		expect( url ).toContain( 'limit=150' );
	} );
} );
