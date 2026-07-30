import { useMutation, useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import {
  api,
  type EeatResult,
  type KnowledgeGraphResult,
  type MedicalEntity,
  type PostRef,
} from '../../api/client';
import { useLicense } from '../../app/license';
import { t } from '../../i18n';

const CATEGORY_LABEL: Record<string, string> = {
  disease: 'بیماری',
  symptom: 'علامت',
  treatment: 'درمان',
  drug: 'دارو',
  specialty: 'تخصص',
  body_part: 'عضو بدن',
  // medical_marketing preset categories
  service: 'خدمت',
  seo_term: 'اصطلاح سئو',
  branding: 'برندینگ',
  platform: 'پلتفرم',
  audience: 'مخاطب',
};

function ErrorBox({ error }: { error: Error }) {
  return (
    <div className="sda-empty" style={{ marginBlockStart: 12 }}>
      <strong>{t('Failed to load')}</strong>
      {error.message}
    </div>
  );
}

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

function EeatTool() {
  const [postId, setPostId] = useState(0);
  const eeat = useMutation<EeatResult, Error, number>({ mutationFn: (id) => api.medicalEeat(id) });

  const band = (s: number) => (s >= 70 ? 'var(--sda-positive)' : s >= 40 ? 'var(--sda-warning)' : 'var(--sda-negative)');

  return (
    <div className="sda-card">
      <h2 style={{ marginBlockStart: 0 }}>{t('Medical E-E-A-T analyzer')}</h2>
      <p style={{ fontSize: 13, color: 'var(--sda-text-muted)' }}>
        {t('Scores a medical (YMYL) page against Google’s trust signals: named author, author bio, medical reviewer, authoritative citations, freshness, disclaimer, and topic depth.')}
      </p>
      <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', maxWidth: 520 }}>
        <div style={{ flex: 1, minWidth: 240 }}>
          <PostPicker value={postId} onChange={setPostId} />
        </div>
        <button type="button" className="sda-btn sda-btn--primary" disabled={postId === 0 || eeat.isPending} onClick={() => eeat.mutate(postId)}>
          {eeat.isPending ? t('Analyzing…') : t('Analyze')}
        </button>
      </div>

      {eeat.error != null && <ErrorBox error={eeat.error} />}

      {eeat.data && (
        <div style={{ marginBlockStart: 16 }}>
          <div style={{ fontSize: 40, fontWeight: 700, color: band(eeat.data.score) }}>{eeat.data.score} / 100</div>
          {!eeat.data.is_medical && (
            <p style={{ fontSize: 12, color: 'var(--sda-warning)' }}>{t('No medical entities detected — is this a medical page?')}</p>
          )}
          <table className="sda-table" style={{ marginBlockStart: 10 }}>
            <tbody>
              {eeat.data.checks.map((c) => (
                <tr key={c.code}>
                  <td>
                    <span aria-hidden style={{ color: c.ok ? 'var(--sda-positive)' : 'var(--sda-negative)' }}>
                      {c.ok ? '✓' : '○'}
                    </span>{' '}
                    {c.label}
                  </td>
                  <td>{c.points}/{c.max}</td>
                  <td style={{ whiteSpace: 'normal', color: 'var(--sda-text-muted)' }}>{c.detail}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

function EntitiesSchemaTool() {
  const [postId, setPostId] = useState(0);
  const entities = useQuery<{ entities: MedicalEntity[] }, Error>({
    queryKey: ['medical-entities', postId],
    queryFn: () => api.medicalEntities(postId),
    enabled: postId > 0,
  });
  const save = useMutation({ mutationFn: () => api.medicalSchemaSave(postId) });
  const remove = useMutation({ mutationFn: () => api.medicalSchemaRemove(postId) });

  return (
    <div className="sda-card">
      <h2 style={{ marginBlockStart: 0 }}>{t('Medical entities & schema')}</h2>
      <p style={{ fontSize: 13, color: 'var(--sda-text-muted)' }}>
        {t('Detects the medical concepts a post covers, and generates MedicalWebPage schema (with those conditions/procedures) that you can inject into the page head. Physician / MedicalClinic come from the Settings card.')}
      </p>
      <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', alignItems: 'center' }}>
        <div style={{ flex: 1, minWidth: 240 }}>
          <PostPicker value={postId} onChange={setPostId} />
        </div>
        <button type="button" className="sda-btn sda-btn--primary" disabled={postId === 0 || save.isPending} onClick={() => save.mutate()}>
          {save.isPending ? t('Saving…') : t('Save schema to page')}
        </button>
        <button type="button" className="sda-btn" disabled={postId === 0 || remove.isPending} onClick={() => remove.mutate()}>
          {t('Remove')}
        </button>
      </div>

      {save.data?.saved && <p style={{ color: 'var(--sda-positive)', fontSize: 12 }}>{t('Saved — MedicalWebPage schema now prints on the page.')}</p>}
      {entities.error != null && <ErrorBox error={entities.error} />}
      {entities.isLoading && <div className="sda-skeleton" style={{ height: 100, marginBlockStart: 12 }} />}

      {entities.data && (
        <div style={{ marginBlockStart: 12 }}>
          {entities.data.entities.length === 0 ? (
            <div className="sda-empty">{t('No medical entities detected in this post.')}</div>
          ) : (
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
              {entities.data.entities.map((e) => (
                <span key={e.term} className="sda-badge sda-badge--ok" title={CATEGORY_LABEL[e.category] ?? e.category}>
                  {e.term} <span style={{ opacity: 0.6 }}>×{e.count}</span>
                </span>
              ))}
            </div>
          )}
        </div>
      )}
    </div>
  );
}

function KnowledgeGraphTool() {
  const [force, setForce] = useState(false);
  const kg = useQuery<KnowledgeGraphResult, Error>({
    queryKey: ['medical-kg', force],
    queryFn: () => api.medicalKnowledgeGraph(force),
  });

  return (
    <div className="sda-card">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
        <div>
          <h2 style={{ margin: 0 }}>{t('Medical knowledge graph')}</h2>
          <p style={{ fontSize: 13, color: 'var(--sda-text-muted)', margin: '4px 0 0' }}>
            {t('Which medical concepts your whole site covers, how deeply, and which important concepts you have no page for yet.')}
          </p>
        </div>
        <button type="button" className="sda-btn" onClick={() => setForce(true)} disabled={kg.isFetching}>
          {kg.isFetching ? t('Scanning…') : t('Re-scan')}
        </button>
      </div>

      {kg.error != null && <ErrorBox error={kg.error} />}
      {kg.isLoading && <div className="sda-skeleton" style={{ height: 160, marginBlockStart: 12 }} />}

      {kg.data && (
        <>
          <p style={{ fontSize: 12, color: 'var(--sda-text-muted)', marginBlockStart: 10 }}>
            {kg.data.posts_scanned} {t('posts scanned')} · {kg.data.covered_terms}/{kg.data.total_terms} {t('dictionary concepts covered')}
          </p>

          {Object.entries(kg.data.by_category).map(([category, rows]) => (
            <div key={category} style={{ marginBlockStart: 12 }}>
              <strong style={{ fontSize: 13 }}>{CATEGORY_LABEL[category] ?? category}</strong>
              <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, marginBlockStart: 6 }}>
                {rows.map((r) => (
                  <span key={r.term} className="sda-badge sda-badge--ok">
                    {r.term} <span style={{ opacity: 0.6 }}>({r.posts})</span>
                  </span>
                ))}
              </div>
            </div>
          ))}

          {kg.data.missing.length > 0 && (
            <div style={{ marginBlockStart: 16 }}>
              <strong style={{ fontSize: 13, color: 'var(--sda-warning)' }}>{t('Not covered yet (content gaps)')}</strong>
              <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, marginBlockStart: 6 }}>
                {kg.data.missing.slice(0, 60).map((m) => (
                  <span key={m.term} className="sda-badge sda-badge--off" title={CATEGORY_LABEL[m.category] ?? m.category}>
                    {m.term}
                  </span>
                ))}
              </div>
            </div>
          )}
        </>
      )}
    </div>
  );
}

const TABS = [
  { key: 'eeat', label: 'E-E-A-T' },
  { key: 'entities', label: 'Entities & Schema' },
  { key: 'kg', label: 'Knowledge Graph' },
] as const;

type TabKey = (typeof TABS)[number]['key'];

export function MedicalPage() {
  const { allows, isLoading } = useLicense();
  const [tab, setTab] = useState<TabKey>('eeat');

  if (isLoading) {
    return <div className="sda-skeleton" style={{ height: 200 }} />;
  }

  if (!allows('medical_pack')) {
    return (
      <div className="sda-card sda-empty">
        <strong>{t('The Medical Pack is a Pro feature')}</strong>
        {t('Upgrade your license to unlock medical E-E-A-T, entity detection, medical schema, and the knowledge graph.')}
      </div>
    );
  }

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
          </button>
        ))}
      </div>

      {tab === 'eeat' && <EeatTool />}
      {tab === 'entities' && <EntitiesSchemaTool />}
      {tab === 'kg' && <KnowledgeGraphTool />}
    </div>
  );
}
