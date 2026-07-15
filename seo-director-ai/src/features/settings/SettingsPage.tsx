import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { api, ConnectionsState } from '../../api/client';

function GoogleCard({ state }: { state: ConnectionsState }) {
  const [clientId, setClientId] = useState('');
  const [clientSecret, setClientSecret] = useState('');
  const queryClient = useQueryClient();

  const start = useMutation({
    mutationFn: () => api.startGoogleOAuth(clientId, clientSecret),
    onSuccess: ({ authorize_url }) => {
      window.location.href = authorize_url;
    },
  });

  const disconnect = useMutation({
    mutationFn: () => api.disconnect('google'),
    onSuccess: (next) => queryClient.setQueryData(['connections'], next),
  });

  const connected = state.google.status === 'connected';

  return (
    <div className="sda-card">
      <h2>Google (Search Console + Analytics)</h2>
      {connected ? (
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBlockStart: 8 }}>
          <span className="sda-badge sda-badge--ok">Connected ✓</span>
          <button type="button" className="sda-btn" onClick={() => disconnect.mutate()} disabled={disconnect.isPending}>
            Disconnect
          </button>
        </div>
      ) : (
        <div style={{ display: 'grid', gap: 8, marginBlockStart: 8 }}>
          {state.google.status !== 'disconnected' && (
            <p style={{ color: 'var(--sda-warning)', fontSize: 12, margin: 0 }}>
              Connection state: {state.google.status} — reconnect below.
            </p>
          )}
          <p style={{ fontSize: 12, color: 'var(--sda-text-muted)', margin: 0 }}>
            Create an OAuth client in Google Cloud Console and register this redirect URI:
            <code style={{ display: 'block', marginBlockStart: 4, wordBreak: 'break-all' }}>{state.google.redirect_uri}</code>
          </p>
          <input
            className="sda-input"
            placeholder="OAuth Client ID"
            value={clientId}
            onChange={(e) => setClientId(e.target.value)}
          />
          <input
            className="sda-input"
            type="password"
            placeholder="OAuth Client Secret"
            value={clientSecret}
            onChange={(e) => setClientSecret(e.target.value)}
          />
          <div>
            <button
              type="button"
              className="sda-btn sda-btn--primary"
              disabled={!clientId || !clientSecret || start.isPending}
              onClick={() => start.mutate()}
            >
              {start.isPending ? 'Redirecting…' : 'Connect Google'}
            </button>
          </div>
          {start.isError && <p style={{ color: 'var(--sda-negative)', fontSize: 12 }}>{(start.error as Error).message}</p>}
        </div>
      )}
    </div>
  );
}

function PropertyCard({ state, service, title }: { state: ConnectionsState; service: 'gsc' | 'ga4'; title: string }) {
  const queryClient = useQueryClient();
  const candidates = state.properties.filter((p) => p.service === service);
  const active = candidates.find((p) => p.is_active);
  const sync = state.sync[service];

  const select = useMutation({
    mutationFn: (propertyId: number) => api.selectProperty(service, propertyId),
    onSuccess: (next) => queryClient.setQueryData(['connections'], next),
  });

  return (
    <div className="sda-card">
      <h2>{title}</h2>
      {candidates.length === 0 ? (
        <div className="sda-empty">Connect Google first — properties appear here.</div>
      ) : (
        <div style={{ display: 'grid', gap: 8, marginBlockStart: 8 }}>
          <select
            className="sda-input"
            value={active?.id ?? ''}
            onChange={(e) => select.mutate(Number(e.target.value))}
            disabled={select.isPending}
          >
            <option value="" disabled>
              Select a property to start syncing…
            </option>
            {candidates.map((p) => (
              <option key={p.id} value={p.id}>
                {p.display_name}
              </option>
            ))}
          </select>
          {active && sync && (
            <p style={{ fontSize: 12, color: 'var(--sda-text-muted)', margin: 0 }}>
              Sync: <strong>{sync.status}</strong>
              {typeof sync.cursor?.date === 'string' ? ` · processing ${sync.cursor.date}` : ''}
              {sync.fail_count > 0 ? ` · ${sync.fail_count} recent failure(s)` : ''}
            </p>
          )}
        </div>
      )}
    </div>
  );
}

