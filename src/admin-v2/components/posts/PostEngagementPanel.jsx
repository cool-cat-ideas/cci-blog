import React from 'react';
import { InfoCallout } from '@cci/admin-ui/blog';
import { BlogContentPicker } from './InternalLinkPicker';
import { TooltipHelp } from '../ui/tooltip';
import { apiFetch, pluginData } from '../../api/api';
import { __ } from '../../i18n/i18n';
import { Field, FieldLabel } from '../ui/field';
import { Input, Textarea } from '../ui/input';
import { Select } from '../ui/select';
import { Card, CardContent, CardHeader } from '../ui/card';
import ProBadge from '../licensing/ProBadge';
import SeriesOrderEditor from './SeriesOrderEditor';
import ArticleMetadata from './ArticleMetadata';
import SortableArticleList from './SortableArticleList';
import ConfirmDeleteModal from '../feedback/ConfirmDeleteModal';

export default function PostEngagementPanel({ post, onChange, isPro }) {
  const [pendingRemoval, setPendingRemoval] = React.useState(null);
  const [options, setOptions] = React.useState({ posts: [], series: [] });
  const [loading, setLoading] = React.useState(Boolean(isPro));
  const data = post.engagement || {
    related_ids: [],
    related_title: '',
    related_intro: '',
    id_series: 0,
    position: 0,
  };
  const ids = data.related_ids || [];
  const change = (patch) => onChange({ ...post, engagement: { ...data, ...patch } });
  const selectedKey = [...ids].sort((a, b) => a - b).join(',');
  const automaticEnabled = Number(pluginData.settings?.CCB_RELATED_POSTS ?? 3) > 0;
  const searchArticles = React.useCallback(
    async ({ query, status, signal }) => {
      const response = await apiFetch('/engagement/options', {
        body: JSON.stringify({
          id_lang: post.id_lang,
          query,
          status,
          selected_ids: selectedKey.split(',').filter(Boolean).map(Number),
        }),
        signal,
      });
      if (signal.aborted) return [];
      setOptions((current) => ({
        series: response.series,
        posts: [
          ...new Map(
            [...current.posts, ...response.posts].map((item) => [Number(item.id_post), item]),
          ).values(),
        ],
      }));
      const selected = selectedKey.split(',').map(Number);
      return response.posts
        .filter(
          (item) =>
            Number(item.id_post) !== Number(post.id_post) &&
            !selected.includes(Number(item.id_post)),
        )
        .map((item) => ({ ...item, id: Number(item.id_post), type: 'post', title: item.title }));
    },
    [post.id_lang, post.id_post, selectedKey],
  );

  if (!isPro)
    return (
      <Card>
        <CardContent className="tw-p-5">
          <h2 className="!tw-m-0 !tw-text-lg !tw-font-semibold !tw-leading-6">
            {__('Recommendations and series', 'cci-blog')} <ProBadge />
          </h2>
          <p>
            {__(
              'An active Pro license lets you choose recommended articles and organize guides into a series. Existing published content remains visible when an eligible license expires.',
              'cci-blog',
            )}
          </p>
        </CardContent>
      </Card>
    );

  return (
    <div className="tw-grid tw-items-start tw-gap-4 xl:tw-grid-cols-2">
      <ConfirmDeleteModal
        open={pendingRemoval !== null}
        title={__('Remove recommendation?', 'cci-blog')}
        itemLabel={options.posts.find((item) => Number(item.id_post) === pendingRemoval)?.title}
        suffix={__(
          'from this article’s recommendations. The article itself is not deleted.',
          'cci-blog',
        )}
        confirmLabel={__('Remove recommendation', 'cci-blog')}
        onCancel={() => setPendingRemoval(null)}
        onConfirm={() => {
          change({ related_ids: ids.filter((id) => id !== pendingRemoval) });
          setPendingRemoval(null);
        }}
      />
      <Card>
        <CardHeader>
          <h2 className="!tw-m-0 !tw-text-lg !tw-font-semibold !tw-leading-6">
            {__('Recommended articles', 'cci-blog')}
          </h2>
        </CardHeader>
        <CardContent className="tw-grid tw-gap-4 tw-p-5">
          <p className="tw-m-0 tw-text-cci-blog-muted">
            {__(
              'Choose up to six articles. Only published articles in this shop and language appear to readers.',
              'cci-blog',
            )}
          </p>
          <Field>
            <FieldLabel htmlFor="reader-related-heading">
              {__('Section heading', 'cci-blog')}
            </FieldLabel>
            <Input
              id="reader-related-heading"
              maxLength={180}
              value={data.related_title}
              onChange={(event) => change({ related_title: event.target.value })}
              placeholder={__('You might also like', 'cci-blog')}
            />
          </Field>
          <Field>
            <FieldLabel htmlFor="reader-related-intro">
              {__('Why read these articles?', 'cci-blog')}
            </FieldLabel>
            <Textarea
              id="reader-related-intro"
              maxLength={512}
              rows={2}
              value={data.related_intro}
              onChange={(event) => change({ related_intro: event.target.value })}
            />
          </Field>
          <BlogContentPicker
            inline
            showStatusFilter
            renderMetadata={(item) => <ArticleMetadata article={item} />}
            includeCategories={false}
            languageId={post.id_lang}
            loadItems={searchArticles}
            onLoadingChange={setLoading}
            disabled={ids.length >= 6}
            onSelect={(item) => change({ related_ids: [...ids, item.id] })}
          />
          {!!ids.length && (options.posts.length > 0 || !loading) && (
            <SortableArticleList
              label={__('Recommendation order', 'cci-blog')}
              items={ids.map((id) => ({
                ...options.posts.find((item) => Number(item.id_post) === id),
                id,
                title:
                  options.posts.find((item) => Number(item.id_post) === id)?.title ||
                  __('Unavailable article', 'cci-blog'),
              }))}
              onChange={(items) => change({ related_ids: items.map((item) => item.id) })}
              onRemove={setPendingRemoval}
            />
          )}
          {(!ids.length || !automaticEnabled) && (
            <InfoCallout tone="info">
              <span className="tw-inline-flex tw-flex-wrap tw-items-center tw-gap-2">
                {automaticEnabled
                  ? __('No articles selected. Automatic recommendations are enabled.', 'cci-blog')
                  : __(
                      'Recommendations are hidden. Set a related-article count above zero in Settings.',
                      'cci-blog',
                    )}
                <TooltipHelp
                  text={__(
                    'The blog suggests the newest published articles from this article’s main category, in the same shop and language. They fill remaining places after your selections. Change their number in Settings; zero hides all recommendations.',
                    'cci-blog',
                  )}
                />
              </span>
            </InfoCallout>
          )}
        </CardContent>
      </Card>
      <Card>
        <CardHeader>
          <h2 className="!tw-m-0 !tw-text-lg !tw-font-semibold !tw-leading-6">
            {__('Article series', 'cci-blog')}
          </h2>
        </CardHeader>
        <CardContent className="tw-grid tw-gap-4 tw-p-5">
          <p className="tw-m-0 tw-text-cci-blog-muted">
            {__(
              'Create series in Reader engagement, then assign this article and arrange the parts below.',
              'cci-blog',
            )}
          </p>
          <Field>
            <FieldLabel htmlFor="reader-article-series">{__('Series', 'cci-blog')}</FieldLabel>
            <Select
              id="reader-article-series"
              ariaLabel={__('Series', 'cci-blog')}
              value={String(data.id_series || 0)}
              onValueChange={(value) =>
                change({ id_series: Number(value), series_order: undefined })
              }
              options={[
                { value: '0', label: __('Not part of a series', 'cci-blog') },
                ...options.series.map((item) => ({
                  value: String(item.id_series),
                  label: `${item.title}${Number(item.active) ? '' : ` (${__('Hidden', 'cci-blog')})`}`,
                })),
              ]}
            />
          </Field>
          {Number(data.id_series) > 0 && (
            <SeriesOrderEditor
              seriesId={Number(data.id_series)}
              languageId={Number(post.id_lang)}
              articleId={Number(post.id_post || 0)}
              refreshKey={post.date_upd}
              articleTitle={post.title}
              currentArticle={{
                ...post,
                author_name:
                  pluginData.authors?.find((author) => Number(author.id) === Number(post.id_author))
                    ?.name || post.author_name,
              }}
              value={data.series_order}
              onChange={(series_order) => change({ series_order })}
            />
          )}
        </CardContent>
      </Card>
    </div>
  );
}
