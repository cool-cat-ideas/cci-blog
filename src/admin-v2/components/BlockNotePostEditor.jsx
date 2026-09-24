import React from 'react';
import { createPortal } from 'react-dom';
import {
    BlockNoteSchema,
    defaultBlockSpecs,
    defaultInlineContentSpecs,
} from '@blocknote/core';
import { en, pl } from '@blocknote/core/locales';
import {
    createReactBlockSpec,
    createReactInlineContentSpec,
    getDefaultReactSlashMenuItems,
    SuggestionMenuController,
    useCreateBlockNote,
} from '@blocknote/react';
import { BlockNoteView } from '@blocknote/mantine';
import { filterSuggestionItems, insertOrUpdateBlockForSlashMenu } from '@blocknote/core/extensions';
import { TextSelection } from '@tiptap/pm/state';
import {
    ChevronDown,
    ChevronRight,
    Columns3,
    GripVertical,
    Image as ImageIcon,
    Link2,
    Package,
    PanelsTopLeft,
    Plus,
    Search,
    ShoppingBag,
    Trash2,
} from 'lucide-react';
import { apiFetch, isCciBlogProBlockKindEnabled, isCciBlogProFeatureEnabled, pluginData } from '../api';
import {
    defaultContentHookName,
    getContentHookFieldNote,
    getContentHookHelpText,
    getContentHookOptions,
} from '../content-hooks';
import { __ } from '../i18n';
import { Button } from './ui/button';
import { CssLengthInput, normalizeCssLengthValue } from './ui/css-length-input';
import { Field } from './ui/field';
import { Input, Textarea } from './ui/input';
import { Select } from './ui/select';
import ConfirmDeleteModal from './ConfirmDeleteModal';
import ProBadge from './ProBadge';
import LocalMediaUrlInput from './LocalMediaUrlInput';

const extensionEventName = 'cci-blog:block-extension-registered';
const proEditorFieldsEventName = 'cci-blog:pro-editor-fields-ready';
const columnGapUnits = ['px', 'rem', 'em', '%'];
const BlockExtensionsContext = React.createContext([]);
const FeatureGateContext = React.createContext(() => true);
const LockedFeatureContext = React.createContext({ features: {}, onLockedFeatureClick: null });

function ensureProEditorFieldRegistry() {
    if (typeof window === 'undefined') {
        return null;
    }

    const existing = window.CCIBlogProEditorFields;
    if (existing && typeof existing.register === 'function' && typeof existing.get === 'function') {
        return existing;
    }

    const renderers = existing?.renderers && typeof existing.renderers === 'object' ? existing.renderers : {};
    window.CCIBlogProEditorFields = {
        renderers,
        register(kind, renderer) {
            const normalizedKind = String(kind || '').trim();
            if (!normalizedKind || typeof renderer !== 'function') {
                return;
            }

            renderers[normalizedKind] = renderer;
            window.dispatchEvent(new CustomEvent(proEditorFieldsEventName, { detail: { kind: normalizedKind } }));
        },
        get(kind) {
            return renderers[String(kind || '').trim()] || null;
        },
        all() {
            return { ...renderers };
        },
    };

    return window.CCIBlogProEditorFields;
}

const advancedBlockDefaults = {
    kind: 'link',
    title: '',
    label: '',
    href: '',
    target: '',
    rel: '',
    variant: '',
    htmlId: '',
    ariaLabel: '',
    imageUrl: '',
    alt: '',
    caption: '',
    imageWidth: '',
    imageAlignment: 'left',
    imageFramed: '0',
    captionLinked: '0',
    productId: '',
    productIds: '',
    carouselItemsDesktop: '3',
    carouselItemsTablet: '2',
    carouselItemsMobile: '1',
    carouselMargin: '16',
    carouselStagePadding: '0',
    carouselLoop: '0',
    carouselNav: '1',
    carouselDots: '1',
    carouselAutoplay: '0',
    carouselAutoplayTimeout: '5000',
    carouselAutoplayHoverPause: '1',
    hook: defaultContentHookName,
    gap: '1rem',
    columnsJson: '',
    moduleName: '',
    blockName: '',
    payload: '{}',
};

const advancedInlineLinkDefaults = {
    href: '',
    title: '',
    target: '',
    rel: '',
    variant: '',
    htmlId: '',
    ariaLabel: '',
};

const linkVariantOptions = [
    { value: 'default', label: __('Default link', 'cci-blog') },
    { value: 'button', label: __('Button link', 'cci-blog') },
    { value: 'muted', label: __('Subtle link', 'cci-blog') },
];

const CciBlogInlineLink = createReactInlineContentSpec(
    {
        type: 'cciBlogLink',
        propSchema: Object.keys(advancedInlineLinkDefaults).reduce((schema, key) => ({
            ...schema,
            [key]: { default: advancedInlineLinkDefaults[key] },
        }), {}),
        content: 'styled',
    },
    {
        render: CciBlogInlineLinkView,
        toExternalHTML: CciBlogInlineLinkExternalHtml,
        parse: parseCciBlogInlineLink,
    }
);

function normalizeLinkVariant(value) {
    const normalized = String(value || '').trim().toLowerCase().replace(/[^a-z0-9_-]+/g, '_');
    return ['button', 'muted'].includes(normalized) ? normalized : '';
}

function normalizeImageAlignment(value) {
    const alignment = String(value || '').trim().toLowerCase();

    return ['left', 'center', 'right'].includes(alignment) ? alignment : 'left';
}

function normalizeImageWidth(value) {
    const width = Number(value || 0);

    return Number.isFinite(width) && width > 0 ? Math.max(64, Math.min(2400, Math.round(width))) : 0;
}

function inferLinkVariantFromClass(value) {
    const classes = String(value || '').toLowerCase().split(/\s+/).filter(Boolean);
    if (classes.some((className) => className.includes('button') || className.includes('btn'))) {
        return 'button';
    }
    if (classes.some((className) => className.includes('muted') || className.includes('subtle'))) {
        return 'muted';
    }
    return '';
}

const CciBlogBlock = createReactBlockSpec(
    {
        type: 'cciBlogBlock',
        propSchema: Object.keys(advancedBlockDefaults).reduce((schema, key) => ({
            ...schema,
            [key]: { default: advancedBlockDefaults[key] },
        }), {}),
        content: 'none',
    },
    {
        render: CciBlogBlockView,
    }
)();

const schema = BlockNoteSchema.create({
    blockSpecs: {
        ...defaultBlockSpecs,
        cciBlogBlock: CciBlogBlock,
    },
    inlineContentSpecs: {
        ...defaultInlineContentSpecs,
        cciBlogLink: CciBlogInlineLink,
    },
});

function CciBlogInlineLinkView({ inlineContent, updateInlineContent, contentRef }) {
    const [dialogOpen, setDialogOpen] = React.useState(false);
    const props = normalizeAdvancedInlineLinkProps(inlineContent.props);
    const values = {
        ...props,
        label: inlineContentToText(inlineContent.content),
    };

    return (
        <>
            <a
                {...advancedInlineLinkDomProps(props, true)}
                ref={contentRef}
                onClick={(event) => event.preventDefault()}
                onDoubleClick={(event) => {
                    event.preventDefault();
                    setDialogOpen(true);
                }}
                title={props.title || __('Double-click to edit this advanced link.', 'cci-blog')}
            />
            <AdvancedLinkDialog
                open={dialogOpen}
                initialValues={values}
                title={__('Edit advanced link', 'cci-blog')}
                submitLabel={__('Save link', 'cci-blog')}
                onCancel={() => setDialogOpen(false)}
                onSubmit={(nextValues) => {
                    updateInlineContent({
                        type: 'cciBlogLink',
                        props: normalizeAdvancedInlineLinkProps(nextValues),
                        content: nextValues.label.trim(),
                    });
                    setDialogOpen(false);
                }}
            />
        </>
    );
}

function CciBlogInlineLinkExternalHtml({ inlineContent, contentRef }) {
    return (
        <a
            {...advancedInlineLinkDomProps(normalizeAdvancedInlineLinkProps(inlineContent.props))}
            ref={contentRef}
        />
    );
}

function parseCciBlogInlineLink(element) {
    if (!(element instanceof HTMLElement)
        || element.tagName.toLowerCase() !== 'a'
        || !element.hasAttribute('data-cci-blog-advanced-link')) {
        return undefined;
    }

    return normalizeAdvancedInlineLinkProps({
        href: element.getAttribute('href') || '',
        title: element.getAttribute('title') || '',
        target: element.getAttribute('target') || '',
        rel: element.getAttribute('rel') || '',
        variant: inferLinkVariantFromClass(element.getAttribute('class') || ''),
        htmlId: element.getAttribute('id') || '',
        ariaLabel: element.getAttribute('aria-label') || '',
    });
}

