import React from 'react';
import { createPortal } from 'react-dom';
import { Trash2 } from 'lucide-react';
import { __ } from '../../i18n/i18n';
import { Button } from '../ui/button';

export default function ConfirmDeleteModal({
  open,
  itemLabel,
  title = __('Delete item?', 'cci-blog'),
  suffix = __('and all child elements from the current draft.', 'cci-blog'),
  confirmLabel = __('Delete item', 'cci-blog'),
  onCancel,
  onConfirm,
}) {
  const dialog = React.useRef(null);
  const cancel = React.useRef(null);
  React.useEffect(() => {
    const element = dialog.current;
    if (!open || !element) return;
    element.showModal();
    cancel.current?.focus();
    return () => {
      if (element.open) element.close();
    };
  }, [open]);

  if (!open) {
    return null;
  }

  const modal = (
    <dialog
      ref={dialog}
      className="tw-fixed tw-m-auto tw-w-[calc(100%-2rem)] tw-max-w-md tw-overflow-hidden tw-rounded-lg tw-border tw-border-solid tw-border-cci-blog-border tw-bg-white tw-p-0 tw-text-cci-blog-text tw-shadow-2xl backdrop:tw-bg-slate-950/35"
      aria-labelledby="cci-blog-delete-dialog-title"
      aria-describedby="cci-blog-delete-dialog-description"
      onCancel={(event) => {
        event.preventDefault();
        onCancel();
      }}
    >
      <div className="tw-border-0 tw-border-b tw-border-solid tw-border-cci-blog-border tw-px-5 tw-py-4">
        <h2
          id="cci-blog-delete-dialog-title"
          className="tw-m-0 tw-text-lg tw-font-semibold tw-leading-tight tw-text-cci-blog-text"
        >
          {title}
        </h2>
        <p
          id="cci-blog-delete-dialog-description"
          className="tw-m-0 tw-mt-1 tw-text-sm tw-leading-5 tw-text-cci-blog-muted"
        >
          {__('This removes', 'cci-blog')}{' '}
          {itemLabel ? (
            <strong className="tw-text-cci-blog-text">{itemLabel}</strong>
          ) : (
            __('this item', 'cci-blog')
          )}{' '}
          {suffix}
        </p>
      </div>
      <div className="tw-flex tw-justify-end tw-gap-2 tw-p-5">
        <Button ref={cancel} variant="secondary" type="button" onClick={onCancel}>
          {__('Cancel', 'cci-blog')}
        </Button>
        <Button variant="danger" type="button" onClick={onConfirm}>
          <Trash2 aria-hidden="true" />
          {confirmLabel}
        </Button>
      </div>
    </dialog>
  );

  if (typeof document === 'undefined' || !document.body) {
    return modal;
  }

  return createPortal(modal, document.body);
}
