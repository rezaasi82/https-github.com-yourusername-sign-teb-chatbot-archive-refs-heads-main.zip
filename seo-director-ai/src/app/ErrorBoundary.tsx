import { Component, type ErrorInfo, type ReactNode } from 'react';
import { t } from '../i18n';

interface Props {
  children: ReactNode;
}

interface State {
  error: Error | null;
}

/**
 * Top-level error boundary. Without it, any exception during render leaves the
 * mount point blank with no clue as to why — the exact "black dashboard" a real
 * install hits when boot data or an API shape is unexpected. Here we render the
 * message and stack so the failure is diagnosable in the field.
 */
export class ErrorBoundary extends Component<Props, State> {
  state: State = { error: null };

  static getDerivedStateFromError(error: Error): State {
    return { error };
  }

  componentDidCatch(error: Error, info: ErrorInfo): void {
    // Surface to the browser console for support/debugging.
    // eslint-disable-next-line no-console
    console.error('SEO Director AI crashed:', error, info.componentStack);
  }

  render(): ReactNode {
    const { error } = this.state;
    if (!error) {
      return this.props.children;
    }

    return (
      <div className="sda-card" style={{ margin: 24, maxWidth: 720 }}>
        <h2 style={{ textTransform: 'none', fontSize: 15, color: 'var(--sda-negative)' }}>
          {t('SEO Director AI could not start')}
        </h2>
        <p style={{ fontSize: 13, color: 'var(--sda-text)' }}>{error.message}</p>
        <p style={{ fontSize: 12, color: 'var(--sda-text-muted)' }}>
          {t('Please copy this message (and your browser console output) to support. Reloading the page may help if this was a temporary network error.')}
        </p>
        {error.stack ? (
          <pre
            style={{
              fontSize: 11,
              overflowX: 'auto',
              background: 'var(--sda-surface-2)',
              padding: 12,
              borderRadius: 8,
            }}
          >
            {error.stack}
          </pre>
        ) : null}
      </div>
    );
  }
}
