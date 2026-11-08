import React, { useCallback, useEffect, useRef, useState } from 'react';
import { formatAdminDateTime } from '@cci/admin-ui';
import { InfoCallout, LoadingState } from '@cci/admin-ui/blog';
import { diffWordsWithSpace } from 'diff';
import { apiFetch, pluginData } from '../../api/api';
import { __ } from '../../i18n/i18n';
import { Button } from '../ui/button';
import { Card, CardHeader } from '../ui/card';
import { CheckboxField } from '../ui/checkbox';
import { DataTable, EmptyState, RowPrimary } from './AdminDataTable';
import { sanitizeDraftHtml } from '../builder/model/text';

const fieldLabels = () => ({
  title: __('Title', 'cci-blog'),
  slug: __('Slug', 'cci-blog'),
  intro: __('Introduction', 'cci-blog'),
  content: __('Article content', 'cci-blog'),
  blocks: __('Block settings', 'cci-blog'),
  id_author: __('Author', 'cci-blog'),
  id_category: __('Main category', 'cci-blog'),
  category_ids: __('Categories', 'cci-blog'),
  active: __('Active', 'cci-blog'),
  featured: __('Featured', 'cci-blog'),
  allow_comments: __('Allow comments', 'cci-blog'),
  cover_image: __('Cover image', 'cci-blog'),
  og_image: __('Open Graph image', 'cci-blog'),
  date_published: __('Publication date', 'cci-blog'),
  meta_title: __('Meta title', 'cci-blog'),
  meta_description: __('Meta description', 'cci-blog'),
  meta_keywords: __('Meta keywords', 'cci-blog'),
  focus_keyword: __('Optional focus phrase', 'cci-blog'),
  seo_content_type: __('Content type', 'cci-blog'),
  og_title: __('Open Graph title', 'cci-blog'),
  og_description: __('Open Graph description', 'cci-blog'),
  tags: __('Tags', 'cci-blog'),
});
const date = (value) => formatAdminDateTime(value, pluginData.adminDate) || '-';