function normalizeAdvancedInlineLinkProps(value = {}) {
    const target = value.target === '_blank' ? '_blank' : '';
    const relTokens = String(value.rel || '').trim().split(/\s+/).filter(Boolean);
    if (target === '_blank') {
        relTokens.push('noopener', 'noreferrer');
    }

    return {
        href: String(value.href || '').trim(),
        title: String(value.title || '').trim(),
        target,
        rel: [...new Set(relTokens)].join(' '),
        variant: normalizeLinkVariant(value.variant),
        htmlId: String(value.htmlId || '').trim(),
        ariaLabel: String(value.ariaLabel || '').trim(),
    };
}

function advancedInlineLinkDomProps(props, editorView = false) {
    const classNames = ['cci-blog-content-link'];
    if (editorView) {
        classNames.push('cci-blog-bn-inline-link');
    }
    if (props.variant) {
        classNames.push(`cci-blog-content-link-${props.variant}`);
    }

    return {
        href: props.href || '#',
        className: classNames.join(' '),
        title: props.title || undefined,
        target: props.target || undefined,
        rel: props.rel || undefined,
        id: props.htmlId || undefined,
        'aria-label': props.ariaLabel || undefined,
        'data-cci-blog-advanced-link': 'true',
    };
}

function useAdvancedInlineLink(editor, emitChange) {
    const [initialValues, setInitialValues] = React.useState(null);
    const open = React.useCallback(() => {
        let label = '';
        try {
            label = editor.getSelectedText?.() || '';
        } catch (error) {
            label = '';
        }

        setInitialValues({
            ...advancedInlineLinkDefaults,
            label,
        });
    }, [editor]);
    const close = React.useCallback(() => setInitialValues(null), []);
    const insert = React.useCallback((values) => {
        const label = values.label.trim();
        const inlineLink = {
            type: 'cciBlogLink',
            props: normalizeAdvancedInlineLinkProps(values),
            content: label,
        };
        const editorState = editor.prosemirrorView?.state;
        const insertionStart = editorState?.selection?.from;
        const addTrailingSpace = isSelectionAtTextBlockEnd(editorState);
        const contentToInsert = addTrailingSpace ? [inlineLink, ' '] : [inlineLink];

        editor.focus();
        try {
            editor.insertInlineContent(contentToInsert, { updateSelection: false });
            moveCursorAfterInsertedInlineContent(editor, insertionStart, label, addTrailingSpace);
        } catch (error) {
            const reference = editor.document[editor.document.length - 1];
            if (reference) {
                const inserted = editor.insertBlocks([{
                    type: 'paragraph',
                    content: [inlineLink],
                }], reference, 'after');
                if (inserted[0]) {
                    editor.setTextCursorPosition(inserted[0], 'end');
                }
            }
        }

        close();
        window.requestAnimationFrame(() => {
            moveCursorAfterInsertedInlineContent(editor, insertionStart, label, addTrailingSpace);
            editor.focus();
        });
        scheduleEditorChange(emitChange);
    }, [close, editor, emitChange]);

    return {
        open,
        dialog: (
            <AdvancedLinkDialog
                open={Boolean(initialValues)}
                initialValues={initialValues || { ...advancedInlineLinkDefaults, label: '' }}
                onCancel={close}
                onSubmit={insert}
            />
        ),
    };
}

function isSelectionAtTextBlockEnd(editorState) {
    if (!editorState?.selection) {
        return false;
    }

    const resolvedEnd = editorState.doc.resolve(editorState.selection.to);

    return resolvedEnd.parent.isTextblock && resolvedEnd.parentOffset === resolvedEnd.parent.content.size;
}

function moveCursorAfterInsertedInlineContent(editor, insertionStart, label, hasTrailingSpace = false) {
    if (!Number.isInteger(insertionStart)) {
        return;
    }

    editor.transact((transaction) => {
        const requestedPosition = insertionStart + label.length + 2 + (hasTrailingSpace ? 1 : 0);
        const position = Math.max(0, Math.min(requestedPosition, transaction.doc.content.size));
        transaction.setSelection(TextSelection.create(transaction.doc, position));
    });
}

ensureProEditorFieldRegistry();

export default function BlockNotePostEditor({ blocks, onChange, extensionBlocks = [], editorKey = 'new', isPro = pluginData.isPro, lockedFeatures = {}, onLockedFeatureClick }) {
    const [externalExtensions, setExternalExtensions] = React.useState(() => getRegisteredBlockExtensions(extensionBlocks));
    const [proRuntimeVersion, setProRuntimeVersion] = React.useState(0);
    const [proEditorFieldsVersion, setProEditorFieldsVersion] = React.useState(0);
    const canUseKind = React.useCallback((kind, extension = null) => canUseBlogBlockKind(kind, isPro, extension), [isPro, proRuntimeVersion]);
    const initialContent = React.useMemo(() => contentBlocksToBlockNote(blocks), [editorKey]);
    const editor = useCreateBlockNote({
        schema,
        initialContent,
        dictionary: resolveBlockNoteDictionary(),
        animations: false,
        tables: {
            splitCells: true,
            cellBackgroundColor: true,
            cellTextColor: true,
            headers: true,
        },
        domAttributes: {
            editor: { class: 'cci-blog-blocknote-editor-dom' },
        },
    }, [editorKey]);

    React.useEffect(() => {
        const refresh = () => setExternalExtensions(getRegisteredBlockExtensions(extensionBlocks));
        window.addEventListener(extensionEventName, refresh);

        return () => window.removeEventListener(extensionEventName, refresh);
    }, [extensionBlocks]);

    React.useEffect(() => {
        const refresh = () => setProRuntimeVersion((version) => version + 1);
        window.addEventListener('cci-blog:pro-runtime-ready', refresh);

        return () => window.removeEventListener('cci-blog:pro-runtime-ready', refresh);
    }, []);

    React.useEffect(() => {
        const refresh = () => setProEditorFieldsVersion((version) => version + 1);
        window.addEventListener(proEditorFieldsEventName, refresh);

        return () => window.removeEventListener(proEditorFieldsEventName, refresh);
    }, []);

    const emitChange = React.useCallback(() => {
        onChange(blockNoteToContentBlocks(editor.document, editor));
    }, [editor, onChange]);
    const advancedInlineLink = useAdvancedInlineLink(editor, emitChange);

    const createAdvancedBlock = React.useCallback((kind, patch = {}) => ({
        type: 'cciBlogBlock',
        props: {
            ...advancedBlockDefaults,
            ...defaultsForKind(kind),
            ...patch,
            kind,
        },
    }), []);
    const insertAdvancedBlock = React.useCallback((kind, patch = {}, gate = null) => {
        if (!canUseKind(kind, gate || patch)) {
            return;
        }

        const block = createAdvancedBlock(kind, patch);
        const reference = editor.getTextCursorPosition?.().block || editor.document[editor.document.length - 1];

        if (reference) {
            editor.insertBlocks([block], reference, 'after');
        }

        editor.focus();
        scheduleEditorChange(emitChange);
    }, [canUseKind, createAdvancedBlock, editor, emitChange]);
    const insertRegularImage = React.useCallback(() => {
        const insert = (url = '') => {
            const reference = editor.getTextCursorPosition?.().block || editor.document[editor.document.length - 1];

            if (reference) {
                editor.insertBlocks([{
                    type: 'image',
                    props: {
                        url,
                        name: '',
                        caption: '',
                    },
                }], reference, 'after');
            }

            editor.focus();
            scheduleEditorChange(emitChange);
        };
        const mediaLibrary = typeof window !== 'undefined' ? window.CCIBlogProMediaLibrary : null;

        if (mediaLibrary?.isAvailable?.()) {
            mediaLibrary.open({ onSelect: insert });
            return;
        }

        insert();
    }, [editor, emitChange]);
    const slashMenuItems = React.useCallback(async (query) => {
        const items = [
            ...getDefaultReactSlashMenuItems(editor),
            ...createCciSlashMenuItems(editor, createAdvancedBlock, externalExtensions, emitChange, {
                canUseKind,
                insertAdvancedLink: advancedInlineLink.open,
            }),
        ];

        return filterSuggestionItems(items, query);
    }, [advancedInlineLink.open, canUseKind, createAdvancedBlock, editor, emitChange, externalExtensions]);

    const blockNoteView = (
        <LockedFeatureContext.Provider value={{ features: lockedFeatures, onLockedFeatureClick }}>
            <FeatureGateContext.Provider value={canUseKind}>
                <BlockExtensionsContext.Provider value={externalExtensions}>
                    <BlockNoteView editor={editor} theme='light' onChange={emitChange} slashMenu={false}>
                        <SuggestionMenuController triggerCharacter='/' getItems={slashMenuItems} />
                    </BlockNoteView>
                </BlockExtensionsContext.Provider>
            </FeatureGateContext.Provider>
        </LockedFeatureContext.Provider>
    );

    return (
        <>
        <section className='cci-blog-blocknote-shell tw-rounded-md tw-border tw-border-solid tw-border-cci-blog-border tw-bg-white'>
            <div className='tw-grid tw-gap-4 tw-border-0 tw-border-b tw-border-solid tw-border-cci-blog-border tw-p-4 2xl:tw-grid-cols-[minmax(260px,1fr)_minmax(420px,auto)] 2xl:tw-items-start'>
                <div className='tw-min-w-0'>
                    <h2 className='tw-m-0 tw-text-base tw-font-semibold tw-leading-5 tw-text-cci-blog-text'>{__('Article editor', 'cci-blog')}</h2>
                    <p className='tw-m-0 tw-mt-1 tw-text-sm tw-leading-5 tw-text-cci-blog-muted'>
                        {__('Paste full articles, use slash commands and insert commerce blocks without rebuilding the whole post manually.', 'cci-blog')}
                    </p>
                </div>
                <div className='tw-grid tw-grid-cols-1 tw-gap-2 sm:tw-grid-cols-2 2xl:tw-justify-self-end'>
                    <InsertButton icon={ImageIcon} label={__('Image', 'cci-blog')} onClick={insertRegularImage} />
                    <InsertButton icon={Link2} label={__('Advanced link', 'cci-blog')} onClick={advancedInlineLink.open} />
                    <InsertButton icon={ImageIcon} label={__('Linked image', 'cci-blog')} onClick={() => insertAdvancedBlock('image_link')} />
                    <InsertButton icon={Columns3} label={__('Columns', 'cci-blog')} isPro disabled={!canUseKind('columns')} lockedFeature={lockedFeatures.columns} onLockedFeatureClick={onLockedFeatureClick} onClick={() => insertAdvancedBlock('columns')} />
                    <InsertButton icon={Package} label={__('Product', 'cci-blog')} isPro disabled={!canUseKind('product')} lockedFeature={lockedFeatures.product} onLockedFeatureClick={onLockedFeatureClick} onClick={() => insertAdvancedBlock('product')} />
                    <InsertButton icon={ShoppingBag} label={__('Product carousel', 'cci-blog')} isPro disabled={!canUseKind('product_carousel')} lockedFeature={lockedFeatures.product_carousel} onLockedFeatureClick={onLockedFeatureClick} onClick={() => insertAdvancedBlock('product_carousel')} />
                    <InsertButton icon={PanelsTopLeft} label={__('Hook', 'cci-blog')} isPro disabled={!canUseKind('hook')} lockedFeature={lockedFeatures.hook} onLockedFeatureClick={onLockedFeatureClick} onClick={() => insertAdvancedBlock('hook')} />
                    {externalExtensions.map((extension) => {
                        const locked = !canUseKind('module_block', extension);

                        return (
                        <InsertButton
                            key={`${extension.moduleName}:${extension.blockName}`}
                            icon={Plus}
                            label={extension.label || extension.blockName}
                            isPro={extensionRequiresPro(extension)}
                            disabled={locked}
                            lockedFeature={{
                                ...(lockedFeatures.module_block || {}),
                                featureName: extension.label || extension.blockName || lockedFeatures.module_block?.featureName,
                            }}
                            onLockedFeatureClick={onLockedFeatureClick}
                            onClick={() => insertAdvancedBlock('module_block', {
                                title: extension.label || extension.blockName,
                                moduleName: extension.moduleName,
                                blockName: extension.blockName,
                                payload: normalizePayload(extension.payload),
                            }, extension)}
                        />
                        );
                    })}
                </div>
            </div>
            <div className='cci-blog-blocknote-surface tw-p-3 sm:tw-p-4'>
                {blockNoteView}
            </div>
        </section>
        {advancedInlineLink.dialog}
        </>
    );
}

