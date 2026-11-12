import React from 'react';
import { createBuilderComponents } from '@cci/admin-ui';
import { ArrowUp, ArrowDown, X } from 'lucide-react';
import { Button } from '../ui/button';
import { __ } from '../../i18n/i18n';

const {
  BuilderCanvas,
  BuilderItem,
  BuilderItemMain,
  BuilderGrip,
  BuilderItemCopy,
  BuilderItemTitle,
  BuilderActions,
} = createBuilderComponents('blog');

/** Use the same native drag/drop and shared builder rows as CCI Nice Menu. */
export default function SortableArticleList({
  items,
  onChange,
  onRemove,
  disabled = false,
  label,
}) {
  const drag = React.useRef(null);
  const [draggingId, setDraggingId] = React.useState(null);
  const [overId, setOverId] = React.useState(null);
  const [announcement, setAnnouncement] = React.useState('');
  const move = (from, to) => {
    if (disabled || from < 0 || to < 0 || from >= items.length || to >= items.length || from === to)
      return;
    const next = [...items];
    const [item] = next.splice(from, 1);
    next.splice(to, 0, item);
    onChange(next);
    setAnnouncement(`${item.title}. ${__('Position', 'cci-blog')} ${to + 1} / ${items.length}.`);
  };
  const clear = () => {
    drag.current = null;
    setDraggingId(null);
    setOverId(null);
  };
  return (
    <BuilderCanvas>
      <ol aria-label={label} className="tw-m-0 tw-grid tw-list-none tw-gap-3 tw-p-0">
        {items.map((item, index) => (
          <BuilderItem
            as="li"
            key={item.id}
            draggable={!disabled}
            className={disabled ? '' : 'tw-cursor-grab active:tw-cursor-grabbing'}
            style={{
              opacity: draggingId === item.id ? 0.58 : 1,
              outline:
                overId === item.id && draggingId !== item.id
                  ? '2px dashed var(--cci-builder-brand)'
                  : undefined,
              outlineOffset: '-2px',
            }}
            onDragStart={(event) => {
              if (disabled || event.target.closest('button,a,input,textarea,select')) {
                event.preventDefault();
                return;
              }
              event.stopPropagation();
              drag.current = item.id;
              setDraggingId(item.id);
              event.dataTransfer.effectAllowed = 'move';
              event.dataTransfer.setData('text/plain', String(item.id));
            }}
            onDragOver={(event) => {
              if (drag.current === null || disabled) return;
              event.preventDefault();
              event.stopPropagation();
              event.dataTransfer.dropEffect = 'move';
              setOverId(item.id);
            }}
            onDragLeave={(event) => {
              if (
                !(event.relatedTarget instanceof Node) ||
                !event.currentTarget.contains(event.relatedTarget)
              )
                setOverId(null);
            }}
            onDrop={(event) => {
              if (drag.current === null || disabled) return;
              event.preventDefault();
              event.stopPropagation();
              move(
                items.findIndex((entry) => entry.id === drag.current),
                index,
              );
              clear();
            }}
            onDragEnd={clear}
          >
            <BuilderItemMain asChild>
              <div>
                <BuilderGrip />
                <BuilderItemCopy>
                  <BuilderItemTitle>
                    {index + 1}. {item.title}
                  </BuilderItemTitle>
                </BuilderItemCopy>
              </div>
            </BuilderItemMain>
            <BuilderActions>
              <Button
                variant="builder"
                size="iconXs"
                type="button"
                draggable={false}
                disabled={disabled || index === 0}
                aria-label={`${__('Move up', 'cci-blog')}: ${item.title}`}
                title={__('Move up', 'cci-blog')}
                onClick={() => move(index, index - 1)}
              >
                <ArrowUp aria-hidden="true" />
              </Button>
              <Button
                variant="builder"
                size="iconXs"
                type="button"
                draggable={false}
                disabled={disabled || index === items.length - 1}
                aria-label={`${__('Move down', 'cci-blog')}: ${item.title}`}
                title={__('Move down', 'cci-blog')}
                onClick={() => move(index, index + 1)}
              >
                <ArrowDown aria-hidden="true" />
              </Button>
              {onRemove && (
                <Button
                  variant="danger"
                  size="iconXs"
                  type="button"
                  draggable={false}
                  disabled={disabled}
                  aria-label={`${__('Remove recommendation', 'cci-blog')}: ${item.title}`}
                  title={__('Remove recommendation', 'cci-blog')}
                  onClick={() => onRemove(item.id)}
                >
                  <X aria-hidden="true" />
                </Button>
              )}
            </BuilderActions>
          </BuilderItem>
        ))}
      </ol>
      <span className="tw-sr-only" role="status">
        {announcement}
      </span>
    </BuilderCanvas>
  );
}
