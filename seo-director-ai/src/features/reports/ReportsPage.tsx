import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { api, boot, type ReportRow } from '../../api/client';
import { useLicense } from '../../app/license';

const TYPE_LABELS: Record<string, string> = {
  weekly: 'Weekly',
  monthly: 'Monthly',
  quarterly: 'Quarterly',
};

function formatDate(value: string): string {
  const time = Date.parse(value);
  return Number.isNaN(time) ? value : new Date(time).toLocaleDateString();
}

export function ReportsPage() {
  const queryClient = useQueryClient();
  const { allows } = useLicense();
  const [type, setType] = useState('weekly');

  const { data, isLoading, error } = useQuery({ queryKey: ['reports'], queryFn: api.reports });
  const allFormats = allows('reports_all_formats');

  const generate = useMutation({
    mutationFn: () => api.generateReport(type, allFormats ? ['html', 'csv'] : ['html']),
    onSuccess: (next) => queryClient.setQueryData(['reports'], next),
  });

  return (
    <div className="sda-card">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBlockEnd: 12, gap: 12, flexWrap: 'wrap' }}>
        <span style={{ fontSize: 12, color: 'var(--sda-text-muted)' }}>
          Executive summaries of health, movers, opportunities and risks. Scheduled delivery is a Pro feature.
        </span>
        {boot().canManage && (
          <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
            <select className="sda-input" value={type} onChange={(e) => setType(e.target.value)} aria-label="Report period">
              <option value="weekly">Weekly</option>
              <option value="monthly">Monthly</option>
              <option value="quarterly">Quarterly</option>
            </select>
            <button type="button" className="sda-btn sda-btn--primary" onClick={() => generate.mutate()} disabled={generate.isPending}>
              {generate.isPending ? 'Generating…' : 'Generate now'}
            </button>
          </div>
        )}
      </div>

      {generate.error != null && (
        <div className="sda-empty" style={{ marginBlockEnd: 12 }}>
          <strong>Could not generate report</strong>
          {(generate.error as Error).message}
        </div>
      )}

      {isLoading && <div className="sda-skeleton" style={{ height: 160 }} />}
      {error != null && (
        <div className="sda-empty">
          <strong>Failed to load</strong>
          {(error as Error).message}
        </div>
      )}
      {data && data.items.length === 0 && (
        <div className="sda-empty">
          <strong>No reports yet</strong>
          Generate one now, or let the weekly schedule produce your first report.
        </div>
      )}

      {data && data.items.length > 0 && (
        <div style={{ overflowX: 'auto' }}>
          <table className="sda-table">
            <thead>
              <tr>
                <th>Type</th>
                <th>Period</th>
                <th>Status</th>
                <th>Created</th>
                <th>Download</th>
              </tr>
            </thead>
            <tbody>
              {data.items.map((row: ReportRow) => (
                <tr key={row.id}>
                  <td>{TYPE_LABELS[row.type] ?? row.type}</td>
                  <td>
                    {formatDate(row.period_start)} – {formatDate(row.period_end)}
                  </td>
                  <td>{row.status}</td>
                  <td>{formatDate(row.created_at)}</td>
                  <td>
                    <div style={{ display: 'flex', gap: 8 }}>
                      {row.formats.map((format) => (
                        <a key={format} className="sda-btn" href={api.reportDownloadUrl(row.id, format)} target="_blank" rel="noreferrer">
                          {format.toUpperCase()}
                        </a>
                      ))}
                    </div>
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