function InsertButton({ icon: Icon, label, onClick, isPro = false, disabled = false, lockedFeature = null, onLockedFeatureClick }) {
    const handleClick = (event) => {
        if (disabled) {
            event.preventDefault();
            if (typeof onLockedFeatureClick === 'function') {
                onLockedFeatureClick(lockedFeature || {
                    featureName: label,
                    description: __('This editor feature is available in CCI Blog Pro.', 'cci-blog'),
                });
            }
            return;
        }

        if (typeof onClick === 'function') {
            onClick(event);
        }
    };

    return (
        <Button
            size='sm'
            variant='outlineAccent'
            className={`tw-w-full !tw-justify-start ${disabled ? 'cci-blog-state-locked tw-cursor-not-allowed' : ''}`}
            data-pro-feature={isPro ? 'true' : undefined}
            data-pro-locked={disabled ? 'true' : undefined}
            title={disabled ? __('Requires CCI Blog Pro.', 'cci-blog') : undefined}
            onClick={handleClick}
        >
            <Icon className='tw-shrink-0' aria-hidden='true' />
            <span className='tw-min-w-0 tw-flex-1 tw-text-left'>{label}</span>
            {isPro ? <ProBadge className='tw-ml-auto tw-shrink-0 tw-px-1.5 tw-py-0.5'>{__('Pro', 'cci-blog')}</ProBadge> : null}
        </Button>
    );
}

function NestedBlockNoteEditor({ blocks, onChange, editorKey }) {
    const externalExtensions = React.useContext(BlockExtensionsContext);
    const canUseKind = React.useContext(FeatureGateContext);
    const initialContent = React.useMemo(() => contentBlocksToBlockNote(blocks), [editorKey]);
    const editor = useCreateBlockNote({
        schema,
        initialContent,
        dictionary: resolveBlockNoteDictionary(),
        animations: false,
        tables: {
            splitCells: true,
            cellBackgroundColor: true,
            cellTextColor: true,
            headers: true,
        },
        domAttributes: {
            editor: { class: 'cci-blog-blocknote-editor-dom cci-blog-blocknote-editor-dom-nested' },
        },
    }, [editorKey]);

    const emitChange = React.useCallback(() => {
        onChange(blockNoteToContentBlocks(editor.document, editor));
    }, [editor, onChange]);
    const advancedInlineLink = useAdvancedInlineLink(editor, emitChange);

    const createAdvancedBlock = React.useCallback((kind, patch = {}) => ({
        type: 'cciBlogBlock',
        props: {
            ...advancedBlockDefaults,
            ...defaultsForKind(kind),
            ...patch,
            kind,
        },
    }), []);
    const slashMenuItems = React.useCallback(async (query) => {
        const items = [
            ...getDefaultReactSlashMenuItems(editor),
            ...createCciSlashMenuItems(editor, createAdvancedBlock, externalExtensions, emitChange, {
                allowColumns: false,
                canUseKind,
                insertAdvancedLink: advancedInlineLink.open,
            }),
        ];

        return filterSuggestionItems(items, query);
    }, [advancedInlineLink.open, canUseKind, createAdvancedBlock, editor, emitChange, externalExtensions]);

    return (
        <FeatureGateContext.Provider value={canUseKind}>
            <BlockExtensionsContext.Provider value={externalExtensions}>
                <div className='cci-blog-blocknote-surface cci-blog-blocknote-surface-nested'>
                    <BlockNoteView editor={editor} theme='light' onChange={emitChange} slashMenu={false}>
                        <SuggestionMenuController triggerCharacter='/' getItems={slashMenuItems} />
                    </BlockNoteView>
                </div>
                {advancedInlineLink.dialog}
            </BlockExtensionsContext.Provider>
        </FeatureGateContext.Provider>
    );
}

