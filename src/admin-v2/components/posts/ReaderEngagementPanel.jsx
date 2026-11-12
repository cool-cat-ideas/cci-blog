import React from 'react';
import { formatAdminDateTime, ResourceListHeader } from '@cci/admin-ui';
import { apiFetch, pluginData } from '../../api/api';
import { __ } from '../../i18n/i18n';
import { Button } from '../ui/button';
import { Card, CardContent, CardHeader } from '../ui/card';
import { Field, FieldLabel } from '../ui/field';
import { Input, Textarea } from '../ui/input';
import { Select } from '../ui/select';
import { CheckboxField } from '../ui/checkbox';
import { Tabs, TabsList, TabsPanel, TabsTrigger } from '../ui/tabs';
import { DataTable, EmptyState, RowPrimary } from './AdminDataTable';
import ProBadge from '../licensing/ProBadge';
import LoadingState from '../feedback/LoadingState';
import SeriesOrderEditor from './SeriesOrderEditor';
import { normalizeAdminSlug } from '../builder/model/text';

const blankSeries = { id_series: 0, title: '', slug: '', description: '', active: 1 };
const headingClass = '!tw-m-0 !tw-text-lg !tw-font-semibold !tw-leading-6';
const descriptionClass = '!tw-m-0 !tw-text-sm !tw-font-normal !tw-leading-5 tw-text-cci-blog-muted';