function KeyCard({ state, service, title, hint }: { state: ConnectionsState; service: string; title: string; hint: string }) {
  const [key, setKey] = useState('');
  const queryClient = useQueryClient();

  const save = useMutation({
    mutationFn: () => api.saveKey(service, key),
    onSuccess: (next) => {
      queryClient.setQueryData(['connections'], next);
      setKey('');
    },
  });

  const connected = state.keys[service];

  return (
    <div className="sda-card">
      <h2>{title}</h2>
      <div style={{ display: 'flex', gap: 8, marginBlockStart: 8, alignItems: 'center' }}>
        {connected && <span className="sda-badge sda-badge--ok">Saved ✓</span>}
        <input
          className="sda-input"
          type="password"
          placeholder={connected ? 'Replace key…' : hint}
          value={key}
          onChange={(e) => setKey(e.target.value)}
          style={{ flex: 1 }}
        />
        <button type="button" className="sda-btn" disabled={!key || save.isPending} onClick={() => save.mutate()}>
          Save
        </button>
      </div>
    </div>
  );
}

function AiProviderCard() {
  const queryClient = useQueryClient();
  const { data } = useQuery({ queryKey: ['settings'], queryFn: api.settings });

  const save = useMutation({
    mutationFn: (patch: Record<string, unknown>) => api.updateSettings(patch),
    onSuccess: (next) => queryClient.setQueryData(['settings'], next),
  });

  if (!data) return null;
  const provider = (data.settings.ai_provider as string) ?? '';
  const cap = (data.settings.ai_monthly_token_cap as number) ?? 0;
  const meter = data.ai.meter;
  const pct = meter.cap > 0 ? Math.min(100, Math.round((meter.used / meter.cap) * 100)) : 0;

  return (
    <div className="sda-card">
      <h2>AI Provider</h2>
      <div style={{ display: 'grid', gap: 10, marginBlockStart: 8 }}>
        <label style={{ fontSize: 12, color: 'var(--sda-text-muted)' }}>
          Preferred provider (falls back to any other configured one)
          <select
            className="sda-input"
            style={{ marginBlockStart: 4 }}
            value={provider}
            onChange={(e) => save.mutate({ ai_provider: e.target.value })}
          >
            <option value="">Auto (first configured)</option>
            <option value="claude">Anthropic Claude</option>
            <option value="openai">OpenAI</option>
            <option value="gemini">Google Gemini</option>
          </select>
        </label>

        <label style={{ fontSize: 12, color: 'var(--sda-text-muted)' }}>
          Monthly token cap (0 = unlimited)
          <input
            className="sda-input"
            style={{ marginBlockStart: 4 }}
            type="number"
            defaultValue={cap}
            onBlur={(e) => save.mutate({ ai_monthly_token_cap: Number(e.target.value) })}
          />
        </label>

        <div>
          <div style={{ fontSize: 12, color: 'var(--sda-text-muted)', marginBlockEnd: 4 }}>
            Used this month: {meter.used.toLocaleString()}
            {meter.cap > 0 ? ` / ${meter.cap.toLocaleString()} (${pct}%)` : ' (uncapped)'}
          </div>
          {meter.cap > 0 && (
            <div style={{ height: 6, background: 'var(--sda-surface-2)', borderRadius: 3 }}>
              <div style={{ width: `${pct}%`, height: '100%', background: pct >= 90 ? 'var(--sda-negative)' : 'var(--sda-primary)', borderRadius: 3 }} />
            </div>
          )}
        </div>

        <span className={`sda-badge ${data.ai.available ? 'sda-badge--ok' : 'sda-badge--off'}`}>
          {data.ai.available ? 'AI ready ✓' : 'AI unavailable — add a key or raise the cap'}
        </span>
      </div>
    </div>
  );
}

export function SettingsPage() {
  const { data, isLoading, error } = useQuery({ queryKey: ['connections'], queryFn: api.connections });

  if (isLoading) {
    return <div className="sda-card sda-skeleton" style={{ height: 200 }} />;
  }

  if (error || !data) {
    return (
      <div className="sda-card sda-empty">
        <strong>Could not load connection state</strong>
        {(error as Error)?.message}
      </div>
    );
  }

  return (
    <div className="sda-grid" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(340px, 1fr))' }}>
      <GoogleCard state={data} />
      <PropertyCard state={data} service="gsc" title="Search Console Property" />
      <PropertyCard state={data} service="ga4" title="Analytics 4 Property" />
      <KeyCard state={data} service="psi" title="PageSpeed Insights" hint="Google API key (optional but recommended)" />
      <KeyCard state={data} service="claude" title="AI — Anthropic Claude" hint="sk-ant-…" />
      <KeyCard state={data} service="openai" title="AI — OpenAI" hint="sk-…" />
      <KeyCard state={data} service="gemini" title="AI — Google Gemini" hint="API key" />
      <AiProviderCard />
    </div>
  );
}