function createCciSlashMenuItems(editor, createAdvancedBlock, externalExtensions, emitChange, options = {}) {
    const insertBlock = (kind, patch = {}, gate = null) => {
        if (typeof options.canUseKind === 'function' && !options.canUseKind(kind, gate || patch)) {
            return;
        }

        insertOrUpdateBlockForSlashMenu(editor, createAdvancedBlock(kind, patch));
        scheduleEditorChange(emitChange);
    };
    const canUseKind = typeof options.canUseKind === 'function' ? options.canUseKind : () => true;
    const group = __('CCI Blog', 'cci-blog');
    const items = [
        {
            title: __('Advanced link', 'cci-blog'),
            cciKind: 'link',
            subtext: __('Link with destination, browser behavior, accessibility label and visual style.', 'cci-blog'),
            aliases: ['link', 'href', 'url', 'rel', 'style', 'cci'],
            group,
            icon: <Link2 size={18} />,
            onItemClick: () => {
                insertOrUpdateBlockForSlashMenu(editor, { type: 'paragraph', content: '' });
                options.insertAdvancedLink?.();
            },
        },
        {
            title: __('Linked image', 'cci-blog'),
            cciKind: 'image_link',
            subtext: __('Image wrapped with a configurable link.', 'cci-blog'),
            aliases: ['image link', 'linked image', 'photo link', 'cci'],
            group,
            icon: <ImageIcon size={18} />,
            onItemClick: () => insertBlock('image_link'),
        },
        {
            title: __('Product', 'cci-blog'),
            cciKind: 'product',
            subtext: __('Single product reference rendered by PrestaShop data.', 'cci-blog'),
            aliases: ['product', 'commerce', 'catalog', 'cci'],
            group,
            icon: <Package size={18} />,
            onItemClick: () => insertBlock('product'),
        },
        {
            title: __('Product carousel', 'cci-blog'),
            cciKind: 'product_carousel',
            subtext: __('Selected products carousel.', 'cci-blog'),
            aliases: ['products', 'carousel', 'slider', 'commerce', 'cci'],
            group,
            icon: <ShoppingBag size={18} />,
            onItemClick: () => insertBlock('product_carousel'),
        },
        {
            title: __('Hook', 'cci-blog'),
            cciKind: 'hook',
            subtext: __('Embed content from a blog extension or storefront display hook.', 'cci-blog'),
            aliases: ['hook', 'module', 'prestashop', 'cci'],
            group,
            icon: <PanelsTopLeft size={18} />,
            onItemClick: () => insertBlock('hook'),
        },
    ];

    if (options.allowColumns !== false && canUseKind('columns')) {
        items.splice(2, 0, {
            title: __('Columns', 'cci-blog'),
            cciKind: 'columns',
            subtext: __('Responsive 12-column content layout.', 'cci-blog'),
            aliases: ['columns', 'grid', 'layout', 'responsive', 'cci'],
            group,
            icon: <Columns3 size={18} />,
            onItemClick: () => insertBlock('columns'),
        });
    }

    getRegisteredBlockExtensions(externalExtensions)
        .filter((extension) => canUseKind('module_block', extension))
        .forEach((extension) => {
        items.push({
            title: extension.label || extension.blockName,
            subtext: `${extension.moduleName}:${extension.blockName}`,
            aliases: [extension.moduleName, extension.blockName, extension.label || '', 'extension', 'cci'].filter(Boolean),
            group,
            icon: <Plus size={18} />,
            onItemClick: () => insertBlock('module_block', {
                title: extension.label || extension.blockName,
                moduleName: extension.moduleName,
                blockName: extension.blockName,
                payload: normalizePayload(extension.payload),
            }, extension),
        });
    });

    return items.filter((item) => !item.cciKind || canUseKind(item.cciKind));
}

function canUseBlogBlockKind(kind, isPro, extension = null) {
    const feature = requiredFeatureForKind(kind);
    if (!feature) {
        return true;
    }

    if (extension && extension.locked === false && !extension.requiresPro) {
        return true;
    }

    const requirements = extension && Array.isArray(extension.requiredFeatures) && extension.requiredFeatures.length
        ? extension.requiredFeatures
        : [feature];
    return Boolean(isPro || pluginData.isPro)
        && isCciBlogProBlockKindEnabled(kind)
        && requirements.every((requirement) => isCciBlogProFeatureEnabled(requirement));
}

function requiredFeatureForKind(kind) {
    return {
        columns: 'advanced_layout_blocks',
        product: 'commerce_blocks',
        product_carousel: 'product_carousel',
        hook: 'hook_blocks',
        module_block: 'extension_blocks',
    }[kind] || '';
}

function extensionRequiresPro(extension = {}) {
    return extension.locked !== false || Boolean(extension.requiresPro);
}

function scheduleEditorChange(callback) {
    if (typeof window.queueMicrotask === 'function') {
        window.queueMicrotask(callback);
    } else {
        window.setTimeout(callback, 0);
    }
}

function CciBlogBlockView({ block, editor }) {
    const props = { ...advancedBlockDefaults, ...(block.props || {}) };
    const kind = props.kind || 'link';
    const [isCollapsed, setIsCollapsed] = React.useState(false);
    const externalExtensions = React.useContext(BlockExtensionsContext);
    const canUseKind = React.useContext(FeatureGateContext);
    const lockedFeatureContext = React.useContext(LockedFeatureContext);
    const update = (patch) => editor.updateBlock(block.id, { props: { ...props, ...patch } });
    const title = blockTitle(kind, props);
    const ToggleIcon = isCollapsed ? ChevronRight : ChevronDown;
    const isLockedKind = Boolean(requiredFeatureForKind(kind) && !canUseKind(kind));

    return (
        <div className={`cci-blog-bn-advanced-block${isCollapsed ? ' cci-blog-state-collapsed' : ''}`} contentEditable={false}>
            <div className='cci-blog-bn-advanced-header'>
                <button
                    type='button'
                    className='cci-blog-bn-advanced-toggle'
                    aria-expanded={!isCollapsed}
                    onClick={() => setIsCollapsed((current) => !current)}
                >
                    <ToggleIcon aria-hidden='true' />
                    <span className='cci-blog-bn-advanced-toggle-copy'>
                        <strong>{title}</strong>
                        <span>{advancedBlockSummary(kind, props)}</span>
                    </span>
                </button>
                {!isCollapsed && !isLockedKind && (
                    <Select
                        value={kind}
                        onValueChange={(value) => update({ ...defaultsForKind(value), kind: value })}
                        options={advancedKindOptions(canUseKind).filter((option) => option.value !== 'link' || kind === 'link')}
                        ariaLabel={__('Block type', 'cci-blog')}
                        className='tw-w-44'
                    />
                )}
            </div>
            {!isCollapsed && (
                <div className='cci-blog-bn-advanced-body'>
                    {isLockedKind
                        ? <LockedProBlockFields kind={kind} />
                        : <AdvancedBlockFields
                            kind={kind}
                            props={props}
                            update={update}
                            externalExtensions={externalExtensions}
                            lockedFeatures={lockedFeatureContext.features}
                            onLockedFeatureClick={lockedFeatureContext.onLockedFeatureClick}
                        />}
                </div>
            )}
        </div>
    );
}

function LockedProBlockFields({ kind }) {
    const feature = requiredFeatureForKind(kind);
    const label = blockTitle(kind, {});

    return (
        <div className='tw-rounded-md tw-border tw-border-solid tw-border-amber-300 tw-bg-amber-50 tw-p-3 tw-text-sm tw-leading-5 tw-text-amber-950'>
            <div className='tw-flex tw-flex-wrap tw-items-center tw-gap-2'>
                <strong>{label}</strong>
                <ProBadge>{__('Pro', 'cci-blog')}</ProBadge>
            </div>
            <p className='tw-m-0 tw-mt-2 tw-text-amber-900'>
                {__('This block is saved in the article, but its editor and storefront renderer are available only from the active CCI Blog Pro package.', 'cci-blog')}
            </p>
            {feature ? (
                <p className='tw-m-0 tw-mt-1 tw-text-xs tw-font-semibold tw-uppercase tw-text-amber-800'>
                    {feature}
                </p>
            ) : null}
        </div>
    );
}

