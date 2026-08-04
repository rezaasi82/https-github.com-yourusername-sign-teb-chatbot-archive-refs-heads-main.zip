import { useMutation, useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import {
  api,
  type AuditResult,
  type ContentBrief,
  type ContentGapTopic,
  type LinkSuggestionGroup,
  type MetaSuggestion,
  type PostRef,
  type SchemaBuildResult,
  type ScoreResult,
  type ZombieResult,
} from '../../api/client';
import { useLicense } from '../../app/license';
import { t } from '../../i18n';

// Google truncates titles near ~600px and descriptions near ~960px in SERPs.
const TITLE_LIMIT = 600;
const DESC_LIMIT = 960;

function PixelBar({ px, limit }: { px: number; limit: number }) {
  const pct = Math.min(100, Math.round((px / limit) * 100));
  const over = px > limit;
  return (
    <div style={{ marginBlockStart: 4 }}>
      <div style={{ height: 6, background: 'var(--sda-border)', borderRadius: 3, overflow: 'hidden' }}>
        <div style={{ width: `${pct}%`, height: '100%', background: over ? 'var(--sda-negative)' : 'var(--sda-primary)' }} />
      </div>
      <small style={{ color: over ? 'var(--sda-negative)' : 'var(--sda-text-muted)' }}>
        {px}px / {limit}px{over ? ` — ${t('may be truncated')}` : ''}
      </small>
    </div>
  );
}

function ErrorBox({ title, error }: { title: string; error: Error }) {
  return (
    <div className="sda-empty" style={{ marginBlockStart: 12 }}>
      <strong>{title}</strong>
      {error.message}
    </div>
  );
}

/** Shared post picker backed by GET /content/posts. */
function PostPicker({ value, onChange }: { value: number; onChange: (id: number) => void }) {
  const { data } = useQuery({ queryKey: ['content-posts'], queryFn: api.contentPosts, staleTime: 5 * 60_000 });

  return (
    <select className="sda-input" value={value} onChange={(e) => onChange(Number(e.target.value))}>
      <option value={0}>{t('Select a post…')}</option>
      {(data?.posts ?? []).map((p: PostRef) => (
        <option key={p.id} value={p.id}>
          {p.title}
        </option>
      ))}
    </select>
  );
}

function MetaGenerator() {
  const [hash, setHash] = useState('');
  const meta = useMutation<MetaSuggestion, Error, string>({ mutationFn: (h) => api.contentMeta(h) });

  return (
    <div className="sda-card">
      <h2 style={{ marginBlockStart: 0 }}>{t('Meta generator')}</h2>
      <p style={{ fontSize: 13, color: 'var(--sda-text-muted)' }}>
        {t('Paste a page hash from the Winners & Losers or Opportunities tables to generate an intent-matched title and description, sized against SERP pixel limits.')}
      </p>
      <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
        <input
          className="sda-input"
          style={{ flex: 1, minWidth: 220 }}
          placeholder={t('Page hash')}
          value={hash}
          onChange={(e) => setHash(e.target.value.trim())}
        />
        <button
          type="button"
          className="sda-btn sda-btn--primary"
          onClick={() => meta.mutate(hash)}
          disabled={meta.isPending || hash === ''}
        >
          {meta.isPending ? t('Writing…') : t('Generate')}
        </button>
      </div>

      {meta.error != null && <ErrorBox title={t('Could not generate')} error={meta.error} />}

      {meta.data && (
        <div style={{ marginBlockStart: 16, display: 'grid', gap: 16 }}>
          <div>
            <strong>{t('Title')}</strong>
            <div style={{ marginBlockStart: 4 }}>{meta.data.title}</div>
            <PixelBar px={meta.data.title_px} limit={TITLE_LIMIT} />
          </div>
          <div>
            <strong>{t('Description')}</strong>
            <div style={{ marginBlockStart: 4 }}>{meta.data.description}</div>
            <PixelBar px={meta.data.desc_px} limit={DESC_LIMIT} />
          </div>
        </div>
      )}
    </div>
  );
}

function GapAnalysis() {
  const gap = useMutation<{ topics: ContentGapTopic[] }, Error>({ mutationFn: api.contentGap });

  return (
    <div className="sda-card">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
        <div>
          <h2 style={{ margin: 0 }}>{t('Content gap analysis')}</h2>
          <p style={{ fontSize: 13, color: 'var(--sda-text-muted)', margin: '4px 0 0' }}>
            {t('Topics you rank for on the fringe but have no dedicated page to serve.')}
          </p>
        </div>
        <button type="button" className="sda-btn sda-btn--primary" onClick={() => gap.mutate()} disabled={gap.isPending}>
          {gap.isPending ? t('Analyzing…') : t('Run analysis')}
        </button>
      </div>

      {gap.error != null && <ErrorBox title={t('Could not analyze')} error={gap.error} />}

      {gap.data && gap.data.topics.length === 0 && (
        <div className="sda-empty" style={{ marginBlockStart: 12 }}>
          <strong>{t('No clear gaps found')}</strong>
          {t('Your current pages cover the queries you rank for.')}
        </div>
      )}

      {gap.data && gap.data.topics.length > 0 && (
        <ul style={{ marginBlockStart: 12, paddingInlineStart: 18, display: 'grid', gap: 10 }}>
          {gap.data.topics.map((topic, index) => (
            <li key={index}>
              <strong>{topic.topic}</strong>
              {topic.rationale && <div style={{ fontSize: 13, color: 'var(--sda-text-muted)' }}>{topic.rationale}</div>}
              {Array.isArray(topic.target_queries) && topic.target_queries.length > 0 && (
                <div style={{ fontSize: 12, color: 'var(--sda-text-muted)', marginBlockStart: 2 }}>
                  {topic.target_queries.join(' · ')}
                </div>
              )}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

function BriefTool() {
  const [keyword, setKeyword] = useState('');
  const brief = useMutation<ContentBrief, Error, string>({ mutationFn: (k) => api.contentBrief(k) });

  return (
    <div className="sda-card">
      <h2 style={{ marginBlockStart: 0 }}>{t('SEO brief generator')}</h2>
      <p style={{ fontSize: 13, color: 'var(--sda-text-muted)' }}>
        {t('Enter a target keyword — you get a full writing brief: goal, keyword set, outline, FAQs, and the entities the article must cover. Demand evidence comes from your own Search Console queries.')}
      </p>
      <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
        <input
          className="sda-input"
          style={{ flex: 1, minWidth: 220 }}
          placeholder={t('Target keyword (e.g. عمل فتق شکم)')}
          value={keyword}
          onChange={(e) => setKeyword(e.target.value)}
        />
        <button
          type="button"
          className="sda-btn sda-btn--primary"
          onClick={() => brief.mutate(keyword.trim())}
          disabled={brief.isPending || keyword.trim() === ''}
        >
          {brief.isPending ? t('Writing…') : t('Generate brief')}
        </button>
      </div>

      {brief.error != null && <ErrorBox title={t('Could not generate')} error={brief.error} />}

      {brief.data && (
        <div style={{ marginBlockStart: 16, display: 'grid', gap: 14, fontSize: 13 }}>
          <div>
            <strong>{t('Goal')}</strong>
            <p style={{ margin: '4px 0 0' }}>{brief.data.goal}</p>
          </div>
          <div>
            <strong>{t('Keywords')}</strong>
            <p style={{ margin: '4px 0 0' }}>
              <span className="sda-badge sda-badge--ok">{brief.data.primary_keyword}</span>{' '}
              {brief.data.secondary_keywords.map((k) => (
                <span key={k} className="sda-badge sda-badge--off" style={{ marginInlineEnd: 4 }}>
                  {k}
                </span>
              ))}
            </p>
          </div>
          <div>
            <strong>{t('Outline')}</strong>
            <ul style={{ margin: '4px 0 0', paddingInlineStart: 18 }}>
              {brief.data.outline.map((h, i) => (
                <li key={i} style={{ marginInlineStart: (h.level - 2) * 16 }}>
                  <span style={{ color: 'var(--sda-text-muted)' }}>H{h.level}</span> {h.heading}
                  {h.notes && <div style={{ fontSize: 12, color: 'var(--sda-text-muted)' }}>{h.notes}</div>}
                </li>
              ))}
            </ul>
          </div>
          {brief.data.faq.length > 0 && (
            <div>
              <strong>{t('FAQ')}</strong>
              <ul style={{ margin: '4px 0 0', paddingInlineStart: 18 }}>
                {brief.data.faq.map((f, i) => (
                  <li key={i}>{f.question}</li>
                ))}
              </ul>
            </div>
          )}
          {brief.data.entities.length > 0 && (
            <div>
              <strong>{t('Entities to cover')}</strong>
              <p style={{ margin: '4px 0 0' }}>{brief.data.entities.join(' · ')}</p>
            </div>
          )}
          {brief.data.related_queries.length > 0 && (
            <div>
              <strong>{t('Demand evidence (your GSC queries)')}</strong>
              <p style={{ margin: '4px 0 0', color: 'var(--sda-text-muted)' }}>
                {brief.data.related_queries.slice(0, 10).map((q) => q.query).join(' · ')}
              </p>
            </div>
          )}
          {brief.data.existing_posts.length > 0 && (
            <div>
              <strong>{t('Existing related posts (link to these)')}</strong>
              <ul style={{ margin: '4px 0 0', paddingInlineStart: 18 }}>
                {brief.data.existing_posts.map((p) => (
                  <li key={p.id}>
                    <a href={p.url} target="_blank" rel="noreferrer">
                      {p.title}
                    </a>
                  </li>
                ))}
              </ul>
            </div>
          )}
        </div>
      )}
    </div>
  );
}

function LinksTool() {
  const [postId, setPostId] = useState(0);
  const links = useQuery<{ mode: string; items: LinkSuggestionGroup[] }, Error>({
    queryKey: ['content-links', postId],
    queryFn: () => api.contentLinks(postId > 0 ? postId : undefined),
  });

  return (
    <div className="sda-card">
      <h2 style={{ marginBlockStart: 0 }}>{t('Internal link suggestions')}</h2>
      <p style={{ fontSize: 13, color: 'var(--sda-text-muted)' }}>
        {t('Pages that mention another page’s topic but don’t link to it yet. Pick a post, or leave empty for a site-wide pass over recent posts.')}
      </p>
      <div style={{ maxWidth: 420 }}>
        <PostPicker value={postId} onChange={setPostId} />
      </div>

      {links.error != null && <ErrorBox title={t('Failed to load')} error={links.error} />}
      {links.isLoading && <div className="sda-skeleton" style={{ height: 120, marginBlockStart: 12 }} />}

      {links.data && links.data.items.length === 0 && (
        <div className="sda-empty" style={{ marginBlockStart: 12 }}>
          <strong>{t('No suggestions')}</strong>
          {t('No unlinked topic mentions were found.')}
        </div>
      )}

      {links.data &&
        links.data.items.map((group) => (
          <div key={group.source.id} style={{ marginBlockStart: 14 }}>
            <strong style={{ fontSize: 13 }}>
              <a href={group.source.url} target="_blank" rel="noreferrer">
                {group.source.title}
              </a>
            </strong>
            <table className="sda-table" style={{ marginBlockStart: 6 }}>
              <thead>
                <tr>
                  <th>{t('Anchor text')}</th>
                  <th>{t('Link to')}</th>
                  <th>{t('Score')}</th>
                </tr>
              </thead>
              <tbody>
                {group.suggestions.map((s) => (
                  <tr key={s.target_id}>
                    <td>{s.anchor}</td>
                    <td>
                      <a href={s.target_url} target="_blank" rel="noreferrer">
                        {s.target_title}
                      </a>
                    </td>
                    <td>{s.score}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ))}
    </div>
  );
}

const SEVERITY_COLOR: Record<string, string> = {
  high: 'var(--sda-negative)',
  medium: 'var(--sda-warning)',
  low: 'var(--sda-text-muted)',
};

function AuditTool() {
  const [force, setForce] = useState(false);
  const audit = useQuery<AuditResult, Error>({
    queryKey: ['content-audit', force],
    queryFn: () => api.contentAudit(force),
  });

  return (
    <div className="sda-card">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
        <div>
          <h2 style={{ margin: 0 }}>{t('On-page audit')}</h2>
          <p style={{ fontSize: 13, color: 'var(--sda-text-muted)', margin: '4px 0 0' }}>
            {t('Scans your published posts and pages for on-page problems. Results are cached for an hour.')}
          </p>
        </div>
        <button type="button" className="sda-btn" onClick={() => setForce(true)} disabled={audit.isFetching}>
          {audit.isFetching ? t('Scanning…') : t('Re-scan')}
        </button>
      </div>

      {audit.error != null && <ErrorBox title={t('Failed to load')} error={audit.error} />}
      {audit.isLoading && <div className="sda-skeleton" style={{ height: 160, marginBlockStart: 12 }} />}

      {audit.data && (
        <>
          <p style={{ fontSize: 12, color: 'var(--sda-text-muted)', marginBlockStart: 10 }}>
            {audit.data.scanned} {t('pages scanned')} · {audit.data.issues_total} {t('issues')}
          </p>
          {audit.data.pages.length === 0 ? (
            <div className="sda-empty">
              <strong>{t('No on-page issues found')}</strong>
            </div>
          ) : (
            <div style={{ display: 'grid', gap: 12 }}>
              {audit.data.pages.slice(0, 30).map((page) => (
                <div key={page.id} style={{ border: '1px solid var(--sda-border)', borderRadius: 8, padding: '10px 12px' }}>
                  <strong style={{ fontSize: 13 }}>
                    <a href={page.url} target="_blank" rel="noreferrer">
                      {page.title}
                    </a>
                  </strong>
                  <ul style={{ margin: '6px 0 0', paddingInlineStart: 18, fontSize: 12 }}>
                    {page.issues.map((issue, i) => (
                      <li key={i} style={{ color: SEVERITY_COLOR[issue.severity] }}>
                        {issue.message}
                      </li>
                    ))}
                  </ul>
                </div>
              ))}
            </div>
          )}
        </>
      )}
    </div>
  );
}

const ACTION_LABEL: Record<string, string> = {
  wait: 'Wait',
  improve: 'Improve content',
  improve_meta: 'Rewrite title & meta',
  internal_link: 'Add internal links',
  prune: 'Prune (merge/301 or delete)',
};

const ACTION_COLOR: Record<string, string> = {
  prune: 'var(--sda-negative)',
  improve: 'var(--sda-warning)',
  improve_meta: 'var(--sda-warning)',
  internal_link: 'var(--sda-primary)',
  wait: 'var(--sda-text-muted)',
};

function ZombiesTool() {
  const [days, setDays] = useState(90);
  const [force, setForce] = useState(false);
  const zombies = useQuery<ZombieResult, Error>({
    queryKey: ['content-zombies', days, force],
    queryFn: () => api.contentZombies(days, force),
  });

  return (
    <div className="sda-card">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
        <div>
          <h2 style={{ margin: 0 }}>{t('Zombie pages')}</h2>
          <p style={{ fontSize: 13, color: 'var(--sda-text-muted)', margin: '4px 0 0' }}>
            {t('Pages that earn zero organic clicks in the window — they waste crawl budget and dilute site quality. Each gets a recommended fix.')}
          </p>
        </div>
        <div style={{ display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>
          <select className="sda-input" style={{ width: 'auto' }} value={days} onChange={(e) => setDays(Number(e.target.value))}>
            <option value={90}>{t('last 90 days')}</option>
            <option value={180}>{t('last 180 days')}</option>
            <option value={365}>{t('last 365 days')}</option>
          </select>
          <button type="button" className="sda-btn" onClick={() => setForce(true)} disabled={zombies.isFetching}>
            {zombies.isFetching ? t('Scanning…') : t('Re-scan')}
          </button>
        </div>
      </div>

      {zombies.error != null && <ErrorBox title={t('Failed to load')} error={zombies.error} />}
      {zombies.isLoading && <div className="sda-skeleton" style={{ height: 160, marginBlockStart: 12 }} />}

      {zombies.data && (
        <>
          {!zombies.data.has_gsc && (
            <div className="sda-card" style={{ marginBlockStart: 12, borderInlineStart: '3px solid var(--sda-warning)', background: 'var(--sda-surface-2)' }}>
              <p style={{ margin: 0, fontSize: 12, color: 'var(--sda-text-muted)' }}>
                {t('Search Console isn’t connected, so this uses content signals only (thin + orphan pages). Connect Google for click-based detection.')}
              </p>
            </div>
          )}
          <p style={{ fontSize: 12, color: 'var(--sda-text-muted)', marginBlockStart: 10 }}>
            {zombies.data.scanned} {t('pages scanned')} · {zombies.data.zombie_count} {t('zombie page(s)')}
          </p>

          {zombies.data.pages.length === 0 ? (
            <div className="sda-empty">
              <strong>{t('No zombie pages found')}</strong>
              {t('Every scanned page earns organic clicks — nice.')}
            </div>
          ) : (
            <div style={{ overflowX: 'auto' }}>
              <table className="sda-table" style={{ marginBlockStart: 8 }}>
                <thead>
                  <tr>
                    <th>{t('Page')}</th>
                    <th>{t('Clicks')}</th>
                    <th>{t('Impr.')}</th>
                    <th>{t('Words')}</th>
                    <th>{t('In-links')}</th>
                    <th>{t('Recommended fix')}</th>
                  </tr>
                </thead>
                <tbody>
                  {zombies.data.pages.map((p) => (
                    <tr key={p.id}>
                      <td style={{ whiteSpace: 'normal', maxWidth: 280 }}>
                        <a href={p.url} target="_blank" rel="noreferrer">
                          {p.title || p.url}
                        </a>
                        <div style={{ fontSize: 11, color: 'var(--sda-text-muted)' }}>{p.reason}</div>
                      </td>
                      <td>{p.clicks}</td>
                      <td>{p.impressions}</td>
                      <td>{p.words}</td>
                      <td style={{ color: p.inbound === 0 ? 'var(--sda-negative)' : 'inherit' }}>{p.inbound}</td>
                      <td style={{ whiteSpace: 'nowrap' }}>
                        <span style={{ fontWeight: 600, color: ACTION_COLOR[p.action] ?? 'var(--sda-text)' }}>
                          {t(ACTION_LABEL[p.action] ?? p.action)}
                        </span>
                        {p.edit_url && (
                          <>
                            {' · '}
                            <a href={p.edit_url} target="_blank" rel="noreferrer" style={{ fontSize: 12 }}>
                              {t('Edit')}
                            </a>
                          </>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </>
      )}
    </div>
  );
}

function SchemaTool() {
  const [postId, setPostId] = useState(0);
  const preview = useQuery<SchemaBuildResult, Error>({
    queryKey: ['content-schema', postId],
    queryFn: () => api.contentSchemaPreview(postId),
    enabled: postId > 0,
  });
  const save = useMutation<SchemaBuildResult, Error>({
    mutationFn: () => api.contentSchemaSave(postId, ['article', 'breadcrumb', 'faq']),
  });
  const remove = useMutation({ mutationFn: () => api.contentSchemaRemove(postId) });

  return (
    <div className="sda-card">
      <h2 style={{ marginBlockStart: 0 }}>{t('Schema generator')}</h2>
      <p style={{ fontSize: 13, color: 'var(--sda-text-muted)' }}>
        {t('Generates Article + Breadcrumb JSON-LD for a post, plus FAQ schema from question-style headings (ending with ? or ؟). Saving injects it into the page head.')}
      </p>
      <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', alignItems: 'center' }}>
        <div style={{ flex: 1, minWidth: 260 }}>
          <PostPicker value={postId} onChange={setPostId} />
        </div>
        <button
          type="button"
          className="sda-btn sda-btn--primary"
          disabled={postId === 0 || save.isPending}
          onClick={() => save.mutate()}
        >
          {save.isPending ? t('Saving…') : t('Save to page')}
        </button>
        <button type="button" className="sda-btn" disabled={postId === 0 || remove.isPending} onClick={() => remove.mutate()}>
          {t('Remove')}
        </button>
      </div>

      {save.data?.saved && <p style={{ color: 'var(--sda-positive)', fontSize: 12 }}>{t('Saved — the JSON-LD is now printed on the page.')}</p>}
      {(preview.error ?? save.error) != null && <ErrorBox title={t('Failed to load')} error={(preview.error ?? save.error) as Error} />}

      {preview.data && (
        <div style={{ marginBlockStart: 12 }}>
          <p style={{ fontSize: 12, color: 'var(--sda-text-muted)' }}>
            {preview.data.faq_found > 0
              ? `${preview.data.faq_found} ${t('FAQ question(s) detected in the content.')}`
              : t('No FAQ-style headings detected — only Article and Breadcrumb will be generated.')}
          </p>
          <pre
            style={{
              fontSize: 11,
              direction: 'ltr',
              textAlign: 'left',
              overflowX: 'auto',
              background: 'var(--sda-surface-2)',
              padding: 12,
              borderRadius: 8,
              maxHeight: 320,
            }}
          >
            {JSON.stringify(preview.data.graph, null, 2)}
          </pre>
        </div>
      )}
    </div>
  );
}

function ScoreTool() {
  const [postId, setPostId] = useState(0);
  const [keyword, setKeyword] = useState('');
  const [withEntities, setWithEntities] = useState(false);
  const score = useMutation<ScoreResult, Error>({
    mutationFn: () => api.contentScore(postId, keyword.trim(), withEntities),
  });

  const band = (s: number) => (s >= 70 ? 'var(--sda-positive)' : s >= 40 ? 'var(--sda-warning)' : 'var(--sda-negative)');

  return (
    <div className="sda-card">
      <h2 style={{ marginBlockStart: 0 }}>{t('Optimization score')}</h2>
      <p style={{ fontSize: 13, color: 'var(--sda-text-muted)' }}>
        {t('Scores a post against its target keyword (0–100): placement, structure, depth, FAQ, links, images. The number is deterministic — the optional AI pass only lists missing entities.')}
      </p>
      <div style={{ display: 'grid', gap: 8, maxWidth: 520 }}>
        <PostPicker value={postId} onChange={setPostId} />
        <input className="sda-input" placeholder={t('Target keyword')} value={keyword} onChange={(e) => setKeyword(e.target.value)} />
        <label style={{ fontSize: 12, color: 'var(--sda-text-muted)', display: 'flex', gap: 8, alignItems: 'center' }}>
          <input type="checkbox" checked={withEntities} onChange={(e) => setWithEntities(e.target.checked)} />
          {t('Also check entity coverage with AI')}
        </label>
        <div>
          <button
            type="button"
            className="sda-btn sda-btn--primary"
            disabled={postId === 0 || keyword.trim() === '' || score.isPending}
            onClick={() => score.mutate()}
          >
            {score.isPending ? t('Scoring…') : t('Calculate score')}
          </button>
        </div>
      </div>

      {score.error != null && <ErrorBox title={t('Could not analyze')} error={score.error} />}

      {score.data && (
        <div style={{ marginBlockStart: 16 }}>
          <div style={{ fontSize: 40, fontWeight: 700, color: band(score.data.score) }}>{score.data.score} / 100</div>
          <table className="sda-table" style={{ marginBlockStart: 10 }}>
            <tbody>
              {score.data.checks.map((c) => (
                <tr key={c.code}>
                  <td>{c.label}</td>
                  <td style={{ color: c.points === c.max ? 'var(--sda-positive)' : c.points === 0 ? 'var(--sda-negative)' : 'var(--sda-warning)' }}>
                    {c.points}/{c.max}
                  </td>
                  <td style={{ whiteSpace: 'normal', color: 'var(--sda-text-muted)' }}>{c.detail}</td>
                </tr>
              ))}
            </tbody>
          </table>
          {score.data.entities && (
            <div style={{ marginBlockStart: 12, fontSize: 13 }}>
              {score.data.entities.missing.length > 0 && (
                <p style={{ margin: 0 }}>
                  <strong style={{ color: 'var(--sda-warning)' }}>{t('Missing entities:')}</strong>{' '}
                  {score.data.entities.missing.join(' · ')}
                </p>
              )}
              {score.data.entities.covered.length > 0 && (
                <p style={{ margin: '6px 0 0', color: 'var(--sda-text-muted)' }}>
                  <strong>{t('Covered:')}</strong> {score.data.entities.covered.join(' · ')}
                </p>
              )}
            </div>
          )}
        </div>
      )}
    </div>
  );
}

const TABS = [
  { key: 'brief', label: 'Brief', pro: true },
  { key: 'score', label: 'Score', pro: true },
  { key: 'links', label: 'Internal links', pro: false },
  { key: 'audit', label: 'Audit', pro: false },
  { key: 'zombies', label: 'Zombies', pro: false },
  { key: 'schema', label: 'Schema', pro: false },
  { key: 'meta', label: 'Meta & Gap', pro: true },
] as const;

type TabKey = (typeof TABS)[number]['key'];

export function ContentPage() {
  const { allows, isLoading } = useLicense();
  const [tab, setTab] = useState<TabKey>('brief');

  if (isLoading) {
    return <div className="sda-skeleton" style={{ height: 200 }} />;
  }

  const hasPro = allows('content_strategist');
  const hasStarter = allows('core_detectors');

  const locked = (pro: boolean) => (pro ? !hasPro : !hasStarter);

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
            {locked(item.pro) && ' 🔒'}
          </button>
        ))}
      </div>

      {locked(TABS.find((x) => x.key === tab)?.pro ?? true) ? (
        <div className="sda-card sda-empty">
          <strong>{t('This tool is not included in your plan')}</strong>
          {t('Upgrade your license to unlock it.')}
        </div>
      ) : (
        <>
          {tab === 'brief' && <BriefTool />}
          {tab === 'score' && <ScoreTool />}
          {tab === 'links' && <LinksTool />}
          {tab === 'audit' && <AuditTool />}
          {tab === 'zombies' && <ZombiesTool />}
          {tab === 'schema' && <SchemaTool />}
          {tab === 'meta' && (
            <>
              <MetaGenerator />
              <GapAnalysis />
            </>
          )}
        </>
      )}
    </div>
  );
}
