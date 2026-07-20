/**
 * Typed REST client for sda/v1. All requests carry the WP REST nonce.
 */

export interface Branding {
  active: boolean;
  name: string;
  logo_url: string;
  primary_color: string;
  hide_powered_by: boolean;
}

export interface BootData {
  restUrl: string;
  nonce: string;
  locale: string;
  isRtl: boolean;
  canManage: boolean;
  canManageClients: boolean;
  medicalMode: boolean;
  version: string;
  siteName: string;
  branding: Branding;
}

declare global {
  interface Window {
    sdaBoot?: BootData;
  }
}

export function boot(): BootData {
  if (!window.sdaBoot) {
    throw new Error('SEO Director AI boot data missing — script not localized.');
  }
  return window.sdaBoot;
}

export interface TrafficPoint {
  date: string;
  clicks: number;
  impressions: number;
  ctr: number;
  position: number;
}

export interface Health {
  score: number;
  band: 'green' | 'yellow' | 'red';
  delta: number | null;
  components: Record<string, { score: number | null; weight: number; available: boolean }>;
}

export interface Opportunity {
  id: number;
  detector: string;
  entity_type: string;
  label: string;
  secondary_label: string;
  score: number;
  est_traffic_gain: number;
  difficulty: number;
  status: string;
  data: Record<string, unknown>;
  refreshed_at: string;
}

export interface Alert {
  id: number;
  rule: string;
  severity: 'critical' | 'high' | 'medium' | 'low';
  entity_label: string;
  message: string;
  status: string;
  raised_at: string;
  resolved_at: string | null;
}

export interface Winner {
  label: string;
  hash: string;
  clicks: number;
  clicks_delta: number;
  growth_pct: number | null;
  is_new: boolean;
  position: number;
  position_delta: number;
  reason: string;
  next_action: string;
}

export interface Loser {
  label: string;
  hash: string;
  clicks: number;
  clicks_delta: number;
  loss_pct: number | null;
  position: number;
  position_delta: number;
  cause: string;
  priority: 'critical' | 'high' | 'medium' | 'low';
  suggested_fix: string;
}

export interface RoadmapTask {
  id: number;
  scope: string;
  title: string;
  description: string;
  category: string;
  impact: number;
  difficulty: number;
  est_hours: number | null;
  priority: number;
  expected_result: string;
  status: 'todo' | 'in_progress' | 'done' | 'dismissed';
  measured_result: Record<string, unknown> | null;
}

export interface RootCause {
  cause: string;
  confidence: number;
  fix: string;
}

export interface ExplainResponse {
  payload: {
    summary?: string;
    causes?: RootCause[];
    explanation?: string;
    recommendation?: string;
  };
  cached: boolean;
}

export interface AiMeter {
  used: number;
  cap: number;
  remaining: number | null;
}

export interface OverviewResponse {
  connections: { gsc: boolean; ga4: boolean; psi: boolean; ai: boolean };
  health: Health | null;
  traffic: {
    series: TrafficPoint[];
    compare: unknown;
  };
  opportunities: Opportunity[];
  risks: Alert[];
  summaries: { weekly: string | null; monthly: string | null };
  meta: {
    plugin_version: string;
    backfill: { status: string; date: string | null } | null;
    demo?: boolean;
    setup?: SetupStatus;
  };
}

export interface SetupStep {
  key: string;
  label: string;
  done: boolean;
}

export interface SetupStatus {
  steps: SetupStep[];
  complete: boolean;
  has_data: boolean;
}

export interface SyncState {
  cursor: Record<string, unknown>;
  status: string;
  fail_count: number;
}

export interface ConnectionsState {
  google: { status: string; redirect_uri: string };
  keys: Record<string, boolean>;
  properties: Array<{
    id: number;
    service: 'gsc' | 'ga4';
    external_id: string;
    display_name: string;
    is_active: boolean;
  }>;
  sync: { gsc: SyncState | null; ga4: SyncState | null };
}

