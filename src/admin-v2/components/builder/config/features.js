import { __ } from '../../../i18n/i18n';

export const blogProFeatures = {
  localMediaLibrary: {
    featureName: __('Local image library', 'cci-blog'),
    description: __(
      'Browse existing PrestaShop images and select a local file without copying its URL manually.',
      'cci-blog',
    ),
    benefits: [
      __('Reuse images that are already stored by the shop.', 'cci-blog'),
      __('Choose image URLs from a safe read-only browser.', 'cci-blog'),
      __('Keep cover, category and Open Graph image selection consistent.', 'cci-blog'),
    ],
  },
  additionalCategories: {
    featureName: __('Additional categories', 'cci-blog'),
    description: __(
      'Assign one post to several blog categories while keeping a primary category for canonical URLs and breadcrumbs.',
      'cci-blog',
    ),
    benefits: [
      __('Use a main category plus secondary categories for navigation.', 'cci-blog'),
      __('Build richer blog menus and category landing pages.', 'cci-blog'),
      __('Keep Free stores clean with one primary category per post.', 'cci-blog'),
    ],
  },
  translations: {
    featureName: __('Translations', 'cci-blog'),
    description: __(
      'Edit translated post content, category labels, tags and SEO metadata for every shop language.',
      'cci-blog',
    ),
    benefits: [
      __('Translate titles, slugs, intro text and SEO fields per language.', 'cci-blog'),
      __('Keep tags and categories language-aware.', 'cci-blog'),
      __('Prepare multilingual storefront blog navigation.', 'cci-blog'),
    ],
  },
  columns: {
    featureName: __('Advanced layout blocks', 'cci-blog'),
    description: __(
      'Build responsive article layouts with columns and richer content sections.',
      'cci-blog',
    ),
    benefits: [
      __('Use advanced column layouts inside long-form articles.', 'cci-blog'),
      __('Create more editorial landing pages without custom templates.', 'cci-blog'),
    ],
  },
  product: {
    featureName: __('Commerce content blocks', 'cci-blog'),
    description: __(
      'Embed products and merchandising content directly inside articles.',
      'cci-blog',
    ),
    benefits: [
      __('Connect guides and inspiration articles with catalog products.', 'cci-blog'),
      __('Use product cards without manually copying storefront HTML.', 'cci-blog'),
    ],
  },
  product_carousel: {
    featureName: __('Product carousel', 'cci-blog'),
    description: __(
      'Create draggable product carousels with responsive carousel settings.',
      'cci-blog',
    ),
    benefits: [
      __('Promote selected products inside buying guides.', 'cci-blog'),
      __('Control carousel behaviour from the editor.', 'cci-blog'),
    ],
  },
  hook: {
    featureName: __('Hook blocks', 'cci-blog'),
    description: __('Insert approved module hook content inside the article flow.', 'cci-blog'),
    benefits: [
      __('Place reusable module content between article sections.', 'cci-blog'),
      __('Keep hook usage controlled by the module allowlist.', 'cci-blog'),
    ],
  },
  module_block: {
    featureName: __('Extension blocks', 'cci-blog'),
    description: __('Use editor blocks registered by installed CCI Blog extensions.', 'cci-blog'),
    benefits: [
      __('Extend the editor without bloating the core module.', 'cci-blog'),
      __('Unlock add-on blocks from your store as your workflow grows.', 'cci-blog'),
    ],
  },
};
