import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { useEffect } from 'react';
import { boot } from '../api/client';
import { AlertsPage } from '../features/alerts/AlertsPage';
import { ContentPage } from '../features/content/ContentPage';
import { WinnersLosersPage } from '../features/movers/WinnersLosersPage';
import { OpportunitiesPage } from '../features/opportunities/OpportunitiesPage';
import { OverviewPage } from '../features/overview/OverviewPage';
import { ReportsPage } from '../features/reports/ReportsPage';
import { RoadmapPage } from '../features/roadmap/RoadmapPage';
import { SettingsPage } from '../features/settings/SettingsPage';
import { useHashRoute } from './router';
import { useTheme } from './theme';

const queryClient = new QueryClient({
  defaultOptions: { queries: { retry: 1, staleTime: 60_000, refetchOnWindowFocus: false } },
});

const NAV: Array<{ route: string; label: string }> = [
  { route: 'overview', label: 'Overview' },
  { route: 'movers', label: 'Winners & Losers' },
  { route: 'opportunities', label: 'Opportunities' },
  { route: 'content', label: 'Content' },
  { route: 'roadmap', label: 'Roadmap' },
  { route: 'alerts', label: 'Alerts' },
  { route: 'reports', label: 'Reports' },
  { route: 'settings', label: 'Settings' },
];

function ComingSoon({ label }: { label: string }) {
  return (
    <div className="sda-card sda-empty">
      <strong>{label}</strong>
      This screen ships in an upcoming phase.
    </div>
  );
}

export function App() {
  const route = useHashRoute();
  const [theme, toggleTheme] = useTheme();

  useEffect(() => {
    document.getElementById('sda-root')?.setAttribute('data-theme', theme);
  }, [theme]);

  const current = NAV.find((n) => route.startsWith(n.route)) ?? NAV[0];

  return (
    <QueryClientProvider client={queryClient}>
      <div className="sda-shell">
        <nav className="sda-sidebar" aria-label="SEO Director">
          <div className="sda-sidebar__brand">SEO Director AI</div>
          {NAV.map((item) => (
            <a key={item.route} href={`#/${item.route}`} className={item.route === current.route ? 'is-active' : ''}>
              {item.label}
            </a>
          ))}
        </nav>
        <main className="sda-main">
          <div className="sda-topbar">
            <h1>{current.label}</h1>
            <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
              <span style={{ fontSize: 12, color: 'var(--sda-text-muted)' }}>{boot().siteName}</span>
              <button type="button" className="sda-btn" onClick={toggleTheme} aria-label="Toggle theme">
                {theme === 'dark' ? '☀' : '☾'}
              </button>
            </div>
          </div>
          {current.route === 'overview' && <OverviewPage />}
          {current.route === 'movers' && <WinnersLosersPage />}
          {current.route === 'opportunities' && <OpportunitiesPage />}
          {current.route === 'content' && <ContentPage />}
          {current.route === 'roadmap' && <RoadmapPage />}
          {current.route === 'alerts' && <AlertsPage />}
          {current.route === 'reports' && <ReportsPage />}
          {current.route === 'settings' && <SettingsPage />}
          {!['overview', 'movers', 'opportunities', 'content', 'roadmap', 'alerts', 'reports', 'settings'].includes(
            current.route
          ) && <ComingSoon label={current.label} />}
        </main>
      </div>
    </QueryClientProvider>
  );
}
