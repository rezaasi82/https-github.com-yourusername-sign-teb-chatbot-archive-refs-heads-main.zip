import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import type { BootData } from '../types';

/**
 * Thin typed wrapper over `@wordpress/api-fetch`.
 *
 * Everything the dashboard sends goes through here so nonce handling, error
 * shaping and the base path live in exactly one place. WordPress REST errors
 * arrive as `{ code, message, data: { status } }`; that gets normalised into a
 * real `Error` with the server's message intact, because a dashboard that
 * shows "Request failed" instead of "This module requires the Agency plan" is
 * actively unhelpful.
 */

declare global {
	interface Window {
		medoraBoot?: BootData;
	}
}

export const boot: BootData = window.medoraBoot ?? {
	restUrl: '/wp-json/medora/v1',
	nonce: '',
	adminUrl: '',
	screen: 'overview',
	version: '0.0.0',
	locale: 'en-US',
	isRtl: false,
	postId: 0,
	capabilities: {
		manage: false,
		analyze: false,
		entities: false,
		audit: false,
	},
	experience: 'professional',
	features: {},
};

/**
 * Whether a surface is shown in this install's experience mode.
 *
 * Unknown features are shown. This mirrors the server, which fails open for the
 * same reason: it is a presentational filter, so a screen nobody registered
 * should appear rather than vanish. Never use it to gate anything that matters
 * — `boot.capabilities` is the boundary.
 */
export function shows( feature: string ): boolean {
	return boot.features[ feature ] ?? true;
}

if ( boot.nonce ) {
	apiFetch.use( apiFetch.createNonceMiddleware( boot.nonce ) );
}

export class ApiError extends Error {
	public readonly status: number;
	public readonly code: string;
	public readonly data: Record< string, unknown >;

	constructor(
		message: string,
		status: number,
		code: string,
		data: Record< string, unknown > = {}
	) {
		super( message );
		this.name = 'ApiError';
		this.status = status;
		this.code = code;
		this.data = data;
	}

	/** True when the failure is fixable by upgrading, not by retrying. */
	get isUpgradeRequired(): boolean {
		return this.status === 402 || this.code === 'medora_upgrade_required';
	}
}

interface RequestOptions {
	method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
	data?: unknown;
	params?: Record< string, string | number | boolean | undefined >;
	signal?: AbortSignal;
}

function buildPath(
	path: string,
	params?: RequestOptions[ 'params' ]
): string {
	const base = `${ boot.restUrl.replace( /\/$/, '' ) }/${ path.replace(
		/^\//,
		''
	) }`;

	if ( ! params ) {
		return base;
	}

	const query = new URLSearchParams();

	for ( const [ key, value ] of Object.entries( params ) ) {
		if ( value !== undefined && value !== '' ) {
			query.append( key, String( value ) );
		}
	}

	const suffix = query.toString();

	return suffix ? `${ base }?${ suffix }` : base;
}

export async function request< T >(
	path: string,
	options: RequestOptions = {}
): Promise< T > {
	try {
		return ( await apiFetch( {
			url: buildPath( path, options.params ),
			method: options.method ?? 'GET',
			data: options.data,
			signal: options.signal,
		} ) ) as T;
	} catch ( error ) {
		// An aborted request is a navigation, not a failure; let the caller
		// distinguish it rather than surfacing it as an error toast.
		if ( error instanceof DOMException && error.name === 'AbortError' ) {
			throw error;
		}

		const rest = error as {
			message?: string;
			code?: string;
			data?: { status?: number } & Record< string, unknown >;
		};

		throw new ApiError(
			rest?.message ?? __( 'Something went wrong.', 'medora-authority' ),
			rest?.data?.status ?? 0,
			rest?.code ?? 'medora_unknown_error',
			rest?.data ?? {}
		);
	}
}

