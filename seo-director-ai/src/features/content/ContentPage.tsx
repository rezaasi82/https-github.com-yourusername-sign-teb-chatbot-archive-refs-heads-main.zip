import { useMutation } from '@tanstack/react-query';
import { useState } from 'react';
import { api, type ContentGapTopic, type MetaSuggestion } from '../../api/client';
import { useLicense } from '../../app/license';

// Google truncates titles near ~600px and descriptions near ~960px in SERPs.
const TITLE_LIMIT = 600;
const DESC_LIMIT = 960;

function PixelBar({ px, limit }: { px: number; limit: number }) {
  const pct = Math.min(100, Math.round((px / limit) * 100));
  const over = px > limit;
  return (
    <div style={{ marginBlockStart: 4 }}>
      <div style={{ height: 6, background: 'var(--sda-border)', borderRadius: 3, overflow: 'hidden' }}>
        <div style={{ width: `${pct}%`, height: '100%', background: over ? 'var(--sda-negative)' : 'var(--sda-primary)' }} />
      </div>
      <small style={{ color: over ? 'var(--sda-negative)' : 'var(--sda-text-muted)' }}>
        {px}px / {limit}px{over ? ' — may be truncated' : ''}
      </small>
    </div>
  );
}

function MetaGenerator() {
  const [hash, setHash] = useState('');
  const meta = useMutation<MetaSuggestion, Error, string>({ mutationFn: (h) => api.contentMeta(h) });

  return (
    <div className="sda-card">
      <h2 style={{ marginBlockStart: 0 }}>Meta generator</h2>
      <p style={{ fontSize: 13, color: 'var(--sda-text-muted)' }}>
        Paste a page hash from the Winners &amp; Losers or Opportunities tables to generate an intent-matched title and
        description, sized against SERP pixel limits.
      </p>
      <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
        <input
          className="sda-input"
          style={{ flex: 1, minWidth: 220 }}
          placeholder="Page hash"
          value={hash}
          onChange={(e) => setHash(e.target.value.trim())}
        />
        <button
          type="button"
          className="sda-btn sda-btn--primary"
          onClick={() => meta.mutate(hash)}
          disabled={meta.isPending || hash === ''}
        >
          {meta.isPending ? 'Writing…' : 'Generate'}
        </button>
      </div>

      {meta.error != null && (
        <div className="sda-empty" style={{ marginBlockStart: 12 }}>
          <strong>Could not generate</strong>
          {meta.error.message}
        </div>
      )}

      {meta.data && (
        <div style={{ marginBlockStart: 16, display: 'grid', gap: 16 }}>
          <div>
            <strong>Title</strong>
            <div style={{ marginBlockStart: 4 }}>{meta.data.title}</div>
            <PixelBar px={meta.data.title_px} limit={TITLE_LIMIT} />
          </div>
          <div>
            <strong>Description</strong>
            <div style={{ marginBlockStart: 4 }}>{meta.data.description}</div>
            <PixelBar px={meta.data.desc_px} limit={DESC_LIMIT} />
          </div>
        </div>
      )}
    </div>
  );
}

function GapAnalysis() {
  const gap = useMutation<{ topics: ContentGapTopic[] }, Error>({ mutationFn: api.contentGap });

  return (
    <div className="sda-card">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
        <div>
          <h2 style={{ margin: 0 }}>Content gap analysis</h2>
          <p style={{ fontSize: 13, color: 'var(--sda-text-muted)', margin: '4px 0 0' }}>
            Topics you rank for on the fringe but have no dedicated page to serve.
          </p>
        </div>
        <button type="button" className="sda-btn sda-btn--primary" onClick={() => gap.mutate()} disabled={gap.isPending}>
          {gap.isPending ? 'Analyzing…' : 'Run analysis'}
        </button>
      </div>

      {gap.error != null && (
        <div className="sda-empty" style={{ marginBlockStart: 12 }}>
          <strong>Could not analyze</strong>
          {gap.error.message}
        </div>
      )}

      {gap.data && gap.data.topics.length === 0 && (
        <div className="sda-empty" style={{ marginBlockStart: 12 }}>
          <strong>No clear gaps found</strong>
          Your current pages cover the queries you rank for.
        </div>
      )}

      {gap.data && gap.data.topics.length > 0 && (
        <ul style={{ marginBlockStart: 12, paddingInlineStart: 18, display: 'grid', gap: 10 }}>
          {gap.data.topics.map((topic, index) => (
            <li key={index}>
              <strong>{topic.topic}</strong>
              {topic.rationale && (
                <div style={{ fontSize: 13, color: 'var(--sda-text-muted)' }}>{topic.rationale}</div>
              )}
              {Array.isArray(topic.target_queries) && topic.target_queries.length > 0 && (
                <div style={{ fontSize: 12, color: 'var(--sda-text-muted)', marginBlockStart: 2 }}>
                  {topic.target_queries.join(' · ')}
                </div>
              )}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

export function ContentPage() {
  const { allows, isLoading } = useLicense();

  if (isLoading) {
    return <div className="sda-skeleton" style={{ height: 200 }} />;
  }

  if (!allows('content_strategist')) {
    return (
      <div className="sda-card sda-empty">
        <strong>Content Strategist is a Pro feature</strong>
        Upgrade your license to generate meta tags and run content gap analysis.
      </div>
    );
  }

  return (
    <div style={{ display: 'grid', gap: 16 }}>
      <MetaGenerator />
      <GapAnalysis />
    </div>
  );
}
