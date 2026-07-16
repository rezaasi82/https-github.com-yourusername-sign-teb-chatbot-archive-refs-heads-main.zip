import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { api, boot, type AgencySite, type PairResult } from '../../api/client';
import { useLicense } from '../../app/license';

function bandColor(band: string | undefined): string {
  if (band === 'green') return 'var(--sda-positive)';
  if (band === 'yellow') return 'var(--sda-warning)';
  if (band === 'red') return 'var(--sda-negative)';
  return 'var(--sda-text-muted)';
}

function relativeSeen(value: string | null): string {
  if (!value) return 'never';
  const then = Date.parse(value.replace(' ', 'T') + 'Z');
  if (Number.isNaN(then)) return value;
  const hours = Math.floor((Date.now() - then) / 3_600_000);
  if (hours < 1) return 'just now';
  if (hours < 24) return `${hours}h ago`;
  return `${Math.floor(hours / 24)}d ago`;
}

function SiteCard({ site, onRemove }: { site: AgencySite; onRemove: (id: number) => void }) {
  const snap = site.snapshot;
  const stale = site.status !== 'active' || !site.last_seen_at;

  return (
    <div className="sda-card" style={{ display: 'grid', gap: 8 }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'start', gap: 8 }}>
        <div>
          <strong>{site.client_name}</strong>
          <div style={{ fontSize: 12, color: 'var(--sda-text-muted)', wordBreak: 'break-all' }}>{site.site_url}</div>
        </div>
        {boot().canManageClients && (
          <button type="button" className="sda-btn" aria-label={`Unpair ${site.client_name}`} onClick={() => onRemove(site.id)}>
            ✕
          </button>
        )}
      </div>

      <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
        <span className={`sda-badge ${site.status === 'active' ? 'sda-badge--ok' : 'sda-badge--off'}`}>
          {site.status === 'active' ? 'Active' : 'Pending pairing'}
        </span>
        <span style={{ fontSize: 12, color: 'var(--sda-text-muted)' }}>seen {relativeSeen(site.last_seen_at)}</span>
      </div>

      {snap ? (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 8, marginBlockStart: 4 }}>
          <div>
            <div style={{ fontSize: 22, fontWeight: 700, color: bandColor(snap.health?.band) }}>
              {snap.health ? snap.health.score : '—'}
            </div>
            <div style={{ fontSize: 11, color: 'var(--sda-text-muted)' }}>Health</div>
          </div>
          <div>
            <div style={{ fontSize: 22, fontWeight: 700 }}>{snap.traffic ? snap.traffic.clicks.toLocaleString() : '—'}</div>
            <div style={{ fontSize: 11, color: 'var(--sda-text-muted)' }}>
              Clicks 28d
              {snap.traffic?.change_pct != null && (
                <span style={{ color: snap.traffic.change_pct >= 0 ? 'var(--sda-positive)' : 'var(--sda-negative)' }}>
                  {' '}
                  {snap.traffic.change_pct >= 0 ? '+' : ''}
                  {snap.traffic.change_pct}%
                </span>
              )}
            </div>
          </div>
          <div>
            <div style={{ fontSize: 22, fontWeight: 700, color: snap.alerts.total > 0 ? 'var(--sda-negative)' : undefined }}>
              {snap.alerts.total}
            </div>
            <div style={{ fontSize: 11, color: 'var(--sda-text-muted)' }}>Alerts</div>
          </div>
        </div>
      ) : (
        <div style={{ fontSize: 12, color: 'var(--sda-text-muted)' }}>
          {stale ? 'Waiting for the first snapshot from this site.' : 'No data yet.'}
        </div>
      )}
    </div>
  );
}

