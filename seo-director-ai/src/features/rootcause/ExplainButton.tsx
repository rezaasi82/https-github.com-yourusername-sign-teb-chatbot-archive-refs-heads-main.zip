import { useMutation } from '@tanstack/react-query';
import { useState } from 'react';
import { api, ExplainResponse } from '../../api/client';

/**
 * On-demand AI explanation for a keyword/page. Shows ranked causes with
 * confidence bars (root cause) or explanation + recommendation (growth/decline).
 * Numbers come from the deterministic layer; only the prose is AI-generated.
 */
export function ExplainButton({
  entity,
  hash,
  kind,
}: {
  entity: 'query' | 'page';
  hash: string;
  kind: 'root_cause' | 'growth' | 'decline';
}) {
  const [open, setOpen] = useState(false);
  const explain = useMutation<ExplainResponse>({ mutationFn: () => api.explain(entity, hash, kind) });

  const run = () => {
    setOpen(true);
    if (!explain.data && !explain.isPending) explain.mutate();
  };

  return (
    <>
      <button type="button" className="sda-btn" style={{ padding: '4px 10px' }} onClick={run}>
        ✦ Explain
      </button>
      {open && (
        <div className="sda-card" style={{ marginBlockStart: 8, padding: 12 }}>
          {explain.isPending && <div className="sda-skeleton" style={{ height: 60 }} />}
          {explain.isError && (
            <div style={{ fontSize: 12, color: 'var(--sda-negative)' }}>{(explain.error as Error).message}</div>
          )}
          {explain.data && (
            <div style={{ fontSize: 13 }}>
              <span className="sda-badge" style={{ background: 'var(--sda-primary-soft)', color: 'var(--sda-primary)' }}>
                ✦ AI{explain.data.cached ? ' · cached' : ''}
              </span>
              {explain.data.payload.summary && <p style={{ marginBlockStart: 8 }}>{explain.data.payload.summary}</p>}
              {explain.data.payload.explanation && (
                <p style={{ marginBlockStart: 8 }}>{explain.data.payload.explanation}</p>
              )}
              {explain.data.payload.recommendation && (
                <p style={{ marginBlockStart: 6, color: 'var(--sda-text-muted)' }}>
                  <strong>Recommendation:</strong> {explain.data.payload.recommendation}
                </p>
              )}
              {explain.data.payload.causes?.map((cause, i) => (
                <div key={i} style={{ marginBlockStart: 10 }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                    <strong>
                      #{i + 1} {cause.cause}
                    </strong>
                    <span style={{ color: 'var(--sda-text-muted)' }}>{cause.confidence}%</span>
                  </div>
                  <div style={{ height: 6, background: 'var(--sda-surface-2)', borderRadius: 3, margin: '4px 0' }}>
                    <div
                      style={{
                        width: `${Math.max(0, Math.min(100, cause.confidence))}%`,
                        height: '100%',
                        background: 'var(--sda-primary)',
                        borderRadius: 3,
                      }}
                    />
                  </div>
                  <div style={{ fontSize: 12, color: 'var(--sda-text-muted)' }}>{cause.fix}</div>
                </div>
              ))}
            </div>
          )}
        </div>
      )}
    </>
  );
}
