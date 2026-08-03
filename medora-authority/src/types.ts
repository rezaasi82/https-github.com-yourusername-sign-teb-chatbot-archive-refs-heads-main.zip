/**
 * Shapes returned by the Medora REST API.
 *
 * These mirror the PHP response arrays. Keeping them in one file makes the
 * contract between the two halves explicit — when a controller changes shape,
 * `tsc` fails here rather than the dashboard silently rendering `undefined`.
 */

export type Severity = 'critical' | 'high' | 'medium' | 'low';
export type Grade = 'A' | 'B' | 'C' | 'D' | 'F';

export interface BootData {
	restUrl: string;
	nonce: string;
	adminUrl: string;
	screen: string;
	version: string;
	locale: string;
	isRtl: boolean;
	postId: number;
	capabilities: {
		manage: boolean;
		analyze: boolean;
		entities: boolean;
		audit: boolean;
	};
}

export interface Deduction {
	code: string;
	label: string;
	points: number;
	recommendation: string;
	severity: Severity;
	fix_location: string;
	component?: string;
}

export interface ScoreComponent {
	id: string;
	label: string;
	score: number;
	weight: number;
	grade: Grade;
	metrics: Record< string, unknown >;
	deductions: Deduction[];
}

export interface PostScore {
	post_id: number;
	overall: number;
	grade: Grade;
	components: ScoreComponent[];
	deductions: Deduction[];
	analyzed_at: string;
	cached?: boolean;
}

export interface SiteReport {
	average: number;
	grade: Grade;
	analyzed: number;
	distribution: Record< Grade, number >;
	weakest: Array< {
		object_id: number;
		score: number;
		title: string;
		url: string;
		analyzed_at: string;
	} >;
	top_issues: Array< {
		code: string;
		label: string;
		count: number;
		recommendation: string;
	} >;
}

export interface EntitySummary {
	id: number;
	uid: string;
	name: string;
	type: string;
	type_label: string;
	description: string;
	permalink: string;
	same_as: string[];
	authority_score: number;
	confidence: number;
	occurrences: number;
	object: { type: string; id: number };
}

export interface EntityDetail extends EntitySummary {
	relations?: Array< {
		predicate: string;
		predicate_label: string;
		direction: 'incoming' | 'outgoing';
		weight: number;
		entity: EntitySummary;
	} >;
	authority?: {
		score: number;
		components: Array< {
			id: string;
			label: string;
			points: number;
			max: number;
			note: string;
		} >;
	};
}

export interface ModuleInfo {
	id: string;
	title: string;
	description: string;
	enabled: boolean;
	booted: boolean;
	required_tier: string;
	dependencies: string[];
	skip_reason: string;
}

export interface LicenseState {
	tier: string;
	tier_label: string;
	status: string;
	masked_key: string;
	expires_at: string;
	grace_days_left: number | null;
	domain_bound: boolean;
	last_error: string;
}

export interface CrawlerRow {
	slug: string;
	name: string;
	vendor: string;
	purpose: string;
	purpose_label: string;
	robots_token: string;
	ua_token: string;
	recommended: boolean;
	decision: 'allow' | 'block' | 'delay';
	is_override: boolean;
}

export interface CrawlerReport {
	window_days: number;
	total_hits: number;
	unique_crawlers: number;
	coverage: { seen: number; known: number; percent: number };
	crawlers: Array< {
		slug: string;
		name: string;
		vendor: string;
		purpose: string;
		hits: number;
		last_seen: string;
	} >;
	series: Array< { day: string; crawler_slug: string; hits: number } >;
	top_paths: Array< { request_uri: string; object_id: number; hits: number } >;
	missing: Array< { slug: string; name: string; vendor: string } >;
}

export interface ReferralReport {
	window_days: number;
	total_visits: number;
	previous_visits: number;
	change_percent: number | null;
	sources: Array< {
		source_slug: string;
		label: string;
		visits: number;
		visitors: number;
	} >;
	series: Array< { day: string; source_slug: string; visits: number } >;
	top_pages: Array< {
		object_id: number;
		title: string;
		url: string;
		visits: number;
	} >;
}

export interface GraphData {
	nodes: Array< {
		id: number;
		uid: string;
		label: string;
		type: string;
		score: number;
		degree: number;
		url: string;
	} >;
	links: Array< {
		source: number;
		target: number;
		predicate: string;
		weight: number;
	} >;
	stats: { entities: number; relations: number };
}

export interface Overview {
	version: string;
	site: { name: string; url: string };
	license: LicenseState;
	modules: ModuleInfo[];
	queue: { pending: number; running: number; failed: number };
	authority?: SiteReport;
	entities?: {
		total: number;
		by_type: Record< string, number >;
		top: EntitySummary[];
	};
	graph?: { relations: number };
	crawlers?: CrawlerReport;
	referrals?: ReferralReport;
	vectors?: {
		chunks: number;
		objects: number;
		providers: string[];
		needs_external_index: boolean;
	};
	citations?: { total: number; with_doi: number; average_quality: number };
}

export interface BriefSection {
	heading: string;
	why: string;
	cover: string[];
	status: 'present' | 'missing';
}

export interface Brief {
	post_id: number;
	title: string;
	url: string;
	score: number;
	potential_score: number;
	subject: EntitySummary | null;
	word_count: { current: number; target: number };
	opening: { needs_rewrite: boolean; current: string; spec: string };
	sections: BriefSection[];
	questions: Array< { question: string; status: 'answered' | 'unanswered' } >;
	entities: {
		covered: string[];
		add: Array< { name: string; type: string; why: string } >;
	};
	evidence: { required: boolean; current: number; target: number; note: string };
	internal_links: LinkSuggestion[];
	checklist: Array< { done: boolean; task: string } >;
}

export interface LinkSuggestion {
	target_id: number;
	title: string;
	url: string;
	score: number;
	reason: string;
	anchors: string[];
	already_linked: boolean;
}

export interface LinkReport {
	post_id: number;
	outbound: LinkSuggestion[];
	inbound: Array< {
		source_id: number;
		title: string;
		url: string;
		score: number;
	} >;
	applied: number;
}

export interface SecurityScan {
	passed: number;
	failed: number;
	checks: Array< {
		id: string;
		label: string;
		status: 'pass' | 'fail';
		detail: string;
		severity: Severity;
	} >;
}

export interface WizardStep {
	id: string;
	title: string;
	description: string;
	field?: string;
	options?: Array< { value: string; label: string } >;
}

export interface WizardState {
	completed: boolean;
	defaults: Record< string, string >;
	steps: WizardStep[];
}