export interface LicenseState {
  state: 'none' | 'active' | 'grace' | 'expired';
  edition: string;
  tier: string | null;
  expires_at: string | null;
  days_left: number | null;
  in_grace: boolean;
  has_license: boolean;
}

export interface LicenseStatusResponse {
  license: LicenseState;
  features: Record<string, boolean>;
  edition: string;
  is_lite: boolean;
  upgrade_url: string;
}

export interface MetaSuggestion {
  title: string;
  description: string;
  title_px: number;
  desc_px: number;
}

export interface ContentGapTopic {
  topic: string;
  rationale?: string;
  target_queries?: string[];
  [key: string]: unknown;
}

export interface PostRef {
  id: number;
  title: string;
  url: string;
}

export interface ContentBrief {
  goal: string;
  primary_keyword: string;
  secondary_keywords: string[];
  outline: Array<{ level: number; heading: string; notes?: string }>;
  faq: Array<{ question: string; answer?: string }>;
  entities: string[];
  related_queries: Array<{ query: string; impressions: number; position: number }>;
  existing_posts: PostRef[];
}

export interface LinkSuggestionGroup {
  source: PostRef;
  suggestions: Array<{ target_id: number; target_title: string; target_url: string; anchor: string; score: number }>;
}

export interface AuditResult {
  scanned: number;
  issues_total: number;
  generated_at: string;
  pages: Array<{
    id: number;
    title: string;
    url: string;
    issues: Array<{ code: string; severity: 'high' | 'medium' | 'low'; message: string }>;
  }>;
}

export interface SchemaBuildResult {
  graph: Array<Record<string, unknown>>;
  faq_found: number;
  saved?: boolean;
}

export interface ScoreResult {
  score: number;
  checks: Array<{ code: string; label: string; points: number; max: number; detail: string }>;
  entities: { covered: string[]; missing: string[] } | null;
}

export interface KeywordRow {
  keyword: string;
  sources: string[];
  intent: string;
  impressions: number | null;
  position: number | null;
}

export interface ClusterPlan {
  pillar: { title?: string; target_keyword?: string; rationale?: string };
  clusters: Array<{ title: string; target_keyword: string; role: 'create' | 'update'; intent?: string }>;
  linking_notes: string;
  keywords_used: number;
}

export interface CompetitorOverview {
  competitors: Array<{ domain: string; appearances: number; avg_position: number; sample_queries: string[] }>;
  queries_checked: number;
  our_domain: string;
}

export interface SerpView {
  query: string;
  our_domain: string;
  our_position: number | null;
  results: Array<{ position: number; title: string; link: string; domain: string; is_us: boolean }>;
}

export interface MedicalEntity {
  term: string;
  category: string;
  count: number;
}

export interface EeatResult {
  score: number;
  checks: Array<{ code: string; label: string; points: number; max: number; ok: boolean; detail: string }>;
  entities_found: number;
  is_medical: boolean;
}

export interface KnowledgeGraphResult {
  generated_at: string;
  posts_scanned: number;
  by_category: Record<string, Array<{ term: string; posts: number; mentions: number }>>;
  missing: Array<{ term: string; category: string }>;
  covered_terms: number;
  total_terms: number;
}

export interface ReportRow {
  id: number;
  type: string;
  period_start: string;
  period_end: string;
  formats: string[];
  status: string;
  created_at: string;
}

export interface AgencySnapshot {
  site_name: string;
  site_url: string;
  generated_at: string;
  plugin_version: string;
  health: { score: number; band: string; delta: number | null } | null;
  alerts: { critical: number; high: number; total: number };
  opportunities: number;
  traffic: { clicks: number; change_pct: number | null } | null;
}

export interface AgencySite {
  id: number;
  client_name: string;
  site_url: string;
  status: 'pending' | 'active';
  last_seen_at: string | null;
  snapshot: AgencySnapshot | null;
  created_at: string;
}

export interface PairResult {
  items: AgencySite[];
  pair_key: string;
  ingest_url: string;
  hub_url: string;
}

