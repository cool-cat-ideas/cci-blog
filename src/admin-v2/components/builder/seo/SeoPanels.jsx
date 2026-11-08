import React from 'react';
import { Link2 } from 'lucide-react';
import { __ } from '../../../i18n/i18n';
import { Button } from '../../ui/button';
import { Field } from '../../ui/field';
import { Input, Textarea } from '../../ui/input';
import { Select } from '../../ui/select';
import LocalMediaUrlInput from '../../media/LocalMediaUrlInput';
import { normalizeAdminSlug } from '../model/text.js';
import { blogProFeatures } from '../config/features.js';
import { buildPostStorefrontUrl } from '../model/links.js';
import { seoContentTypeOptions } from './content-types.js';

export function SeoEditorPanel({ post, audit, onChange, onLockedFeatureClick }) {
  const previewTitle = post.meta_title || post.title || __('Untitled post', 'cci-blog');
  const previewDescription =
    post.meta_description || post.intro || __('Meta description will appear here.', 'cci-blog');
  const previewSlug =
    post.slug ||
    normalizeAdminSlug(post.title || '') ||
    normalizeAdminSlug(__('auto-generated from title', 'cci-blog'));
  const previewUrl = buildPostStorefrontUrl(previewSlug);
  const update = (patch) => onChange(patch);

  return (
    <div className="cci-blog-seo-tab-layout tw-grid tw-gap-4">
      <div className="tw-grid tw-content-start tw-gap-4">
        <section className="tw-rounded-md tw-border tw-border-solid tw-border-cci-blog-border tw-bg-white tw-p-4">
          <div className="tw-grid tw-gap-3 md:tw-grid-cols-2">
            <Field label={__('Optional focus phrase', 'cci-blog')}>
              <Input
                value={post.focus_keyword || ''}
                onChange={(event) => update({ focus_keyword: event.target.value })}
                placeholder={__('main search phrase', 'cci-blog')}
              />
            </Field>
            <Field
              label={__('Content type', 'cci-blog')}
              note={__(
                'Article type provides context for custom rules. It does not set a minimum word count.',
                'cci-blog',
              )}
            >
              <Select
                value={post.seo_content_type || 'article'}
                onValueChange={(value) => update({ seo_content_type: value })}
                options={seoContentTypeOptions()}
              />
            </Field>
            <Field label={__('Tags', 'cci-blog')}>
              <Input
                value={post.tags || ''}
                onChange={(event) => update({ tags: event.target.value })}
                placeholder={__('news, guide, sale', 'cci-blog')}
              />
            </Field>
            <Field label={__('Title tag', 'cci-blog')}>
              <Input
                value={post.meta_title || ''}
                onChange={(event) => update({ meta_title: event.target.value })}
                placeholder={post.title || __('Post title fallback', 'cci-blog')}
              />
            </Field>
            <Field label={__('Meta description', 'cci-blog')}>
              <Textarea
                value={post.meta_description || ''}
                onChange={(event) => update({ meta_description: event.target.value })}
              />
            </Field>
            <Field
              label={__('Open Graph image URL', 'cci-blog')}
              note={__('Leave empty to use the article cover image.', 'cci-blog')}
            >
              <LocalMediaUrlInput
                value={post.og_image || ''}
                onChange={(og_image) => update({ og_image })}
                onLockedClick={() => onLockedFeatureClick?.(blogProFeatures.localMediaLibrary)}
                placeholder="/img/..."
              />
            </Field>
            <Field
              label={__('Social sharing title', 'cci-blog')}
              note={__('Leave empty to use the SEO title.', 'cci-blog')}
            >
              <Input
                value={post.og_title || ''}
                onChange={(event) => update({ og_title: event.target.value })}
              />
            </Field>
            <Field
              label={__('Social sharing description', 'cci-blog')}
              note={__('Leave empty to use the SEO description.', 'cci-blog')}
            >
              <Textarea
                value={post.og_description || ''}
                onChange={(event) => update({ og_description: event.target.value })}
              />
            </Field>
          </div>
        </section>

        <section className="tw-rounded-md tw-border tw-border-solid tw-border-cci-blog-border tw-bg-white tw-p-4">
          <div className="tw-grid tw-gap-2">
            <strong className="tw-text-base tw-font-semibold tw-text-cci-blog-text">
              {__('Search preview', 'cci-blog')}
            </strong>
            <div className="tw-rounded-md tw-border tw-border-solid tw-border-cci-blog-border tw-bg-cci-blog-surfaceSoft tw-p-4">
              <div className="tw-break-words tw-text-lg tw-font-semibold tw-leading-6 tw-text-blue-700">
                {previewTitle}
              </div>
              <div className="tw-mt-1 tw-break-all tw-text-xs tw-leading-5 tw-text-green-700">
                {previewUrl}
              </div>
              <p className="tw-m-0 tw-mt-2 tw-text-sm tw-leading-6 tw-text-cci-blog-muted">
                {previewDescription}
              </p>
            </div>
          </div>
        </section>

        <SeoChecksPanel audit={audit} />
      </div>

      <aside className="cci-blog-seo-sidebar tw-grid tw-content-start tw-gap-4">
        <SeoScorePanel audit={audit} />
      </aside>
    </div>
  );
}

