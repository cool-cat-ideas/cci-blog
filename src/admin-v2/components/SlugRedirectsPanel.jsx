import React from 'react';
import { apiFetch, pluginData } from '../api';
import { __, coreString } from '../i18n';
import { DataTable, EmptyState, RowActions, RowPrimary } from './AdminDataTable';
import ConfirmDeleteModal from './ConfirmDeleteModal';
import { Card, CardHeader } from './ui/card';
import { Input } from './ui/input';

export default function SlugRedirectsPanel({ setNotice }) {
    const [redirects, setRedirects] = React.useState(() => pluginData.slugRedirects || []);
    const [loading, setLoading] = React.useState(false);
    const [editingId, setEditingId] = React.useState(0);
    const [editingSlug, setEditingSlug] = React.useState('');
    const [pendingDelete, setPendingDelete] = React.useState(null);

    const applyRedirects = React.useCallback((nextRedirects) => {
        const normalized = Array.isArray(nextRedirects) ? nextRedirects : [];

        pluginData.slugRedirects = normalized;
        setRedirects(normalized);
    }, []);

    const request = React.useCallback((operation, successFallback) => {
        setLoading(true);

        return operation()
            .then((response) => {
                if (response.redirects) {
                    applyRedirects(response.redirects);
                }
                setNotice?.({
                    type: 'success',
                    message: response.message || successFallback,
                });

                return response;
            })
            .catch((error) => {
                setNotice?.({
                    type: 'error',
                    message: error.message || __('Slug redirect request failed.', 'cci-blog'),
                    details: error.details || '',
                });
                throw error;
            })
            .finally(() => setLoading(false));
    }, [applyRedirects, setNotice]);

    const beginEdit = (redirect) => {
        setEditingId(Number(redirect.id_redirect));
        setEditingSlug(String(redirect.old_slug || ''));
    };

    const cancelEdit = () => {
        setEditingId(0);
        setEditingSlug('');
    };

    const saveEdit = () => {
        const oldSlug = editingSlug.trim();

        if (!oldSlug) {
            setNotice?.({ type: 'error', message: __('Enter the old slug.', 'cci-blog') });
            return;
        }

        request(
            () => apiFetch('/redirect/update', {
                body: JSON.stringify({ id_redirect: editingId, old_slug: oldSlug }),
            }),
            __('Slug redirect was updated.', 'cci-blog')
        ).then(cancelEdit).catch(() => {});
    };

    const deleteRedirect = () => {
        if (!pendingDelete) {
            return;
        }

        request(
            () => apiFetch('/redirect/delete', {
                body: JSON.stringify({ id_redirect: pendingDelete.id_redirect }),
            }),
            __('Slug redirect was deleted.', 'cci-blog')
        ).then(() => setPendingDelete(null)).catch(() => {});
    };

    const rows = redirects.map((redirect) => {
        const isEditing = editingId === Number(redirect.id_redirect);
        const actions = isEditing
            ? [
                { label: coreString('save', 'Save'), onClick: saveEdit, disabled: loading },
                { label: coreString('cancel', 'Cancel'), onClick: cancelEdit, disabled: loading },
            ]
            : [
                { label: coreString('edit', 'Edit'), onClick: () => beginEdit(redirect), disabled: loading },
                { label: coreString('delete', 'Delete'), tone: 'danger', onClick: () => setPendingDelete(redirect), disabled: loading },
            ];

        return [
            isEditing ? (
                <div>
                    <Input
                        autoFocus
                        value={editingSlug}
                        disabled={loading}
                        onChange={(event) => setEditingSlug(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') saveEdit();
                            if (event.key === 'Escape') cancelEdit();
                        }}
                    />
                    <RowActions actions={actions} ariaLabel={__('Slug redirect actions', 'cci-blog')} />
                </div>
            ) : (
                <RowPrimary
                    label={redirect.old_slug}
                    onOpen={() => beginEdit(redirect)}
                    actions={actions}
                    actionsLabel={__('Slug redirect actions', 'cci-blog')}
                />
            ),
            <span className='tw-break-all'>{redirect.current_slug || '-'}</span>,
            <div>
                <span className='tw-block tw-font-medium'>{redirect.title || `#${redirect.target_id}`}</span>
                <span className='tw-mt-1 tw-block tw-text-xs tw-text-cci-blog-muted'>
                    {redirect.entity_type === 'category' ? __('Category', 'cci-blog') : __('Post', 'cci-blog')} · ID {redirect.target_id}
                </span>
            </div>,
            <span className='tw-whitespace-nowrap tw-text-cci-blog-muted'>{redirect.date_add || '-'}</span>,
        ];
    });

    return (
        <>
            <Card>
                <CardHeader>
                    <div>
                        <h2>{__('Redirects', 'cci-blog')}</h2>
                        <p>{__('Old post and category addresses are redirected automatically after a slug change.', 'cci-blog')}</p>
                    </div>
                </CardHeader>

                {rows.length ? (
                    <DataTable
                        columns={[__('Old slug', 'cci-blog'), __('Current slug', 'cci-blog'), __('Target', 'cci-blog'), __('Created', 'cci-blog')]}
                        rows={rows}
                    />
                ) : (
                    <EmptyState label={__('No slug redirects yet.', 'cci-blog')} />
                )}
            </Card>

            <ConfirmDeleteModal
                open={Boolean(pendingDelete)}
                itemLabel={pendingDelete?.old_slug || ''}
                title={__('Delete slug redirect?', 'cci-blog')}
                suffix={__('from the redirect list. Links using this old address will stop working.', 'cci-blog')}
                confirmLabel={coreString('delete', 'Delete')}
                onCancel={() => setPendingDelete(null)}
                onConfirm={deleteRedirect}
            />
        </>
    );
}
