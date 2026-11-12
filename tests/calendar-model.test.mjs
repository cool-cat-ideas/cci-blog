import test from 'node:test';
import assert from 'node:assert/strict';
import { monthDays, shiftMonth, groupEvents } from '../src/admin-v2/components/calendar/calendar-model.mjs';

test('calendar starts on Monday and contains leap day exactly once', () => {
  const days = monthDays('2032-02');
  assert.equal(days.length % 7, 0);
  assert.equal(new Date(`${days[0]}T12:00:00Z`).getUTCDay(), 1);
  assert.equal(days.filter(day => day.startsWith('2032-02')).length, 29);
  assert.equal(new Set(days).size, days.length);
});
test('month navigation crosses year boundaries without browser-zone drift', () => {
  assert.equal(shiftMonth('2026-12', 1), '2027-01');
  assert.equal(shiftMonth('2026-01', -1), '2025-12');
  assert.equal(monthDays('2026-03').filter(day => day.startsWith('2026-03')).length, 31);
});
test('events retain their civil shop date and every entry on a busy day', () => {
  const events = Array.from({ length: 8 }, (_, id) => ({ id, date: '2026-03-29 00:30:00' }));
  const grouped = groupEvents(events);
  assert.deepEqual(grouped.get('2026-03-29'), events);
  assert.equal(grouped.size, 1);
});