export function SeoContentAssistPanel({
  audit,
  onOpenSeo,
  compact = false,
  description,
  openLabel,
}) {
  const tone = seoStatusTone(audit.overallStatus);
  const priorityChecks = getSeoPriorityChecks(audit.checks, compact ? 4 : 4);
  const panelDescription = description || __('Verified checks and editorial guidance', 'cci-blog');
  const seoButtonLabel = openLabel || __('Open SEO settings', 'cci-blog');

  if (compact) {
    return (
      <section className="tw-rounded-md tw-border tw-border-solid tw-border-cci-blog-border tw-bg-white tw-p-3">
        <div className="tw-grid tw-gap-2.5">
          <div className="tw-flex tw-items-start tw-justify-between tw-gap-2">
            <div>
              <strong className="tw-block tw-text-sm tw-font-semibold tw-leading-5 tw-text-cci-blog-text">
                {__('Publication checks', 'cci-blog')}
              </strong>
              <p className="tw-m-0 tw-text-xs tw-leading-4 tw-text-cci-blog-muted">
                {panelDescription}
              </p>
            </div>
            <span
              className={`tw-inline-flex tw-min-w-12 tw-shrink-0 tw-items-center tw-justify-center tw-rounded-md tw-border tw-border-solid tw-px-2 tw-py-1 tw-text-sm tw-font-semibold ${tone.badge}`}
            >
              {audit.score ?? '-'}
            </span>
          </div>
          <div
            className="tw-h-2 tw-overflow-hidden tw-rounded-full tw-bg-slate-100"
            aria-hidden="true"
          >
            <div
              className={`tw-h-full tw-rounded-full ${tone.bar}`}
              style={{ width: `${audit.score ?? '-'}%` }}
            />
          </div>
          <AuditRequestState audit={audit} />
          <div className="tw-flex tw-flex-wrap tw-gap-1">
            <SeoTinyMetric label={__('Words', 'cci-blog')} value={audit.metrics.wordCount} />
            <SeoTinyMetric
              label={__('Title', 'cci-blog')}
              value={`${audit.metrics.titleLength} ${__('chars', 'cci-blog')}`}
            />
            <SeoTinyMetric
              label={__('Meta', 'cci-blog')}
              value={`${audit.metrics.metaLength} ${__('chars', 'cci-blog')}`}
            />
          </div>
          <div className="tw-grid tw-gap-1.5">
            {priorityChecks.map((check) => (
              <SeoSuggestionRow key={check.id} check={check} compact />
            ))}
          </div>
          <Button
            variant="builder"
            size="sm"
            type="button"
            className="tw-w-full"
            onClick={onOpenSeo}
          >
            <Link2 aria-hidden="true" />
            {seoButtonLabel}
          </Button>
        </div>
      </section>
    );
  }

  return (
    <section className="tw-rounded-md tw-border tw-border-solid tw-border-cci-blog-border tw-bg-white tw-p-4">
      <div className="tw-grid tw-gap-4 xl:tw-grid-cols-[220px_minmax(0,1fr)_auto] xl:tw-items-start">
        <div className="tw-grid tw-gap-2">
          <div className="tw-flex tw-items-center tw-justify-between tw-gap-3">
            <div>
              <strong className="tw-block tw-text-base tw-font-semibold tw-leading-5 tw-text-cci-blog-text">
                {__('Publication checks', 'cci-blog')}
              </strong>
              <p className="tw-m-0 tw-mt-1 tw-text-xs tw-leading-5 tw-text-cci-blog-muted">
                {description ||
                  __(
                    'Server checks for this draft, not a prediction of search positions.',
                    'cci-blog',
                  )}
              </p>
            </div>
            <span
              className={`tw-inline-flex tw-min-w-14 tw-items-center tw-justify-center tw-rounded-md tw-border tw-border-solid tw-px-2.5 tw-py-1.5 tw-text-base tw-font-semibold ${tone.badge}`}
            >
              {audit.score ?? '-'}
            </span>
          </div>
          <div
            className="tw-h-2 tw-overflow-hidden tw-rounded-full tw-bg-slate-100"
            aria-hidden="true"
          >
            <div
              className={`tw-h-full tw-rounded-full ${tone.bar}`}
              style={{ width: `${audit.score ?? '-'}%` }}
            />
          </div>
          <AuditRequestState audit={audit} />
          <div className="tw-flex tw-flex-wrap tw-gap-1.5">
            <SeoTinyMetric label={__('Words', 'cci-blog')} value={audit.metrics.wordCount} />
            <SeoTinyMetric
              label={__('Title', 'cci-blog')}
              value={`${audit.metrics.titleLength} ${__('chars', 'cci-blog')}`}
            />
            <SeoTinyMetric
              label={__('Meta', 'cci-blog')}
              value={`${audit.metrics.metaLength} ${__('chars', 'cci-blog')}`}
            />
          </div>
        </div>

        <div className="tw-grid tw-gap-2 md:tw-grid-cols-2">
          {priorityChecks.map((check) => (
            <SeoSuggestionRow key={check.id} check={check} />
          ))}
        </div>

        <div className="tw-flex tw-justify-start xl:tw-justify-end">
          <Button variant="builder" type="button" onClick={onOpenSeo}>
            <Link2 aria-hidden="true" />
            {seoButtonLabel}
          </Button>
        </div>
      </div>
    </section>
  );
}

