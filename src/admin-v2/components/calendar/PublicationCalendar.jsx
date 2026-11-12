import React from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '../ui/button';
import { monthDays, shiftMonth, groupEvents } from './calendar-model.mjs';

/** Presentation only: dates, data loading and editor navigation belong to the caller. */
export default function PublicationCalendar({
  month,
  today,
  locale,
  events,
  selectedDate,
  onSelectDate,
  onMonthChange,
  labels,
  busy = false,
}) {
  const captionId = React.useId();
  const cells = monthDays(month);
  const grouped = React.useMemo(() => groupEvents(events), [events]);
  const fullDate = new Intl.DateTimeFormat(locale, {
    timeZone: 'UTC',
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
  const monthFormat = new Intl.DateTimeFormat(locale, {
    timeZone: 'UTC',
    month: 'long',
    year: 'numeric',
  });
  const weekday = new Intl.DateTimeFormat(locale, { timeZone: 'UTC', weekday: 'short' });
  const asDate = (date) => new Date(`${date}T12:00:00Z`);
  const navigate = (offset) => onMonthChange(shiftMonth(month, offset));
  const focusDay = (event, date) => {
    const offsets = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 };
    if (!(event.key in offsets)) return;
    event.preventDefault();
    const next = asDate(date);
    next.setUTCDate(next.getUTCDate() + offsets[event.key]);
    const key = next.toISOString().slice(0, 10);
    // Focus only; choosing a date remains an explicit button activation.
    event.currentTarget.closest('table')?.querySelector(`[data-calendar-date="${key}"]`)?.focus();
  };
  return (
    <div className="tw-min-w-0">
      <div className="tw-flex tw-flex-wrap tw-items-center tw-justify-between tw-gap-3 tw-px-5 tw-py-3">
        <h3 id={captionId} className="!tw-m-0 !tw-text-base !tw-font-semibold" aria-live="polite">
          {monthFormat.format(asDate(`${month}-01`))}
        </h3>
        <div className="tw-flex tw-items-center tw-gap-2">
          <Button
            variant="secondary"
            size="sm"
            type="button"
            onClick={() => onMonthChange(today.slice(0, 7))}
          >
            {labels.today}
          </Button>
          <Button
            variant="secondary"
            size="iconSm"
            type="button"
            aria-label={labels.previousMonth}
            disabled={month <= '1900-01'}
            onClick={() => navigate(-1)}
          >
            <ChevronLeft aria-hidden="true" />
          </Button>
          <Button
            variant="secondary"
            size="iconSm"
            type="button"
            aria-label={labels.nextMonth}
            disabled={month >= '2199-12'}
            onClick={() => navigate(1)}
          >
            <ChevronRight aria-hidden="true" />
          </Button>
        </div>
      </div>
      <table
        className="tw-w-full tw-table-fixed tw-border-collapse"
        aria-labelledby={captionId}
        aria-busy={busy}
      >
        <thead className="tw-bg-cci-blog-surfaceSoft">
          <tr>
            {cells.slice(0, 7).map((date) => (
              <th
                key={date}
                scope="col"
                className="tw-py-2 tw-text-center tw-text-xs tw-font-medium tw-text-cci-blog-muted"
              >
                <span className="tw-block tw-text-center">{weekday.format(asDate(date))}</span>
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {Array.from({ length: cells.length / 7 }, (_, week) => (
            <tr key={week}>
              {cells.slice(week * 7, week * 7 + 7).map((date) => {
                const currentMonth = date.startsWith(month);
                const count = (grouped.get(date) || []).length;
                return (
                  <td
                    key={date}
                    className="tw-border-0 tw-px-1 tw-py-1 tw-text-center tw-align-top"
                  >
                    <div className="tw-flex tw-justify-center">
                      <Button
                        variant={
                          selectedDate === date ? 'primary' : date === today ? 'secondary' : 'ghost'
                        }
                        type="button"
                        className="tw-mx-auto tw-flex tw-h-12 tw-w-10 tw-max-w-full tw-flex-col tw-items-center tw-justify-center tw-gap-1 !tw-p-0"
                        data-calendar-date={date}
                        aria-pressed={selectedDate === date}
                        aria-current={date === today ? 'date' : undefined}
                        aria-label={`${fullDate.format(asDate(date))}. ${labels.eventCount(count)}`}
                        onKeyDown={(event) => focusDay(event, date)}
                        onClick={() => {
                          if (!currentMonth) onMonthChange(date.slice(0, 7));
                          onSelectDate(date);
                        }}
                      >
                        <span
                          className={
                            currentMonth
                              ? 'tw-font-semibold'
                              : 'tw-font-normal tw-text-cci-blog-muted'
                          }
                        >
                          {Number(date.slice(-2))}
                        </span>
                        <span
                          aria-hidden="true"
                          className="tw-inline-flex tw-h-3 tw-items-center tw-gap-1 tw-text-[10px] tw-leading-3"
                        >
                          {count > 0 && (
                            <>
                              <span className="tw-h-1 tw-w-1 tw-rounded-full tw-bg-current" />
                              {count}
                            </>
                          )}
                        </span>
                      </Button>
                    </div>
                  </td>
                );
              })}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
