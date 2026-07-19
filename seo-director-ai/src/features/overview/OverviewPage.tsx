import { useQuery } from '@tanstack/react-query';
import { api, type SetupStatus } from '../../api/client';
import { TrafficChart } from '../../components/charts/TrafficChart';
import { DETECTOR_LABELS, RULE_LABELS, severityColor } from '../../components/ui/labels';
import { ScoreDial } from '../../components/ui/ScoreDial';
import { t } from '../../i18n';

function ConnectionBadge({ label, connected }: { label: string; connected: boolean }) {
  return (
    <span className={`sda-badge ${connected ? 'sda-badge--ok' : 'sda-badge--off'}`}>
      {label} {connected ? '✓' : '—'}
    </span>
  );
}

function DemoBanner() {
  return (
    <div
      className="sda-card"
      style={{ marginBlockEnd: 16, borderInlineStart: '3px solid var(--sda-primary)', background: 'var(--sda-primary-soft)' }}
    >
      <strong>{t('You’re viewing sample data')}</strong>
      <p style={{ margin: '4px 0 0', fontSize: 13, color: 'var(--sda-text-muted)' }}>
        {t('This is a preview so you can see what SEO Director AI does. Connect Google Search Console in')}{' '}
        <a href="#/settings">{t('Settings')}</a> {t('to replace it with your site’s real numbers.')}
      </p>
    </div>
  );
}

function SetupChecklist({ setup }: { setup: SetupStatus }) {
  const doneCount = setup.steps.filter((s) => s.done).length;

  return (
    <div className="sda-card" style={{ marginBlockEnd: 16 }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 8 }}>
        <h2 style={{ margin: 0 }}>{t('Get set up')}</h2>
        <span style={{ fontSize: 12, color: 'var(--sda-text-muted)' }}>
          {doneCount} / {setup.steps.length} {t('done')}
        </span>
      </div>
      <ul style={{ margin: '10px 0 0', padding: 0, listStyle: 'none', display: 'grid', gap: 6 }}>
        {setup.steps.map((step) => (
          <li key={step.key} style={{ display: 'flex', gap: 8, alignItems: 'center', fontSize: 14 }}>
            <span aria-hidden style={{ color: step.done ? 'var(--sda-positive)' : 'var(--sda-text-muted)' }}>
              {step.done ? '✓' : '○'}
            </span>
            <span style={{ color: step.done ? 'var(--sda-text-muted)' : 'var(--sda-text)', textDecoration: step.done ? 'line-through' : 'none' }}>
              {step.label}
            </span>
            {!step.done && step.key !== 'sync' && (
              <a href="#/settings" style={{ fontSize: 12 }}>
                {t('Set up ▸')}
              </a>
            )}
          </li>
        ))}
      </ul>
    </div>
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
        <strong>{t('Could not load the dashboard')}</strong>
        {(error as Error)?.message ?? t('Unknown error')}
      </div>
    );
  }

  const anyConnected = data.connections.gsc || data.connections.ga4;
  const setup = data.meta.setup;
  const showChecklist = setup != null && !setup.complete;

  return (
    <>
      {data.meta.demo && <DemoBanner />}
      {showChecklist && <SetupChecklist setup={setup} />}

      <div className="sda-card" style={{ marginBlockEnd: 16, display: 'flex', gap: 8, flexWrap: 'wrap' }}>
        <ConnectionBadge label={t('Search Console')} connected={data.connections.gsc} />
        <ConnectionBadge label={t('Analytics 4')} connected={data.connections.ga4} />
        <ConnectionBadge label={t('PageSpeed')} connected={data.connections.psi} />
        <ConnectionBadge label={t('AI')} connected={data.connections.ai} />
      </div>

      <div className="sda-grid">
        <div className="sda-card">
          <h2>{t('SEO Health Score')}</h2>
          {data.health ? (
            <ScoreDial health={data.health} />
          ) : (
            <div className="sda-empty">
              <strong>{t('No score yet')}</strong>
              {t('Scores appear after the first analysis pass over synced data.')}
            </div>
          )}
        </div>

        {data.summaries.weekly && (
          <div className="sda-card" style={{ gridColumn: 'span 2' }}>
            <h2>
              <span className="sda-badge" style={{ background: 'var(--sda-primary-soft)', color: 'var(--sda-primary)' }}>
                ✦ AI
              </span>{' '}
              {t('Weekly Summary')}
            </h2>
            <p style={{ fontSize: 14, marginBlockStart: 8 }}>{data.summaries.weekly}</p>
          </div>
        )}

        <div className="sda-card" style={{ gridColumn: 'span 2' }}>
          <h2>{t('Organic Traffic')}</h2>
          {data.traffic.series.length > 0 ? (
            <TrafficChart series={data.traffic.series} />
          ) : (
            <div className="sda-empty">
              <strong>{t('No traffic data')}</strong>
              {data.meta.backfill
                ? `Backfill ${data.meta.backfill.status}${data.meta.backfill.date ? ` — processing ${data.meta.backfill.date}` : ''}. Check back soon.`
                : anyConnected
                  ? t('Select a property in Settings to start syncing.')
                  : t('Connect Google in Settings to begin.')}
            </div>
          )}
        </div>

        <div className="sda-card">
          <h2>{t('Top Opportunities')}</h2>
          {data.opportunities.length === 0 ? (
            <div className="sda-empty">{t('Opportunities appear after the first analysis run.')}</div>
          ) : (
            <ol style={{ margin: '8px 0 0', paddingInlineStart: 18, display: 'grid', gap: 6, fontSize: 13 }}>
              {data.opportunities.map((opp) => (
                <li key={opp.id}>
                  <strong style={{ wordBreak: 'break-all' }}>{opp.label}</strong>
                  <br />
                  <small style={{ color: 'var(--sda-text-muted)' }}>
                    {DETECTOR_LABELS[opp.detector] ?? opp.detector} · +{opp.est_traffic_gain.toLocaleString()} {t('clicks/mo')}
                  </small>
                </li>
              ))}
            </ol>
          )}
          <a href="#/opportunities" style={{ fontSize: 12, display: 'inline-block', marginBlockStart: 8 }}>
            {t('All opportunities ▸')}
          </a>
        </div>

        <div className="sda-card">
          <h2>{t('Top Risks')}</h2>
          {data.risks.length === 0 ? (
            <div className="sda-empty">{t('No active risks detected.')}</div>
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
            {t('All alerts ▸')}
          </a>
        </div>
      </div>
    </>
  );
}