function AdvancedBlockFields({ kind, props, update, externalExtensions = [], lockedFeatures = {}, onLockedFeatureClick }) {
    if (kind === 'link') {
        return <LinkSettings value={props} update={update} showLabel />;
    }

    if (kind === 'image_link') {
        return (
            <div className='tw-grid tw-gap-3'>
                <div className='tw-grid tw-gap-3 md:tw-grid-cols-2'>
                    <Field className='md:tw-col-span-2' label={__('Image URL', 'cci-blog')}>
                        <LocalMediaUrlInput
                            value={props.imageUrl || ''}
                            onChange={(imageUrl) => update({ imageUrl })}
                            onLockedClick={() => onLockedFeatureClick?.(lockedFeatures.localMediaLibrary)}
                        />
                    </Field>
                    <Field label={__('Alt text', 'cci-blog')}>
                        <Input value={props.alt || ''} onChange={(event) => update({ alt: event.target.value })} />
                    </Field>
                    <Field label={__('Caption', 'cci-blog')}>
                        <Input value={props.caption || ''} onChange={(event) => update({ caption: event.target.value })} />
                    </Field>
                    <Field label={__('Image width in pixels', 'cci-blog')} note={__('Leave empty to use the image natural width.', 'cci-blog')}>
                        <Input
                            type='number'
                            min='64'
                            max='2400'
                            value={props.imageWidth || ''}
                            onChange={(event) => update({ imageWidth: event.target.value })}
                        />
                    </Field>
                    <Field label={__('Image alignment', 'cci-blog')}>
                        <Select
                            value={normalizeImageAlignment(props.imageAlignment)}
                            onValueChange={(imageAlignment) => update({ imageAlignment })}
                            options={[
                                { value: 'left', label: __('Left', 'cci-blog') },
                                { value: 'center', label: __('Center', 'cci-blog') },
                                { value: 'right', label: __('Right', 'cci-blog') },
                            ]}
                        />
                    </Field>
                    <Field label={__('Image frame', 'cci-blog')}>
                        <Select
                            value={props.imageFramed === '1' ? '1' : '0'}
                            onValueChange={(imageFramed) => update({ imageFramed })}
                            options={yesNoOptions()}
                        />
                    </Field>
                    <Field label={__('Link the caption', 'cci-blog')}>
                        <Select
                            value={props.captionLinked === '1' ? '1' : '0'}
                            onValueChange={(captionLinked) => update({ captionLinked })}
                            options={yesNoOptions()}
                        />
                    </Field>
                </div>
                <div className='tw-rounded-md tw-border tw-border-solid tw-border-cci-blog-border tw-bg-cci-blog-surfaceSoft tw-p-3'>
                    <LinkSettings value={props} update={update} />
                </div>
            </div>
        );
    }

    const registeredRenderer = getRegisteredProFieldRenderer(kind);
    if (registeredRenderer) {
        return renderRegisteredProField(registeredRenderer, { kind, props, update, externalExtensions });
    }

    return <LockedProBlockFields kind={kind} props={props} />;
}

function getRegisteredProFieldRenderer(kind) {
    const registry = ensureProEditorFieldRegistry();
    if (!registry || typeof registry.get !== 'function') {
        return null;
    }

    return registry.get(kind);
}

function renderRegisteredProField(renderer, args) {
    try {
        const result = renderer({
            ...args,
            React,
            components: {
                Button,
                ConfirmDeleteModal,
                CssLengthInput,
                Field,
                Input,
                // Keep component identity stable while a parent block updates.
                // Extensions and feature gates are inherited from the outer editor.
                NestedBlockEditor: NestedBlockNoteEditor,
                Select,
                Textarea,
            },
            icons: {
                ChevronDown,
                ChevronRight,
                GripVertical,
                Package,
                Plus,
                Search,
                Trash2,
            },
            helpers: {
                __,
                apiFetch,
                columnGapUnits,
                getContentHookFieldNote,
                getContentHookHelpText,
                getContentHookOptions,
                normalizePayload,
                normalizeCssLengthValue,
                normalizeColumnGap,
                parseProductIds,
                yesNoOptions,
            },
        });

        return result || <LockedProBlockFields kind={args.kind} props={args.props} />;
    } catch (error) {
        console.error('CCI Blog Pro editor field failed.', error);
        return <LockedProBlockFields kind={args.kind} props={args.props} />;
    }
}

function LinkSettings({ value, update, showLabel = false }) {
    return (
        <div className='tw-grid tw-gap-3'>
            {showLabel && (
                <Field label={__('Label', 'cci-blog')}>
                    <Input value={value.label || ''} onChange={(event) => update({ label: event.target.value })} />
                </Field>
            )}
            <div className='tw-grid tw-gap-3 md:tw-grid-cols-2'>
                <Field label={__('Href', 'cci-blog')}>
                    <Input value={value.href || ''} onChange={(event) => update({ href: event.target.value })} placeholder='https://example.com' />
                </Field>
                <Field label={__('Title', 'cci-blog')}>
                    <Input value={value.title || ''} onChange={(event) => update({ title: event.target.value })} />
                </Field>
                <Field label={__('Target', 'cci-blog')}>
                    <Select
                        value={value.target || '_self'}
                        onValueChange={(target) => update({ target: target === '_self' ? '' : target })}
                        options={[
                            { value: '_self', label: __('Same tab', 'cci-blog') },
                            { value: '_blank', label: __('New tab', 'cci-blog') },
                        ]}
                    />
                </Field>
                <Field label={__('Rel', 'cci-blog')}>
                    <Input value={value.rel || ''} onChange={(event) => update({ rel: event.target.value })} placeholder='nofollow sponsored' />
                </Field>
                <Field label={__('Link style', 'cci-blog')}>
                    <Select
                        value={normalizeLinkVariant(value.variant) || 'default'}
                        onValueChange={(variant) => update({ variant: variant === 'default' ? '' : variant })}
                        options={linkVariantOptions}
                    />
                </Field>
            </div>
            <Field label={__('ARIA label', 'cci-blog')}>
                <Input value={value.ariaLabel || ''} onChange={(event) => update({ ariaLabel: event.target.value })} />
            </Field>
        </div>
    );
}

function AdvancedLinkDialog({
    open,
    initialValues,
    title = __('Insert advanced link', 'cci-blog'),
    submitLabel = __('Insert link', 'cci-blog'),
    onCancel,
    onSubmit,
}) {
    const [values, setValues] = React.useState(initialValues);

    React.useEffect(() => {
        if (open) {
            setValues(initialValues);
        }
    }, [initialValues, open]);

    React.useEffect(() => {
        if (!open) {
            return undefined;
        }

        const handleKeyDown = (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                onCancel?.();
            }
        };
        window.addEventListener('keydown', handleKeyDown);

        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [onCancel, open]);

    if (!open) {
        return null;
    }

    const update = (patch) => setValues((current) => ({ ...current, ...patch }));
    const canSubmit = Boolean(values.label?.trim() && values.href?.trim());
    const modal = (
        <div
            className='tw-fixed tw-inset-0 tw-z-[100000] tw-grid tw-place-items-center tw-bg-slate-950/35 tw-p-4'
            role='presentation'
            onMouseDown={(event) => {
                if (event.target === event.currentTarget) {
                    onCancel?.();
                }
            }}
        >
            <form
                className='tw-w-full tw-max-w-2xl tw-overflow-hidden tw-rounded-lg tw-border tw-border-solid tw-border-cci-blog-border tw-bg-white'
                role='dialog'
                aria-modal='true'
                aria-labelledby='cci-blog-advanced-link-dialog-title'
                onSubmit={(event) => {
                    event.preventDefault();
                    if (canSubmit) {
                        onSubmit?.(values);
                    }
                }}
            >
                <div className='tw-border-0 tw-border-b tw-border-solid tw-border-cci-blog-border tw-px-5 tw-py-4'>
                    <h2 id='cci-blog-advanced-link-dialog-title' className='tw-m-0 tw-text-lg tw-font-semibold tw-leading-tight tw-text-cci-blog-text'>
                        {title}
                    </h2>
                    <p className='tw-m-0 tw-mt-1 tw-text-sm tw-leading-5 tw-text-cci-blog-muted'>
                        {__('The link will be inserted inline at the current cursor position.', 'cci-blog')}
                    </p>
                </div>
                <div className='tw-p-5'>
                    <LinkSettings value={values} update={update} showLabel />
                </div>
                <div className='tw-flex tw-justify-end tw-gap-2 tw-border-0 tw-border-t tw-border-solid tw-border-cci-blog-border tw-px-5 tw-py-4'>
                    <Button variant='secondary' type='button' onClick={onCancel}>{__('Cancel', 'cci-blog')}</Button>
                    <Button type='submit' disabled={!canSubmit}>
                        <Link2 aria-hidden='true' />
                        {submitLabel}
                    </Button>
                </div>
            </form>
        </div>
    );

    if (typeof document === 'undefined' || !document.body) {
        return modal;
    }

    return createPortal(modal, document.body);
}

function blockNoteToContentBlocks(documentBlocks, editor) {
    return (Array.isArray(documentBlocks) ? documentBlocks : [])
        .map((block) => convertBlockNoteBlock(block, editor))
        .flat()
        .filter(Boolean);
}

