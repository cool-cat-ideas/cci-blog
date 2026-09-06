import React from 'react';
import { Images } from 'lucide-react';
import { __ } from '../i18n';
import ProBadge from './ProBadge';
import { Button } from './ui/button';
import { Input } from './ui/input';

const readyEvent = 'cci-blog:local-media-library-ready';

export default function LocalMediaUrlInput({ value = '', onChange, onLockedClick, placeholder = 'https://...' }) {
    const getAvailability = () => typeof window !== 'undefined' && Boolean(window.CCIBlogProMediaLibrary?.isAvailable?.());
    const [available, setAvailable] = React.useState(getAvailability);

    React.useEffect(() => {
        const refresh = () => setAvailable(getAvailability());
        window.addEventListener(readyEvent, refresh);
        refresh();
        return () => window.removeEventListener(readyEvent, refresh);
    }, []);

    const chooseImage = () => {
        if (!available) {
            onLockedClick?.();
            return;
        }

        window.CCIBlogProMediaLibrary?.open?.({
            value,
            onSelect: (url) => onChange?.(url),
        });
    };

    return (
        <div className='tw-flex tw-min-w-0 tw-flex-col tw-gap-2 sm:tw-flex-row sm:tw-items-center'>
            <Input className='tw-min-w-0 tw-flex-1' value={value} onChange={(event) => onChange?.(event.target.value)} placeholder={placeholder} />
            <Button
                className={`tw-shrink-0 ${available ? '' : 'cci-blog-state-locked tw-cursor-not-allowed'}`}
                type='button'
                variant={available ? 'secondary' : 'outlineAccent'}
                data-pro-feature='true'
                data-pro-locked={available ? undefined : 'true'}
                title={available ? undefined : __('Requires CCI Blog Pro.', 'cci-blog')}
                onClick={chooseImage}
            >
                <Images aria-hidden='true' />
                <span>{__('Choose local image', 'cci-blog')}</span>
                <ProBadge className='tw-ml-1 tw-shrink-0 tw-px-1.5 tw-py-0.5'>{__('Pro', 'cci-blog')}</ProBadge>
            </Button>
        </div>
    );
}
