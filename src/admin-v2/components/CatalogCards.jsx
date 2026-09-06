import { createCatalogComponents } from '@cci/admin-ui';
import { __ } from '../i18n';

export const { CatalogEmptyState, CatalogGrid, CatalogProductCard } = createCatalogComponents('blog', {
    labels: {
        requiresPro: __('Requires Pro', 'cci-blog'),
        installed: __('Installed', 'cci-blog'),
        available: __('Available', 'cci-blog'),
        viewInStore: __('View in store', 'cci-blog'),
        comingSoon: __('Coming soon', 'cci-blog'),
        tags: __('Tags', 'cci-blog'),
    },
});