function convertBlockNoteBlock(block, editor) {
    if (!block || typeof block !== 'object') {
        return null;
    }

    if (block.type === 'heading') {
        return {
            type: 'heading',
            level: clampNumber(block.props?.level, 2, 4, 2),
            text: inlineContentToText(block.content),
        };
    }

    if (block.type === 'image') {
        return {
            type: 'image',
            src: block.props?.url || '',
            alt: block.props?.name || '',
            caption: block.props?.caption || '',
            width: normalizeImageWidth(block.props?.previewWidth),
            alignment: normalizeImageAlignment(block.props?.textAlignment),
            link: {},
        };
    }

    if (block.type === 'video') {
        return {
            type: 'video',
            src: block.props?.url || '',
            title: block.props?.name || '',
            caption: block.props?.caption || '',
        };
    }

    if (block.type === 'cciBlogBlock') {
        return advancedBlockToContent(block.props || {});
    }

    const html = serializeBlock(editor, block);
    const content = inlineContentToText(block.content) || stripHtml(html);

    if (!content && !html) {
        return null;
    }

    return {
        type: 'paragraph',
        content,
        html: html || escapeHtml(content),
    };
}

function advancedBlockToContent(props) {
    const kind = props.kind || 'link';

    if (kind === 'link') {
        return {
            type: 'link',
            label: props.label || props.href || '',
            href: props.href || '',
            title: props.title || '',
            target: props.target || '',
            rel: props.rel || '',
            variant: normalizeLinkVariant(props.variant || props.linkVariant || '') || inferLinkVariantFromClass(props.cssClass),
            id: props.htmlId || '',
            aria_label: props.ariaLabel || '',
        };
    }

    if (kind === 'image_link') {
        const link = {
            href: props.href || '',
            title: props.title || '',
            target: props.target || '',
            rel: props.rel || '',
            variant: normalizeLinkVariant(props.variant || props.linkVariant || '') || inferLinkVariantFromClass(props.cssClass),
            id: props.htmlId || '',
            aria_label: props.ariaLabel || '',
        };

        return {
            type: 'image',
            src: props.imageUrl || '',
            alt: props.alt || '',
            caption: props.caption || '',
            width: normalizeImageWidth(props.imageWidth),
            alignment: normalizeImageAlignment(props.imageAlignment),
            framed: props.imageFramed === '1',
            link,
            caption_link: props.captionLinked === '1' ? link : {},
        };
    }

    if (kind === 'columns') {
        return {
            type: 'columns',
            gap: normalizeColumnGap(props.gap || '1rem'),
            columns: parseColumns(props.columnsJson),
        };
    }

    if (kind === 'product') {
        return {
            type: 'product',
            id_product: Number(props.productId || 0),
            label: props.label || __('Mentioned product', 'cci-blog'),
        };
    }

    if (kind === 'product_carousel') {
        return {
            type: 'product_carousel',
            title: props.title || __('Recommended products', 'cci-blog'),
            ids: props.productIds || '',
            options: carouselPropsToContentOptions(props),
        };
    }

    if (kind === 'hook') {
        return {
            type: 'hook',
            hook: props.hook || defaultContentHookName,
        };
    }

    return {
        type: 'module_block',
        module: props.moduleName || '',
        block: props.blockName || '',
        label: props.title || '',
        payload: parseJsonObject(props.payload),
    };
}

function contentBlocksToBlockNote(contentBlocks) {
    const output = [];
    const blocks = Array.isArray(contentBlocks) ? contentBlocks : [];

    for (let index = 0; index < blocks.length; index += 1) {
        const block = blocks[index];
        if (isLegacyInlineLinkBlock(block)) {
            const inlineLink = legacyLinkBlockToInlineContent(block);
            const previous = output[output.length - 1];
            const nextBlocks = contentBlockToBlockNote(blocks[index + 1]);
            const nextParagraph = nextBlocks.length === 1 && nextBlocks[0]?.type === 'paragraph'
                ? nextBlocks[0]
                : null;

            if (previous?.type === 'paragraph') {
                previous.content = appendInlineContent(previous.content, [inlineLink]);
                if (nextParagraph) {
                    previous.content = appendInlineContent(previous.content, nextParagraph.content);
                    index += 1;
                }
                continue;
            }

            const paragraph = { type: 'paragraph', content: [inlineLink] };
            if (nextParagraph) {
                paragraph.content = appendInlineContent(paragraph.content, nextParagraph.content);
                index += 1;
            }
            output.push(paragraph);
            continue;
        }

        output.push(...contentBlockToBlockNote(block));
    }

    return output.length ? output : [{ type: 'paragraph', content: '' }];
}

function isLegacyInlineLinkBlock(block) {
    return block?.type === 'link'
        && normalizeLinkVariant(block.variant || block.link_variant || '') !== 'button';
}

function legacyLinkBlockToInlineContent(block) {
    return {
        type: 'cciBlogLink',
        props: normalizeAdvancedInlineLinkProps({
            href: block.href || '',
            title: block.title || '',
            target: block.target || '',
            rel: block.rel || '',
            variant: block.variant || block.link_variant || '',
            htmlId: block.id || '',
            ariaLabel: block.aria_label || '',
        }),
        content: block.label || block.href || '',
    };
}

function appendInlineContent(currentContent, addedContent) {
    const current = normalizeInlineContentArray(currentContent);
    const added = normalizeInlineContentArray(addedContent);
    const currentText = inlineContentToText(current);
    const addedText = inlineContentToText(added);

    if (currentText && addedText && needsInlineSeparator(currentText, addedText)) {
        current.push({ type: 'text', text: ' ', styles: {} });
    }

    return [...current, ...added];
}

function normalizeInlineContentArray(content) {
    if (Array.isArray(content)) {
        return [...content];
    }
    if (typeof content === 'string' && content !== '') {
        return [{ type: 'text', text: content, styles: {} }];
    }

    return [];
}

function needsInlineSeparator(left, right) {
    const last = left.slice(-1);
    const first = right.charAt(0);

    return !/\s|[(\[{\-/]/.test(last) && !/\s|[.,;:!?)}\]\/\-]/.test(first);
}

function contentBlockToBlockNote(block) {
    if (!block || typeof block !== 'object') {
        return [];
    }

    if (block.type === 'heading') {
        return [{
            type: 'heading',
            props: { level: clampNumber(block.level, 2, 4, 2) },
            content: block.text || '',
        }];
    }

    if (block.type === 'paragraph') {
        return htmlToBlockNoteBlocks(block.html || plainTextToHtml(block.content || ''));
    }

    if (block.type === 'image' && !hasLink(block.link) && !block.framed && !hasLink(block.caption_link)) {
        return [{
            type: 'image',
            props: {
                url: block.src || '',
                name: block.alt || '',
                caption: block.caption || '',
                previewWidth: normalizeImageWidth(block.width) || undefined,
                textAlignment: normalizeImageAlignment(block.alignment),
            },
        }];
    }

    if (block.type === 'video') {
        return [{
            type: 'video',
            props: {
                url: block.src || '',
                name: block.title || '',
                caption: block.caption || '',
            },
        }];
    }

    return [{
        type: 'cciBlogBlock',
        props: contentBlockToAdvancedProps(block),
    }];
}

