import React from 'react';
import { ProBadge as SharedProBadge } from '@cci/admin-ui/blog';
import { __ } from '../i18n';

export default function ProBadge({ children, ...props }) {
    return <SharedProBadge {...props}>{children || __('Pro', 'cci-blog')}</SharedProBadge>;
}
