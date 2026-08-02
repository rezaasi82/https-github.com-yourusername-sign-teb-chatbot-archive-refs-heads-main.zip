import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { ApiError } from '../api/client';

interface AsyncState< T > {
	data: T | null;
	loading: boolean;
	error: ApiError | null;
}

/**
 * Runs an async loader and tracks its lifecycle.
 *
 * Two things this handles that a naive `useEffect` + `setState` does not:
 * in-flight requests are aborted when the effect re-runs, and a resolved
 * promise from a stale run is discarded rather than overwriting newer data.
 * Without both, switching the reporting window twice quickly leaves the
 * dashboard showing whichever response happened to land last.
 */
export function useAsync< T >(
	loader: ( signal: AbortSignal ) => Promise< T >,
	deps: unknown[] = []
): AsyncState< T > & { reload: () => void } {
	const [ state, setState ] = useState< AsyncState< T > >( {
		data: null,
		loading: true,
		error: null,
	} );

	const [ nonce, setNonce ] = useState( 0 );
	const runIdRef = useRef( 0 );

	const reload = useCallback( () => setNonce( ( n ) => n + 1 ), [] );

	useEffect( () => {
		const controller = new AbortController();
		const runId = ++runIdRef.current;

		setState( ( previous ) => ( { ...previous, loading: true } ) );

		loader( controller.signal )
			.then( ( data ) => {
				if ( runId === runIdRef.current ) {
					setState( { data, loading: false, error: null } );
				}
			} )
			.catch( ( error: unknown ) => {
				if (
					error instanceof DOMException &&
					error.name === 'AbortError'
				) {
					return;
				}

				if ( runId === runIdRef.current ) {
					setState( {
						data: null,
						loading: false,
						error:
							error instanceof ApiError
								? error
								: new ApiError(
										String( error ),
										0,
										'medora_unknown_error'
								  ),
					} );
				}
			} );

		return () => controller.abort();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ ...deps, nonce ] );

	return { ...state, reload };
}
