import React from 'react';
import { apiFetch } from '../../api/api';
import { __ } from '../../i18n/i18n';
import { Button } from '../ui/button';
import LoadingState from '../feedback/LoadingState';
import SortableArticleList from './SortableArticleList';

/** Ordering is part of the surrounding article or series draft, never a separate save. */
export default function SeriesOrderEditor({
  seriesId,
  languageId,
  articleId,
  articleTitle,
  currentArticle,
  refreshKey,
  value,
  onChange,
}) {
  const [order, setOrder] = React.useState({ items: [], revision: '' });
  const [loading, setLoading] = React.useState(true);
  const [error, setError] = React.useState('');
  const [retry, setRetry] = React.useState(0);
  const pending = React.useRef(value);
  pending.current = value;
  const hadDraft = React.useRef(Boolean(value));
  const current = React.useRef(currentArticle);
  current.current = currentArticle;
  const title = React.useRef(articleTitle);
  title.current = articleTitle;
  React.useEffect(() => {
    if (hadDraft.current && !value) setRetry((number) => number + 1);
    hadDraft.current = Boolean(value);
  }, [value]);
  React.useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setError('');
    apiFetch('/engagement/series/order', {
      body: JSON.stringify({ id_series: seriesId, id_lang: languageId }),
    })
      .then((response) => {
        if (cancelled) return;
        const items = [...response.order.items];
        const currentId = articleId > 0 ? articleId : 'current';
        if (
          articleId !== undefined &&
          !items.some((item) => Number(item.id_post) === Number(articleId))
        )
          items.push({
            ...current.current,
            id_post: currentId,
            title: title.current || __('Untitled article', 'cci-blog'),
          });
        const previous = pending.current;
        if (previous && Number(previous.id_series) === Number(seriesId)) {
          const byId = new Map(items.map((item) => [String(item.id_post), item]));
          const restored = previous.post_ids.map((id) => byId.get(String(id))).filter(Boolean);
          setOrder({ items: restored, revision: previous.revision });
        } else setOrder({ items, revision: response.order.revision });
      })
      .catch((failure) => {
        if (!cancelled) setError(failure.message);
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [seriesId, languageId, articleId, refreshKey, retry]);
  if (loading) return <LoadingState label={__('Loading articles...', 'cci-blog')} />;
  const items = order.items.map((item) => ({
    ...item,
    ...(item.id_post === 'current' || Number(item.id_post) === Number(articleId)
      ? currentArticle
      : {}),
    id: item.id_post === 'current' ? 'current' : Number(item.id_post),
    title:
      (item.id_post === 'current' || Number(item.id_post) === Number(articleId)) && articleTitle
        ? articleTitle
        : item.title,
  }));
  return (
    <div className="tw-grid tw-gap-3">
      {error && (
        <p role="alert" className="tw-text-sm tw-text-cci-blog-dangerText">
          {error}
        </p>
      )}
      <p className="tw-m-0 tw-text-sm tw-text-cci-blog-muted">
        {__('Drag articles to change their order.', 'cci-blog')}
      </p>
      {items.length ? (
        <SortableArticleList
          label={__('Series article order', 'cci-blog')}
          disabled={Boolean(error)}
          items={items}
          onChange={(next) => {
            const byId = new Map(order.items.map((item) => [String(item.id_post), item]));
            setOrder({ ...order, items: next.map((item) => byId.get(String(item.id))) });
            onChange({
              id_series: Number(seriesId),
              post_ids: next.map((item) => item.id),
              revision: order.revision,
            });
          }}
        />
      ) : (
        <p className="tw-m-0 tw-text-sm tw-text-cci-blog-muted">
          {__('No articles assigned to this series yet.', 'cci-blog')}
        </p>
      )}
      {(error || value) && (
        <div>
          <Button
            type="button"
            variant="ghost"
            onClick={() => {
              pending.current = null;
              setRetry((number) => number + 1);
              onChange(undefined);
            }}
          >
            {__('Reload articles', 'cci-blog')}
          </Button>
        </div>
      )}
    </div>
  );
}
