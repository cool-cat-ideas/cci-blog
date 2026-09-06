import React from 'react';
import { ReviewPrompt as SharedReviewPrompt } from '@cci/admin-ui/blog';
import { apiFetch, pluginData } from '../api';
import { __ } from '../i18n';

const copy = {
    quickFeedback: __('Quick feedback', 'cci-blog'),
    title: __('How is CCI Blog working for you?', 'cci-blog'),
    description: __(
        'If this module helps your store, a short review helps us keep improving it.',
        'cci-blog'
    ),
    addReview: __('Add review', 'cci-blog'),
    openReviewPage: __('Open review page', 'cci-blog'),
    close: __('Close', 'cci-blog'),
    dialogTitle: __('Share your CCI Blog feedback', 'cci-blog'),
    dialogDescription: __('Your review will be sent for moderation. It will not be published automatically.', 'cci-blog'),
    rating: __('Rating', 'cci-blog'),
    setRating: __('Set rating', 'cci-blog'),
    ratingHelp: __('Choose a rating from 1 to 5 stars.', 'cci-blog'),
    shortReview: __('Short review', 'cci-blog'),
    placeholder: __('What worked well? What should we improve?', 'cci-blog'),
    consentRequired: __('Required consent', 'cci-blog'),
    cancel: __('Cancel', 'cci-blog'),
    saveFeedback: __('Save feedback', 'cci-blog'),
    saving: __('Saving...', 'cci-blog'),
    thankYou: __('Thank you for your feedback.', 'cci-blog'),
    failed: __('Failed saving your feedback.', 'cci-blog'),
    tryAgain: __('Please try again in a moment.', 'cci-blog'),
};

export default function ReviewPrompt(props) {
    const consent = {
        codeName: 'productReviewPublication',
        content: 'I agree to send this rating and review to Cool Cat Ideas for moderation and possible publication on the product page. The review will not be published automatically.',
        locale: 'en',
        version: 1,
    };

    return (
        <SharedReviewPrompt
            {...props}
            apiFetch={apiFetch}
            mascotUrl={pluginData.modulePath ? `${pluginData.modulePath}views/img/cci-hello.png` : ''}
            reviewEndpoint={pluginData.reviewEndpoint || pluginData.reviewFeedbackEndpoint || '/review-feedback'}
            reviewUrl={
                pluginData.reviewUrl ||
                pluginData.wordpressReviewUrl ||
                pluginData.documentationUrl ||
                '#'
            }
            copy={copy}
            consent={consent}
        />
    );
}