function PairForm({ onPaired }: { onPaired: (result: PairResult) => void }) {
  const [name, setName] = useState('');
  const [url, setUrl] = useState('');

  const pair = useMutation<PairResult, Error, void>({
    mutationFn: () => api.pairSite(name, url),
    onSuccess: (result) => {
      onPaired(result);
      setName('');
      setUrl('');
    },
  });

  return (
    <div className="sda-card">
      <h2 style={{ marginBlockStart: 0 }}>Add a client site</h2>
      <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
        <input className="sda-input" style={{ flex: 1, minWidth: 160 }} placeholder="Client name" value={name} onChange={(e) => setName(e.target.value)} />
        <input className="sda-input" style={{ flex: 2, minWidth: 220 }} placeholder="https://client-site.com" value={url} onChange={(e) => setUrl(e.target.value.trim())} />
        <button type="button" className="sda-btn sda-btn--primary" disabled={name === '' || url === '' || pair.isPending} onClick={() => pair.mutate()}>
          {pair.isPending ? 'Pairing…' : 'Generate pairing key'}
        </button>
      </div>
      {pair.error && <p style={{ color: 'var(--sda-negative)', fontSize: 12, margin: '8px 0 0' }}>{pair.error.message}</p>}
    </div>
  );
}

function PairingKeyPanel({ result, onDismiss }: { result: PairResult; onDismiss: () => void }) {
  return (
    <div className="sda-card" style={{ borderInlineStart: '3px solid var(--sda-primary)' }}>
      <h2 style={{ marginBlockStart: 0 }}>Pairing details — copy these now</h2>
      <p style={{ fontSize: 13, color: 'var(--sda-text-muted)' }}>
        Paste these into the client site under <strong>Settings → Agency (client mode)</strong>. The pairing key is shown
        only once and is stored encrypted here.
      </p>
      <div style={{ display: 'grid', gap: 8 }}>
        <label style={{ fontSize: 12, color: 'var(--sda-text-muted)' }}>
          Hub ingest URL
          <input className="sda-input" readOnly value={result.ingest_url} onFocus={(e) => e.target.select()} />
        </label>
        <label style={{ fontSize: 12, color: 'var(--sda-text-muted)' }}>
          Pairing key
          <input className="sda-input" readOnly value={result.pair_key} onFocus={(e) => e.target.select()} style={{ fontFamily: 'monospace' }} />
        </label>
      </div>
      <div style={{ marginBlockStart: 8 }}>
        <button type="button" className="sda-btn" onClick={onDismiss}>
          I&apos;ve copied it
        </button>
      </div>
    </div>
  );
}

export function AgencyPage() {
  const queryClient = useQueryClient();
  const { allows, isLoading: licenseLoading } = useLicense();
  const [paired, setPaired] = useState<PairResult | null>(null);

  const { data, isLoading, error } = useQuery({
    queryKey: ['agency-sites'],
    queryFn: api.agencySites,
    enabled: allows('agency_hub'),
  });

  const remove = useMutation({
    mutationFn: (id: number) => api.unpairSite(id),
    onSuccess: (next) => queryClient.setQueryData(['agency-sites'], next),
  });

  if (licenseLoading) {
    return <div className="sda-skeleton" style={{ height: 200 }} />;
  }

  if (!allows('agency_hub')) {
    return (
      <div className="sda-card sda-empty">
        <strong>Agency Hub is an Agency-tier feature</strong>
        Upgrade to pair client sites and manage them from one dashboard.
      </div>
    );
  }

  return (
    <div style={{ display: 'grid', gap: 16 }}>
      {boot().canManageClients && <PairForm onPaired={(r) => { setPaired(r); queryClient.setQueryData(['agency-sites'], { items: r.items }); }} />}
      {paired && <PairingKeyPanel result={paired} onDismiss={() => setPaired(null)} />}

      {isLoading && <div className="sda-skeleton" style={{ height: 160 }} />}
      {error != null && (
        <div className="sda-empty">
          <strong>Failed to load client sites</strong>
          {(error as Error).message}
        </div>
      )}
      {data && data.items.length === 0 && (
        <div className="sda-empty">
          <strong>No client sites yet</strong>
          Add your first client above to start collecting snapshots.
        </div>
      )}
      {data && data.items.length > 0 && (
        <div className="sda-grid" style={{ gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))' }}>
          {data.items.map((site) => (
            <SiteCard key={site.id} site={site} onRemove={(id) => remove.mutate(id)} />
          ))}
        </div>
      )}
    </div>
  );
}
