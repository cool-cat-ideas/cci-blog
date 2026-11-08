import { __ } from '../../../i18n/i18n';

// Labels only. All audit rules and scoring live in the PHP engine.
export const seoContentTypeConfig = {
  article: { label: __('Article', 'cci-blog') },
  guide: { label: __('Guide', 'cci-blog') },
  news: { label: __('News', 'cci-blog') },
  landing: { label: __('Landing article', 'cci-blog') },
  category: { label: __('Category page', 'cci-blog'), hiddenInPost: true },
};
export function seoContentTypeOptions() {
  return Object.entries(seoContentTypeConfig)
    .filter(([, config]) => !config.hiddenInPost)
    .map(([value, config]) => ({ value, label: config.label }));
}
