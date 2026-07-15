import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { api, boot } from '../../api/client';
import { RULE_LABELS, severityColor } from '../../components/ui/labels';

export function AlertsPage() {
  const [status, setStatus] = useState<'active' | 'resolved'>('active');
  const queryClient = useQueryClient();

  const { data, isLoading, error } = useQuery({
    queryKey: ['alerts', status],
    queryFn: () => api.alerts(status),
  });

  const update = useMutation({
    mutationFn: ({ id, next }: { id: number; next: string }) => api.updateAlert(id, next),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['alerts'] }),
  });

  return (
    <div className="sda-card">
      <div style={{ display: 'flex', justifyContent: 'space-between', flexWrap: 'wrap', gap: 8, marginBlockEnd: 12 }}>
        <div style={{ display: 'flex', gap: 8 }}>
          {(['active', 'resolved'] as const).map((s) => (
            <button key={s} type="button" className={`sda-btn ${status === s ? 'sda-btn--primary' : ''}`} onClick={() => setStatus(s)}>
              {s === 'active' ? 'Active' : 'Resolved'}
            </button>
          ))}
        </div>
        {data && (
          <div style={{ display: 'flex', gap: 6, alignItems: 'center', fontSize: 12 }}>
            {Object.entries(data.counts).map(([severity, count]) => (
              <span key={severity} className="sda-badge" style={{ background: 'transparent', border: `1px solid ${severityColor(severity)}`, color: severityColor(severity) }}>
                {severity}: {count}
              </span>
            ))}
          </div>
        )}
      </div>

      {isLoading && <div className="sda-skeleton" style={{ height: 160 }} />}
      {error != null && (
        <div className="sda-empty">
          <strong>Failed to load</strong>
          {(error as Error).message}
        </div>
      )}
      {data && data.items.length === 0 && (
        <div className="sda-empty">
          <strong>{status === 'active' ? 'No active alerts' : 'No resolved alerts'}</strong>
          {status === 'active' ? 'All monitored conditions are healthy.' : ''}
        </div>
      )}

      {data && data.items.length > 0 && (
        <ul style={{ listStyle: 'none', margin: 0, padding: 0, display: 'grid', gap: 8 }}>
          {data.items.map((alert) => (
            <li
              key={alert.id}
              style={{
                display: 'flex',
                justifyContent: 'space-between',
                gap: 12,
                alignItems: 'center',
                padding: '10px 12px',
                border: '1px solid var(--sda-border)',
                borderInlineStart: `4px solid ${severityColor(alert.severity)}`,
                borderRadius: 8,
              }}
            >
              <div>
                <div style={{ fontSize: 13 }}>{alert.message}</div>
                <small style={{ color: 'var(--sda-text-muted)' }}>
                  {RULE_LABELS[alert.rule] ?? alert.rule} · {alert.raised_at}
                  {alert.status !== 'active' && ` · ${alert.status}`}
                </small>
              </div>
              {status === 'active' && boot().canManage && (
                <div style={{ display: 'flex', gap: 6, flexShrink: 0 }}>
                  <button type="button" className="sda-btn" onClick={() => update.mutate({ id: alert.id, next: 'acknowledged' })}>
                    Ack
                  </button>
                  <button type="button" className="sda-btn" onClick={() => update.mutate({ id: alert.id, next: 'resolved' })}>
                    Resolve
                  </button>
                </div>
              )}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
