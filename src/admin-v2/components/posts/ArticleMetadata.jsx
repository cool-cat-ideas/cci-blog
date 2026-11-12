import React from 'react';
import { formatAdminDateTime } from '@cci/admin-ui';
import { pluginData } from '../../api/api';
import { __ } from '../../i18n/i18n';
import { publicationStatus } from '../builder/model/publication';
import { PublicationBadge } from './PublicationStatusBadge';
import { usePublicationClock } from './usePublicationClock';

/** The same status, author and date line in search results and ordered article rows. */
export default function ArticleMetadata({ article }) {
  const now = usePublicationClock();
  if (article.active === undefined) return null;
  const status =
    article.id_post === 0 || article.id === 'current'
      ? 'draft'
      : publicationStatus(article, new Date(now), pluginData.adminDate?.timeZone || 'UTC');
  const value =
    status === 'draft'
      ? article.date_upd || article.date_add
      : article.date_published || article.date_add;
  const label =
    status === 'draft'
      ? __('Updated', 'cci-blog')
      : status === 'scheduled'
        ? __('Scheduled for', 'cci-blog')
        : article.date_published
          ? __('Published on', 'cci-blog')
          : __('Created', 'cci-blog');
  const formatted = value ? formatAdminDateTime(value, pluginData.adminDate) : '';
  const author =
    article.author_name && article.author_name !== '-'
      ? article.author_name
      : __('Author not assigned', 'cci-blog');
  return (
    <span
      data-article-metadata=""
      className="tw-mt-1 tw-flex tw-flex-wrap tw-items-center tw-gap-x-2 tw-gap-y-1 tw-text-xs tw-font-normal tw-leading-5 tw-text-cci-blog-muted"
    >
      <PublicationBadge status={status} />
      <span>{author}</span>
      {formatted && (
        <>
          <span aria-hidden="true">·</span>
          <time dateTime={String(value).replace(' ', 'T')} title={`${label}: ${formatted}`}>
            {status === 'draft' ? `${__('Updated', 'cci-blog')} ` : ''}
            {formatted}
          </time>
        </>
      )}
    </span>
  );
}
