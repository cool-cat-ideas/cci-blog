import React from 'react';
import { createBuilderComponents } from '@cci/admin-ui';
import { X } from 'lucide-react';
import { Button } from '../ui/button';
import ArticleMetadata from './ArticleMetadata';
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

/** Native builder rows with one handle for mouse, touch and keyboard reordering. */
export default function SortableArticleList({
  items,
  onChange,
  onRemove,
  disabled = false,
  label,
}) {
  const rows = React.useRef(new Map());
  const list = React.useRef(null);
  const drag = React.useRef(null);
  const touch = React.useRef(null);
  const picked = React.useRef(null);
  const originalOrder = React.useRef([]);
  const suppressClick = React.useRef(false);
  const focusAfterMove = React.useRef(null);
  const [draggingId, setDraggingId] = React.useState(null);
  const [overId, setOverId] = React.useState(null);
  const [pickedId, setPickedId] = React.useState(null);
  const [announcement, setAnnouncement] = React.useState('');
  const hintId = React.useId();

  React.useLayoutEffect(() => {
    const gesture = touch.current;
    if (gesture?.moved) {
      const handle = rows.current.get(gesture.id)?.querySelector('[data-article-drag-handle]');
      if (handle && !handle.hasPointerCapture(gesture.pointerId))
        handle.setPointerCapture(gesture.pointerId);
    }
    const id = focusAfterMove.current;
    if (id === null) return;
    focusAfterMove.current = null;
    rows.current.get(id)?.querySelector('[data-article-drag-handle]')?.focus();
  }, [items]);

  const finishPick = () => {
    picked.current = null;
    originalOrder.current = [];
    setPickedId(null);
  };
  React.useEffect(() => {
    const outside = (event) => {
      if (picked.current !== null && !list.current?.contains(event.target)) finishPick();
    };
    document.addEventListener('pointerdown', outside);
    return () => document.removeEventListener('pointerdown', outside);
  }, []);
  React.useEffect(() => {
    if (picked.current !== null && !items.some((item) => item.id === picked.current)) finishPick();
  }, [items]);

  const announce = (item, index) =>
    setAnnouncement(`${item.title}. ${__('Position', 'cci-blog')} ${index + 1} / ${items.length}.`);
  const move = (from, to) => {
    if (disabled || from < 0 || to < 0 || from >= items.length || to >= items.length || from === to)
      return;
    const next = [...items];
    const [item] = next.splice(from, 1);
    next.splice(to, 0, item);
    if (rows.current.get(item.id)?.contains(document.activeElement))
      focusAfterMove.current = item.id;
    onChange(next);
    announce(item, to);
  };
  const clearDrag = () => {
    drag.current = null;
    setDraggingId(null);
    setOverId(null);
  };
  const choosePosition = (item, index) => {
    if (disabled) return;
    if (picked.current !== null) {
      if (picked.current !== item.id) {
        focusAfterMove.current = picked.current;
        move(
          items.findIndex((row) => row.id === picked.current),
          index,
        );
      }
      finishPick();
    } else {
      picked.current = item.id;
      originalOrder.current = items.map((row) => row.id);
      setPickedId(item.id);
      setAnnouncement(
        `${item.title}. ${__('Article selected. Choose a new position.', 'cci-blog')}`,
      );
    }
  };
  const cancelPick = () => {
    const id = picked.current;
    if (id === null) return;
    const byId = new Map(items.map((item) => [item.id, item]));
    if (
      originalOrder.current.length === items.length &&
      originalOrder.current.every((key) => byId.has(key))
    ) {
      focusAfterMove.current = id;
      onChange(originalOrder.current.map((key) => byId.get(key)));
    }
    finishPick();
    setAnnouncement(__('Reordering cancelled.', 'cci-blog'));
  };
  const endTouch = (cancelled) => {
    const gesture = touch.current;
    if (!gesture) return;
    touch.current = null;
    if (gesture.moved) {
      suppressClick.current = true;
      window.setTimeout(() => {
        suppressClick.current = false;
      }, 0);
      if (cancelled) {
        const byId = new Map(items.map((item) => [item.id, item]));
        if (gesture.order.length === items.length && gesture.order.every((key) => byId.has(key)))
          onChange(gesture.order.map((key) => byId.get(key)));
      }
    }
    clearDrag();
  };

  return (
    <BuilderCanvas>
      <ol ref={list} aria-label={label} className="tw-m-0 tw-grid tw-list-none tw-gap-3 tw-p-0">
        {items.map((item, index) => (
          <BuilderItem
            as="li"
            key={item.id}
            ref={(node) => {
              if (node) rows.current.set(item.id, node);
              else rows.current.delete(item.id);
            }}
            draggable={!disabled}
            className={disabled ? '' : 'tw-cursor-grab active:tw-cursor-grabbing'}
            style={{
              opacity: draggingId === item.id ? 0.58 : 1,
              outline:
                (overId === item.id && draggingId !== item.id) || pickedId === item.id
                  ? '2px dashed var(--cci-builder-brand)'
                  : undefined,
              outlineOffset: '-2px',
            }}
            onClick={(event) => {
              if (picked.current === null || event.target.closest('button,a,input,textarea,select'))
                return;
              choosePosition(item, index);
            }}
            onDragStart={(event) => {
              if (
                disabled ||
                (event.target.closest('button,a,input,textarea,select') &&
                  !event.target.closest('[data-article-drag-handle]'))
              ) {
                event.preventDefault();
                return;
              }
              event.stopPropagation();
              finishPick();
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
                items.findIndex((row) => row.id === drag.current),
                index,
              );
              clearDrag();
            }}
            onDragEnd={clearDrag}
          >
            <BuilderItemMain asChild>
              <div>
                <Button
                  variant="unstyled"
                  type="button"
                  data-article-drag-handle=""
                  style={{ touchAction: 'none' }}
                  disabled={disabled}
                  className="tw-flex tw-h-6 tw-w-6 tw-shrink-0 tw-cursor-grab tw-items-center tw-justify-center tw-border-0 tw-bg-transparent tw-p-0 active:tw-cursor-grabbing focus-visible:tw-outline focus-visible:tw-outline-2 focus-visible:tw-outline-offset-2 focus-visible:tw-outline-cci-blog-brand"
                  aria-label={`${__('Reorder article', 'cci-blog')}: ${item.title}`}
                  aria-describedby={hintId}
                  aria-pressed={pickedId === item.id}
                  title={__(
                    'Drag to reorder, or select this handle and then select a new position.',
                    'cci-blog',
                  )}
                  onClick={(event) => {
                    event.stopPropagation();
                    if (suppressClick.current) return;
                    choosePosition(item, index);
                  }}
                  onKeyDown={(event) => {
                    if (event.key === ' ' || event.key === 'Enter') {
                      event.preventDefault();
                      event.stopPropagation();
                      choosePosition(item, index);
                    } else if (event.key === 'Escape' && picked.current !== null) {
                      event.preventDefault();
                      event.stopPropagation();
                      cancelPick();
                    } else if (
                      picked.current === item.id &&
                      ['ArrowUp', 'ArrowDown', 'Home', 'End'].includes(event.key)
                    ) {
                      event.preventDefault();
                      event.stopPropagation();
                      move(
                        index,
                        event.key === 'Home'
                          ? 0
                          : event.key === 'End'
                            ? items.length - 1
                            : index + (event.key === 'ArrowUp' ? -1 : 1),
                      );
                    }
                  }}
                  onPointerDown={(event) => {
                    if (disabled || event.pointerType === 'mouse') return;
                    touch.current = {
                      id: item.id,
                      pointerId: event.pointerId,
                      x: event.clientX,
                      y: event.clientY,
                      moved: false,
                      order: items.map((row) => row.id),
                    };
                    event.currentTarget.setPointerCapture(event.pointerId);
                  }}
                  onPointerMove={(event) => {
                    const gesture = touch.current;
                    if (!gesture || disabled) return;
                    if (
                      !gesture.moved &&
                      Math.hypot(event.clientX - gesture.x, event.clientY - gesture.y) < 6
                    )
                      return;
                    event.preventDefault();
                    finishPick();
                    gesture.moved = true;
                    setDraggingId(gesture.id);
                    const target = document
                      .elementFromPoint(event.clientX, event.clientY)
                      ?.closest('[data-cci-builder-item]');
                    const destination = items.findIndex(
                      (row) => rows.current.get(row.id) === target,
                    );
                    if (destination >= 0)
                      move(
                        items.findIndex((row) => row.id === gesture.id),
                        destination,
                      );
                  }}
                  onPointerUp={() => endTouch(false)}
                  onPointerCancel={() => endTouch(true)}
                >
                  <BuilderGrip aria-hidden="true" />
                </Button>
                <BuilderItemCopy>
                  <BuilderItemTitle>
                    {index + 1}. {item.title}
                  </BuilderItemTitle>
                  <ArticleMetadata article={item} />
                </BuilderItemCopy>
              </div>
            </BuilderItemMain>
            <BuilderActions>
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
      <span id={hintId} className="tw-sr-only">
        {__(
          'Press Space to select an article, use arrow keys to move it, then press Space to drop. Escape cancels.',
          'cci-blog',
        )}
      </span>
      <span className="tw-sr-only" role="status">
        {announcement}
      </span>
    </BuilderCanvas>
  );
}
