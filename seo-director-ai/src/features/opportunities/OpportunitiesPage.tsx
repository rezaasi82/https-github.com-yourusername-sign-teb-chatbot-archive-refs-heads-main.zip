import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '../../api/client';
import { DETECTOR_LABELS } from '../../components/ui/labels';
import { boot } from '../../api/client';

export function OpportunitiesPage() {
  const queryClient = useQueryClient();
  const { data, isLoading, error } = useQuery({ queryKey: ['opportunities'], queryFn: api.opportunities });

  const rescan = useMutation({
    mutationFn: api.rescanOpportunities,
    onSuccess: () => setTimeout(() => queryClient.invalidateQueries({ queryKey: ['opportunities'] }), 4000),
  });

  const dismiss = useMutation({
    mutationFn: (id: number) => api.updateOpportunity(id, 'dismissed'),
    onSuccess: (next) => queryClient.setQueryData(['opportunities'], next),
  });

  return (
    <div className="sda-card">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBlockEnd: 12 }}>
        <span style={{ fontSize: 12, color: 'var(--sda-text-muted)' }}>
          Ranked by estimated impact ÷ difficulty. Dismissed items stay dismissed on rescans.
        </span>
        {boot().canManage && (
          <button type="button" className="sda-btn" onClick={() => rescan.mutate()} disabled={rescan.isPending}>
            {rescan.isPending ? 'Queued…' : '⟳ Rescan'}
          </button>
        )}
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
          <strong>No opportunities detected yet</strong>
          Opportunities appear after the first analysis pass over synced data.
        </div>
      )}

      {data && data.items.length > 0 && (
        <div style={{ overflowX: 'auto' }}>
          <table className="sda-table">
            <thead>
              <tr>
                <th>Opportunity</th>
                <th>Type</th>
                <th>Detector</th>
                <th>Est. traffic gain</th>
                <th>Difficulty</th>
                <th>Score</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {data.items.map((item) => (
                <tr key={item.id}>
                  <td style={{ maxWidth: 320, overflow: 'hidden', textOverflow: 'ellipsis' }} title={item.label}>
                    {item.label}
                    {typeof item.data.position === 'number' && (
                      <small style={{ color: 'var(--sda-text-muted)' }}> · pos {String(item.data.position)}</small>
                    )}
                  </td>
                  <td>{item.entity_type}</td>
                  <td>{DETECTOR_LABELS[item.detector] ?? item.detector}</td>
                  <td>+{item.est_traffic_gain.toLocaleString()} clicks/mo</td>
                  <td>{item.difficulty}/10</td>
                  <td style={{ fontWeight: 600 }}>{item.score.toLocaleString()}</td>
                  <td>
                    {boot().canManage && (
                      <button
                        type="button"
                        className="sda-btn"
                        onClick={() => dismiss.mutate(item.id)}
                        title="Dismiss"
                        aria-label={`Dismiss opportunity ${item.label}`}
                      >
                        ✕
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
