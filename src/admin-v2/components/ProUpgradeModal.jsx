import React from 'react';
import { ProUpgradeModal as SharedProUpgradeModal } from '@cci/admin-ui/blog';
import { pluginData } from '../api';
import { __ } from '../i18n';

export default function ProUpgradeModal({
    featureName,
    description,
    benefits = [],
    productUrl = '',
    open,
    onClose,
    onActivateLicense,
}) {
    const label = featureName || __('Pro feature', 'cci-blog');
    const canManageLicense = Boolean(
        pluginData.license?.canManage && pluginData.license?.proModuleAvailable
    );

    return (
        <SharedProUpgradeModal
            open={open}
            productName='CCI Blog'
            featureName={label}
            description={description}
            benefits={benefits}
            productUrl={productUrl || pluginData.upgradeUrl || ''}
            productUrlLabel={__('View product page', 'cci-blog')}
            activateLabel={__('Activate license here', 'cci-blog')}
            cancelLabel={__('Maybe later', 'cci-blog')}
            title={`${__('Available in Pro', 'cci-blog')}: ${label}`}
            message={__('Upgrade CCI Blog to unlock this workflow in the editor.', 'cci-blog')}
            licenseHint={
                canManageLicense
                    ? __('Already have a license? Activate it here in the module settings to unlock Pro features on this domain.', 'cci-blog')
                    : __('Install and enable CCI Blog Pro before activating a license on this site.', 'cci-blog')
            }
            onActivateLicense={canManageLicense ? onActivateLicense : undefined}
            onClose={onClose}
        />
    );
}