export interface SettingsResponse {
  settings: Record<string, unknown>;
  secrets_set: Record<string, boolean>;
  ai: { available: boolean; meter: AiMeter };
}

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const { restUrl, nonce } = boot();
  const response = await fetch(`${restUrl}${path}`, {
    ...init,
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': nonce,
      ...(init.headers ?? {}),
    },
  });

  if (!response.ok) {
    const body = await response.json().catch(() => null);
    throw new Error(body?.message ?? `Request failed (${response.status})`);
  }

  return (await response.json()) as T;
}

export const api = {
  overview: () => request<OverviewResponse>('/overview'),
  settings: () => request<SettingsResponse>('/settings'),
  updateSettings: (settings: Record<string, unknown>) =>
    request<SettingsResponse>('/settings', {
      method: 'POST',
      body: JSON.stringify({ settings }),
    }),
  connections: () => request<ConnectionsState>('/connections'),
  startGoogleOAuth: (clientId: string, clientSecret: string) =>
    request<{ authorize_url: string }>('/connections/google/start', {
      method: 'POST',
      body: JSON.stringify({ client_id: clientId, client_secret: clientSecret }),
    }),
  saveKey: (service: string, apiKey: string) =>
    request<ConnectionsState>('/connections/key', {
      method: 'POST',
      body: JSON.stringify({ service, api_key: apiKey }),
    }),
  selectProperty: (service: 'gsc' | 'ga4', propertyId: number) =>
    request<ConnectionsState>('/connections/property', {
      method: 'POST',
      body: JSON.stringify({ service, property_id: propertyId }),
    }),
  disconnect: (service: string) => request<ConnectionsState>(`/connections/${service}`, { method: 'DELETE' }),
  syncNow: () => request<ConnectionsState & { queued: boolean }>('/connections/sync', { method: 'POST' }),
  winners: (entity: 'query' | 'page', days: number) =>
    request<{ items: Winner[]; period: unknown }>(`/winners?entity=${entity}&days=${days}`),
  losers: (entity: 'query' | 'page', days: number) =>
    request<{ items: Loser[]; period: unknown }>(`/losers?entity=${entity}&days=${days}`),
  opportunities: () => request<{ items: Opportunity[] }>('/opportunities'),
  rescanOpportunities: () => request<{ queued: boolean }>('/opportunities/rescan', { method: 'POST' }),
  updateOpportunity: (id: number, status: string) =>
    request<{ items: Opportunity[] }>(`/opportunities/${id}`, { method: 'PATCH', body: JSON.stringify({ status }) }),
  alerts: (status = 'active') =>
    request<{ items: Alert[]; counts: Record<string, number> }>(`/alerts?status=${status}`),
  updateAlert: (id: number, status: string) =>
    request<{ items: Alert[]; counts: Record<string, number> }>(`/alerts/${id}`, {
      method: 'PATCH',
      body: JSON.stringify({ status }),
    }),
  roadmap: (scope: string) => request<{ items: RoadmapTask[]; scope: string }>(`/roadmap?scope=${scope}`),
  generateRoadmap: (scope: string) =>
    request<{ items: RoadmapTask[]; scope: string }>('/roadmap/generate', {
      method: 'POST',
      body: JSON.stringify({ scope }),
    }),
  syncRoadmap: (scope: string) =>
    request<{ pushed: number; skipped: number; provider: string }>('/roadmap/sync', {
      method: 'POST',
      body: JSON.stringify({ scope }),
    }),
  updateTask: (id: number, status: string, scope: string) =>
    request<{ items: RoadmapTask[]; scope: string }>(`/roadmap/tasks/${id}`, {
      method: 'PATCH',
      body: JSON.stringify({ status, scope }),
    }),
  explain: (entity: 'query' | 'page', hash: string, kind: string) =>
    request<ExplainResponse>('/insights/explain', {
      method: 'POST',
      body: JSON.stringify({ entity, hash, kind }),
    }),
  licenseStatus: () => request<LicenseStatusResponse>('/license/status'),
  activateLicense: (licenseKey: string) =>
    request<LicenseStatusResponse>('/license/activate', {
      method: 'POST',
      body: JSON.stringify({ license_key: licenseKey }),
    }),
  deactivateLicense: () => request<LicenseStatusResponse>('/license/deactivate', { method: 'POST' }),
  contentMeta: (hash: string) =>
    request<MetaSuggestion>('/content/meta', { method: 'POST', body: JSON.stringify({ hash }) }),
  contentGap: () => request<{ topics: ContentGapTopic[] }>('/content/gap', { method: 'POST' }),
  contentBrief: (keyword: string) =>
    request<ContentBrief>('/content/brief', { method: 'POST', body: JSON.stringify({ keyword }) }),
  contentPosts: () => request<{ posts: PostRef[] }>('/content/posts'),
  contentLinks: (postId?: number) =>
    request<{ mode: string; items: LinkSuggestionGroup[] }>(`/content/links${postId ? `?post_id=${postId}` : ''}`),
  contentAudit: (force = false) => request<AuditResult>(`/content/audit${force ? '?force=true' : ''}`),
  contentSchemaPreview: (postId: number) => request<SchemaBuildResult>(`/content/schema?post_id=${postId}`),
  contentSchemaSave: (postId: number, types: string[]) =>
    request<SchemaBuildResult>('/content/schema', { method: 'POST', body: JSON.stringify({ post_id: postId, types }) }),
  contentSchemaRemove: (postId: number) =>
    request<{ removed: boolean }>(`/content/schema?post_id=${postId}`, { method: 'DELETE' }),
  researchKeywords: (seed: string, lang = 'fa') =>
    request<{ seed: string; keywords: KeywordRow[]; serp_used: boolean }>(
      `/research/keywords?seed=${encodeURIComponent(seed)}&lang=${lang}`,
    ),
  researchCluster: (seed: string, lang = 'fa') =>
    request<ClusterPlan>('/research/cluster', { method: 'POST', body: JSON.stringify({ seed, lang }) }),
  researchCompetitors: () => request<CompetitorOverview>('/research/competitors'),
  researchSerp: (query: string) => request<SerpView>(`/research/serp?query=${encodeURIComponent(query)}`),
  medicalEeat: (postId: number) => request<EeatResult>(`/medical/eeat?post_id=${postId}`),
  medicalEntities: (postId: number) => request<{ entities: MedicalEntity[] }>(`/medical/entities?post_id=${postId}`),
  medicalSchemaSave: (postId: number) =>
    request<{ graph: unknown[]; entities: MedicalEntity[]; saved: boolean }>('/medical/schema', {
      method: 'POST',
      body: JSON.stringify({ post_id: postId }),
    }),
  medicalSchemaRemove: (postId: number) =>
    request<{ removed: boolean }>(`/medical/schema?post_id=${postId}`, { method: 'DELETE' }),
  medicalKnowledgeGraph: (force = false) =>
    request<KnowledgeGraphResult>(`/medical/knowledge-graph${force ? '?force=true' : ''}`),
  contentScore: (postId: number, keyword: string, entities = false) =>
    request<ScoreResult>('/content/score', {
      method: 'POST',
      body: JSON.stringify({ post_id: postId, keyword, entities }),
    }),
  reports: () => request<{ items: ReportRow[] }>('/reports'),
  generateReport: (type: string, formats: string[]) =>
    request<{ items: ReportRow[] }>('/reports', { method: 'POST', body: JSON.stringify({ type, formats }) }),
  reportDownloadUrl: (id: number, format: string): string => {
    const { restUrl, nonce } = boot();
    return `${restUrl}/reports/${id}/download?format=${format}&_wpnonce=${encodeURIComponent(nonce)}`;
  },
  agencySites: () => request<{ items: AgencySite[] }>('/agency/sites'),
  pairSite: (clientName: string, siteUrl: string) =>
    request<PairResult>('/agency/sites/pair', {
      method: 'POST',
      body: JSON.stringify({ client_name: clientName, site_url: siteUrl }),
    }),
  unpairSite: (id: number) => request<{ items: AgencySite[] }>(`/agency/sites/${id}`, { method: 'DELETE' }),
};
