import { useMemo } from 'react';
import type { TrafficPoint } from '../../api/client';

/**
 * Dependency-free SVG line chart for the overview traffic series.
 * (Chart.js arrives with the richer Phase 2 screens.)
 */
export function TrafficChart({ series }: { series: TrafficPoint[] }) {
  const { path, area, max } = useMemo(() => {
    const width = 600;
    const height = 160;
    const pad = 4;
    const values = series.map((p) => p.clicks);
    const maxValue = Math.max(1, ...values);

    const x = (i: number) => pad + (i * (width - pad * 2)) / Math.max(1, series.length - 1);
    const y = (v: number) => height - pad - (v * (height - pad * 2)) / maxValue;

    const points = series.map((p, i) => `${x(i).toFixed(1)},${y(p.clicks).toFixed(1)}`);
    const line = points.length > 0 ? `M ${points.join(' L ')}` : '';
    const areaPath =
      points.length > 0
        ? `${line} L ${x(series.length - 1).toFixed(1)},${height - pad} L ${x(0).toFixed(1)},${height - pad} Z`
        : '';

    return { path: line, area: areaPath, max: maxValue };
  }, [series]);

  if (series.length === 0) return null;

  const total = series.reduce((sum, p) => sum + p.clicks, 0);

  return (
    <div>
      <div style={{ display: 'flex', alignItems: 'baseline', gap: 8, marginBlockEnd: 8 }}>
        <span style={{ fontSize: 28, fontWeight: 700 }}>{total.toLocaleString()}</span>
        <span style={{ fontSize: 12, color: 'var(--sda-text-muted)' }}>
          clicks · last {series.length} days · peak {max.toLocaleString()}/day
        </span>
      </div>
      <svg viewBox="0 0 600 160" role="img" aria-label="Daily organic clicks" style={{ width: '100%', height: 'auto', display: 'block' }}>
        <path d={area} fill="var(--sda-primary-soft)" />
        <path d={path} fill="none" stroke="var(--sda-primary)" strokeWidth="2" />
      </svg>
    </div>
  );
}
