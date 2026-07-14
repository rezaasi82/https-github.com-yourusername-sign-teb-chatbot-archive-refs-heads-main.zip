import { useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';
import { TrafficChart } from '../../components/charts/TrafficChart';

function ConnectionBadge({ label, connected }: { label: string; connected: boolean }) {
  return (
    <span className={`sda-badge ${connected ? 'sda-badge--ok' : 'sda-badge--off'}`}>
      {label} {connected ? '✓' : '—'}
    </span>
  );
}

export function OverviewPage() {
  const { data, isLoading, error } = useQuery({ queryKey: ['overview'], queryFn: api.overview });

  if (isLoading) {
    return (
      <div className="sda-grid">
        {[0, 1, 2, 3].map((i) => (
          <div key={i} className="sda-card">
            <div className="sda-skeleton" style={{ width: '40%' }} />
            <div className="sda-skeleton" style={{ marginTop: 12, height: 48 }} />
          </div>
        ))}
      </div>
    );
  }

  if (error || !data) {
    return (
      <div className="sda-card sda-empty">
        <strong>Could not load the dashboard</strong>
        {(error as Error)?.message ?? 'Unknown error'}
      </div>
    );
  }

  const anyConnected = data.connections.gsc || data.connections.ga4;

  return (
    <>
      <div className="sda-card" style={{ marginBlockEnd: 16, display: 'flex', gap: 8, flexWrap: 'wrap' }}>
        <ConnectionBadge label="Search Console" connected={data.connections.gsc} />
        <ConnectionBadge label="Analytics 4" connected={data.connections.ga4} />
        <ConnectionBadge label="PageSpeed" connected={data.connections.psi} />
        <ConnectionBadge label="AI" connected={data.connections.ai} />
      </div>

      <div className="sda-grid">
        <div className="sda-card">
          <h2>SEO Health Score</h2>
          {data.health ? (
            <p style={{ fontSize: 36, fontWeight: 700, margin: '8px 0' }}>{data.health.score}</p>
          ) : (
            <div className="sda-empty">
              <strong>No score yet</strong>
              Connect Google Search Console to start scoring.
            </div>
          )}
        </div>

        <div className="sda-card" style={{ gridColumn: 'span 2' }}>
          <h2>Organic Traffic</h2>
          {data.traffic.series.length > 0 ? (
            <TrafficChart series={data.traffic.series} />
          ) : (
            <div className="sda-empty">
              <strong>No traffic data</strong>
              {data.meta.backfill
                ? `Backfill ${data.meta.backfill.status}${data.meta.backfill.date ? ` — processing ${data.meta.backfill.date}` : ''}. Check back soon.`
                : anyConnected
                  ? 'Select a property in Settings to start syncing.'
                  : 'Connect Google in Settings to begin.'}
            </div>
          )}
        </div>

        <div className="sda-card">
          <h2>Top Opportunities</h2>
          <div className="sda-empty">
            {data.opportunities.length === 0 ? 'Opportunities appear after the first analysis run.' : ''}
          </div>
        </div>

        <div className="sda-card">
          <h2>Top Risks</h2>
          <div className="sda-empty">
            {data.risks.length === 0 ? 'No risks detected yet.' : ''}
          </div>
        </div>
      </div>
    </>
  );
}