export function SeoTinyMetric({ label, value }) {
  return (
    <span className="tw-inline-flex tw-items-center tw-gap-1 tw-rounded-full tw-border tw-border-solid tw-border-cci-blog-border tw-bg-cci-blog-surfaceSoft tw-px-1.5 tw-py-1 tw-text-[11px] tw-leading-none tw-text-cci-blog-muted">
      {label}
      <strong className="tw-font-semibold tw-text-cci-blog-text">{value}</strong>
    </span>
  );
}

export function SeoSuggestionRow({ check, compact = false }) {
  const tone = seoStatusTone(check.status);

  if (compact) {
    return (
      <div className="tw-grid tw-grid-cols-[auto_minmax(0,1fr)] tw-gap-2 tw-rounded-md tw-border tw-border-solid tw-border-cci-blog-border tw-bg-cci-blog-surfaceSoft tw-px-2.5 tw-py-2">
        <span className={`tw-mt-1 tw-h-2 tw-w-2 tw-rounded-full ${tone.dot}`} aria-hidden="true" />
        <div className="tw-min-w-0">
          <div className="tw-flex tw-min-w-0 tw-items-center tw-gap-2">
            <strong className="tw-min-w-0 tw-truncate tw-text-xs tw-font-semibold tw-leading-4 tw-text-cci-blog-text">
              {check.label}
            </strong>
          </div>
          <p className="tw-m-0 tw-mt-0.5 tw-line-clamp-2 tw-text-[11px] tw-leading-4 tw-text-cci-blog-muted">
            {compactSeoMessage(check)}
          </p>
        </div>
      </div>
    );
  }

  return (
    <div className="tw-grid tw-grid-cols-[auto_minmax(0,1fr)] tw-gap-2 tw-rounded-md tw-border tw-border-solid tw-border-cci-blog-border tw-bg-cci-blog-surfaceSoft tw-p-3">
      <span
        className={`tw-mt-1.5 tw-h-2.5 tw-w-2.5 tw-rounded-full ${tone.dot}`}
        aria-hidden="true"
      />
      <div className="tw-min-w-0">
        <div className="tw-flex tw-flex-wrap tw-items-center tw-gap-1.5">
          <strong className="tw-text-xs tw-font-semibold tw-leading-4 tw-text-cci-blog-text">
            {check.label}
          </strong>
        </div>
        <p className="tw-m-0 tw-mt-1 tw-text-xs tw-leading-5 tw-text-cci-blog-muted">
          {check.message}
        </p>
        {check.source && check.source !== 'core' && (
          <p className="tw-m-0 tw-mt-1 tw-text-xs tw-text-cci-blog-muted">
            {__('Custom rule from:', 'cci-blog')} {check.source}
          </p>
        )}
        {check.details?.length > 0 && (
          <ul className="tw-m-0 tw-mt-2 tw-grid tw-gap-1 tw-pl-4 tw-text-xs tw-leading-5">
            {check.details.map((detail, index) => (
              <li key={index}>
                <span className="tw-break-all">
                  {detail.href ||
                    detail.title ||
                    (detail.id_product ? `#${detail.id_product}` : '')}
                </span>
                {detail.reason ? `: ${detail.reason}` : ''}
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
}

export function compactSeoMessage(check) {
  const message = String(check?.message || '');
  return message
    .replace(/^Set a /i, __('Add ', 'cci-blog'))
    .replace(/^Add the /i, __('Add ', 'cci-blog'))
    .replace(/^Title tag length:/i, __('Title:', 'cci-blog'))
    .replace(/^Meta description length:/i, __('Meta:', 'cci-blog'))
    .replace(/^Keyword density:/i, __('Density:', 'cci-blog'));
}

function AuditRequestState({ audit }) {
  if (audit.pending)
    return (
      <p className="tw-m-0 tw-text-xs tw-leading-5 tw-text-cci-blog-muted" role="status">
        {__('Checking this draft...', 'cci-blog')}
      </p>
    );
  if (audit.error)
    return (
      <div className="tw-grid tw-gap-2" role="alert">
        <p className="tw-m-0 tw-text-xs tw-leading-5">{audit.error}</p>
        <Button variant="builder" size="sm" type="button" onClick={audit.retry}>
          {__('Retry', 'cci-blog')}
        </Button>
      </div>
    );
  if (audit.configurationWarnings?.length)
    return (
      <div className="tw-text-xs tw-leading-5" role="alert">
        <p className="tw-m-0">
          {__('Audit rules need attention. Check your integration configuration.', 'cci-blog')}
        </p>
        <ul className="tw-m-0 tw-mt-2 tw-pl-4 tw-break-words">
          {audit.configurationWarnings.slice(0, 10).map((warning, index) => (
            <li key={index}>{warning}</li>
          ))}
        </ul>
      </div>
    );
  if (!audit.complete && audit.checks.length)
    return (
      <p className="tw-m-0 tw-text-xs tw-leading-5" role="status">
        {__(
          'Some checks are unavailable. Review the report; no complete score is shown.',
          'cci-blog',
        )}
      </p>
    );
  if (audit.customized)
    return (
      <p className="tw-m-0 tw-text-xs tw-leading-5 tw-text-cci-blog-muted">
        {__('This shop uses custom audit rules.', 'cci-blog')}
      </p>
    );
  return null;
}

export function SeoScorePanel({ audit, description }) {
  const tone = seoStatusTone(audit.overallStatus);

  return (
    <section className="tw-rounded-md tw-border tw-border-solid tw-border-cci-blog-border tw-bg-white tw-p-4">
      <div className="tw-grid tw-gap-3">
        <div className="tw-flex tw-items-start tw-justify-between tw-gap-3">
          <div>
            <strong className="tw-text-base tw-font-semibold tw-text-cci-blog-text">
              {__('Publication checks', 'cci-blog')}
            </strong>
            <p className="tw-m-0 tw-mt-1 tw-text-xs tw-leading-5 tw-text-cci-blog-muted">
              {description ||
                __(
                  'Only applicable scored checks affect this result. It does not measure usefulness or predict Google rankings.',
                  'cci-blog',
                )}
            </p>
          </div>
          <span
            className={`tw-inline-flex tw-min-w-16 tw-items-center tw-justify-center tw-rounded-md tw-border tw-border-solid tw-px-3 tw-py-2 tw-text-lg tw-font-semibold ${tone.badge}`}
          >
            {audit.score ?? '-'}
          </span>
        </div>
        <div
          className="tw-h-2 tw-overflow-hidden tw-rounded-full tw-bg-slate-100"
          aria-hidden="true"
        >
          <div
            className={`tw-h-full tw-rounded-full ${tone.bar}`}
            style={{ width: `${audit.score ?? 0}%` }}
          />
        </div>
        <AuditRequestState audit={audit} />
        <div className="tw-grid tw-grid-cols-1 tw-gap-2">
          <SeoMetricPill label={__('Words', 'cci-blog')} value={audit.metrics.wordCount} />
          <SeoMetricPill
            label={__('Content type', 'cci-blog')}
            value={audit.metrics.contentTypeLabel}
          />
          <SeoMetricPill
            label={__('Literal phrase matches', 'cci-blog')}
            value={audit.metrics.keywordOccurrences || 0}
          />
          <SeoMetricPill label={__('Title chars', 'cci-blog')} value={audit.metrics.titleLength} />
          <SeoMetricPill label={__('Meta chars', 'cci-blog')} value={audit.metrics.metaLength} />
          <SeoMetricPill
            label={__('Internal links', 'cci-blog')}
            value={audit.metrics.internalLinks}
          />
          <SeoMetricPill label={__('Images', 'cci-blog')} value={audit.metrics.images} />
        </div>
      </div>
    </section>
  );
}

export function SeoMetricPill({ label, value }) {
  return (
    <div className="tw-rounded-md tw-border tw-border-solid tw-border-cci-blog-border tw-bg-cci-blog-surfaceSoft tw-px-3 tw-py-2.5">
      <span className="tw-block tw-text-xs tw-leading-4 tw-text-cci-blog-muted">{label}</span>
      <strong className="tw-mt-1 tw-block tw-text-sm tw-font-semibold tw-text-cci-blog-text">
        {value}
      </strong>
    </div>
  );
}

export function SeoChecksPanel({ audit }) {
  const groups = groupSeoChecks(audit.checks);

  return (
    <div className="tw-grid tw-gap-4">
      {groups.map((group) => (
        <section
          key={group.id}
          className="tw-rounded-md tw-border tw-border-solid tw-border-cci-blog-border tw-bg-white tw-p-4"
        >
          <div className="tw-grid tw-gap-3">
            <div>
              <strong className="tw-text-base tw-font-semibold tw-text-cci-blog-text">
                {group.label}
              </strong>
              <p className="tw-m-0 tw-mt-1 tw-text-xs tw-leading-5 tw-text-cci-blog-muted">
                {seoGroupDescription(group.id)}
              </p>
            </div>
            <div className="tw-grid tw-gap-2">
              {group.checks.map((check) => (
                <SeoCheckRow key={check.id} check={check} />
              ))}
            </div>
          </div>
        </section>
      ))}
    </div>
  );
}

export function seoGroupDescription(groupId) {
  const descriptions = {
    keyword: __('Optional phrase information without default scoring.', 'cci-blog'),
    title: __('Title length, estimated pixel width and keyword placement.', 'cci-blog'),
    meta: __('Meta description length, sentence quality and keyword usage.', 'cci-blog'),
    slug: __('URL slug length, stop words and keyword coverage.', 'cci-blog'),
    metadata: __(
      'Available and duplicate search metadata. Display-length advice is optional.',
      'cci-blog',
    ),
    content: __('Visible content and heading structure. Word count is informational.', 'cci-blog'),
    links: __(
      'Locally checked pages, section anchors and products. External URLs are not fetched.',
      'cci-blog',
    ),
    media: __('Descriptions for informative images and decorative-image guidance.', 'cci-blog'),
    readability: __('Sentence length and simple readability heuristics.', 'cci-blog'),
  };

  return descriptions[groupId] || __('SEO checks for this area.', 'cci-blog');
}

export function SeoCheckRow({ check }) {
  const tone = seoStatusTone(check.status);

  return (
    <div
      className="tw-grid tw-grid-cols-[auto_minmax(0,1fr)] tw-gap-3 tw-rounded-md tw-border tw-border-solid tw-border-cci-blog-border tw-bg-cci-blog-surfaceSoft tw-p-3"
      title={check.help || ''}
    >
      <span
        className={`tw-mt-1 tw-h-2.5 tw-w-2.5 tw-rounded-full ${tone.dot}`}
        aria-hidden="true"
      />
      <div className="tw-min-w-0">
        <div className="tw-flex tw-flex-wrap tw-items-center tw-gap-2">
          <strong className="tw-text-sm tw-font-semibold tw-text-cci-blog-text">
            {check.label}
          </strong>
          <span
            className={`tw-rounded-full tw-px-2 tw-py-1 tw-text-[11px] tw-font-semibold tw-leading-none ${tone.badge}`}
          >
            {statusLabel(check.status)}
          </span>
        </div>
        <p className="tw-m-0 tw-mt-1 tw-text-xs tw-leading-5 tw-text-cci-blog-muted">
          {check.message}
        </p>
        {check.source && check.source !== 'core' && (
          <p className="tw-m-0 tw-mt-1 tw-text-xs tw-text-cci-blog-muted">
            {__('Custom rule from:', 'cci-blog')} {check.source}
          </p>
        )}
        {check.details?.length > 0 && (
          <ul className="tw-m-0 tw-mt-2 tw-grid tw-gap-1 tw-pl-4 tw-text-xs tw-leading-5">
            {check.details.map((detail, index) => (
              <li key={index}>
                <span className="tw-break-all">
                  {detail.href ||
                    detail.title ||
                    (detail.id_product ? `#${detail.id_product}` : '')}
                </span>
                {detail.reason ? `: ${detail.reason}` : ''}
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
}

export function getSeoPriorityChecks(checks, limit = 4) {
  const rank = { unavailable: 0, bad: 0, warning: 1, info: 2, good: 3, not_applicable: 4 };
  const filtered = (Array.isArray(checks) ? checks : [])
    .filter((check) => check.id !== 'content_type')
    .sort((a, b) => {
      const statusDelta = (rank[a.status] ?? 2) - (rank[b.status] ?? 2);
      if (statusDelta !== 0) {
        return statusDelta;
      }

      return Number(b.weight || 0) - Number(a.weight || 0);
    });

  const needsWork = filtered
    .filter((check) => ['bad', 'warning', 'unavailable'].includes(check.status))
    .slice(0, limit);
  if (needsWork.length > 0) {
    return needsWork;
  }

  return filtered.filter((check) => check.status === 'good' && check.scored).slice(0, limit);
}

export function groupSeoChecks(checks) {
  const labels = {
    keyword: __('Optional focus phrase', 'cci-blog'),
    metadata: __('Search metadata', 'cci-blog'),
    custom: __('Custom rules', 'cci-blog'),
    title: __('Title tag', 'cci-blog'),
    meta: __('Meta description', 'cci-blog'),
    slug: __('Slug and URL', 'cci-blog'),
    content: __('Content structure', 'cci-blog'),
    links: __('Links', 'cci-blog'),
    media: __('Images', 'cci-blog'),
    readability: __('Readability', 'cci-blog'),
  };
  const order = [
    ...Object.keys(labels),
    ...new Set(checks.map((check) => check.group).filter((id) => !Object.hasOwn(labels, id))),
  ];

  return order
    .map((id) => ({
      id,
      label: labels[id] || id,
      checks: checks.filter((check) => check.group === id),
    }))
    .filter((group) => group.checks.length > 0);
}

export function seoStatusTone(status) {
  const tones = {
    good: {
      dot: 'tw-bg-green-500',
      badge: 'tw-border-green-200 tw-bg-green-50 tw-text-green-700',
      bar: 'tw-bg-green-500',
      text: 'tw-text-green-700',
    },
    warning: {
      dot: 'tw-bg-amber-500',
      badge: 'tw-border-amber-200 tw-bg-amber-50 tw-text-amber-700',
      bar: 'tw-bg-amber-500',
      text: 'tw-text-amber-700',
    },
    bad: {
      dot: 'tw-bg-red-500',
      badge: 'tw-border-red-200 tw-bg-red-50 tw-text-red-700',
      bar: 'tw-bg-red-500',
      text: 'tw-text-red-700',
    },
    info: {
      dot: 'tw-bg-blue-500',
      badge: 'tw-border-blue-200 tw-bg-blue-50 tw-text-blue-700',
      bar: 'tw-bg-blue-500',
      text: 'tw-text-blue-700',
    },
  };

  return tones[status] || tones.info;
}

export function statusLabel(status) {
  if (status === 'unavailable') return __('Unavailable', 'cci-blog');
  if (status === 'not_applicable') return __('Not applicable', 'cci-blog');
  if (status === 'good') return __('Good', 'cci-blog');
  if (status === 'warning') return __('Warning', 'cci-blog');
  if (status === 'bad') return __('Needs work', 'cci-blog');
  return __('Info', 'cci-blog');
}
