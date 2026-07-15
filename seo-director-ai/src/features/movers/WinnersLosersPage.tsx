import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { api, Loser, Winner } from '../../api/client';
import { ACTION_LABELS, CAUSE_LABELS, FIX_LABELS, REASON_LABELS, severityColor } from '../../components/ui/labels';
import { ExplainButton } from '../rootcause/ExplainButton';

type Tab = 'winners' | 'losers';
type Entity = 'query' | 'page';

interface MoversData {
  items: Array<Winner | Loser>;
  period: unknown;
}

export function WinnersLosersPage() {
  const [tab, setTab] = useState<Tab>('winners');
  const [entity, setEntity] = useState<Entity>('query');

  const { data, isLoading, error } = useQuery<MoversData>({
    queryKey: ['movers', tab, entity],
    queryFn: () => (tab === 'winners' ? api.winners(entity, 28) : api.losers(entity, 28)),
  });

  return (
    <div className="sda-card">
      <div style={{ display: 'flex', justifyContent: 'space-between', flexWrap: 'wrap', gap: 8, marginBlockEnd: 12 }}>
        <div style={{ display: 'flex', gap: 8 }}>
          {(['winners', 'losers'] as Tab[]).map((t) => (
            <button
              key={t}
              type="button"
              className={`sda-btn ${tab === t ? 'sda-btn--primary' : ''}`}
              onClick={() => setTab(t)}
            >
              {t === 'winners' ? '▲ Winners' : '▼ Losers'}
            </button>
          ))}
        </div>
        <div style={{ display: 'flex', gap: 8 }}>
          {(['query', 'page'] as Entity[]).map((e) => (
            <button
              key={e}
              type="button"
              className={`sda-btn ${entity === e ? 'sda-btn--primary' : ''}`}
              onClick={() => setEntity(e)}
            >
              {e === 'query' ? 'Keywords' : 'Pages'}
            </button>
          ))}
        </div>
      </div>

      {isLoading && <div className="sda-skeleton" style={{ height: 200 }} />}
      {error != null && (
        <div className="sda-empty">
          <strong>Failed to load</strong>
          {(error as Error).message}
        </div>
      )}
      {data && data.items.length === 0 && (
        <div className="sda-empty">
          <strong>No significant {tab} yet</strong>
          Movers appear once two full comparison periods of data are synced.
        </div>
      )}

      {data && data.items.length > 0 && (
        <div style={{ overflowX: 'auto' }}>
          <table className="sda-table">
            <thead>
              <tr>
                <th>{entity === 'query' ? 'Keyword' : 'Page'}</th>
                <th>Clicks</th>
                <th>Δ</th>
                <th>%</th>
                <th>Position</th>
                <th>{tab === 'winners' ? 'Reason' : 'Cause'}</th>
                {tab === 'losers' && <th>Priority</th>}
                <th>{tab === 'winners' ? 'Next action' : 'Suggested fix'}</th>
                <th>AI</th>
              </tr>
            </thead>
            <tbody>
              {data.items.map((item) => {
                const winner = 'reason' in item ? item : null;
                const loser = 'cause' in item ? item : null;
                const pct = winner ? winner.growth_pct : loser?.loss_pct ?? null;

                return (
                  <tr key={item.hash}>
                    <td style={{ maxWidth: 320, overflow: 'hidden', textOverflow: 'ellipsis' }} title={item.label}>
                      {item.label}
                    </td>
                    <td>{item.clicks.toLocaleString()}</td>
                    <td style={{ color: item.clicks_delta >= 0 ? 'var(--sda-positive)' : 'var(--sda-negative)' }}>
                      {item.clicks_delta >= 0 ? '▲' : '▼'} {Math.abs(item.clicks_delta).toLocaleString()}
                    </td>
                    <td>{winner?.is_new ? 'new' : pct !== null ? `${pct > 0 ? '+' : ''}${pct}%` : '—'}</td>
                    <td>
                      {item.position}{' '}
                      {item.position_delta !== 0 && (
                        <small style={{ color: item.position_delta < 0 ? 'var(--sda-positive)' : 'var(--sda-negative)' }}>
                          ({item.position_delta > 0 ? '+' : ''}
                          {item.position_delta})
                        </small>
                      )}
                    </td>
                    <td>{winner ? REASON_LABELS[winner.reason] ?? winner.reason : CAUSE_LABELS[loser!.cause] ?? loser!.cause}</td>
                    {loser && (
                      <td>
                        <span className="sda-badge" style={{ background: 'transparent', border: `1px solid ${severityColor(loser.priority)}`, color: severityColor(loser.priority) }}>
                          {loser.priority}
                        </span>
                      </td>
                    )}
                    <td style={{ color: 'var(--sda-text-muted)' }}>
                      {winner ? ACTION_LABELS[winner.next_action] ?? winner.next_action : FIX_LABELS[loser!.suggested_fix] ?? loser!.suggested_fix}
                    </td>
                    <td style={{ whiteSpace: 'normal', minWidth: 240 }}>
                      <ExplainButton entity={entity} hash={item.hash} kind={winner ? 'growth' : 'root_cause'} />
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
