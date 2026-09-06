import React from 'react';
import { createPortal } from 'react-dom';
import { Trash2 } from 'lucide-react';
import { __ } from '../i18n';
import { Button } from './ui/button';

export default function ConfirmDeleteModal({
    open,
    itemLabel,
    title = __('Delete item?', 'cci-blog'),
    suffix = __('and all child elements from the current draft.', 'cci-blog'),
    confirmLabel = __('Delete item', 'cci-blog'),
    onCancel,
    onConfirm,
}) {
    if (!open) {
        return null;
    }

    const modal = (
        <div className='tw-fixed tw-inset-0 tw-z-[100000] tw-grid tw-place-items-center tw-bg-slate-950/35 tw-p-4' role='presentation'>
            <div className='tw-w-full tw-max-w-md tw-overflow-hidden tw-rounded-lg tw-border tw-border-solid tw-border-cci-blog-border tw-bg-white tw-shadow-2xl' role='dialog' aria-modal='true' aria-labelledby='cci-blog-delete-dialog-title'>
                <div className='tw-border-0 tw-border-b tw-border-solid tw-border-cci-blog-border tw-px-5 tw-py-4'>
                    <h2 id='cci-blog-delete-dialog-title' className='tw-m-0 tw-text-lg tw-font-semibold tw-leading-tight tw-text-cci-blog-text'>
                        {title}
                    </h2>
                    <p className='tw-m-0 tw-mt-1 tw-text-sm tw-leading-5 tw-text-cci-blog-muted'>
                        {__('This removes', 'cci-blog')} {itemLabel ? <strong className='tw-text-cci-blog-text'>{itemLabel}</strong> : __('this item', 'cci-blog')} {suffix}
                    </p>
                </div>
                <div className='tw-flex tw-justify-end tw-gap-2 tw-p-5'>
                    <Button variant='secondary' type='button' onClick={onCancel}>{__('Cancel', 'cci-blog')}</Button>
                    <Button variant='danger' type='button' onClick={onConfirm}>
                        <Trash2 aria-hidden='true' />
                        {confirmLabel}
                    </Button>
                </div>
            </div>
        </div>
    );

    if (typeof document === 'undefined' || !document.body) {
        return modal;
    }

    return createPortal(modal, document.body);
}
