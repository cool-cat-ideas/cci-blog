import React from 'react';
import { ResourceListHeader, formatAdminDateTime } from '@cci/admin-ui';
import { __ } from '../../i18n/i18n';
import { pluginData } from '../../api/api';
import { Button } from '../ui/button';
import { Card, CardHeader } from '../ui/card';
import { DataTable, EmptyState, RowPrimary } from './AdminDataTable';

export default function BlogDashboardWork({ stats, onCreate, onOpen, onManage }) {
  const recent = stats.recentPosts || [];
  const upcoming = stats.upcomingPosts || [];
  const date = (value) => formatAdminDateTime(value, pluginData.adminDate) || '-';
  const entries = (items, scheduled = false) => (
    <DataTable
      compact
      label={
        scheduled ? __('Publication plan', 'cci-blog') : __('Recently edited posts', 'cci-blog')
      }
      columns={[
        { id: 'title', label: __('Title', 'cci-blog') },
        {
          id: 'date',
          label: scheduled ? __('Publication date', 'cci-blog') : __('Updated', 'cci-blog'),
          className: 'tw-w-[30%] sm:tw-w-36',
        },
        ...(!scheduled
          ? [
              {
                id: 'modifiedBy',
                label: __('Modified by', 'cci-blog'),
                className: 'tw-w-[30%] sm:tw-w-32',
              },
            ]
          : []),
      ]}
      rows={items.map((post) => [
        <RowPrimary
          key="title"
          label={post.title || __('(no title)', 'cci-blog')}
          onOpen={() => onOpen(post)}
          actions={[{ label: __('Edit', 'cci-blog'), onClick: () => onOpen(post) }]}
        />,
        <time
          key="date"
          className="tw-text-xs tw-text-cci-blog-muted"
          dateTime={(scheduled ? post.date_published : post.date_upd)?.replace(' ', 'T')}
        >
          {date(scheduled ? post.date_published : post.date_upd)}
        </time>,
        ...(!scheduled
          ? [
              <span key="modifiedBy" className="tw-text-xs tw-text-cci-blog-muted">
                {post.modified_by_name || __('Not recorded', 'cci-blog')}
              </span>,
            ]
          : []),
      ])}
    />
  );
  return (
    <>
      <Card>
        <ResourceListHeader
          title={__('Recently edited posts', 'cci-blog')}
          createLabel={__('New post', 'cci-blog')}
          onCreate={onCreate}
        />
        {recent.length ? entries(recent) : <EmptyState label={__('No posts yet.', 'cci-blog')} />}
        <div className="tw-border-0 tw-border-t tw-border-solid tw-border-cci-blog-border tw-px-5 tw-py-3">
          <Button variant="secondary" onClick={onManage}>
            {__('View all posts', 'cci-blog')}
          </Button>
        </div>
      </Card>
      <Card>
        <CardHeader>
          <h2 className="!tw-m-0 !tw-text-lg !tw-font-semibold !tw-leading-6">
            {__('Publication plan', 'cci-blog')}
          </h2>
        </CardHeader>
        <dl className="tw-m-0 tw-grid tw-grid-cols-2 tw-gap-4 tw-px-5 tw-py-4">
          <div>
            <dt className="tw-text-sm tw-text-cci-blog-muted">{__('Drafts', 'cci-blog')}</dt>
            <dd className="tw-m-0 tw-text-2xl tw-font-semibold">{stats.draftPosts ?? '-'}</dd>
          </div>
          <div>
            <dt className="tw-text-sm tw-text-cci-blog-muted">{__('Scheduled', 'cci-blog')}</dt>
            <dd className="tw-m-0 tw-text-2xl tw-font-semibold">{stats.scheduledPosts ?? '-'}</dd>
          </div>
        </dl>
        {upcoming.length ? (
          entries(upcoming, true)
        ) : (
          <EmptyState label={__('No scheduled posts.', 'cci-blog')} />
        )}
      </Card>
    </>
  );
}
