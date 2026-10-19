import { pluginData } from '../../../api/api';
import { normalizeAdminSlug } from './text.js';

export function buildStorefrontBase(baseSlug) {
  const origin =
    typeof window !== 'undefined' && window.location?.origin ? window.location.origin : '';
  const cleanBaseSlug = normalizeAdminSlug(baseSlug) || 'blog';

  return `${origin.replace(/\/$/, '')}/${cleanBaseSlug}/`;
}

export function buildPostStorefrontUrl(slug) {
  const suffix = pluginData.settings?.CCB_URL_SUFFIX_HTML ? '.html' : '';

  return `${buildStorefrontBase(pluginData.settings?.CCB_BASE_SLUG || 'blog')}${slug}${suffix}`;
}
