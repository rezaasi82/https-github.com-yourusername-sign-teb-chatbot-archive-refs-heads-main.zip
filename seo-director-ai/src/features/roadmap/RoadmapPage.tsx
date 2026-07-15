import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { api, boot, RoadmapTask } from '../../api/client';

type Scope = 'weekly' | 'monthly' | 'quarterly';
const COLUMNS: Array<{ status: RoadmapTask['status']; label: string }> = [
  { status: 'todo', label: 'To do' },
  { status: 'in_progress', label: 'In progress' },
  { status: 'done', label: 'Done' },
];

function TaskCard({ task, scope }: { task: RoadmapTask; scope: Scope }) {
  const queryClient = useQueryClient();
  const move = useMutation({
    mutationFn: (status: string) => api.updateTask(task.id, status, scope),
    onSuccess: (next) => queryClient.setQueryData(['roadmap', scope], next),
  });

  const next: Record<string, string> = { todo: 'in_progress', in_progress: 'done', done: 'todo' };

  return (
    <div className="sda-card" style={{ padding: 12 }}>
      <div style={{ fontSize: 13, fontWeight: 600, marginBlockEnd: 6 }}>{task.title}</div>
      {task.description && (
        <div style={{ fontSize: 12, color: 'var(--sda-text-muted)', marginBlockEnd: 8 }}>{task.description}</div>
      )}
      <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', fontSize: 11, color: 'var(--sda-text-muted)' }}>
        <span>◆ Impact {task.impact}/10</span>
        <span>◆ Difficulty {task.difficulty}/10</span>
        {task.est_hours !== null && <span>◆ ~{task.est_hours}h</span>}
        <span>◆ Priority {task.priority}</span>
      </div>
      {task.expected_result && (
        <div style={{ fontSize: 11, color: 'var(--sda-positive)', marginBlockStart: 6 }}>{task.expected_result}</div>
      )}
      <div style={{ marginBlockStart: 8, display: 'flex', gap: 6, alignItems: 'center', flexWrap: 'wrap' }}>
        <span className="sda-badge sda-badge--off">{task.category}</span>
        {boot().canManage && (
          <>
            <button type="button" className="sda-btn" style={{ padding: '4px 10px' }} onClick={() => move.mutate(next[task.status])}>
              → {next[task.status].replace('_', ' ')}
            </button>
            {task.status !== 'dismissed' && (
              <button type="button" className="sda-btn" style={{ padding: '4px 10px' }} onClick={() => move.mutate('dismissed')}>
                Dismiss
              </button>
            )}
          </>
        )}
      </div>
    </div>
  );
}

export function RoadmapPage() {
  const [scope, setScope] = useState<Scope>('monthly');
  const queryClient = useQueryClient();
  const { data, isLoading, error } = useQuery({ queryKey: ['roadmap', scope], queryFn: () => api.roadmap(scope) });

  const generate = useMutation({
    mutationFn: () => api.generateRoadmap(scope),
    onSuccess: (next) => queryClient.setQueryData(['roadmap', scope], next),
  });

  const grouped = (status: RoadmapTask['status']) => (data?.items ?? []).filter((t) => t.status === status);

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', flexWrap: 'wrap', gap: 8, marginBlockEnd: 16 }}>
        <div style={{ display: 'flex', gap: 8 }}>
          {(['weekly', 'monthly', 'quarterly'] as Scope[]).map((s) => (
            <button key={s} type="button" className={`sda-btn ${scope === s ? 'sda-btn--primary' : ''}`} onClick={() => setScope(s)}>
              {s.charAt(0).toUpperCase() + s.slice(1)}
            </button>
          ))}
        </div>
        {boot().canManage && (
          <button type="button" className="sda-btn" onClick={() => generate.mutate()} disabled={generate.isPending}>
            {generate.isPending ? 'Generating…' : '✦ Regenerate from opportunities'}
          </button>
        )}
      </div>

      {isLoading && <div className="sda-skeleton" style={{ height: 240 }} />}
      {error != null && (
        <div className="sda-card sda-empty">
          <strong>Failed to load roadmap</strong>
          {(error as Error).message}
        </div>
      )}

      {data && data.items.length === 0 && (
        <div className="sda-card sda-empty">
          <strong>No roadmap tasks yet</strong>
          Regenerate to turn current opportunities into a prioritized plan.
        </div>
      )}

      {data && data.items.length > 0 && (
        <div className="sda-grid" style={{ gridTemplateColumns: 'repeat(3, 1fr)', alignItems: 'start' }}>
          {COLUMNS.map((col) => (
            <div key={col.status}>
              <h2 style={{ fontSize: 12, textTransform: 'uppercase', color: 'var(--sda-text-muted)', marginBlockEnd: 8 }}>
                {col.label} ({grouped(col.status).length})
              </h2>
              <div style={{ display: 'grid', gap: 10 }}>
                {grouped(col.status).map((task) => (
                  <TaskCard key={task.id} task={task} scope={scope} />
                ))}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