function contentBlockToAdvancedProps(block) {
    if (block.type === 'link') {
        return {
            ...advancedBlockDefaults,
            kind: 'link',
            label: block.label || '',
            href: block.href || '',
            title: block.title || '',
            target: block.target || '',
            rel: block.rel || '',
            variant: normalizeLinkVariant(block.variant || block.link_variant || '') || inferLinkVariantFromClass(block.class),
            htmlId: block.id || '',
            ariaLabel: block.aria_label || '',
        };
    }

    if (block.type === 'image') {
        const link = block.link || {};

        return {
            ...advancedBlockDefaults,
            kind: 'image_link',
            imageUrl: block.src || '',
            alt: block.alt || '',
            caption: block.caption || '',
            imageWidth: normalizeImageWidth(block.width) ? String(normalizeImageWidth(block.width)) : '',
            imageAlignment: normalizeImageAlignment(block.alignment),
            imageFramed: block.framed ? '1' : '0',
            captionLinked: hasLink(block.caption_link) ? '1' : '0',
            href: link.href || '',
            title: link.title || '',
            target: link.target || '',
            rel: link.rel || '',
            variant: normalizeLinkVariant(link.variant || link.link_variant || '') || inferLinkVariantFromClass(link.class),
            htmlId: link.id || '',
            ariaLabel: link.aria_label || '',
        };
    }

    if (block.type === 'columns') {
        return {
            ...advancedBlockDefaults,
            kind: 'columns',
            gap: normalizeColumnGap(block.gap || '1rem'),
            columnsJson: JSON.stringify(Array.isArray(block.columns) && block.columns.length ? block.columns : [createColumn(), createColumn()]),
        };
    }

    if (block.type === 'product') {
        return {
            ...advancedBlockDefaults,
            kind: 'product',
            productId: String(block.id_product || ''),
            label: block.label || '',
        };
    }

    if (block.type === 'product_carousel') {
        const options = block.options && typeof block.options === 'object' ? block.options : {};

        return {
            ...advancedBlockDefaults,
            kind: 'product_carousel',
            title: block.title || '',
            productIds: block.ids || '',
            carouselItemsDesktop: String(options.items_desktop ?? block.items_desktop ?? advancedBlockDefaults.carouselItemsDesktop),
            carouselItemsTablet: String(options.items_tablet ?? block.items_tablet ?? advancedBlockDefaults.carouselItemsTablet),
            carouselItemsMobile: String(options.items_mobile ?? block.items_mobile ?? advancedBlockDefaults.carouselItemsMobile),
            carouselMargin: String(options.margin ?? block.margin ?? advancedBlockDefaults.carouselMargin),
            carouselStagePadding: String(options.stage_padding ?? block.stage_padding ?? advancedBlockDefaults.carouselStagePadding),
            carouselLoop: boolOptionValue(options.loop ?? block.loop, advancedBlockDefaults.carouselLoop),
            carouselNav: boolOptionValue(options.nav ?? block.nav, advancedBlockDefaults.carouselNav),
            carouselDots: boolOptionValue(options.dots ?? block.dots, advancedBlockDefaults.carouselDots),
            carouselAutoplay: boolOptionValue(options.autoplay ?? block.autoplay, advancedBlockDefaults.carouselAutoplay),
            carouselAutoplayTimeout: String(options.autoplay_timeout ?? block.autoplay_timeout ?? advancedBlockDefaults.carouselAutoplayTimeout),
            carouselAutoplayHoverPause: boolOptionValue(options.autoplay_hover_pause ?? block.autoplay_hover_pause, advancedBlockDefaults.carouselAutoplayHoverPause),
        };
    }

    if (block.type === 'hook') {
        return {
            ...advancedBlockDefaults,
            kind: 'hook',
            hook: block.hook || defaultContentHookName,
        };
    }

    return {
        ...advancedBlockDefaults,
        kind: 'module_block',
        moduleName: block.module || '',
        blockName: block.block || '',
        title: block.label || '',
        payload: normalizePayload(block.payload || {}),
    };
}

function htmlToBlockNoteBlocks(html) {
    if (typeof document === 'undefined') {
        return [{ type: 'paragraph', content: stripHtml(html) }];
    }

    const template = document.createElement('template');
    template.innerHTML = String(html || '').trim();
    const blocks = Array.from(template.content.childNodes)
        .flatMap((node) => domNodeToBlockNoteBlocks(node))
        .filter(Boolean);

    return blocks.length ? blocks : [{ type: 'paragraph', content: stripHtml(html) }];
}

function domNodeToBlockNoteBlocks(node) {
    if (node.nodeType === Node.TEXT_NODE) {
        const text = normalizeWhitespace(node.textContent || '');
        return text ? [{ type: 'paragraph', content: text }] : [];
    }
    if (node.nodeType !== Node.ELEMENT_NODE) {
        return [];
    }

    const element = node;
    const tagName = element.tagName.toLowerCase();
    if (/^h[1-6]$/.test(tagName)) {
        return [{
            type: 'heading',
            props: { level: clampNumber(Number(tagName.slice(1)), 1, 6, 2) },
            content: domNodesToInlineContent(element.childNodes),
        }];
    }
    if (tagName === 'ul' || tagName === 'ol') {
        return Array.from(element.children)
            .filter((child) => child.tagName?.toLowerCase() === 'li')
            .map((child) => ({
                type: tagName === 'ul' ? 'bulletListItem' : 'numberedListItem',
                content: domNodesToInlineContent(child.childNodes),
            }));
    }
    if (tagName === 'blockquote') {
        return [{
            type: 'quote',
            content: domNodesToInlineContent(element.childNodes),
        }];
    }
    if (tagName === 'img') {
        return [{
            type: 'image',
            props: {
                url: element.getAttribute('src') || '',
                name: element.getAttribute('alt') || '',
                caption: '',
            },
        }];
    }

    return [{
        type: 'paragraph',
        content: domNodesToInlineContent(element.childNodes),
    }];
}

function domNodesToInlineContent(nodes, inheritedStyles = {}) {
    const content = [];

    Array.from(nodes || []).forEach((node) => {
        if (node.nodeType === Node.TEXT_NODE) {
            const text = node.textContent || '';
            if (text) {
                content.push({ type: 'text', text, styles: inheritedStyles });
            }
            return;
        }
        if (node.nodeType !== Node.ELEMENT_NODE) {
            return;
        }

        const element = node;
        const tagName = element.tagName.toLowerCase();
        if (tagName === 'br') {
            content.push({ type: 'text', text: '\n', styles: inheritedStyles });
            return;
        }

        const nextStyles = {
            ...inheritedStyles,
            ...(tagName === 'strong' || tagName === 'b' ? { bold: true } : {}),
            ...(tagName === 'em' || tagName === 'i' ? { italic: true } : {}),
            ...(tagName === 'u' ? { underline: true } : {}),
            ...(tagName === 's' || tagName === 'strike' ? { strike: true } : {}),
            ...(tagName === 'code' ? { code: true } : {}),
        };
        const children = domNodesToInlineContent(element.childNodes, nextStyles);

        if (tagName === 'a') {
            if (element.hasAttribute('data-cci-blog-advanced-link')) {
                content.push({
                    type: 'cciBlogLink',
                    props: parseCciBlogInlineLink(element),
                    content: children,
                });
                return;
            }
            content.push({
                type: 'link',
                href: element.getAttribute('href') || '',
                content: children,
            });
            return;
        }

        content.push(...children);
    });

    return content.length ? content : '';
}

function serializeBlock(editor, block) {
    try {
        return String(editor.blocksToHTMLLossy([block]) || '').trim();
    } catch (error) {
        return '';
    }
}

function inlineContentToText(content) {
    if (typeof content === 'string') {
        return content.trim();
    }
    if (!Array.isArray(content)) {
        return '';
    }

    return content.map((item) => {
        if (typeof item === 'string') {
            return item;
        }
        if (item.type === 'text') {
            return item.text || '';
        }
        if (item.type === 'link') {
            return inlineContentToText(item.content);
        }
        if (item.type === 'cciBlogLink') {
            return inlineContentToText(item.content);
        }

        return '';
    }).join('').trim();
}

function getRegisteredBlockExtensions(seedExtensions = []) {
    const globalExtensions = window.CCIBlogBlocks?.all?.() || window.cciBlogBlockExtensions || [];
    const extensions = [...(Array.isArray(seedExtensions) ? seedExtensions : []), ...(Array.isArray(globalExtensions) ? globalExtensions : [])];
    const seen = new Set();

    return extensions
        .map(normalizeExtension)
        .filter((extension) => {
            if (!extension.moduleName || !extension.blockName) {
                return false;
            }
            const key = `${extension.moduleName}:${extension.blockName}`;
            if (seen.has(key)) {
                return false;
            }
            seen.add(key);

            return true;
        });
}

function normalizeExtension(extension = {}) {
    return {
        moduleName: extension.moduleName || extension.module || '',
        blockName: extension.blockName || extension.block || '',
        label: extension.label || extension.title || '',
        payload: extension.payload || {},
        locked: extension.locked,
        requiresPro: Boolean(extension.requiresPro),
        requiredFeatures: Array.isArray(extension.requiredFeatures) ? extension.requiredFeatures : [],
    };
}

function normalizePayload(payload) {
    if (typeof payload === 'string') {
        return payload || '{}';
    }

    return JSON.stringify(payload || {}, null, 2);
}

function parseJsonObject(value) {
    if (value && typeof value === 'object' && !Array.isArray(value)) {
        return value;
    }

    try {
        const parsed = JSON.parse(String(value || '{}'));

        return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
    } catch (error) {
        return {};
    }
}

function parseColumns(value) {
    try {
        const parsed = JSON.parse(String(value || ''));
        if (Array.isArray(parsed) && parsed.length) {
            return parsed.slice(0, 12).map(normalizeColumn);
        }
    } catch (error) {
        return [createColumn(), createColumn()];
    }

    return [createColumn(), createColumn()];
}

function createColumn() {
    return {
        span_desktop: 6,
        span_tablet: 6,
        span_mobile: 12,
        content: '',
        html: '',
        blocks: [],
        id: '',
    };
}

