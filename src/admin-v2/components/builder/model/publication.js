export function publicationStatus(post, now = new Date(), timeZone = 'UTC') {
  if (Number(post.active) !== 1) return 'draft';
  const published = String(post.date_published || '')
    .trim()
    .replace('T', ' ');
  if (!/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}/.test(published)) return 'published';
  return published.slice(0, 19) > shopDateTime(now, timeZone) ? 'scheduled' : 'published';
}

export function shopDateTime(now, timeZone) {
  const parts = Object.fromEntries(
    new Intl.DateTimeFormat('en-CA', {
      timeZone,
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hourCycle: 'h23',
    })
      .formatToParts(now)
      .map(({ type, value }) => [type, value]),
  );
  return `${parts.year}-${parts.month}-${parts.day} ${parts.hour}:${parts.minute}:${parts.second}`;
}