export default function ReaderEngagementPanel({ isPro }) {
  const [languageId, setLanguageId] = React.useState(Number(pluginData.language?.id || 0));
  const [page, setPage] = React.useState(1);
  const [tab, setTab] = React.useState('feedback');
  const [report, setReport] = React.useState({ rows: [], messages: [], topics: [], pages: 1 });
  const [series, setSeries] = React.useState([]);
  const [draft, setDraft] = React.useState(null);
  const [loading, setLoading] = React.useState(true);
  const [saving, setSaving] = React.useState(false);
  const [error, setError] = React.useState('');
  const [notice, setNotice] = React.useState('');
  const [refresh, setRefresh] = React.useState(0);
  React.useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setError('');
    const load = async () => {
      const response = await apiFetch('/engagement/report', {
        body: JSON.stringify({ id_lang: languageId, page }),
      });
      if (!cancelled) setReport(response.report);
      if (isPro) {
        const result = await apiFetch('/engagement/options', {
          body: JSON.stringify({ id_lang: languageId }),
        });
        if (!cancelled) setSeries(result.series);
      }
    };
    load()
      .catch((failure) => {
        if (!cancelled) setError(failure.message);
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [languageId, page, isPro, refresh]);
  const saveSeries = async (event) => {
    event.preventDefault();
    setSaving(true);
    setError('');
    setNotice('');
    try {
      await apiFetch('/engagement/series/save', {
        body: JSON.stringify({ ...draft, id_lang: languageId }),
      });
      setDraft(null);
      setRefresh((value) => value + 1);
      setNotice(
        __('Series saved. Assign articles in the Reader engagement tab of each post.', 'cci-blog'),
      );
    } catch (failure) {
      setError(failure.message);
    } finally {
      setSaving(false);
    }
  };
  const clearMessage = async (id) => {
    setSaving(true);
    setError('');
    try {
      await apiFetch('/engagement/feedback/delete', {
        body: JSON.stringify({ id_feedback: id, id_lang: languageId }),
      });
      setRefresh((value) => value + 1);
    } catch (failure) {
      setError(failure.message);
    } finally {
      setSaving(false);
    }
  };
  const languages = (pluginData.languages || []).filter((language) => isPro || language.isDefault);
  const votes = (withHelpful = true) => [
    { id: 'votes', label: __('Votes', 'cci-blog'), className: 'tw-w-24 tw-whitespace-nowrap' },
    ...(withHelpful
      ? [{ id: 'yes', label: __('Helpful', 'cci-blog'), className: 'tw-w-28 tw-whitespace-nowrap' }]
      : []),
    { id: 'no', label: __('Not helpful', 'cci-blog'), className: 'tw-w-32 tw-whitespace-nowrap' },
  ];
  const readDate = (value) => formatAdminDateTime(value, pluginData.adminDate) || '-';
  const openArticle = (id) => {
    const url = new URL(pluginData.apiBase, window.location.href);
    url.searchParams.delete('ajax');
    url.searchParams.delete('ajaxAction');
    url.searchParams.set('section', 'posts');
    url.searchParams.set('reader_post', String(id));
    url.searchParams.set('reader_lang', String(languageId));
    window.location.assign(url.toString());
  };
  return (
    <Card>
      <CardHeader className="tw-flex tw-flex-wrap tw-items-center tw-justify-between tw-gap-4">
        {languages.length > 1 && (
          <Field orientation="horizontal" className="tw-ml-auto tw-w-full sm:tw-w-80">
            <FieldLabel htmlFor="reader-content-language" className="tw-shrink-0">
              {__('Content language', 'cci-blog')}
            </FieldLabel>
            <Select
              id="reader-content-language"
              ariaLabel={__('Content language', 'cci-blog')}
              value={String(languageId)}
              onValueChange={(value) => {
                setLanguageId(Number(value));
                setPage(1);
                setDraft(null);
                setNotice('');
              }}
              options={languages.map((item) => ({ value: String(item.id), label: item.name }))}
            />
          </Field>
        )}
      </CardHeader>
      <Tabs value={tab} onValueChange={setTab}>
        <TabsList>
          <TabsTrigger value="feedback">{__('Reader feedback', 'cci-blog')}</TabsTrigger>
          <TabsTrigger value="series">
            {__('Article series', 'cci-blog')} <ProBadge />
          </TabsTrigger>
        </TabsList>
        <CardContent className="tw-grid tw-gap-4 tw-bg-slate-50 tw-p-4">
          {error && (
            <p role="alert" className="tw-m-0 tw-text-sm tw-text-cci-blog-dangerText">
              {error}
            </p>
          )}
          {notice && (
            <p role="status" className="tw-m-0 tw-text-sm">
              {notice}
            </p>
          )}
          <TabsPanel value="feedback" transparent className="tw-m-0 tw-grid tw-gap-4">
            <Card>
              <CardHeader>
                <div className="tw-grid tw-gap-1">
                  <h2 className={headingClass}>{__('Article usefulness', 'cci-blog')}</h2>
                  <p className={descriptionClass}>
                    {__(
                      'Articles with the most negative votes appear first. Votes are kept for one year.',
                      'cci-blog',
                    )}
                  </p>
                </div>
              </CardHeader>
              {loading ? (
                <LoadingState label={__('Loading feedback...', 'cci-blog')} />
              ) : report.rows.length ? (
                <DataTable
                  label={__('Article usefulness', 'cci-blog')}
                  columns={[{ id: 'title', label: __('Article', 'cci-blog') }, ...votes()]}
                  rows={report.rows.map((item) => [
                    <RowPrimary
                      key="title"
                      label={item.title}
                      onOpen={() => openArticle(item.id_post)}
                      actions={[
                        { label: __('Edit', 'cci-blog'), onClick: () => openArticle(item.id_post) },
                      ]}
                    />,
                    Number(item.votes),
                    Number(item.helpful),
                    Number(item.votes) - Number(item.helpful),
                  ])}
                />
              ) : (
                <EmptyState
                  label={__(
                    'No reader feedback yet. Ratings appear below published articles.',
                    'cci-blog',
                  )}
                />
              )}
            </Card>
            {isPro && (
              <>
                {!loading && !!report.topics?.length && (
                  <Card>
                    <CardHeader>
                      <h2 className={headingClass}>{__('Topics to review', 'cci-blog')}</h2>
                    </CardHeader>
                    <DataTable
                      label={__('Topics to review', 'cci-blog')}
                      columns={[{ id: 'topic', label: __('Topic', 'cci-blog') }, ...votes()]}
                      rows={report.topics.map((item) => [
                        item.name,
                        Number(item.votes),
                        Number(item.helpful),
                        Number(item.votes) - Number(item.helpful),
                      ])}
                    />
                  </Card>
                )}
                <Card>
                  <CardHeader>
                    <div className="tw-grid tw-gap-1">
                      <h2 className={headingClass}>{__('What readers are missing', 'cci-blog')}</h2>
                      <p className={descriptionClass}>
                        {__(
                          'Private messages from the last 90 days. Deleting text keeps the vote.',
                          'cci-blog',
                        )}
                      </p>
                    </div>
                  </CardHeader>
                  {loading ? (
                    <LoadingState label={__('Loading feedback...', 'cci-blog')} />
                  ) : report.messages.length ? (
                    <DataTable
                      label={__('Private reader messages', 'cci-blog')}
                      columns={[
                        { id: 'title', label: __('Article', 'cci-blog'), className: 'tw-w-[32%]' },
                        { id: 'message', label: __('Message', 'cci-blog') },
                        {
                          id: 'date',
                          label: __('Updated', 'cci-blog'),
                          className: 'tw-w-40 tw-whitespace-nowrap',
                        },
                      ]}
                      rows={report.messages.map((item) => [
                        <RowPrimary
                          key="title"
                          label={item.title}
                          onOpen={() => openArticle(item.id_post)}
                          actions={[
                            {
                              label: __('Edit', 'cci-blog'),
                              onClick: () => openArticle(item.id_post),
                            },
                            {
                              label: __('Delete message text', 'cci-blog'),
                              tone: 'danger',
                              disabled: saving,
                              onClick: () => clearMessage(item.id_feedback),
                            },
                          ]}
                        />,
                        <p key="message" className="tw-m-0 tw-whitespace-pre-wrap tw-break-words">
                          {item.message}
                        </p>,
                        <time
                          key="date"
                          className="tw-text-xs tw-text-cci-blog-muted"
                          dateTime={item.date_upd?.replace(' ', 'T')}
                        >
                          {readDate(item.date_upd)}
                        </time>,
                      ])}
                    />
                  ) : (
                    <EmptyState label={__('No written feedback yet.', 'cci-blog')} />
                  )}
                </Card>
              </>
            )}
            {!isPro && (
              <EmptyState
                label={__(
                  'Pro adds private reader messages and an editorial feedback report.',
                  'cci-blog',
                )}
              />
            )}
            {report.pages > 1 && (
              <nav
                className="tw-flex tw-flex-wrap tw-items-center tw-gap-3"
                aria-label={__('Feedback pages', 'cci-blog')}
              >
                <Button
                  type="button"
                  variant="secondary"
                  disabled={loading || page <= 1}
                  onClick={() => setPage((value) => value - 1)}
                >
                  {__('Previous', 'cci-blog')}
                </Button>
                <span>
                  {page} / {report.pages}
                </span>
                <Button
                  type="button"
                  variant="secondary"
                  disabled={loading || page >= report.pages}
                  onClick={() => setPage((value) => value + 1)}
                >
                  {__('Next', 'cci-blog')}
                </Button>
              </nav>
            )}
          </TabsPanel>
          <TabsPanel value="series" transparent className="tw-m-0">
            {!isPro ? (
              <EmptyState
                label={__(
                  'An active Pro license is required to create and edit article series.',
                  'cci-blog',
                )}
              />
            ) : draft ? (
              <Card>
                <CardHeader className="tw-flex tw-flex-wrap tw-items-center tw-justify-between tw-gap-3">
                  <h2 className={headingClass}>
                    {Number(draft.id_series)
                      ? __('Edit series', 'cci-blog')
                      : __('Create a series', 'cci-blog')}
                  </h2>
                  <Button
                    variant="ghost"
                    type="button"
                    disabled={saving}
                    onClick={() => setDraft(null)}
                  >
                    {__('Back to series', 'cci-blog')}
                  </Button>
                </CardHeader>
                <CardContent className="tw-p-5">
                  <form onSubmit={saveSeries} className="tw-grid tw-max-w-3xl tw-gap-4">
                    <Field>
                      <FieldLabel htmlFor="reader-series-title">
                        {__('Title', 'cci-blog')}
                      </FieldLabel>
                      <Input
                        id="reader-series-title"
                        required
                        maxLength={180}
                        value={draft.title}
                        onChange={(event) =>
                          setDraft({
                            ...draft,
                            title: event.target.value,
                            slug:
                              !Number(draft.id_series) &&
                              draft.slug === normalizeAdminSlug(draft.title)
                                ? normalizeAdminSlug(event.target.value)
                                : draft.slug,
                          })
                        }
                      />
                    </Field>
                    <Field>
                      <FieldLabel htmlFor="reader-series-slug">
                        {__('URL slug', 'cci-blog')}
                      </FieldLabel>
                      <Input
                        id="reader-series-slug"
                        required
                        maxLength={180}
                        value={draft.slug}
                        disabled={Number(draft.id_series) > 0}
                        onChange={(event) => setDraft({ ...draft, slug: event.target.value })}
                      />
                    </Field>
                    <Field>
                      <FieldLabel htmlFor="reader-series-description">
                        {__('Description', 'cci-blog')}
                      </FieldLabel>
                      <Textarea
                        id="reader-series-description"
                        maxLength={1000}
                        rows={3}
                        value={draft.description}
                        onChange={(event) =>
                          setDraft({ ...draft, description: event.target.value })
                        }
                      />
                    </Field>
                    <CheckboxField
                      checked={Boolean(Number(draft.active))}
                      onCheckedChange={(checked) => setDraft({ ...draft, active: checked ? 1 : 0 })}
                    >
                      {__('Visible to readers', 'cci-blog')}
                    </CheckboxField>
                    <p className={descriptionClass}>
                      {__(
                        'A series appears publicly once it contains a published article. Hide a series to remove its navigation without unpublishing its articles. Existing series URLs stay unchanged.',
                        'cci-blog',
                      )}
                    </p>
                    <div>
                      <Button type="submit" disabled={saving || loading}>
                        {saving ? __('Saving...', 'cci-blog') : __('Save series', 'cci-blog')}
                      </Button>
                    </div>
                  </form>
                  {Number(draft.id_series) > 0 && (
                    <div className="tw-mt-5 tw-grid tw-gap-3 tw-border-0 tw-border-t tw-border-solid tw-border-cci-blog-border tw-pt-5">
                      <h3 className={headingClass}>{__('Articles in this series', 'cci-blog')}</h3>
                      <SeriesOrderEditor
                        seriesId={Number(draft.id_series)}
                        languageId={languageId}
                        value={draft.series_order}
                        onChange={(series_order) => setDraft({ ...draft, series_order })}
                      />
                    </div>
                  )}
                </CardContent>
              </Card>
            ) : (
              <Card>
                <ResourceListHeader
                  title={__('Article series', 'cci-blog')}
                  createLabel={__('New series', 'cci-blog')}
                  onCreate={() => {
                    setDraft({ ...blankSeries });
                    setNotice('');
                  }}
                  disabled={loading}
                />
                {loading ? (
                  <LoadingState label={__('Loading series...', 'cci-blog')} />
                ) : series.length ? (
                  <DataTable
                    label={__('Article series', 'cci-blog')}
                    columns={[
                      { id: 'title', label: __('Title', 'cci-blog') },
                      { id: 'status', label: __('Status', 'cci-blog'), className: 'tw-w-36' },
                      { id: 'slug', label: __('URL slug', 'cci-blog'), className: 'tw-w-[35%]' },
                    ]}
                    rows={series.map((item) => [
                      <RowPrimary
                        key="title"
                        label={item.title}
                        onOpen={() => setDraft(item)}
                        actions={[{ label: __('Edit', 'cci-blog'), onClick: () => setDraft(item) }]}
                      />,
                      Number(item.active) ? __('Visible', 'cci-blog') : __('Hidden', 'cci-blog'),
                      item.slug,
                    ])}
                  />
                ) : (
                  <EmptyState
                    label={__(
                      'No series yet. Create a series, then assign articles in the post editor.',
                      'cci-blog',
                    )}
                  />
                )}
              </Card>
            )}
          </TabsPanel>
        </CardContent>
      </Tabs>
    </Card>
  );
}