function normalizeColumn(column) {
    const columnData = column && typeof column === 'object' ? column : { content: String(column || '') };

    return {
        ...createColumn(),
        ...columnData,
        blocks: columnContentBlocks(columnData),
    };
}

function columnContentBlocks(column) {
    if (Array.isArray(column?.blocks)) {
        return column.blocks.filter((block) => block && typeof block === 'object');
    }

    if (String(column?.html || '').trim()) {
        return [{
            type: 'paragraph',
            html: String(column.html || ''),
            content: stripHtml(column.html || ''),
        }];
    }

    if (String(column?.content || '').trim()) {
        return [{
            type: 'paragraph',
            content: String(column.content || ''),
            html: plainTextToHtml(column.content || ''),
        }];
    }

    return [];
}

function contentBlocksToPlainText(blocks) {
    return (Array.isArray(blocks) ? blocks : [])
        .map((block) => {
            if (!block || typeof block !== 'object') {
                return '';
            }

            if (block.type === 'heading') {
                return block.text || '';
            }

            if (block.type === 'paragraph') {
                return block.content || stripHtml(block.html || '');
            }

            if (block.type === 'link') {
                return block.label || block.href || '';
            }

            if (block.type === 'image') {
                return block.caption || block.alt || '';
            }

            if (block.type === 'product') {
                return block.label || '';
            }

            if (block.type === 'product_carousel') {
                return block.title || '';
            }

            return block.label || block.title || '';
        })
        .filter(Boolean)
        .join(' ')
        .trim();
}

function parseProductIds(value) {
    const values = Array.isArray(value) ? value : String(value || '').split(/[\s,]+/);
    const seen = new Set();
    const ids = [];

    values.forEach((item) => {
        const id = Number.parseInt(item, 10);
        if (id > 0 && !seen.has(id)) {
            seen.add(id);
            ids.push(id);
        }
    });

    return ids;
}

function normalizeColumnGap(value) {
    return normalizeCssLengthValue(value, {
        fallback: '1rem',
        units: columnGapUnits,
        min: 0,
        max: 1000,
    });
}

function carouselPropsToContentOptions(props) {
    return {
        items_desktop: clampInteger(props.carouselItemsDesktop, 1, 8, 3),
        items_tablet: clampInteger(props.carouselItemsTablet, 1, 6, 2),
        items_mobile: clampInteger(props.carouselItemsMobile, 1, 3, 1),
        margin: clampInteger(props.carouselMargin, 0, 80, 16),
        stage_padding: clampInteger(props.carouselStagePadding, 0, 160, 0),
        loop: boolOptionValue(props.carouselLoop, '0') === '1',
        nav: boolOptionValue(props.carouselNav, '1') === '1',
        dots: boolOptionValue(props.carouselDots, '1') === '1',
        autoplay: boolOptionValue(props.carouselAutoplay, '0') === '1',
        autoplay_timeout: clampInteger(props.carouselAutoplayTimeout, 1000, 20000, 5000),
        autoplay_hover_pause: boolOptionValue(props.carouselAutoplayHoverPause, '1') === '1',
    };
}

function clampInteger(value, min, max, fallback) {
    return Math.round(clampNumber(value, min, max, fallback));
}

function boolOptionValue(value, fallback = '0') {
    if (value === undefined || value === null || value === '') {
        return fallback;
    }

    return value === true || value === 1 || String(value).toLowerCase() === 'true' || String(value) === '1' ? '1' : '0';
}

function yesNoOptions() {
    return [
        { value: '1', label: __('Enabled', 'cci-blog') },
        { value: '0', label: __('Disabled', 'cci-blog') },
    ];
}

function advancedKindOptions(canUseKind = null) {
    const options = [
        { value: 'link', label: __('Advanced link', 'cci-blog') },
        { value: 'image_link', label: __('Linked image', 'cci-blog') },
        { value: 'columns', label: __('Columns', 'cci-blog') },
        { value: 'product', label: __('Product', 'cci-blog') },
        { value: 'product_carousel', label: __('Product carousel', 'cci-blog') },
        { value: 'hook', label: __('Hook', 'cci-blog') },
        { value: 'module_block', label: __('Extension block', 'cci-blog') },
    ];

    if (typeof canUseKind !== 'function') {
        return options;
    }

    return options.filter((option) => !requiredFeatureForKind(option.value) || canUseKind(option.value));
}

function defaultsForKind(kind) {
    if (kind === 'columns') {
        return { columnsJson: JSON.stringify([createColumn(), createColumn()]) };
    }
    if (kind === 'product') {
        return { label: __('Mentioned product', 'cci-blog') };
    }
    if (kind === 'product_carousel') {
        return {
            title: __('Recommended products', 'cci-blog'),
            carouselItemsDesktop: advancedBlockDefaults.carouselItemsDesktop,
            carouselItemsTablet: advancedBlockDefaults.carouselItemsTablet,
            carouselItemsMobile: advancedBlockDefaults.carouselItemsMobile,
            carouselMargin: advancedBlockDefaults.carouselMargin,
            carouselStagePadding: advancedBlockDefaults.carouselStagePadding,
            carouselLoop: advancedBlockDefaults.carouselLoop,
            carouselNav: advancedBlockDefaults.carouselNav,
            carouselDots: advancedBlockDefaults.carouselDots,
            carouselAutoplay: advancedBlockDefaults.carouselAutoplay,
            carouselAutoplayTimeout: advancedBlockDefaults.carouselAutoplayTimeout,
            carouselAutoplayHoverPause: advancedBlockDefaults.carouselAutoplayHoverPause,
        };
    }
    if (kind === 'module_block') {
        return { payload: '{}' };
    }

    return {};
}

function blockTitle(kind, props) {
    if (kind === 'module_block') {
        return props.title || props.blockName || __('Extension block', 'cci-blog');
    }

    return advancedKindOptions().find((option) => option.value === kind)?.label || __('Content block', 'cci-blog');
}

function blockDescription(kind, props) {
    if (kind === 'link') {
        return props.href || __('Link with rel, style variant, ID and title attributes.', 'cci-blog');
    }
    if (kind === 'image_link') {
        return props.imageUrl || __('Image wrapped with a configurable link.', 'cci-blog');
    }
    if (kind === 'columns') {
        return __('Responsive 12-column layout saved as content blocks.', 'cci-blog');
    }
    if (kind === 'product') {
        return props.productId ? `${__('Product ID', 'cci-blog')}: ${props.productId}` : __('Single product reference.', 'cci-blog');
    }
    if (kind === 'product_carousel') {
        const productCount = parseProductIds(props.productIds).length;
        if (productCount > 0) {
            return `${productCount} ${__('products', 'cci-blog')} · ${props.carouselItemsDesktop || '3'}/${props.carouselItemsTablet || '2'}/${props.carouselItemsMobile || '1'} ${__('items', 'cci-blog')}`;
        }

        return __('Selected products carousel.', 'cci-blog');
    }
    if (kind === 'hook') {
        return props.hook || defaultContentHookName;
    }

    return props.moduleName && props.blockName ? `${props.moduleName}:${props.blockName}` : __('Rendered by another CCI module.', 'cci-blog');
}

function advancedBlockSummary(kind, props) {
    if (kind === 'columns') {
        const columns = parseColumns(props.columnsJson);
        const filledColumns = columns.filter((column) => contentBlocksToPlainText(columnContentBlocks(column))).length;

        return `${columns.length} ${__('columns', 'cci-blog')} · ${filledColumns} ${__('with content', 'cci-blog')}`;
    }

    const summary = blockDescription(kind, props);

    return summary.length > 110 ? `${summary.slice(0, 107)}...` : summary;
}

function hasLink(link) {
    return Boolean(link && typeof link === 'object' && String(link.href || '').trim());
}

function plainTextToHtml(value) {
    return `<p>${escapeHtml(String(value || '')).replace(/\n/g, '<br>')}</p>`;
}

function stripHtml(value) {
    if (typeof document !== 'undefined') {
        const element = document.createElement('div');
        element.innerHTML = String(value || '');

        return normalizeWhitespace(element.textContent || '');
    }

    return normalizeWhitespace(String(value || '').replace(/<[^>]*>/g, ' '));
}

function normalizeWhitespace(value) {
    return String(value || '').replace(/\s+/g, ' ').trim();
}

function escapeHtml(value) {
    return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function clampNumber(value, min, max, fallback) {
    const number = Number(value);
    if (Number.isNaN(number)) {
        return fallback;
    }

    return Math.max(min, Math.min(max, number));
}

function resolveBlockNoteDictionary() {
    const language = String(document.documentElement.lang || '').toLowerCase();

    return language.startsWith('pl') ? pl : en;
}
