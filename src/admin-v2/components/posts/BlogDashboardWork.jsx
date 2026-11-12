import React from 'react';
import { ResourceListHeader, formatAdminDateTime } from '@cci/admin-ui';
import { __ } from '../../i18n/i18n';
import { pluginData } from '../../api/api';
import { Button } from '../ui/button';
import { Card, CardHeader } from '../ui/card';
import { DataTable, EmptyState, RowPrimary } from './AdminDataTable';
import BlogPublicationPlan from './BlogPublicationPlan';

export default function BlogDashboardWork({ stats, onCreate, onOpen, onManage }) {
  const recent = stats.recentPosts || [];
  const date = (value) => formatAdminDateTime(value, pluginData.adminDate) || '-';
  const entries = (items) => (
    <DataTable
      compact
      label={__('Recently edited posts', 'cci-blog')}
      columns={[
        { id: 'title', label: __('Title', 'cci-blog') },
        {
          id: 'date',
          label: __('Updated', 'cci-blog'),
          className: 'tw-w-[30%] sm:tw-w-36',
        },
        {
          id: 'modifiedBy',
          label: __('Modified by', 'cci-blog'),
          className: 'tw-w-[30%] sm:tw-w-32',
        },
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
          dateTime={post.date_upd?.replace(' ', 'T')}
        >
          {date(post.date_upd)}
        </time>,
        <span key="modifiedBy" className="tw-text-xs tw-text-cci-blog-muted">
          {post.modified_by_name || __('Not recorded', 'cci-blog')}
        </span>,
      ])}
    />
  );
  return (
    <div className="tw-grid tw-items-start tw-gap-4">
      <Card>
        <CardHeader className="tw-flex tw-flex-row tw-flex-wrap tw-items-center tw-justify-between tw-gap-3">
          <h2 className="!tw-m-0 !tw-text-lg !tw-font-semibold !tw-leading-6">
            {__('Publication plan', 'cci-blog')}
          </h2>
          <div className="tw-flex tw-gap-4 tw-text-sm tw-text-cci-blog-muted">
            <span>
              {__('Drafts', 'cci-blog')}:{' '}
              <strong className="tw-text-cci-blog-text">{stats.draftPosts ?? '-'}</strong>
            </span>
            <span>
              {__('Scheduled', 'cci-blog')}:{' '}
              <strong className="tw-text-cci-blog-text">{stats.scheduledPosts ?? '-'}</strong>
            </span>
          </div>
        </CardHeader>
        <BlogPublicationPlan onOpen={onOpen} />
      </Card>
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
    </div>
  );
}