export default function PostHistoryPanel({ postId, languageId, revisionKey, authors, categories }) {
  const [rows, setRows] = useState([]);
  const [beforeId, setBeforeId] = useState(null);
  const [selectedId, setSelectedId] = useState(0);
  const [loading, setLoading] = useState(false);
  const [listError, setListError] = useState('');
  const [detail, setDetail] = useState(null);
  const [detailLoading, setDetailLoading] = useState(false);
  const [detailError, setDetailError] = useState('');
  const [retryDetail, setRetryDetail] = useState(0);
  const [mode, setMode] = useState('changes');
  const [showUnchanged, setShowUnchanged] = useState(false);
  const listRequest = useRef(null);
  const fetchPage = useCallback(
    (cursor = 0) => {
      listRequest.current?.abort();
      const request = new AbortController();
      listRequest.current = request;
      setLoading(true);
      setListError('');
      return apiFetch('/post/history', {
        signal: request.signal,
        body: JSON.stringify({ id_post: postId, id_lang: languageId, before_id: cursor }),
      })
        .then((response) => {
          if (request.signal.aborted) return;
          const next = response.revisions || [];
          setRows((current) => (cursor ? [...current, ...next] : next));
          setBeforeId(response.next_before_id);
          if (!cursor) setSelectedId(Number(next[1]?.id_revision || next[0]?.id_revision || 0));
        })
        .catch((error) => {
          if (!request.signal.aborted) setListError(error.message);
        })
        .finally(() => {
          if (!request.signal.aborted) setLoading(false);
        });
    },
    [postId, languageId],
  );

  useEffect(() => {
    setRows([]);
    setSelectedId(0);
    setDetail(null);
    setBeforeId(null);
    if (postId > 0) fetchPage();
    return () => listRequest.current?.abort();
  }, [postId, languageId, revisionKey, fetchPage]);

  useEffect(() => {
    setDetail(null);
    setDetailError('');
    if (!selectedId) return;
    const request = new AbortController();
    setDetailLoading(true);
    apiFetch('/post/revision', {
      signal: request.signal,
      body: JSON.stringify({ id_post: postId, id_lang: languageId, id_revision: selectedId }),
    })
      .then((response) => {
        if (!request.signal.aborted) setDetail(response);
      })
      .catch((error) => {
        if (!request.signal.aborted) setDetailError(error.message);
      })
      .finally(() => {
        if (!request.signal.aborted) setDetailLoading(false);
      });
    return () => request.abort();
  }, [selectedId, postId, languageId, revisionKey, retryDetail]);

  if (!postId)
    return <EmptyState label={__('Save the article to start its version history.', 'cci-blog')} />;
  const labels = fieldLabels();
  const revision = detail?.revision;
  return (
    <div className="tw-grid tw-min-w-0 tw-grid-cols-1 tw-gap-4">
      <InfoCallout tone="info">
        {__(
          'Versions contain saved changes in this shop and language. Comparisons use the current saved article; unsaved edits are not included.',
          'cci-blog',
        )}
      </InfoCallout>
      <div className="tw-grid tw-min-w-0 tw-grid-cols-1 tw-items-start tw-gap-4 xl:tw-grid-cols-[minmax(0,280px)_minmax(0,1fr)]">
        <Card>
          <CardHeader>
            <h2 className="!tw-m-0 !tw-text-lg !tw-font-semibold">
              {__('Saved versions', 'cci-blog')}
            </h2>
          </CardHeader>
          {listError && (
            <div className="tw-p-4">
              <InfoCallout
                tone="danger"
                actions={
                  <Button variant="secondary" onClick={() => fetchPage(rows.length ? beforeId : 0)}>
                    {__('Retry', 'cci-blog')}
                  </Button>
                }
              >
                {listError}
              </InfoCallout>
            </div>
          )}
          {rows.length > 0 && (
            <DataTable
              compact
              label={__('Saved versions', 'cci-blog')}
              columns={[__('Version', 'cci-blog'), __('Saved by', 'cci-blog')]}
              rows={rows.map((row) => [
                <div key="version">
                  <RowPrimary
                    label={`#${row.id_revision}`}
                    onOpen={() => setSelectedId(Number(row.id_revision))}
                  />
                  <span className="tw-mt-1 tw-block tw-text-xs tw-text-cci-blog-muted">
                    {date(row.date_add)}
                  </span>
                  {selectedId === Number(row.id_revision) && (
                    <span className="tw-mt-1 tw-block tw-text-xs tw-font-semibold tw-text-cci-blog-brandStrong">
                      {__('Selected', 'cci-blog')}
                    </span>
                  )}
                </div>,
                <span key="employee">
                  {row.event_type === 'baseline'
                    ? __('Not recorded', 'cci-blog')
                    : row.employee_name || __('Former employee', 'cci-blog')}
                </span>,
              ])}
            />
          )}
          {loading && <LoadingState label={__('Loading versions...', 'cci-blog')} />}
          {!rows.length && !loading && !listError && (
            <EmptyState
              label={__('No saved versions yet. History starts with the next save.', 'cci-blog')}
            />
          )}
          {beforeId && (
            <div className="tw-p-4">
              <Button variant="secondary" disabled={loading} onClick={() => fetchPage(beforeId)}>
                {__('Load older versions', 'cci-blog')}
              </Button>
            </div>
          )}
        </Card>
        <div className="tw-grid tw-min-w-0 tw-grid-cols-1 tw-gap-4">
          {detailLoading && <LoadingState label={__('Loading version...', 'cci-blog')} />}
          {detailError && (
            <InfoCallout
              tone="danger"
              actions={
                <Button variant="secondary" onClick={() => setRetryDetail((value) => value + 1)}>
                  {__('Retry', 'cci-blog')}
                </Button>
              }
            >
              {detailError}
            </InfoCallout>
          )}
          {revision && (
            <>
              <Card>
                <CardHeader>
                  <h2 className="!tw-m-0 !tw-text-lg !tw-font-semibold">
                    {__('Version', 'cci-blog')} #{revision.id_revision}
                  </h2>
                </CardHeader>
                <div className="tw-grid tw-gap-3 tw-p-4">
                  <p className="tw-m-0 tw-text-sm">
                    {date(revision.date_add)} ·{' '}
                    {revision.employee_name || __('Not recorded', 'cci-blog')}
                  </p>
                  {revision.event_type === 'baseline' ? (
                    <InfoCallout tone="info">
                      {__(
                        'This is the article before version tracking started. Its previous editor is not known.',
                        'cci-blog',
                      )}
                    </InfoCallout>
                  ) : (
                    <p className="tw-m-0 tw-text-sm tw-text-cci-blog-muted">
                      {revision.changed_fields.length
                        ? `${__('Changed fields', 'cci-blog')}: ${revision.changed_fields.map((field) => labels[field] || field).join(', ')}`
                        : __('Saved without content changes.', 'cci-blog')}
                    </p>
                  )}
                  <div className="tw-flex tw-flex-wrap tw-gap-2">
                    <Button
                      variant={mode === 'changes' ? 'primary' : 'secondary'}
                      aria-pressed={mode === 'changes'}
                      onClick={() => setMode('changes')}
                    >
                      {__('Compare with current version', 'cci-blog')}
                    </Button>
                    <Button
                      variant={mode === 'version' ? 'primary' : 'secondary'}
                      aria-pressed={mode === 'version'}
                      onClick={() => setMode('version')}
                    >
                      {__('View full version', 'cci-blog')}
                    </Button>
                  </div>
                  {mode === 'changes' && (
                    <CheckboxField checked={showUnchanged} onCheckedChange={setShowUnchanged}>
                      {__('Show unchanged fields', 'cci-blog')}
                    </CheckboxField>
                  )}
                </div>
              </Card>
              {mode === 'changes' ? (
                <VersionComparison
                  before={revision.snapshot}
                  after={detail.current}
                  showUnchanged={showUnchanged}
                  authors={authors}
                  categories={categories}
                />
              ) : (
                <FullVersion
                  snapshot={revision.snapshot}
                  authors={authors}
                  categories={categories}
                />
              )}
            </>
          )}
        </div>
      </div>
    </div>
  );
}

