import React from 'react';
import { ProductLogo as SharedProductLogo } from '@cci/admin-ui/blog';
import { pluginData } from '../api';

export default function ProductLogo({ homeUrl = pluginData.apiBase || pluginData.upgradeUrl || pluginData.documentationUrl || '', ...props }) {
    const logoUrl = pluginData.modulePath
        ? `${pluginData.modulePath}views/img/cci-blog-logo-small.png`
        : '';

    return <SharedProductLogo {...props} logoUrl={logoUrl} homeUrl={homeUrl} />;
}
