import React from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '../ui/button';
import { monthDays, shiftMonth, groupEvents } from './calendar-model.mjs';

/** Civil-date month view. Loading, publication rules and editor navigation stay with the caller. */
export default function PublicationCalendar({
  month,
  today,
  locale,
  events,
  selectedDate,
  onSelectDate,
  onMonthChange,
  onOpenEvent,
  onShowDate,
  labels,
  busy = false,
}) {
  const captionId = React.useId();
  const table = React.useRef(null);
  const pendingFocus = React.useRef(null);
  const cells = monthDays(month);
  const grouped = React.useMemo(() => groupEvents(events), [events]);
  const asDate = (date) => new Date(`${date}T12:00:00Z`);
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
  React.useLayoutEffect(() => {
    if (!pendingFocus.current) return;
    const target = table.current?.querySelector(`[data-calendar-date="${pendingFocus.current}"]`);
    if (target) {
      target.focus();
      pendingFocus.current = null;
    }
  }, [month, selectedDate]);
  const selectDate = (date) => {
    if (!date.startsWith(month)) onMonthChange(date.slice(0, 7));
    onSelectDate(date);
  };
  const focusDay = (event, date) => {
    const offsets = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 };
    if (!(event.key in offsets) && !['Home', 'End', 'PageUp', 'PageDown'].includes(event.key))
      return;
    event.preventDefault();
    const next = asDate(date);
    if (event.key === 'PageUp' || event.key === 'PageDown') {
      const nextMonth = shiftMonth(date.slice(0, 7), event.key === 'PageUp' ? -1 : 1);
      const lastDay = new Date(
        Date.UTC(Number(nextMonth.slice(0, 4)), Number(nextMonth.slice(5)), 0),
      ).getUTCDate();
      next.setUTCFullYear(
        Number(nextMonth.slice(0, 4)),
        Number(nextMonth.slice(5)) - 1,
        Math.min(next.getUTCDate(), lastDay),
      );
    } else {
      const weekDay = (next.getUTCDay() + 6) % 7;
      next.setUTCDate(
        next.getUTCDate() +
          (event.key === 'Home'
            ? -weekDay
            : event.key === 'End'
              ? 6 - weekDay
              : offsets[event.key]),
      );
    }
    const key = next.toISOString().slice(0, 10);
    if (key < '1900-01-01' || key > '2199-12-31') return;
    pendingFocus.current = key;
    selectDate(key);
  };
  return (
    <div className="cci-blog-publication-calendar">
      <div className="cci-blog-calendar-toolbar">
        <div className="cci-blog-calendar-navigation">
          <Button
            variant="secondary"
            size="sm"
            type="button"
            onClick={() => {
              onMonthChange(today.slice(0, 7));
              onSelectDate(today);
            }}
          >
            {labels.today}
          </Button>
          <div className="cci-blog-calendar-arrows">
            <Button
              variant="ghost"
              size="iconSm"
              type="button"
              aria-label={labels.previousMonth}
              disabled={month <= '1900-01'}
              onClick={() => onMonthChange(shiftMonth(month, -1))}
            >
              <ChevronLeft aria-hidden="true" />
            </Button>
            <Button
              variant="ghost"
              size="iconSm"
              type="button"
              aria-label={labels.nextMonth}
              disabled={month >= '2199-12'}
              onClick={() => onMonthChange(shiftMonth(month, 1))}
            >
              <ChevronRight aria-hidden="true" />
            </Button>
          </div>
        </div>
        <h3 id={captionId} className="cci-blog-calendar-month" aria-live="polite">
          {monthFormat.format(asDate(`${month}-01`))}
        </h3>
      </div>
      <table
        ref={table}
        className="cci-blog-calendar-grid"
        aria-labelledby={captionId}
        aria-busy={busy}
      >
        <thead>
          <tr>
            {cells.slice(0, 7).map((date) => (
              <th key={date} scope="col">
                <span>{weekday.format(asDate(date))}</span>
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {Array.from({ length: cells.length / 7 }, (_, week) => (
            <tr key={week}>
              {cells.slice(week * 7, week * 7 + 7).map((date) => {
                const dayEvents = grouped.get(date) || [];
                const currentMonth = date.startsWith(month);
                const isToday = date === today;
                const selected = date === selectedDate;
                return (
                  <td
                    key={date}
                    data-outside-month={!currentMonth || undefined}
                    data-selected={selected || undefined}
                  >
                    <div className="cci-blog-calendar-cell">
                      <Button
                        variant="unstyled"
                        type="button"
                        className="cci-blog-calendar-date"
                        data-calendar-date={date}
                        tabIndex={selected ? 0 : -1}
                        aria-pressed={selected}
                        aria-current={isToday ? 'date' : undefined}
                        aria-label={`${fullDate.format(asDate(date))}. ${labels.eventCount(dayEvents.length)}`}
                        onKeyDown={(event) => focusDay(event, date)}
                        onClick={() => selectDate(date)}
                      >
                        {Number(date.slice(-2))}
                      </Button>
                      <div className="cci-blog-calendar-events">
                        {dayEvents.slice(0, 2).map((item) => (
                          <Button
                            key={item.id}
                            variant="unstyled"
                            type="button"
                            className="cci-blog-calendar-event"
                            title={`${item.date.slice(11, 16)} · ${item.title}`}
                            aria-label={`${item.title}. ${item.date.slice(11, 16)}${item.statusLabel ? `. ${item.statusLabel}` : ''}`}
                            disabled={busy}
                            onClick={() => onOpenEvent(item)}
                          >
                            <span className="cci-blog-calendar-event-dot" aria-hidden="true" />
                            <time dateTime={item.date.replace(' ', 'T')}>
                              {item.date.slice(11, 16)}
                            </time>
                            <span className="cci-blog-calendar-event-title">{item.title}</span>
                          </Button>
                        ))}
                        {dayEvents.length > 2 && (
                          <Button
                            variant="unstyled"
                            type="button"
                            className="cci-blog-calendar-more"
                            onClick={() => {
                              selectDate(date);
                              onShowDate?.();
                            }}
                          >
                            {labels.moreEvents(dayEvents.length - 2)}
                          </Button>
                        )}
                      </div>
                      {dayEvents.length > 0 && (
                        <span className="cci-blog-calendar-mobile-count" aria-hidden="true">
                          {dayEvents.length}
                        </span>
                      )}
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
