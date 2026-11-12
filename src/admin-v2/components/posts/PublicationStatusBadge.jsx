import React from 'react';
import { StatusBadge } from '@cci/admin-ui';
import { pluginData } from '../../api/api';
import { __ } from '../../i18n/i18n';
import { publicationStatus } from '../builder/model/publication';
import { usePublicationClock } from './usePublicationClock';

export function PublicationBadge({ status }) {
  return (
    <StatusBadge
      tone={status === 'scheduled' ? 'info' : status === 'published' ? 'success' : 'neutral'}
      data-publication-status={status}
    >
      {status === 'scheduled'
        ? __('Scheduled', 'cci-blog')
        : status === 'published'
          ? __('Published', 'cci-blog')
          : __('Draft', 'cci-blog')}
    </StatusBadge>
  );
}

export default function PublicationStatusBadge({ post }) {
  const now = usePublicationClock();
  return (
    <PublicationBadge
      status={publicationStatus(post, new Date(now), pluginData.adminDate?.timeZone || 'UTC')}
    />
  );
}
