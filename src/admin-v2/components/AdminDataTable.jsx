import React from 'react';

export function RowPrimary({ label, onOpen, actions, actionsLabel }) {
    return (
        <div>
            <button
                type='button'
                className='tw-border-0 tw-bg-transparent tw-p-0 tw-text-left tw-font-semibold tw-text-cci-blog-text hover:tw-text-cci-blog-brandStrong hover:tw-underline'
                onClick={onOpen}
            >
                {label}
            </button>
            <RowActions ariaLabel={actionsLabel} actions={actions} />
        </div>
    );
}

export function RowActions({ actions, ariaLabel }) {
    const visibleActions = (actions || []).filter(Boolean);
    if (!visibleActions.length) {
        return null;
    }

    return (
        <div
            className='tw-mt-1 tw-flex tw-flex-wrap tw-items-center tw-leading-5 tw-opacity-100 tw-transition-opacity md:tw-opacity-0 md:group-hover:tw-opacity-100 md:group-focus-within:tw-opacity-100'
            aria-label={ariaLabel}
        >
            {visibleActions.map((action, index) => {
                const isLast = index === visibleActions.length - 1;
                const toneClass = action.tone === 'danger'
                    ? 'tw-text-cci-blog-danger hover:tw-text-cci-blog-dangerText'
                    : 'tw-text-cci-blog-brand hover:tw-text-cci-blog-brandStrong';
                const disabledClass = action.disabled ? 'tw-cursor-not-allowed tw-opacity-50' : 'tw-cursor-pointer';
                const separatorClass = isLast ? '' : ' after:tw-mx-2 after:tw-text-cci-blog-borderStrong after:tw-content-["|"]';

                return (
                    <button
                        key={`${action.label}-${index}`}
                        type='button'
                        className={`tw-inline-flex tw-border-0 tw-bg-transparent tw-p-0 tw-text-[13px] tw-font-medium tw-no-underline hover:tw-underline disabled:tw-no-underline ${toneClass} ${disabledClass}${separatorClass}`}
                        disabled={Boolean(action.disabled)}
                        onClick={action.onClick}
                    >
                        {action.label}
                    </button>
                );
            })}
        </div>
    );
}

export function DataTable({ columns, rows }) {
    const normalizedColumns = columns.map((column) => (
        typeof column === 'string' ? { id: column, label: column } : column
    ));

    return (
        <div className='tw-overflow-x-auto'>
            <table className='tw-w-full tw-min-w-[860px] tw-border-collapse'>
                <thead className='tw-bg-cci-blog-surfaceSoft'>
                    <tr>
                        {normalizedColumns.map((column) => (
                            <th
                                key={column.id || column.label}
                                className='tw-border-0 tw-border-b tw-border-solid tw-border-cci-blog-border tw-px-4 tw-py-3 tw-text-left tw-text-xs tw-font-bold tw-uppercase tw-text-cci-blog-muted'
                            >
                                {column.sortable ? (
                                    <button
                                        type='button'
                                        className='tw-inline-flex tw-items-center tw-gap-1.5 tw-border-0 tw-bg-transparent tw-p-0 tw-text-xs tw-font-bold tw-uppercase tw-text-cci-blog-muted hover:tw-text-cci-blog-brandStrong'
                                        aria-sort={column.sortDirection || 'none'}
                                        onClick={column.onSort}
                                    >
                                        <span>{column.label}</span>
                                        <span className='tw-text-[10px]' aria-hidden='true'>{column.sortIcon || '↕'}</span>
                                    </button>
                                ) : column.label}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row, rowIndex) => (
                        <tr key={rowIndex} className='tw-group tw-border-0 tw-border-b tw-border-solid tw-border-cci-blog-border last:tw-border-b-0'>
                            {row.map((cell, cellIndex) => <td key={cellIndex} className='tw-px-4 tw-py-4 tw-align-middle tw-text-sm tw-text-cci-blog-text'>{cell}</td>)}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export function EmptyState({ label }) {
    return <div className='tw-grid tw-min-h-44 tw-place-items-center tw-p-8 tw-text-center tw-text-sm tw-text-cci-blog-muted'>{label}</div>;
}
