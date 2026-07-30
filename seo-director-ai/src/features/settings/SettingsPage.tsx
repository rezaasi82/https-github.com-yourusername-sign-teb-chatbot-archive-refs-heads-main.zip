import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { api, boot, ConnectionsState, type LicenseStatusResponse } from '../../api/client';
import { t } from '../../i18n';

function LicenseCard() {
  const queryClient = useQueryClient();
  const { data } = useQuery({ queryKey: ['license'], queryFn: api.licenseStatus });
  const [key, setKey] = useState('');

  const activate = useMutation({
    mutationFn: () => api.activateLicense(key),
    onSuccess: (next: LicenseStatusResponse) => {
      queryClient.setQueryData(['license'], next);
      setKey('');
    },
  });

  const deactivate = useMutation({
    mutationFn: api.deactivateLicense,
    onSuccess: (next: LicenseStatusResponse) => queryClient.setQueryData(['license'], next),
  });

  if (!data) return null;
  const { license, edition } = data;

  const stateBadge: Record<string, string> = {
    active: 'sda-badge--ok',
    grace: 'sda-badge--off',
    expired: 'sda-badge--off',
    none: 'sda-badge--off',
  };

  return (
    <div className="sda-card">
      <h2>{t('License')}</h2>
      <div style={{ display: 'grid', gap: 10, marginBlockStart: 8 }}>
        <div style={{ display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>
          <span className={`sda-badge ${stateBadge[license.state] ?? 'sda-badge--off'}`}>
            {license.state === 'active' ? 'Active ✓' : license.state === 'grace' ? 'Grace period' : license.state === 'expired' ? 'Expired' : 'Not activated'}
          </span>
          <strong style={{ textTransform: 'capitalize' }}>{edition}</strong>
          {license.days_left != null && license.state !== 'none' && (
            <span style={{ fontSize: 12, color: license.in_grace ? 'var(--sda-warning)' : 'var(--sda-text-muted)' }}>
              {license.days_left} day(s) {license.in_grace ? 'left in grace' : 'remaining'}
            </span>
          )}
        </div>

        {license.in_grace && (
          <p style={{ fontSize: 12, color: 'var(--sda-warning)', margin: 0 }}>
            Your license has expired but Pro features remain active during the grace period. Renew to avoid interruption.
          </p>
        )}

        {boot().canManage &&
          (license.has_license ? (
            <div>
              <button type="button" className="sda-btn" onClick={() => deactivate.mutate()} disabled={deactivate.isPending}>
                {deactivate.isPending ? 'Deactivating…' : 'Deactivate on this domain'}
              </button>
            </div>
          ) : (
            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
              <input
                className="sda-input"
                style={{ flex: 1, minWidth: 200 }}
                placeholder="License key"
                value={key}
                onChange={(e) => setKey(e.target.value.trim())}
              />
              <button
                type="button"
                className="sda-btn sda-btn--primary"
                disabled={key === '' || activate.isPending}
                onClick={() => activate.mutate()}
              >
                {activate.isPending ? 'Activating…' : 'Activate'}
              </button>
            </div>
          ))}

        {activate.isError && <p style={{ color: 'var(--sda-negative)', fontSize: 12, margin: 0 }}>{(activate.error as Error).message}</p>}
      </div>
    </div>
  );
}

const ALERT_CHANNEL_FIELDS: Array<{ key: string; label: string; placeholder: string; type?: string }> = [
  { key: 'alert_email', label: 'Alert email', placeholder: 'you@example.com', type: 'email' },
  { key: 'alert_webhook_url', label: 'Webhook URL', placeholder: 'https://…' },
  { key: 'alert_slack_url', label: 'Slack incoming webhook', placeholder: 'https://hooks.slack.com/…' },
  { key: 'alert_telegram_token', label: 'Telegram bot token', placeholder: '123456:ABC…' },
  { key: 'alert_telegram_chat', label: 'Telegram chat ID', placeholder: '-1001234567890' },
  { key: 'alert_bale_token', label: 'Bale bot token', placeholder: '123456:ABC… (@BotFather در بله)' },
  { key: 'alert_bale_chat', label: 'Bale chat id', placeholder: '123456789' },
];

function AlertChannelsCard() {
  const queryClient = useQueryClient();
  const { data } = useQuery({ queryKey: ['settings'], queryFn: api.settings });
  const { data: licenseData } = useQuery({ queryKey: ['license'], queryFn: api.licenseStatus });

  const save = useMutation({
    mutationFn: (patch: Record<string, unknown>) => api.updateSettings(patch),
    onSuccess: (next) => queryClient.setQueryData(['settings'], next),
  });

  if (!data) return null;
  const proChannels = Boolean(licenseData?.features?.alert_channels);

  return (
    <div className="sda-card">
      <h2>{t('Alert channels')}</h2>
      <p style={{ fontSize: 12, color: 'var(--sda-text-muted)', margin: '4px 0 8px' }}>
        {t('Email always works. Webhook, Slack, Telegram and Bale (بله) require a Pro license.')}
      </p>
      <div style={{ display: 'grid', gap: 10 }}>
        {ALERT_CHANNEL_FIELDS.map((field) => {
          const isPro = field.key !== 'alert_email';
          return (
            <label key={field.key} style={{ fontSize: 12, color: 'var(--sda-text-muted)' }}>
              {t(field.label)}
              {isPro && !proChannels && <span style={{ color: 'var(--sda-warning)' }}> · Pro</span>}
              <input
                className="sda-input"
                style={{ marginBlockStart: 4 }}
                type={field.type ?? 'text'}
                defaultValue={(data.settings[field.key] as string) ?? ''}
                placeholder={field.placeholder}
                disabled={isPro && !proChannels}
                onBlur={(e) => save.mutate({ [field.key]: e.target.value })}
              />
            </label>
          );
        })}
      </div>
    </div>
  );
}

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
      <h2>{t('Google (Search Console + Analytics)')}</h2>
      {connected ? (
        <div style={{ display: 'grid', gap: 8, marginBlockStart: 8 }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <span className="sda-badge sda-badge--ok">Connected ✓</span>
            <button type="button" className="sda-btn" onClick={() => disconnect.mutate()} disabled={disconnect.isPending}>
              {t('Disconnect')}
            </button>
          </div>
          <p style={{ fontSize: 12, color: 'var(--sda-text-muted)', margin: 0 }}>
            {t('Google Business Profile and Google Ads use the same Google connection — reconnect Google to grant the new permissions.')}
          </p>
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
      <h2>{t(title)}</h2>
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
      <h2>{t(title)}</h2>
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
      <h2>{t('AI Provider')}</h2>
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
            <option value="gapgpt">GapGPT (گپ‌جی‌پی‌تی)</option>
          </select>
        </label>

        {provider === 'gapgpt' && (
          <label style={{ fontSize: 12, color: 'var(--sda-text-muted)' }}>
            GapGPT model
            <select
              className="sda-input"
              style={{ marginBlockStart: 4 }}
              value={(data.settings.ai_model_gapgpt as string) ?? 'gpt-4o-mini'}
              onChange={(e) => save.mutate({ ai_model_gapgpt: e.target.value })}
            >
              <option value="gpt-4o-mini">gpt-4o-mini (ارزان و سریع)</option>
              <option value="gpt-4o">gpt-4o</option>
              <option value="gpt-4.1-mini">gpt-4.1-mini</option>
              <option value="gpt-4.1">gpt-4.1</option>
              <option value="o4-mini">o4-mini</option>
              <option value="claude-3-5-sonnet">claude-3-5-sonnet</option>
              <option value="gemini-2.0-flash">gemini-2.0-flash</option>
              <option value="deepseek-chat">deepseek-chat</option>
            </select>
          </label>
        )}

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

function WhiteLabelCard() {
  const queryClient = useQueryClient();
  const { data } = useQuery({ queryKey: ['settings'], queryFn: api.settings });
  const { data: licenseData } = useQuery({ queryKey: ['license'], queryFn: api.licenseStatus });

  const save = useMutation({
    mutationFn: (patch: Record<string, unknown>) => api.updateSettings(patch),
    onSuccess: (next) => queryClient.setQueryData(['settings'], next),
  });

  if (!data) return null;
  const allowed = Boolean(licenseData?.features?.white_label);

  return (
    <div className="sda-card">
      <h2>{t('White label')}</h2>
      <p style={{ fontSize: 12, color: 'var(--sda-text-muted)', margin: '4px 0 8px' }}>
        Rebrand the dashboard for your clients. {allowed ? '' : 'Requires an Agency license.'}
      </p>
      <div style={{ display: 'grid', gap: 10, opacity: allowed ? 1 : 0.6 }}>
        <label style={{ fontSize: 12, color: 'var(--sda-text-muted)' }}>
          Brand name (replaces “SEO Director AI”)
          <input
            className="sda-input"
            style={{ marginBlockStart: 4 }}
            defaultValue={(data.settings.brand_name as string) ?? ''}
            placeholder="Acme SEO"
            disabled={!allowed}
            onBlur={(e) => save.mutate({ brand_name: e.target.value })}
          />
        </label>
        <label style={{ fontSize: 12, color: 'var(--sda-text-muted)' }}>
          Logo URL
          <input
            className="sda-input"
            style={{ marginBlockStart: 4 }}
            defaultValue={(data.settings.brand_logo_url as string) ?? ''}
            placeholder="https://…/logo.svg"
            disabled={!allowed}
            onBlur={(e) => save.mutate({ brand_logo_url: e.target.value })}
          />
        </label>
        <label style={{ fontSize: 12, color: 'var(--sda-text-muted)' }}>
          Primary color
          <input
            className="sda-input"
            style={{ marginBlockStart: 4 }}
            type="text"
            defaultValue={(data.settings.brand_primary_color as string) ?? ''}
            placeholder="#2563eb"
            disabled={!allowed}
            onBlur={(e) => save.mutate({ brand_primary_color: e.target.value })}
          />
        </label>
        <label style={{ fontSize: 12, color: 'var(--sda-text-muted)', display: 'flex', gap: 8, alignItems: 'center' }}>
          <input
            type="checkbox"
            defaultChecked={Boolean(data.settings.brand_hide_powered_by)}
            disabled={!allowed}
            onChange={(e) => save.mutate({ brand_hide_powered_by: e.target.checked })}
          />
          Hide “powered by” footer
        </label>
        <label style={{ fontSize: 12, color: 'var(--sda-text-muted)' }}>
          Terminology overrides (one <code>key=value</code> per line)
          <textarea
            className="sda-input"
            style={{ marginBlockStart: 4, minHeight: 72, fontFamily: 'monospace' }}
            defaultValue={(data.settings.brand_string_overrides as string) ?? ''}
            placeholder={'provider=Consultant\nbooking=Session'}
            disabled={!allowed}
            onBlur={(e) => save.mutate({ brand_string_overrides: e.target.value })}
          />
        </label>
      </div>
    </div>
  );
}

function ClientModeCard() {
  const queryClient = useQueryClient();
  const { data } = useQuery({ queryKey: ['settings'], queryFn: api.settings });

  const save = useMutation({
    mutationFn: (patch: Record<string, unknown>) => api.updateSettings(patch),
    onSuccess: (next) => queryClient.setQueryData(['settings'], next),
  });

  if (!data) return null;
  const keySet = Boolean(data.secrets_set?.agency_pair_key);

  return (
    <div className="sda-card">
      <h2>{t('Agency (client mode)')}</h2>
      <p style={{ fontSize: 12, color: 'var(--sda-text-muted)', margin: '4px 0 8px' }}>
        Push this site’s daily snapshot to an agency hub. Paste the ingest URL and pairing key your agency generated.
      </p>
      <div style={{ display: 'grid', gap: 10 }}>
        <label style={{ fontSize: 12, color: 'var(--sda-text-muted)' }}>
          Hub ingest URL
          <input
            className="sda-input"
            style={{ marginBlockStart: 4 }}
            defaultValue={(data.settings.agency_hub_url as string) ?? ''}
            placeholder="https://agency.com/wp-json/sda/v1/hub/ingest"
            onBlur={(e) => save.mutate({ agency_hub_url: e.target.value })}
          />
        </label>
        <label style={{ fontSize: 12, color: 'var(--sda-text-muted)' }}>
          Pairing key {keySet && <span className="sda-badge sda-badge--ok">saved ✓</span>}
          <input
            className="sda-input"
            style={{ marginBlockStart: 4, fontFamily: 'monospace' }}
            type="password"
            placeholder={keySet ? 'Replace key…' : 'Paste pairing key'}
            onBlur={(e) => e.target.value && save.mutate({ agency_pair_key: e.target.value })}
          />
        </label>
      </div>
    </div>
  );
}

const BRAND_VOICE_TONES = ['professional', 'friendly', 'authoritative', 'playful', 'concise', 'technical'];

function EnterpriseCard() {
  const queryClient = useQueryClient();
  const { data } = useQuery({ queryKey: ['settings'], queryFn: api.settings });
  const { data: licenseData } = useQuery({ queryKey: ['license'], queryFn: api.licenseStatus });

  const save = useMutation({
    mutationFn: (patch: Record<string, unknown>) => api.updateSettings(patch),
    onSuccess: (next) => queryClient.setQueryData(['settings'], next),
  });

  if (!data) return null;
  const f = licenseData?.features ?? {};
  const s = data.settings;
  const secretSet = data.secrets_set ?? {};
  const anyEnterprise = f.brand_voice || f.sla_alerting || f.task_sync || f.serp_enrichment;

  const provider = (s.task_sync_provider as string) ?? '';

  return (
    <div className="sda-card">
      <h2>{t('Enterprise')}</h2>
      <p style={{ fontSize: 12, color: 'var(--sda-text-muted)', margin: '4px 0 10px' }}>
        {anyEnterprise ? 'Advanced controls for Enterprise accounts.' : 'These controls require an Enterprise license.'}
      </p>

      <div style={{ display: 'grid', gap: 16, opacity: anyEnterprise ? 1 : 0.6 }}>
        <fieldset style={{ border: '1px solid var(--sda-border)', borderRadius: 8, padding: 12 }}>
          <legend style={{ fontSize: 12, color: 'var(--sda-text-muted)' }}>Brand voice</legend>
          <div style={{ display: 'grid', gap: 8 }}>
            <select
              className="sda-input"
              defaultValue={(s.brand_voice_tone as string) ?? ''}
              disabled={!f.brand_voice}
              onChange={(e) => save.mutate({ brand_voice_tone: e.target.value })}
            >
              <option value="">Default tone</option>
              {BRAND_VOICE_TONES.map((t) => (
                <option key={t} value={t}>
                  {t}
                </option>
              ))}
            </select>
            <input
              className="sda-input"
              placeholder="Audience (e.g. B2B founders)"
              defaultValue={(s.brand_voice_audience as string) ?? ''}
              disabled={!f.brand_voice}
              onBlur={(e) => save.mutate({ brand_voice_audience: e.target.value })}
            />
            <textarea
              className="sda-input"
              style={{ minHeight: 56 }}
              placeholder="Style notes"
              defaultValue={(s.brand_voice_notes as string) ?? ''}
              disabled={!f.brand_voice}
              onBlur={(e) => save.mutate({ brand_voice_notes: e.target.value })}
            />
            <input
              className="sda-input"
              placeholder="Words to avoid (comma-separated)"
              defaultValue={(s.brand_voice_avoid as string) ?? ''}
              disabled={!f.brand_voice}
              onBlur={(e) => save.mutate({ brand_voice_avoid: e.target.value })}
            />
          </div>
        </fieldset>

        <label style={{ fontSize: 12, color: 'var(--sda-text-muted)' }}>
          SLA escalation — hours a critical alert may stay open
          <input
            className="sda-input"
            style={{ marginBlockStart: 4 }}
            type="number"
            min={1}
            max={168}
            defaultValue={(s.sla_escalation_hours as number) ?? 24}
            disabled={!f.sla_alerting}
            onBlur={(e) => save.mutate({ sla_escalation_hours: Number(e.target.value) })}
          />
        </label>

        <fieldset style={{ border: '1px solid var(--sda-border)', borderRadius: 8, padding: 12 }}>
          <legend style={{ fontSize: 12, color: 'var(--sda-text-muted)' }}>Task sync</legend>
          <div style={{ display: 'grid', gap: 8 }}>
            <select
              className="sda-input"
              defaultValue={provider}
              disabled={!f.task_sync}
              onChange={(e) => save.mutate({ task_sync_provider: e.target.value })}
            >
              <option value="">Off</option>
              <option value="jira">Jira</option>
              <option value="trello">Trello</option>
            </select>
            {provider === 'jira' && (
              <>
                <input className="sda-input" placeholder="Jira base URL" defaultValue={(s.jira_base_url as string) ?? ''} disabled={!f.task_sync} onBlur={(e) => save.mutate({ jira_base_url: e.target.value })} />
                <input className="sda-input" placeholder="Account email" defaultValue={(s.jira_email as string) ?? ''} disabled={!f.task_sync} onBlur={(e) => save.mutate({ jira_email: e.target.value })} />
                <input className="sda-input" type="password" placeholder={secretSet.jira_token ? 'API token saved — replace…' : 'API token'} disabled={!f.task_sync} onBlur={(e) => e.target.value && save.mutate({ jira_token: e.target.value })} />
                <input className="sda-input" placeholder="Project key (e.g. SEO)" defaultValue={(s.jira_project_key as string) ?? ''} disabled={!f.task_sync} onBlur={(e) => save.mutate({ jira_project_key: e.target.value })} />
              </>
            )}
            {provider === 'trello' && (
              <>
                <input className="sda-input" placeholder="Trello key" defaultValue={(s.trello_key as string) ?? ''} disabled={!f.task_sync} onBlur={(e) => save.mutate({ trello_key: e.target.value })} />
                <input className="sda-input" type="password" placeholder={secretSet.trello_token ? 'Token saved — replace…' : 'Token'} disabled={!f.task_sync} onBlur={(e) => e.target.value && save.mutate({ trello_token: e.target.value })} />
                <input className="sda-input" placeholder="List ID" defaultValue={(s.trello_list_id as string) ?? ''} disabled={!f.task_sync} onBlur={(e) => save.mutate({ trello_list_id: e.target.value })} />
              </>
            )}
          </div>
        </fieldset>

        <fieldset style={{ border: '1px solid var(--sda-border)', borderRadius: 8, padding: 12 }}>
          <legend style={{ fontSize: 12, color: 'var(--sda-text-muted)' }}>SERP enrichment (root cause)</legend>
          <div style={{ display: 'grid', gap: 8 }}>
            <select
              className="sda-input"
              defaultValue={(s.serp_provider as string) ?? ''}
              disabled={!f.serp_enrichment}
              onChange={(e) => save.mutate({ serp_provider: e.target.value })}
            >
              <option value="">Off</option>
              <option value="serpapi">SerpApi</option>
            </select>
            <input
              className="sda-input"
              type="password"
              placeholder={secretSet.serp_api_key ? 'API key saved — replace…' : 'SerpApi API key'}
              disabled={!f.serp_enrichment}
              onBlur={(e) => e.target.value && save.mutate({ serp_api_key: e.target.value })}
            />
          </div>
        </fieldset>
      </div>
    </div>
  );
}

function SyncNowCard({ state }: { state: ConnectionsState }) {
  const queryClient = useQueryClient();

  const sync = useMutation({
    mutationFn: api.syncNow,
    onSuccess: (next) => queryClient.setQueryData(['connections'], next),
  });

  const anyProperty = state.properties.some((p) => p.is_active);
  const running = Object.values(state.sync).some((s) => s?.status === 'running');

  return (
    <div className="sda-card">
      <h2>{t('Data sync')}</h2>
      <p style={{ fontSize: 12, color: 'var(--sda-text-muted)', margin: '4px 0 8px' }}>
        {t('Data refreshes automatically once a day. Use this to pull the latest Search Console / Analytics numbers right now.')}
      </p>
      <div style={{ display: 'grid', gap: 8 }}>
        {(['gsc', 'ga4'] as const).map((service) => {
          const s = state.sync[service];
          if (!s) return null;
          return (
            <p key={service} style={{ fontSize: 12, color: 'var(--sda-text-muted)', margin: 0 }}>
              {service === 'gsc' ? t('Search Console') : t('Analytics 4')}: <strong>{s.status === 'running' ? t('running…') : s.status === 'completed' ? t('up to date') : s.status}</strong>
              {typeof s.cursor?.date === 'string' ? ` · ${s.cursor.date}` : ''}
            </p>
          );
        })}
        <div>
          <button
            type="button"
            className="sda-btn sda-btn--primary"
            disabled={!anyProperty || running || sync.isPending}
            onClick={() => sync.mutate()}
          >
            {running || sync.isPending ? t('Syncing…') : t('Sync now')}
          </button>
          {!anyProperty && (
            <p style={{ fontSize: 12, color: 'var(--sda-text-muted)', margin: '6px 0 0' }}>{t('Select a property first.')}</p>
          )}
        </div>
        {sync.isError && <p style={{ color: 'var(--sda-negative)', fontSize: 12, margin: 0 }}>{(sync.error as Error).message}</p>}
      </div>
    </div>
  );
}

function GoogleAdsCard() {
  const queryClient = useQueryClient();
  const { data } = useQuery({ queryKey: ['settings'], queryFn: api.settings });

  const save = useMutation({
    mutationFn: (patch: Record<string, unknown>) => api.updateSettings(patch),
    onSuccess: (next) => queryClient.setQueryData(['settings'], next),
  });

  if (!data) return null;

  return (
    <div className="sda-card">
      <h2>{t('Google Ads')}</h2>
      <p style={{ fontSize: 12, color: 'var(--sda-text-muted)', margin: '4px 0 8px' }}>
        {t('Google Ads needs a developer token and the account’s customer id; it uses the same Google connection above.')}
      </p>
      <div style={{ display: 'grid', gap: 10 }}>
        <label style={{ fontSize: 12, color: 'var(--sda-text-muted)' }}>
          {t('Developer token')}
          <input
            className="sda-input"
            style={{ marginBlockStart: 4 }}
            type="password"
            defaultValue={(data.settings.gads_developer_token as string) ?? ''}
            placeholder="dev-token…"
            onBlur={(e) => save.mutate({ gads_developer_token: e.target.value })}
          />
        </label>
        <label style={{ fontSize: 12, color: 'var(--sda-text-muted)' }}>
          {t('Customer ID')}
          <input
            className="sda-input"
            style={{ marginBlockStart: 4 }}
            defaultValue={(data.settings.gads_customer_id as string) ?? ''}
            placeholder="123-456-7890"
            onBlur={(e) => save.mutate({ gads_customer_id: e.target.value })}
          />
        </label>
      </div>
    </div>
  );
}

function AutoUpdateCard() {
  const queryClient = useQueryClient();
  const { data } = useQuery({ queryKey: ['settings'], queryFn: api.settings });

  const save = useMutation({
    mutationFn: (patch: Record<string, unknown>) => api.updateSettings(patch),
    onSuccess: (next) => queryClient.setQueryData(['settings'], next),
  });

  if (!data) return null;

  return (
    <div className="sda-card">
      <h2>{t('Automatic updates')}</h2>
      <label style={{ fontSize: 13, color: 'var(--sda-text)', display: 'flex', gap: 8, alignItems: 'center', marginBlockStart: 8 }}>
        <input
          type="checkbox"
          defaultChecked={Boolean(data.settings.auto_update ?? true)}
          onChange={(e) => save.mutate({ auto_update: e.target.checked })}
        />
        {t('Update automatically when a new version is released')}
      </label>
    </div>
  );
}

function MedicalCard() {
  const queryClient = useQueryClient();
  const { data } = useQuery({ queryKey: ['settings'], queryFn: api.settings });
  const { data: licenseData } = useQuery({ queryKey: ['license'], queryFn: api.licenseStatus });

  const save = useMutation({
    mutationFn: (patch: Record<string, unknown>) => api.updateSettings(patch),
    onSuccess: (next) => queryClient.setQueryData(['settings'], next),
  });

  if (!data) return null;
  const allowed = Boolean(licenseData?.features?.medical_pack);
  const on = Boolean(data.settings.medical_mode);

  return (
    <div className="sda-card">
      <h2>{t('Medical mode (Medical Pack)')}</h2>
      <p style={{ fontSize: 12, color: 'var(--sda-text-muted)', margin: '4px 0 8px' }}>
        {allowed
          ? t('Turn this on for medical/clinic sites to unlock E-E-A-T analysis, medical entity detection, medical schema, and the knowledge graph. Reload the dashboard after toggling.')
          : t('The Medical Pack requires a Pro license.')}
      </p>
      <div style={{ display: 'grid', gap: 10, opacity: allowed ? 1 : 0.6 }}>
        <label style={{ fontSize: 13, color: 'var(--sda-text)', display: 'flex', gap: 8, alignItems: 'center' }}>
          <input
            type="checkbox"
            defaultChecked={on}
            disabled={!allowed}
            onChange={(e) => save.mutate({ medical_mode: e.target.checked })}
          />
          {t('Enable medical mode')}
        </label>

        <label style={{ fontSize: 12, color: 'var(--sda-text-muted)' }}>
          {t('Specialty dictionary (sharpens entity detection)')}
          <select
            className="sda-input"
            style={{ marginBlockStart: 4 }}
            defaultValue={(data.settings.med_specialty_preset as string) ?? 'general'}
            disabled={!allowed}
            onChange={(e) => save.mutate({ med_specialty_preset: e.target.value })}
          >
            <option value="general">{t('General medical')}</option>
            <option value="gastro_hepatology">{t('Gastroenterology & hepatology (گوارش و کبد)')}</option>
            <option value="general_surgery">{t('General & bariatric surgery (جراحی عمومی و چاقی)')}</option>
            <option value="hand_shoulder_elbow">{t('Hand, shoulder & elbow surgery (دست، شانه و آرنج)')}</option>
            <option value="medical_marketing">{t('Medical web design, branding & SEO (ساین‌طب)')}</option>
          </select>
        </label>

        <fieldset style={{ border: '1px solid var(--sda-border)', borderRadius: 8, padding: 12 }}>
          <legend style={{ fontSize: 12, color: 'var(--sda-text-muted)' }}>{t('Physician & clinic (for schema)')}</legend>
          <div style={{ display: 'grid', gap: 8 }}>
            <input className="sda-input" placeholder={t('Physician name')} defaultValue={(data.settings.med_physician_name as string) ?? ''} disabled={!allowed} onBlur={(e) => save.mutate({ med_physician_name: e.target.value })} />
            <input className="sda-input" placeholder={t('Specialty (e.g. جراح عمومی)')} defaultValue={(data.settings.med_physician_specialty as string) ?? ''} disabled={!allowed} onBlur={(e) => save.mutate({ med_physician_specialty: e.target.value })} />
            <input className="sda-input" placeholder={t('Medical license no. (شماره نظام پزشکی)')} defaultValue={(data.settings.med_physician_license as string) ?? ''} disabled={!allowed} onBlur={(e) => save.mutate({ med_physician_license: e.target.value })} />
            <input className="sda-input" placeholder={t('Clinic name')} defaultValue={(data.settings.med_clinic_name as string) ?? ''} disabled={!allowed} onBlur={(e) => save.mutate({ med_clinic_name: e.target.value })} />
            <input className="sda-input" placeholder={t('Clinic phone')} defaultValue={(data.settings.med_clinic_phone as string) ?? ''} disabled={!allowed} onBlur={(e) => save.mutate({ med_clinic_phone: e.target.value })} />
            <textarea className="sda-input" style={{ minHeight: 48 }} placeholder={t('Clinic address')} defaultValue={(data.settings.med_clinic_address as string) ?? ''} disabled={!allowed} onBlur={(e) => save.mutate({ med_clinic_address: e.target.value })} />
          </div>
        </fieldset>
      </div>
    </div>
  );
}

export function SettingsPage() {
  const { data, isLoading, error } = useQuery({
    queryKey: ['connections'],
    queryFn: api.connections,
    // While a sync chain is running, poll so the status line updates live.
    refetchInterval: (query) => {
      const state = query.state.data as ConnectionsState | undefined;
      const running = state != null && Object.values(state.sync).some((s) => s?.status === 'running');
      return running ? 10_000 : false;
    },
  });

  if (isLoading) {
    return <div className="sda-card sda-skeleton" style={{ height: 200 }} />;
  }

  if (error || !data) {
    return (
      <div className="sda-card sda-empty">
        <strong>{t('Could not load connection state')}</strong>
        {(error as Error)?.message}
      </div>
    );
  }

  return (
    <div className="sda-grid" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(340px, 1fr))' }}>
      <GoogleCard state={data} />
      <PropertyCard state={data} service="gsc" title={t('Search Console Property')} />
      <PropertyCard state={data} service="ga4" title={t('Analytics 4 Property')} />
      <SyncNowCard state={data} />
      <KeyCard state={data} service="psi" title="PageSpeed Insights" hint="Google API key (optional but recommended)" />
      <KeyCard state={data} service="claude" title="AI — Anthropic Claude" hint="sk-ant-…" />
      <KeyCard state={data} service="openai" title="AI — OpenAI" hint="sk-…" />
      <KeyCard state={data} service="gemini" title="AI — Google Gemini" hint="API key" />
      <KeyCard state={data} service="gapgpt" title="AI — GapGPT (گپ‌جی‌پی‌تی)" hint="sk-… (gapgpt.app)" />
      <AiProviderCard />
      <GoogleAdsCard />
      <AutoUpdateCard />
      <MedicalCard />
      <LicenseCard />
      <AlertChannelsCard />
      <WhiteLabelCard />
      <ClientModeCard />
      <EnterpriseCard />
    </div>
  );
}
