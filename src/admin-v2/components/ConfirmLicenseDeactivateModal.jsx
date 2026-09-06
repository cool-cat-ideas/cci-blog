import React from 'react';
import { createPortal } from 'react-dom';
import { __ } from '../i18n';
import { Button } from './ui/button';

export default function ConfirmLicenseDeactivateModal({ open, saving = false, onCancel, onConfirm }) {
    if (!open) {
        return null;
    }

    const modal = (
        <div className='tw-fixed tw-inset-0 tw-z-[100000] tw-grid tw-place-items-center tw-bg-slate-950/35 tw-p-4' role='presentation'>
            <div className='tw-w-full tw-max-w-md tw-overflow-hidden tw-rounded-lg tw-border tw-border-solid tw-border-cci-blog-border tw-bg-white tw-shadow-2xl' role='dialog' aria-modal='true' aria-labelledby='cci-blog-deactivate-license-title'>
                <div className='tw-grid tw-gap-3 tw-border-0 tw-border-b tw-border-solid tw-border-cci-blog-border tw-px-5 tw-py-4'>
                    <div>
                        <h2 id='cci-blog-deactivate-license-title' className='tw-m-0 tw-text-lg tw-font-semibold tw-leading-tight tw-text-cci-blog-text'>
                            {__('Deactivate Pro license?', 'cci-blog')}
                        </h2>
                        <p className='tw-m-0 tw-mt-1 tw-text-sm tw-leading-5 tw-text-cci-blog-muted'>
                            {__('Pro features will stop working on this site until the license is activated again.', 'cci-blog')}
                        </p>
                    </div>
                </div>
                <div className='tw-flex tw-justify-end tw-gap-2 tw-p-5'>
                    <Button variant='secondary' type='button' onClick={onCancel} disabled={saving}>
                        {__('Cancel', 'cci-blog')}
                    </Button>
                    <Button variant='danger' type='button' onClick={onConfirm} disabled={saving}>
                        {saving ? __('Deactivating...', 'cci-blog') : __('Deactivate license', 'cci-blog')}
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
