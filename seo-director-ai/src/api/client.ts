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

export interface OverviewResponse {
  connections: { gsc: boolean; ga4: boolean; psi: boolean; ai: boolean };
  health: { score: number; band: 'green' | 'yellow' | 'red'; delta: number } | null;
  traffic: {
    series: Array<{ date: string; clicks: number; impressions: number; ctr: number; position: number }>;
    compare: unknown;
  };
  opportunities: unknown[];
  risks: unknown[];
  summaries: { weekly: string | null; monthly: string | null };
  meta: { plugin_version: string; backfill: { progress: number } | null };
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
};
