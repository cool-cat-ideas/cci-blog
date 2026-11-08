import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
  imageLinkState,
  imageLinkValues,
  captionLinkProps,
  patchCaptionLink,
} from '../src/admin-v2/components/posts/editorial-image-links.js';

test('a caption-only link survives reopening without adding a link to the image', () => {
  const caption = { href: '#details', id: 'caption-details', target: '_self', rel: 'nofollow' };
  const state = { ...imageLinkState({ link: {}, caption_link: caption }), captionLinked: '1' };
  assert.deepEqual(imageLinkValues(state, {}), { link: {}, caption_link: caption });
});
test('independent image and caption URLs retain all caption attributes', () => {
  const image = { href: '/product' };
  const caption = { href: '/guide', aria_label: 'Read the guide', id: 'guide-link' };
  const state = { ...imageLinkState({ link: image, caption_link: caption }), captionLinked: '1' };
  assert.deepEqual(imageLinkValues(state, image), { link: image, caption_link: caption });
  const changed = {
    ...state,
    ...patchCaptionLink(state, { href: '/another-guide', htmlId: 'another-id' }),
  };
  assert.equal(captionLinkProps(changed).ariaLabel, 'Read the guide');
  assert.equal(imageLinkValues(changed, image).caption_link.id, 'another-id');
  assert.equal(imageLinkValues(changed, image).link.href, '/product');
});
test('shared captions still follow edits to the image link', () => {
  const shared = { href: '/original' };
  const state = { ...imageLinkState({ link: shared, caption_link: shared }), captionLinked: '1' };
  assert.equal(imageLinkValues(state, { href: '/updated' }).caption_link.href, '/updated');
});