export const api = {
	overview: ( days = 30 ) =>
		request< import('../types').Overview >( 'overview', {
			params: { days },
		} ),

	siteScore: () => request< import('../types').SiteReport >( 'score' ),

	postScore: ( id: number, refresh = false ) =>
		request< import('../types').PostScore >( `score/${ id }`, {
			params: { refresh },
		} ),

	analyze: ( id: number, sync = false ) =>
		request< { queued?: boolean } >( `score/${ id }/analyze`, {
			method: 'POST',
			data: { sync },
		} ),

	recommendations: ( id: number ) =>
		request< Record< string, unknown > >( `score/${ id }/recommendations` ),

	brief: ( id: number ) =>
		request< import('../types').Brief >( `score/${ id }/brief` ),

	briefMarkdown: ( id: number ) =>
		request< { post_id: number; markdown: string } >(
			`score/${ id }/brief`,
			{ params: { format: 'markdown' } }
		),

	links: ( id: number ) =>
		request< import('../types').LinkReport >( `links/${ id }` ),

	geo: ( id: number ) =>
		request< import('../types').GeoReport >( `geo/${ id }` ),

	auditLog: ( page = 1, perPage = 50 ) =>
		request< import('../types').AuditReport >( 'audit-log', {
			params: { page, per_page: perPage },
		} ),

	applyLink: (
		id: number,
		targetId: number,
		anchor: string,
		occurrence = 1
	) =>
		request< { applied: boolean; anchor: string; target_id: number } >(
			`links/${ id }`,
			{
				method: 'POST',
				data: { target_id: targetId, anchor, occurrence },
			}
		),

	revertLinks: ( id: number, targetId?: number ) =>
		request< { reverted: number } >( `links/${ id }`, {
			method: 'DELETE',
			data: targetId ? { target_id: targetId } : {},
		} ),

	entities: ( params: Record< string, string | number > ) =>
		request< {
			items: import('../types').EntitySummary[];
			total: number;
			page: number;
		} >( 'entities', { params } ),

	entity: ( id: number ) =>
		request< import('../types').EntityDetail >( `entities/${ id }` ),

	updateEntity: ( id: number, data: Record< string, unknown > ) =>
		request< import('../types').EntitySummary >( `entities/${ id }`, {
			method: 'PATCH',
			data,
		} ),

	deleteEntity: ( id: number, suppress = true ) =>
		request< { deleted: boolean; suppressed: boolean } >(
			`entities/${ id }`,
			{ method: 'DELETE', data: { suppress } }
		),

	suppressedEntities: () =>
		request< import('../types').SuppressionReport >(
			'entities/suppressed'
		),

	restoreEntity: ( uid?: string ) =>
		request< import('../types').SuppressionReport >(
			'entities/suppressed',
			{ method: 'DELETE', data: uid ? { uid } : {} }
		),

	graph: ( limit = 300 ) =>
		request< import('../types').GraphData >( 'graph', {
			params: { format: 'nodes', limit },
		} ),

	crawlers: () =>
		request< {
			preset: string;
			crawl_delay: number;
			crawlers: import('../types').CrawlerRow[];
			robots: { physical_file_present: boolean; preview: string };
		} >( 'crawlers' ),

	updateCrawler: ( data: {
		slug?: string;
		decision?: string;
		preset?: string;
	} ) =>
		request< {
			preset: string;
			crawlers: import('../types').CrawlerRow[];
			robots: { physical_file_present: boolean; preview: string };
		} >( 'crawlers', { method: 'PATCH', data } ),

	crawlerActivity: ( days = 30 ) =>
		request< import('../types').CrawlerReport >( 'crawler-activity', {
			params: { days },
		} ),

	analytics: ( days = 30 ) =>
		request< import('../types').ReferralReport >( 'analytics', {
			params: { days },
		} ),

	settings: () =>
		request< {
			settings: Record< string, unknown >;
			defaults: Record< string, unknown >;
			has_embedding_key: boolean;
			has_llm_key: boolean;
			languages: Array< { value: string; label: string } >;
		} >( 'settings' ),

	saveSettings: ( data: Record< string, unknown > ) =>
		request< { settings: Record< string, unknown > } >( 'settings', {
			method: 'PATCH',
			data,
		} ),

	toggleModule: ( id: string, enabled: boolean ) =>
		request< { id: string; enabled: boolean; requires_reload: boolean } >(
			`modules/${ id }`,
			{ method: 'PATCH', data: { enabled } }
		),

	license: () => request< import('../types').LicenseState >( 'license' ),

	licenseAction: ( action: string, key?: string ) =>
		request< {
			message: string;
			license: import('../types').LicenseState;
		} >( 'license', { method: 'POST', data: { action, key } } ),

	securityScan: () =>
		request< import('../types').SecurityScan >( 'security/scan' ),

	wizard: () => request< import('../types').WizardState >( 'onboarding' ),

	completeWizard: ( data: Record< string, unknown > ) =>
		request< { completed: boolean; queued_posts: number } >( 'onboarding', {
			method: 'POST',
			data,
		} ),
};
