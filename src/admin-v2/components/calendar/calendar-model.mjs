const isoDate = (date) => date.toISOString().slice(0, 10);
export function shiftMonth(month, offset) {
  const [year, number] = month.split('-').map(Number);
  return isoDate(new Date(Date.UTC(year, number - 1 + offset, 1))).slice(0, 7);
}

/** Calendar dates are civil shop dates, never converted through the browser zone. */
export function monthDays(month) {
  const [year, number] = month.split('-').map(Number);
  const first = new Date(Date.UTC(year, number - 1, 1));
  const offset = (first.getUTCDay() + 6) % 7;
  const count = new Date(Date.UTC(year, number, 0)).getUTCDate();
  return Array.from({ length: Math.ceil((offset + count) / 7) * 7 }, (_, index) =>
    isoDate(new Date(Date.UTC(year, number - 1, index - offset + 1))),
  );
}

export function groupEvents(events) {
  const days = new Map();
  for (const event of events) {
    const key = event.date.slice(0, 10);
    days.set(key, [...(days.get(key) || []), event]);
  }
  return days;
}