function valueText(field, value, authors, categories) {
  if (value == null || value === '') return '';
  if (['active', 'featured', 'allow_comments'].includes(field))
    return Number(value) ? __('Yes', 'cci-blog') : __('No', 'cci-blog');
  if (field === 'id_author')
    return (
      (authors || []).find((author) => Number(author.id) === Number(value))?.name || `#${value}`
    );
  if (field === 'id_category')
    return (
      (categories || []).find((category) => Number(category.id_category) === Number(value))?.name ||
      `#${value}`
    );
  if (field === 'category_ids')
    return (value || []).map((id) => valueText('id_category', id, authors, categories)).join(', ');
  if (field === 'blocks') return JSON.stringify(value, null, 2);
  if (field === 'content' || field === 'intro') {
    const template = document.createElement('template');
    template.innerHTML = sanitizeDraftHtml(String(value));
    template.content
      .querySelectorAll('p,br,li,h2,h3,h4,blockquote')
      .forEach((node) => node.after(document.createTextNode('\n')));
    return (template.content.textContent || '').trim();
  }
  return String(value);
}

function VersionComparison({ before, after, showUnchanged, authors, categories }) {
  const labels = fieldLabels();
  const fields = Object.keys(labels).filter(
    (field) => showUnchanged || JSON.stringify(before[field]) !== JSON.stringify(after[field]),
  );
  if (!fields.length)
    return (
      <InfoCallout tone="info">
        {__('Selected version matches the current saved article.', 'cci-blog')}
      </InfoCallout>
    );
  return fields.map((field) => {
    const oldValue = valueText(field, before[field], authors, categories);
    const newValue = valueText(field, after[field], authors, categories);
    const content = <FieldComparison before={oldValue} after={newValue} />;
    return (
      <Card key={field}>
        {field === 'blocks' ? (
          <details className="tw-p-4">
            <summary className="tw-cursor-pointer tw-text-sm tw-font-semibold">
              {labels[field]}
            </summary>
            <div className="tw-mt-4">{content}</div>
          </details>
        ) : (
          <>
            <CardHeader>
              <h3 className="!tw-m-0 !tw-text-base !tw-font-semibold">{labels[field]}</h3>
            </CardHeader>
            {content}
          </>
        )}
      </Card>
    );
  });
}

