import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

const source = readFileSync(
  new URL('../src/admin-v2/components/builder/model/publication.js', import.meta.url),
  'utf8',
);
const { publicationStatus, shopDateTime } = await import(
  `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`
);

test('a draft stays private regardless of its publication date', () => {
  assert.equal(publicationStatus({ active: 0, date_published: '2020-01-01 12:00:00' }), 'draft');
});

test('status follows shop time instead of browser time', () => {
  const now = new Date('2026-11-05T12:00:00Z');
  const post = { active: 1, date_published: '2026-11-05 13:30:00' };
  assert.equal(publicationStatus(post, now, 'Europe/Warsaw'), 'scheduled');
  assert.equal(publicationStatus(post, now, 'Asia/Tokyo'), 'published');
  assert.equal(
    publicationStatus(post, new Date('2026-11-05T12:30:00Z'), 'Europe/Warsaw'),
    'published',
  );
});

test('shop wall clock handles the spring daylight-saving transition', () => {
  assert.equal(
    shopDateTime(new Date('2026-03-29T00:59:59Z'), 'Europe/Warsaw'),
    '2026-03-29 01:59:59',
  );
  assert.equal(
    shopDateTime(new Date('2026-03-29T01:00:00Z'), 'Europe/Warsaw'),
    '2026-03-29 03:00:00',
  );
});

test('legacy active posts without a publication date remain published', () => {
  assert.equal(publicationStatus({ active: '1', date_published: '' }), 'published');
});
