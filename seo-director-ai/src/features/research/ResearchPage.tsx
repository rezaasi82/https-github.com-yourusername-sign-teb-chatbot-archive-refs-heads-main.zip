import { useMutation } from '@tanstack/react-query';
import { useState } from 'react';
import { api, type ClusterPlan, type CompetitorOverview, type KeywordRow, type SerpView } from '../../api/client';
import { useLicense } from '../../app/license';
import { t } from '../../i18n';

function ErrorBox({ error }: { error: Error }) {
  return (
    <div className="sda-empty" style={{ marginBlockStart: 12 }}>
      <strong>{t('Failed to load')}</strong>
      {error.message}
    </div>
  );
}

const INTENT_LABEL: Record<string, string> = {
  informational: 'اطلاعاتی',
  transactional: 'تراکنشی',
  commercial: 'تجاری',
  local: 'محلی',
};

const SOURCE_LABEL: Record<string, string> = {
  autocomplete: 'Autocomplete',
  people_also_ask: 'PAA',
  related_searches: 'Related',
  gsc: 'GSC',
};

function KeywordsTool() {
  const [seed, setSeed] = useState('');
  const research = useMutation<{ seed: string; keywords: KeywordRow[]; serp_used: boolean }, Error, string>({
    mutationFn: (s) => api.researchKeywords(s),
  });

  return (
    <div className="sda-card">
      <h2 style={{ marginBlockStart: 0 }}>{t('Keyword research')}</h2>
      <p style={{ fontSize: 13, color: 'var(--sda-text-muted)' }}>
        {t('Discovers keywords from Google Autocomplete (free), People-Also-Ask and related searches (when a SerpApi key is set), and your own Search Console queries — impressions and position are your real numbers, not estimates.')}
      </p>
      <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
        <input
          className="sda-input"
          style={{ flex: 1, minWidth: 220 }}
          placeholder={t('Seed keyword (e.g. فتق شکم)')}
          value={seed}
          onChange={(e) => setSeed(e.target.value)}
        />
        <button
          type="button"
          className="sda-btn sda-btn--primary"
          disabled={research.isPending || seed.trim() === ''}
          onClick={() => research.mutate(seed.trim())}
        >
          {research.isPending ? t('Researching…') : t('Research')}
        </button>
      </div>

      {research.error != null && <ErrorBox error={research.error} />}

      {research.data && (
        <>
          <p style={{ fontSize: 12, color: 'var(--sda-text-muted)', marginBlockStart: 10 }}>
            {research.data.keywords.length} {t('keywords found')}
            {!research.data.serp_used && ` · ${t('add a SerpApi key in Settings to also get People-Also-Ask questions')}`}
          </p>
          <div style={{ overflowX: 'auto' }}>
            <table className="sda-table">
              <thead>
                <tr>
                  <th>{t('Keyword')}</th>
                  <th>{t('Intent')}</th>
                  <th>{t('Sources')}</th>
                  <th>{t('Impressions (yours)')}</th>
                  <th>{t('Position (yours)')}</th>
                </tr>
              </thead>
              <tbody>
                {research.data.keywords.map((k) => (
                  <tr key={k.keyword}>
                    <td style={{ whiteSpace: 'normal' }}>{k.keyword}</td>
                    <td>{INTENT_LABEL[k.intent] ?? k.intent}</td>
                    <td>
                      {k.sources.map((s) => (
                        <span key={s} className="sda-badge sda-badge--off" style={{ marginInlineEnd: 4 }}>
                          {SOURCE_LABEL[s] ?? s}
                        </span>
                      ))}
                    </td>
                    <td>{k.impressions != null ? k.impressions.toLocaleString() : '—'}</td>
                    <td>{k.position != null ? k.position : '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </>
      )}
    </div>
  );
}

function ClustersTool() {
  const [seed, setSeed] = useState('');
  const cluster = useMutation<ClusterPlan, Error, string>({ mutationFn: (s) => api.researchCluster(s) });

  return (
    <div className="sda-card">
      <h2 style={{ marginBlockStart: 0 }}>{t('Topic cluster builder')}</h2>
      <p style={{ fontSize: 13, color: 'var(--sda-text-muted)' }}>
        {t('Runs keyword research on the seed topic, then plans one pillar page plus cluster articles with an internal-linking map. Existing posts are reused as “update” items instead of duplicates.')}
      </p>
      <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
        <input
          className="sda-input"
          style={{ flex: 1, minWidth: 220 }}
          placeholder={t('Seed topic (e.g. فتق شکم)')}
          value={seed}
          onChange={(e) => setSeed(e.target.value)}
        />
        <button
          type="button"
          className="sda-btn sda-btn--primary"
          disabled={cluster.isPending || seed.trim() === ''}
          onClick={() => cluster.mutate(seed.trim())}
        >
          {cluster.isPending ? t('Planning…') : t('Build cluster')}
        </button>
      </div>

      {cluster.error != null && <ErrorBox error={cluster.error} />}

      {cluster.data && (
        <div style={{ marginBlockStart: 16, display: 'grid', gap: 14, fontSize: 13 }}>
          <div style={{ border: '2px solid var(--sda-primary)', borderRadius: 8, padding: '10px 12px' }}>
            <span className="sda-badge" style={{ background: 'var(--sda-primary-soft)', color: 'var(--sda-primary)' }}>
              {t('Pillar')}
            </span>
            <div style={{ fontWeight: 700, marginBlockStart: 4 }}>{cluster.data.pillar.title}</div>
            <div style={{ color: 'var(--sda-text-muted)' }}>{cluster.data.pillar.target_keyword}</div>
            {cluster.data.pillar.rationale && (
              <div style={{ fontSize: 12, color: 'var(--sda-text-muted)', marginBlockStart: 4 }}>{cluster.data.pillar.rationale}</div>
            )}
          </div>

          <div style={{ display: 'grid', gap: 8 }}>
            {cluster.data.clusters.map((c, i) => (
              <div key={i} style={{ border: '1px solid var(--sda-border)', borderRadius: 8, padding: '8px 12px', display: 'flex', justifyContent: 'space-between', gap: 8, flexWrap: 'wrap' }}>
                <div>
                  <strong>{c.title}</strong>
                  <div style={{ fontSize: 12, color: 'var(--sda-text-muted)' }}>
                    {c.target_keyword}
                    {c.intent ? ` · ${INTENT_LABEL[c.intent] ?? c.intent}` : ''}
                  </div>
                </div>
                <span className={`sda-badge ${c.role === 'update' ? 'sda-badge--off' : 'sda-badge--ok'}`} style={{ alignSelf: 'center' }}>
                  {c.role === 'update' ? t('Update existing') : t('Create new')}
                </span>
              </div>
            ))}
          </div>

          {cluster.data.linking_notes && (
            <div>
              <strong>{t('Internal-linking plan')}</strong>
              <p style={{ margin: '4px 0 0', color: 'var(--sda-text-muted)' }}>{cluster.data.linking_notes}</p>
            </div>
          )}
        </div>
      )}
    </div>
  );
}

function CompetitorsTool() {
  const [query, setQuery] = useState('');
  const overview = useMutation<CompetitorOverview, Error>({ mutationFn: api.researchCompetitors });
  const serp = useMutation<SerpView, Error, string>({ mutationFn: (q) => api.researchSerp(q) });

  return (
    <div style={{ display: 'grid', gap: 16 }}>
      <div className="sda-card">
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
          <div>
            <h2 style={{ margin: 0 }}>{t('Competitor overview')}</h2>
            <p style={{ fontSize: 13, color: 'var(--sda-text-muted)', margin: '4px 0 0' }}>
              {t('Checks the live SERP for your top 10 Search Console queries and shows which domains out-rank you most. Needs a SerpApi key (Settings → Enterprise card or any plan with a key saved). SERPs are cached 24h.')}
            </p>
          </div>
          <button type="button" className="sda-btn sda-btn--primary" onClick={() => overview.mutate()} disabled={overview.isPending}>
            {overview.isPending ? t('Analyzing…') : t('Analyze competitors')}
          </button>
        </div>

        {overview.error != null && <ErrorBox error={overview.error} />}

        {overview.data && (
          <>
            <p style={{ fontSize: 12, color: 'var(--sda-text-muted)', marginBlockStart: 10 }}>
              {overview.data.queries_checked} {t('queries checked against')} {overview.data.our_domain}
            </p>
            <div style={{ overflowX: 'auto' }}>
              <table className="sda-table">
                <thead>
                  <tr>
                    <th>{t('Competitor domain')}</th>
                    <th>{t('Times above you')}</th>
                    <th>{t('Avg position')}</th>
                    <th>{t('Sample queries')}</th>
                  </tr>
                </thead>
                <tbody>
                  {overview.data.competitors.map((c) => (
                    <tr key={c.domain}>
                      <td style={{ direction: 'ltr' }}>{c.domain}</td>
                      <td>{c.appearances}</td>
                      <td>{c.avg_position}</td>
                      <td style={{ whiteSpace: 'normal', fontSize: 12, color: 'var(--sda-text-muted)' }}>{c.sample_queries.join(' · ')}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </>
        )}
      </div>

      <div className="sda-card">
        <h2 style={{ marginBlockStart: 0 }}>{t('SERP check for one query')}</h2>
        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
          <input
            className="sda-input"
            style={{ flex: 1, minWidth: 220 }}
            placeholder={t('e.g. جراح فتق تهران')}
            value={query}
            onChange={(e) => setQuery(e.target.value)}
          />
          <button
            type="button"
            className="sda-btn sda-btn--primary"
            disabled={serp.isPending || query.trim() === ''}
            onClick={() => serp.mutate(query.trim())}
          >
            {serp.isPending ? t('Loading…') : t('Check SERP')}
          </button>
        </div>

        {serp.error != null && <ErrorBox error={serp.error} />}

        {serp.data && (
          <>
            <p style={{ fontSize: 12, marginBlockStart: 10 }}>
              {serp.data.our_position != null
                ? `${t('Your position:')} ${serp.data.our_position}`
                : t('You are not in the top 10 for this query.')}
            </p>
            <ol style={{ margin: '8px 0 0', paddingInlineStart: 22, display: 'grid', gap: 6, fontSize: 13 }}>
              {serp.data.results.map((r) => (
                <li key={r.position} style={r.is_us ? { background: 'var(--sda-primary-soft)', borderRadius: 6, padding: '4px 6px' } : undefined}>
                  <a href={r.link} target="_blank" rel="noreferrer">
                    {r.title}
                  </a>
                  <div style={{ fontSize: 11, color: 'var(--sda-text-muted)', direction: 'ltr', textAlign: 'start' }}>{r.domain}</div>
                </li>
              ))}
            </ol>
          </>
        )}
      </div>
    </div>
  );
}

const TABS = [
  { key: 'keywords', label: 'Keywords', feature: 'keyword_research' },
  { key: 'clusters', label: 'Clusters', feature: 'topic_clusters' },
  { key: 'competitors', label: 'Competitors', feature: 'competitor_intel' },
] as const;

type TabKey = (typeof TABS)[number]['key'];

export function ResearchPage() {
  const { allows, isLoading } = useLicense();
  const [tab, setTab] = useState<TabKey>('keywords');

  if (isLoading) {
    return <div className="sda-skeleton" style={{ height: 200 }} />;
  }

  const current = TABS.find((x) => x.key === tab) ?? TABS[0];

  return (
    <div style={{ display: 'grid', gap: 16 }}>
      <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
        {TABS.map((item) => (
          <button
            key={item.key}
            type="button"
            className={`sda-btn ${tab === item.key ? 'sda-btn--primary' : ''}`}
            onClick={() => setTab(item.key)}
          >
            {t(item.label)}
            {!allows(item.feature) && ' 🔒'}
          </button>
        ))}
      </div>

      {!allows(current.feature) ? (
        <div className="sda-card sda-empty">
          <strong>{t('This tool is not included in your plan')}</strong>
          {t('Upgrade your license to unlock it.')}
        </div>
      ) : (
        <>
          {tab === 'keywords' && <KeywordsTool />}
          {tab === 'clusters' && <ClustersTool />}
          {tab === 'competitors' && <CompetitorsTool />}
        </>
      )}
    </div>
  );
}
