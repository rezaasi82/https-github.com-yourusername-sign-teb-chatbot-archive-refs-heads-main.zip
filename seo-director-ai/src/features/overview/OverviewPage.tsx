import { useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';
import { TrafficChart } from '../../components/charts/TrafficChart';
import { DETECTOR_LABELS, RULE_LABELS, severityColor } from '../../components/ui/labels';
import { ScoreDial } from '../../components/ui/ScoreDial';

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
            <ScoreDial health={data.health} />
          ) : (
            <div className="sda-empty">
              <strong>No score yet</strong>
              Scores appear after the first analysis pass over synced data.
            </div>
          )}
        </div>

        {data.summaries.weekly && (
          <div className="sda-card" style={{ gridColumn: 'span 2' }}>
            <h2>
              <span className="sda-badge" style={{ background: 'var(--sda-primary-soft)', color: 'var(--sda-primary)' }}>
                ✦ AI
              </span>{' '}
              Weekly Summary
            </h2>
            <p style={{ fontSize: 14, marginBlockStart: 8 }}>{data.summaries.weekly}</p>
          </div>
        )}

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
          {data.opportunities.length === 0 ? (
            <div className="sda-empty">Opportunities appear after the first analysis run.</div>
          ) : (
            <ol style={{ margin: '8px 0 0', paddingInlineStart: 18, display: 'grid', gap: 6, fontSize: 13 }}>
              {data.opportunities.map((opp) => (
                <li key={opp.id}>
                  <strong style={{ wordBreak: 'break-all' }}>{opp.label}</strong>
                  <br />
                  <small style={{ color: 'var(--sda-text-muted)' }}>
                    {DETECTOR_LABELS[opp.detector] ?? opp.detector} · +{opp.est_traffic_gain.toLocaleString()} clicks/mo
                  </small>
                </li>
              ))}
            </ol>
          )}
          <a href="#/opportunities" style={{ fontSize: 12, display: 'inline-block', marginBlockStart: 8 }}>
            All opportunities ▸
          </a>
        </div>

        <div className="sda-card">
          <h2>Top Risks</h2>
          {data.risks.length === 0 ? (
            <div className="sda-empty">No active risks detected.</div>
          ) : (
            <ul style={{ margin: '8px 0 0', padding: 0, listStyle: 'none', display: 'grid', gap: 6, fontSize: 13 }}>
              {data.risks.map((risk) => (
                <li key={risk.id}>
                  <span style={{ color: severityColor(risk.severity), fontWeight: 600 }}>
                    {risk.severity.toUpperCase()}
                  </span>{' '}
                  {risk.message}
                  <br />
                  <small style={{ color: 'var(--sda-text-muted)' }}>{RULE_LABELS[risk.rule] ?? risk.rule}</small>
                </li>
              ))}
            </ul>
          )}
          <a href="#/alerts" style={{ fontSize: 12, display: 'inline-block', marginBlockStart: 8 }}>
            All alerts ▸
          </a>
        </div>
      </div>
    </>
  );
}
