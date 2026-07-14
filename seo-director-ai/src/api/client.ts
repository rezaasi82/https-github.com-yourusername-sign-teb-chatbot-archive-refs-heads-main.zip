/**
 * Typed REST client for sda/v1. All requests carry the WP REST nonce.
 */

export interface BootData {
  restUrl: string;
  nonce: string;
  locale: string;
  isRtl: boolean;
  canManage: boolean;
  version: string;
  siteName: string;
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

export interface OverviewResponse {
  connections: { gsc: boolean; ga4: boolean; psi: boolean; ai: boolean };
  health: { score: number; band: 'green' | 'yellow' | 'red'; delta: number } | null;
  traffic: {
    series: TrafficPoint[];
    compare: unknown;
  };
  opportunities: unknown[];
  risks: unknown[];
  summaries: { weekly: string | null; monthly: string | null };
  meta: { plugin_version: string; backfill: { status: string; date: string | null } | null };
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
  settings: () => request<{ settings: Record<string, unknown> }>('/settings'),
  updateSettings: (settings: Record<string, unknown>) =>
    request<{ settings: Record<string, unknown> }>('/settings', {
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
};
