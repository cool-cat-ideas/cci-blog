import React from 'react';
import { apiFetch, pluginData } from '../../../api/api';
import { __ } from '../../../i18n/i18n';
import { seoContentTypeConfig } from './content-types.js';

// No scoring, tokenization or rule evaluation here. Every result comes from PHP.
export function emptyPublicationAudit() {
  return {
    score: null,
    overallStatus: 'info',
    complete: false,
    pending: false,
    checks: [],
    configurationWarnings: [],
    metrics: {
      wordCount: 0,
      titleLength: 0,
      metaLength: 0,
      titlePixels: null,
      internalLinks: 0,
      externalLinks: 0,
      keywordDensity: 0,
      images: 0,
      contentType: 'article',
      contentTypeLabel: __('Article', 'cci-blog'),
    },
  };
}

function localizeAudit(audit) {
  return {
    ...audit,
    checks: (audit.checks || []).map((check) => ({
      ...check,
      label: __(check.label, 'cci-blog'),
      message: __(check.message, 'cci-blog'),
      help: check.help ? __(check.help, 'cci-blog') : '',
      details: (check.details || []).map((detail) => ({
        ...detail,
        reason: detail.reason ? __(detail.reason, 'cci-blog') : '',
      })),
    })),
    metrics: {
      ...audit.metrics,
      contentTypeLabel:
        seoContentTypeConfig[audit.metrics?.contentType]?.label || __('Article', 'cci-blog'),
    },
  };
}

export function usePublicationAudit(post) {
  const [audit, setAudit] = React.useState(emptyPublicationAudit);
  const [revision, setRevision] = React.useState(0);
  const generation = React.useRef(0);
  const { publication_audit, seo_score, ...draft } = post || {};
  const fields = [
    'id_post',
    'id_category',
    'id_author',
    'title',
    'slug',
    'intro',
    'meta_title',
    'meta_description',
    'focus_keyword',
    'seo_content_type',
    'allow_comments',
    'active',
    'blocks',
  ];
  const input = Object.fromEntries(
    fields.filter((key) => Object.hasOwn(draft, key)).map((key) => [key, draft[key]]),
  );
  if (!Array.isArray(draft.blocks)) input.content = draft.content || '';
  const body = JSON.stringify({
    ...input,
    id_lang: Number(draft.id_lang || pluginData.language?.id || 0),
  });
  React.useEffect(() => {
    const current = ++generation.current;
    const controller = new AbortController();
    setAudit((previous) => ({
      ...emptyPublicationAudit(),
      metrics: previous.metrics,
      pending: true,
    }));
    const timeout = window.setTimeout(() => {
      apiFetch('/post/audit', { body, signal: controller.signal })
        .then((response) => {
          if (!controller.signal.aborted && generation.current === current) {
            setAudit({ ...localizeAudit(response.audit), pending: false });
          }
        })
        .catch((error) => {
          if (!controller.signal.aborted && generation.current === current) {
            setAudit({
              ...emptyPublicationAudit(),
              error: error.message || __('The publication check failed. Try again.', 'cci-blog'),
            });
          }
        });
    }, 400);
    return () => {
      window.clearTimeout(timeout);
      controller.abort();
    };
  }, [body, revision]);
  return { ...audit, retry: () => setRevision((value) => value + 1) };
}
