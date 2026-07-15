/** Human-readable labels for deterministic classifier slugs. */

export const REASON_LABELS: Record<string, string> = {
  new_entry: 'New in results',
  ranking_gain: 'Ranking improved',
  demand_increase: 'Search demand up',
  ctr_improvement: 'CTR improved',
  gradual_growth: 'Gradual growth',
};

export const CAUSE_LABELS: Record<string, string> = {
  disappeared: 'Disappeared from results',
  ranking_loss: 'Ranking dropped',
  demand_drop: 'Search demand down',
  ctr_decline: 'CTR declined (SERP/title)',
  gradual_decay: 'Gradual decay',
};

export const ACTION_LABELS: Record<string, string> = {
  push_to_top3: 'Push into Top 3',
  push_to_page1: 'Push onto Page 1',
  protect_position: 'Protect position',
};

export const FIX_LABELS: Record<string, string> = {
  check_indexation: 'Check indexation',
  refresh_content_and_links: 'Refresh content + internal links',
  verify_seasonality: 'Verify seasonality',
  rewrite_title_meta: 'Rewrite title & meta',
  schedule_content_refresh: 'Schedule content refresh',
};

export const DETECTOR_LABELS: Record<string, string> = {
  striking_distance: 'Striking distance (pos 4–20)',
  low_ctr: 'High impressions, low CTR',
  near_top: 'Near Top 3 / Page 1',
};

export const RULE_LABELS: Record<string, string> = {
  traffic_drop: 'Traffic drop',
  keyword_loss: 'Keyword loss',
  cwv_regression: 'Core Web Vitals regression',
};

export function severityColor(severity: string): string {
  switch (severity) {
    case 'critical':
      return 'var(--sda-negative)';
    case 'high':
      return 'var(--sda-warning)';
    case 'medium':
      return 'var(--sda-primary)';
    default:
      return 'var(--sda-text-muted)';
  }
}
