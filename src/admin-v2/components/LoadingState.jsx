import React from 'react';
import { LoadingState as SharedLoadingState } from '@cci/admin-ui/blog';
import { __ } from '../i18n';

export default function LoadingState({ label = __('Loading...', 'cci-blog'), ...props }) {
    return <SharedLoadingState label={label} {...props} />;
}
