import React from 'react';
import { InfoCallout } from '@cci/admin-ui/blog';
import { formatAdminDateTime } from '@cci/admin-ui';
import { __ } from '../../i18n/i18n';
import { apiFetch, pluginData } from '../../api/api';
import { Button } from '../ui/button';
import PublicationCalendar from '../calendar/PublicationCalendar';
import LoadingState from '../feedback/LoadingState';
import PublicationStatusBadge from './PublicationStatusBadge';
import { RowPrimary, EmptyState } from './AdminDataTable';
import { shopDateTime } from '../builder/model/publication';
import { usePublicationClock } from './usePublicationClock';

export default function BlogPublicationPlan({ onOpen }) {
  const now = usePublicationClock();
  const today = shopDateTime(new Date(now), pluginData.adminDate?.timeZone || 'UTC').slice(0, 10);
  const [month, setMonth] = React.useState(today.slice(0, 7));
  const [selected, setSelected] = React.useState(today);
  const [result, setResult] = React.useState({ month: '', posts: [] });
  const [loading, setLoading] = React.useState(true);
  const [error, setError] = React.useState('');
  const [retry, setRetry] = React.useState(0);
  React.useEffect(() => {
    const controller = new AbortController();
    setLoading(true);
    setError('');
    apiFetch('/dashboard/calendar', { body: JSON.stringify({ month }), signal: controller.signal })
      .then(({ calendar }) => {
        if (!controller.signal.aborted) setResult(calendar);
      })
      .catch((failure) => {
        if (!controller.signal.aborted) setError(failure.message);
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false);
      });
    return () => controller.abort();
  }, [month, retry]);
  const posts = result.month === month ? result.posts : [];
  const events = posts.map((post) => ({
    id: post.id_post,
    date: post.date_published,
    title: post.title,
  }));
  const selectedPosts = posts.filter((post) => post.date_published.startsWith(selected));
  const changeMonth = (next) => {
    setMonth(next);
    setSelected(next === today.slice(0, 7) ? today : `${next}-01`);
  };
  return (
    <div>
      <PublicationCalendar
        month={month}
        today={today}
        locale={pluginData.adminDate?.locale || 'en-US'}
        selectedDate={selected}
        onSelectDate={setSelected}
        onMonthChange={changeMonth}
        events={events}
        busy={loading}
        labels={{
          today: __('Today', 'cci-blog'),
          previousMonth: __('Previous month', 'cci-blog'),
          nextMonth: __('Next month', 'cci-blog'),
          eventCount: (count) => `${__('Articles', 'cci-blog')}: ${count}`,
        }}
      />
      <div className="tw-border-0 tw-border-t tw-border-solid tw-border-cci-blog-border">
        {loading ? (
          <LoadingState label={__('Loading articles...', 'cci-blog')} />
        ) : error ? (
          <div className="tw-grid tw-gap-3 tw-p-5">
            <InfoCallout tone="danger" role="alert">
              {error}
            </InfoCallout>
            <Button
              variant="secondary"
              type="button"
              onClick={() => setRetry((value) => value + 1)}
            >
              {__('Retry', 'cci-blog')}
            </Button>
          </div>
        ) : (
          <>
            <h3 className="!tw-m-0 !tw-px-5 !tw-py-3 !tw-text-sm !tw-font-semibold">
              {formatAdminDateTime(`${selected} 00:00:00`, pluginData.adminDate, false)}
            </h3>
            {selectedPosts.length ? (
              <ul className="tw-m-0 tw-grid tw-list-none tw-p-0">
                {selectedPosts.map((post) => (
                  <li
                    key={post.id_post}
                    className="tw-group tw-grid tw-gap-2 tw-border-0 tw-border-t tw-border-solid tw-border-cci-blog-border tw-px-5 tw-py-3 sm:tw-grid-cols-[1fr_auto]"
                  >
                    <RowPrimary
                      label={post.title}
                      onOpen={() => onOpen(post)}
                      actions={[{ label: __('Edit', 'cci-blog'), onClick: () => onOpen(post) }]}
                    />
                    <div className="tw-flex tw-items-center tw-gap-3">
                      <time
                        dateTime={post.date_published.replace(' ', 'T')}
                        className="tw-text-xs tw-text-cci-blog-muted"
                      >
                        {post.date_published.slice(11, 16)}
                      </time>
                      <PublicationStatusBadge post={post} />
                    </div>
                  </li>
                ))}
              </ul>
            ) : (
              <EmptyState label={__('No publications on this date.', 'cci-blog')} />
            )}
          </>
        )}
      </div>
    </div>
  );
}
