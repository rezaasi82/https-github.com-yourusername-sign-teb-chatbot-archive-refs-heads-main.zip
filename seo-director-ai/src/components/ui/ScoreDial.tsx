import type { Health } from '../../api/client';

const BAND_COLORS: Record<Health['band'], string> = {
  green: 'var(--sda-positive)',
  yellow: 'var(--sda-warning)',
  red: 'var(--sda-negative)',
};

export function ScoreDial({ health }: { health: Health }) {
  const radius = 52;
  const circumference = 2 * Math.PI * radius;
  const filled = (health.score / 100) * circumference;
  const color = BAND_COLORS[health.band];

  return (
    <div style={{ display: 'flex', gap: 16, alignItems: 'center' }}>
      <svg width="130" height="130" viewBox="0 0 130 130" role="img" aria-label={`SEO health score ${health.score} of 100`}>
        <circle cx="65" cy="65" r={radius} fill="none" stroke="var(--sda-surface-2)" strokeWidth="12" />
        <circle
          cx="65"
          cy="65"
          r={radius}
          fill="none"
          stroke={color}
          strokeWidth="12"
          strokeLinecap="round"
          strokeDasharray={`${filled} ${circumference - filled}`}
          transform="rotate(-90 65 65)"
        />
        <text x="65" y="72" textAnchor="middle" fontSize="30" fontWeight="700" fill="var(--sda-text)">
          {health.score}
        </text>
      </svg>
      <div style={{ fontSize: 12, color: 'var(--sda-text-muted)' }}>
        {health.delta !== null && (
          <p style={{ margin: '0 0 8px' }}>
            {health.delta >= 0 ? '▲' : '▼'} {Math.abs(health.delta)} vs 30 days ago
          </p>
        )}
        <ul style={{ margin: 0, paddingInlineStart: 0, listStyle: 'none', display: 'grid', gap: 2 }}>
          {Object.entries(health.components)
            .filter(([, c]) => c.available)
            .map(([name, c]) => (
              <li key={name}>
                {name.replace(/_/g, ' ')}: <strong>{c.score}</strong>
              </li>
            ))}
        </ul>
      </div>
    </div>
  );
}
