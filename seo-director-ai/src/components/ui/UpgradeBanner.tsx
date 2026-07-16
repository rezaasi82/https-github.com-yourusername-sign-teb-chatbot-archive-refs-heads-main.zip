import type { ReactNode } from 'react';
import { useLicense } from '../../app/license';

const EDITION_LABEL: Record<string, string> = {
  lite: 'Lite',
  starter: 'Starter',
  pro: 'Pro',
  agency: 'Agency',
  enterprise: 'Enterprise',
};

/**
 * A full-card upsell shown in place of a feature the current edition can't use.
 * `requires` names the edition the user should move to.
 */
export function UpgradeBanner({ title, requires, children }: { title: string; requires: string; children?: ReactNode }) {
  const { upgradeUrl } = useLicense();

  return (
    <div className="sda-card sda-empty" style={{ borderInlineStart: '3px solid var(--sda-primary)' }}>
      <strong>{title}</strong>
      <p style={{ margin: '4px 0 12px', color: 'var(--sda-text-muted)' }}>
        {children ?? <>This is available on the {EDITION_LABEL[requires] ?? requires} plan and above.</>}
      </p>
      <a className="sda-btn sda-btn--primary" href={upgradeUrl} target="_blank" rel="noreferrer">
        Upgrade to {EDITION_LABEL[requires] ?? requires}
      </a>
    </div>
  );
}

/**
 * Small edition chip for the shell. Shows an upgrade affordance on Lite/Starter.
 */
export function EditionBadge() {
  const { edition, isLite, upgradeUrl } = useLicense();
  const label = EDITION_LABEL[edition] ?? edition;
  const canUpsell = isLite || edition === 'lite' || edition === 'starter';

  return (
    <span style={{ display: 'inline-flex', gap: 6, alignItems: 'center' }}>
      <span className="sda-badge" style={{ textTransform: 'none' }}>
        {label}
      </span>
      {canUpsell && (
        <a href={upgradeUrl} target="_blank" rel="noreferrer" style={{ fontSize: 11, color: 'var(--sda-primary)' }}>
          Upgrade
        </a>
      )}
    </span>
  );
}
