import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { lazy, Suspense, useEffect, type ComponentType } from 'react';
import { boot } from '../api/client';
import { t } from '../i18n';
import { EditionBadge, UpgradeBanner } from '../components/ui/UpgradeBanner';
import { useLicense } from './license';
import { useHashRoute } from './router';
import { useTheme } from './theme';

// Route pages are code-split: each becomes its own chunk, so the initial load
// only ships the shell + overview and the rest arrive on navigation.
const OverviewPage = lazy(() => import('../features/overview/OverviewPage').then((m) => ({ default: m.OverviewPage })));
const WinnersLosersPage = lazy(() => import('../features/movers/WinnersLosersPage').then((m) => ({ default: m.WinnersLosersPage })));
const OpportunitiesPage = lazy(() => import('../features/opportunities/OpportunitiesPage').then((m) => ({ default: m.OpportunitiesPage })));
const ContentPage = lazy(() => import('../features/content/ContentPage').then((m) => ({ default: m.ContentPage })));
const RoadmapPage = lazy(() => import('../features/roadmap/RoadmapPage').then((m) => ({ default: m.RoadmapPage })));
const AlertsPage = lazy(() => import('../features/alerts/AlertsPage').then((m) => ({ default: m.AlertsPage })));
const ReportsPage = lazy(() => import('../features/reports/ReportsPage').then((m) => ({ default: m.ReportsPage })));
const AgencyPage = lazy(() => import('../features/agency/AgencyPage').then((m) => ({ default: m.AgencyPage })));
const SettingsPage = lazy(() => import('../features/settings/SettingsPage').then((m) => ({ default: m.SettingsPage })));
const ResearchPage = lazy(() => import('../features/research/ResearchPage').then((m) => ({ default: m.ResearchPage })));
const MedicalPage = lazy(() => import('../features/medical/MedicalPage').then((m) => ({ default: m.MedicalPage })));

const queryClient = new QueryClient({
  defaultOptions: { queries: { retry: 1, staleTime: 60_000, refetchOnWindowFocus: false } },
});

interface NavItem {
  route: string;
  label: string;
  page: ComponentType;
  // Feature the edition must unlock; undefined = always available.
  feature?: string;
  // Edition to advertise in the upsell when the feature is locked.
  requires?: string;
}

const NAV: NavItem[] = [
  { route: 'overview', label: 'Overview', page: OverviewPage },
  { route: 'movers', label: 'Winners & Losers', page: WinnersLosersPage, feature: 'movers', requires: 'starter' },
  { route: 'opportunities', label: 'Opportunities', page: OpportunitiesPage, feature: 'core_detectors', requires: 'starter' },
  { route: 'research', label: 'Research', page: ResearchPage, feature: 'keyword_research', requires: 'starter' },
  { route: 'content', label: 'Content', page: ContentPage, feature: 'content_strategist', requires: 'pro' },
  // Medical Pack — only shown when the site enabled medical mode.
  ...(boot().medicalMode
    ? [{ route: 'medical', label: 'Medical', page: MedicalPage, feature: 'medical_pack', requires: 'pro' } as NavItem]
    : []),
  { route: 'roadmap', label: 'Roadmap', page: RoadmapPage, feature: 'roadmap_monthly', requires: 'starter' },
  { route: 'alerts', label: 'Alerts', page: AlertsPage, feature: 'alerts_email', requires: 'starter' },
  { route: 'reports', label: 'Reports', page: ReportsPage, feature: 'reports_pdf', requires: 'starter' },
  // Agency hub is only shown to users who can manage clients.
  ...(boot().canManageClients
    ? [{ route: 'agency', label: 'Agency', page: AgencyPage, feature: 'agency_hub', requires: 'agency' } as NavItem]
    : []),
  { route: 'settings', label: 'Settings', page: SettingsPage },
];

function PageFallback() {
  return <div className="sda-card sda-skeleton" style={{ height: 240 }} />;
}

function RouteView({ item }: { item: NavItem }) {
  const { allows, isLoading } = useLicense();
  const Page = item.page;

  // Gate by feature. While the license snapshot loads, don't flash the upsell.
  if (item.feature && !isLoading && !allows(item.feature)) {
    return (
      <UpgradeBanner title={`${t(item.label)} ${t('is not available on your plan')}`} requires={item.requires ?? 'pro'} />
    );
  }

  return (
    <Suspense fallback={<PageFallback />}>
      <Page />
    </Suspense>
  );
}

export function App() {
  const route = useHashRoute();
  const [theme, toggleTheme] = useTheme();

  useEffect(() => {
    document.getElementById('sda-root')?.setAttribute('data-theme', theme);
  }, [theme]);

  useEffect(() => {
    const color = boot().branding.primary_color;
    if (color) {
      document.getElementById('sda-root')?.style.setProperty('--sda-primary', color);
    }
  }, []);

  const current = NAV.find((n) => route.startsWith(n.route)) ?? NAV[0];

  return (
    <QueryClientProvider client={queryClient}>
      <div className="sda-shell">
        <nav className="sda-sidebar" aria-label={boot().branding.name}>
          <div className="sda-sidebar__brand">
            {boot().branding.logo_url ? (
              <img src={boot().branding.logo_url} alt={boot().branding.name} style={{ maxWidth: '100%', maxHeight: 40 }} />
            ) : (
              boot().branding.name
            )}
          </div>
          {NAV.map((item) => (
            <a key={item.route} href={`#/${item.route}`} className={item.route === current.route ? 'is-active' : ''}>
              {t(item.label)}
            </a>
          ))}
        </nav>
        <main className="sda-main">
          <div className="sda-topbar">
            <h1>{t(current.label)}</h1>
            <div style={{ display: 'flex', gap: 12, alignItems: 'center' }}>
              <EditionBadge />
              <span style={{ fontSize: 12, color: 'var(--sda-text-muted)' }}>{boot().siteName}</span>
              <button type="button" className="sda-btn" onClick={toggleTheme} aria-label={t('Toggle theme')}>
                {theme === 'dark' ? '☀' : '☾'}
              </button>
            </div>
          </div>
          <RouteView item={current} />
        </main>
      </div>
    </QueryClientProvider>
  );
}
