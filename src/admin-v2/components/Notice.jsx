import React from 'react';
import { Notice as SharedNotice } from '@cci/admin-ui/blog';
import { __ } from '../i18n';

export default function Notice({ closeLabel = __('Close notification', 'cci-blog'), ...props }) {
    return <SharedNotice closeLabel={closeLabel} {...props} />;
}
