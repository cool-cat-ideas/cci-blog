import React from 'react';
import { InfoCallout } from '@cci/admin-ui/blog';

export function RowPrimary({ label, onOpen, actions, actionsLabel }) {
  return (
    <div>
      <button
        type="button"
        className="tw-border-0 tw-bg-transparent tw-p-0 tw-text-left tw-font-semibold tw-text-cci-blog-text hover:tw-text-cci-blog-brandStrong hover:tw-underline"
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
      className="tw-mt-1 tw-flex tw-flex-wrap tw-items-center tw-leading-5 tw-opacity-100 tw-transition-opacity md:tw-opacity-0 md:group-hover:tw-opacity-100 md:group-focus-within:tw-opacity-100"
      aria-label={ariaLabel}
    >
      {visibleActions.map((action, index) => {
        const isLast = index === visibleActions.length - 1;
        const toneClass =
          action.tone === 'danger'
            ? 'tw-text-cci-blog-danger hover:tw-text-cci-blog-dangerText'
            : 'tw-text-cci-blog-brand hover:tw-text-cci-blog-brandStrong';
        const disabledClass = action.disabled
          ? 'tw-cursor-not-allowed tw-opacity-50'
          : 'tw-cursor-pointer';
        const separatorClass = isLast
          ? ''
          : ' after:tw-mx-2 after:tw-text-cci-blog-borderStrong after:tw-content-["|"]';

        return (
          <button
            key={`${action.label}-${index}`}
            type="button"
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

export function DataTable({ columns, rows, compact = false, label }) {
  const normalizedColumns = columns.map((column) =>
    typeof column === 'string' ? { id: column, label: column } : column,
  );

  return (
    <div className="tw-overflow-x-auto">
      <table
        aria-label={label}
        className={`tw-w-full tw-border-collapse ${compact ? 'tw-table-fixed' : 'tw-min-w-[860px]'}`}
      >
        <thead className="tw-bg-cci-blog-surfaceSoft">
          <tr>
            {normalizedColumns.map((column) => (
              <th
                key={column.id || column.label}
                className={`tw-border-0 tw-border-b tw-border-solid tw-border-cci-blog-border tw-py-3 tw-text-left tw-text-xs tw-font-bold tw-uppercase tw-text-cci-blog-muted ${compact ? 'tw-break-words tw-px-2 sm:tw-px-4' : 'tw-px-4'} ${column.className || ''}`}
                scope="col"
              >
                {column.sortable ? (
                  <button
                    type="button"
                    className="tw-inline-flex tw-items-center tw-gap-1.5 tw-border-0 tw-bg-transparent tw-p-0 tw-text-xs tw-font-bold tw-uppercase tw-text-cci-blog-muted hover:tw-text-cci-blog-brandStrong"
                    aria-sort={column.sortDirection || 'none'}
                    onClick={column.onSort}
                  >
                    <span>{column.label}</span>
                    <span className="tw-text-[10px]" aria-hidden="true">
                      {column.sortIcon || '↕'}
                    </span>
                  </button>
                ) : (
                  column.label
                )}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((row, rowIndex) => (
            <tr
              key={rowIndex}
              className="tw-group tw-border-0 tw-border-b tw-border-solid tw-border-cci-blog-border last:tw-border-b-0"
            >
              {row.map((cell, cellIndex) => (
                <td
                  key={cellIndex}
                  className={`tw-align-middle tw-text-sm tw-text-cci-blog-text ${compact ? 'tw-break-words tw-px-2 tw-py-3 sm:tw-px-4' : 'tw-px-4 tw-py-4'}`}
                >
                  {cell}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

export function EmptyState({ label }) {
  return (
    <div className="tw-p-4">
      <InfoCallout tone="info">{label}</InfoCallout>
    </div>
  );
}