function FieldComparison({ before, after }) {
  const parts = React.useMemo(() => {
    if (before.length + after.length > 100000)
      return [
        { value: before, removed: true },
        { value: after, added: true },
      ];
    return (
      diffWordsWithSpace(before, after, { timeout: 40, maxEditLength: 3000 }) || [
        { value: before, removed: true },
        { value: after, added: true },
      ]
    );
  }, [before, after]);
  return (
    <div className="tw-grid tw-min-w-0 tw-grid-cols-1 md:tw-grid-cols-2">
      {[false, true].map((current) => (
        <div key={String(current)} className="tw-min-w-0 tw-p-4">
          <strong className="tw-mb-3 tw-block tw-text-xs tw-text-cci-blog-muted">
            {current ? __('Current saved version', 'cci-blog') : __('Selected version', 'cci-blog')}
          </strong>
          <div className="tw-whitespace-pre-wrap tw-break-words tw-text-sm">
            {!(current ? after : before) ? (
              <span className="tw-text-cci-blog-muted">{__('Empty', 'cci-blog')}</span>
            ) : (
              parts
                .filter((part) => (current ? !part.removed : !part.added))
                .map((part, index) =>
                  part.added ? (
                    <ins key={index} className="tw-bg-green-100 tw-text-green-900 tw-no-underline">
                      {part.value}
                    </ins>
                  ) : part.removed ? (
                    <del key={index} className="tw-bg-red-100 tw-text-red-900">
                      {part.value}
                    </del>
                  ) : (
                    <React.Fragment key={index}>{part.value}</React.Fragment>
                  ),
                )
            )}
          </div>
        </div>
      ))}
    </div>
  );
}

function FullVersion({ snapshot, authors, categories }) {
  const labels = fieldLabels();
  const markup = React.useMemo(() => {
    const template = document.createElement('template');
    template.innerHTML = sanitizeDraftHtml(snapshot.content || '');
    template.content.querySelectorAll('a').forEach((node) => {
      for (const attribute of [...node.attributes]) node.removeAttribute(attribute.name);
    });
    return template.innerHTML;
  }, [snapshot.content]);
  return (
    <Card>
      <CardHeader>
        <h2 className="!tw-m-0 !tw-text-lg !tw-font-semibold">
          {snapshot.title || __('(no title)', 'cci-blog')}
        </h2>
      </CardHeader>
      <div className="tw-grid tw-min-w-0 tw-grid-cols-1 tw-gap-4 tw-p-4">
        <section
          aria-label={__('Article content', 'cci-blog')}
          className="tw-min-w-0 tw-break-words"
          dangerouslySetInnerHTML={{ __html: markup }}
        />
        <dl className="tw-m-0 tw-grid tw-gap-3">
          {Object.keys(labels)
            .filter((field) => !['title', 'content', 'blocks'].includes(field))
            .map((field) => (
              <div key={field} className="tw-grid tw-min-w-0 tw-gap-1">
                <dt className="tw-text-xs tw-text-cci-blog-muted">{labels[field]}</dt>
                <dd className="tw-m-0 tw-whitespace-pre-wrap tw-break-words tw-text-sm">
                  {valueText(field, snapshot[field], authors, categories) ||
                    __('Empty', 'cci-blog')}
                </dd>
              </div>
            ))}
        </dl>
        <details>
          <summary className="tw-cursor-pointer tw-text-sm tw-font-semibold">
            {__('Block settings', 'cci-blog')}
          </summary>
          <pre className="tw-mt-3 tw-whitespace-pre-wrap tw-break-words tw-text-xs">
            {JSON.stringify(snapshot.blocks, null, 2)}
          </pre>
        </details>
      </div>
    </Card>
  );
}
