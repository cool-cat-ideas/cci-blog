import { formatAdminDateTime, ResourceListHeader } from '@cci/admin-ui';
import React from 'react';
import { StatusBadge as SharedStatusBadge } from '@cci/admin-ui';
import {
    ArrowLeft,
    ChevronDown,
    ChevronRight,
    FilePenLine,
    FolderTree,
    Layers,
    Link2,
    MessageSquare,
    Pencil,
    Plus,
    Save,
    Search,
    Store,
    Tags,
} from 'lucide-react';
import { apiFetch, pluginData } from '../api';
import { defaultContentHookName } from '../content-hooks';
import { __, coreString } from '../i18n';
import { Button } from './ui/button';
import { Card, CardContent, CardHeader } from './ui/card';
import { Checkbox, CheckboxField } from './ui/checkbox';
import { normalizeCssLengthValue } from './ui/css-length-input';
import { Field, FieldLabel } from './ui/field';
import { Input, Textarea } from './ui/input';
import { Select } from './ui/select';
import { Tabs, TabsList, TabsPanel, TabsTrigger } from './ui/tabs';
import MetricCard from './MetricCard';
import BlockNotePostEditor from './BlockNotePostEditor';
import ConfirmDeleteModal from './ConfirmDeleteModal';
import ProBadge from './ProBadge';
import ProUpgradeModal from './ProUpgradeModal';
import ReviewPrompt from './ReviewPrompt';
import LocalMediaUrlInput from './LocalMediaUrlInput';
import { DataTable, EmptyState, RowPrimary } from './AdminDataTable';

const editorTabs = [
    { value: 'content', label: __('Content', 'cci-blog'), icon: Layers },
    { value: 'seo', label: __('SEO', 'cci-blog'), icon: Link2 },
];

const columnGapUnits = ['px', 'rem', 'em', '%'];

const defaultPost = {
    id_post: 0,
    id_lang: Number(pluginData.language?.id || 0),
    id_author: Number(pluginData.currentEmployeeId || 0),
    title: '',
    slug: '',
    intro: '',
    id_category: 0,
    category_ids: [],
    active: 1,
    featured: 0,
    allow_comments: 1,
    tags: '',
    cover_image: '',
    og_image: '',
    meta_title: '',
    meta_description: '',
    focus_keyword: '',
    seo_content_type: 'article',
    date_add: '',
    date_published: '',
    blocks: [
        { type: 'heading', level: 2, text: __('Section heading', 'cci-blog') },
        { type: 'paragraph', content: __('Start writing your article here.', 'cci-blog') },
    ],
};

const defaultCategory = {
    id_category: 0,
    id_lang: Number(pluginData.language?.id || 0),
    id_parent: 0,
    id_author: Number(pluginData.currentEmployeeId || 0),
    name: '',
    slug: '',
    description: '',
    image_url: '',
    active: 1,
    position: 0,
    meta_title: '',
    meta_description: '',
    meta_keywords: '',
    focus_keyword: '',
};

function createDefaultPost() {
    return {
        ...defaultPost,
        blocks: defaultPost.blocks.map((block) => ({ ...block })),
    };
}

function createDefaultCategory() {
    return { ...defaultCategory };
}

export default function BlogBuilderPanel({ isPro = pluginData.isPro, setNotice: pushNotice, onOpenSettings, onEditorStateChange, section = 'dashboard' }) {
    const [posts, setPosts] = React.useState(pluginData.posts || []);
    const [postsPagination, setPostsPagination] = React.useState(pluginData.postsPagination || {
        page: 1,
        limit: 10,
        total: Array.isArray(pluginData.posts) ? pluginData.posts.length : 0,
        totalPages: 1,
        sort: 'updated',
        direction: 'desc',
        query: '',
    });
    const [categories, setCategories] = React.useState(pluginData.categories || []);
    const [comments, setComments] = React.useState(pluginData.comments || []);
    const [stats, setStats] = React.useState(pluginData.stats || {});
    const [view, setView] = React.useState('list');
    const [editorTab, setEditorTab] = React.useState('content');
    const [working, setWorking] = React.useState(createDefaultPost);
    const [workingCategory, setWorkingCategory] = React.useState(createDefaultCategory);
    const [pendingDelete, setPendingDelete] = React.useState(null);
    const [saving, setSaving] = React.useState(false);

    React.useEffect(() => {
        if (section !== 'dashboard' && view === 'editor') {
            setView('list');
        }
        if (section !== 'categories' && view === 'category-editor') {
            setView('list');
        }
    }, [section, view]);

    React.useEffect(() => {
        if (typeof onEditorStateChange !== 'function') {
            return undefined;
        }

        onEditorStateChange(view === 'editor' || view === 'category-editor');

        return () => onEditorStateChange(false);
    }, [onEditorStateChange, view]);

    const notify = React.useCallback((notice) => {
        if (pushNotice) {
            pushNotice(notice);
        }
    }, [pushNotice]);

    const applyServerState = React.useCallback((response = {}) => {
        if (response.stats) setStats(response.stats);
        if (response.posts) setPosts(response.posts);
        if (response.postsPagination) setPostsPagination(response.postsPagination);
        if (response.categories) setCategories(response.categories);
        if (response.comments) setComments(response.comments);
    }, []);

    const runRequest = React.useCallback((request, successMessage = '') => {
        setSaving(true);

        return request()
            .then((response) => {
                applyServerState(response);
                if (successMessage) {
                    notify({
                        type: response.warning ? 'warning' : 'success',
                        message: successMessage,
                        details: response.warning || '',
                    });
                }
                return response;
            })
            .catch((error) => {
                notify({
                    type: 'error',
                    message: error.message,
                    details: error.details || '',
                });
                throw error;
            })
            .finally(() => setSaving(false));
    }, [applyServerState, notify]);

    const contentLanguages = React.useMemo(() => normalizeContentLanguages(pluginData.languages, pluginData.language), []);
    const defaultContentLanguageId = Number(pluginData.language?.id || contentLanguages[0]?.id || 0);
    const translationsEnabled = Boolean(pluginData.enabledFeatures?.includes?.('translations'));

    const openPost = (post, languageId = defaultContentLanguageId) => {
        setSaving(true);
        apiFetch('/post/get', { params: { id_post: post.id_post, id_lang: languageId } })
            .then((response) => {
                setWorking(normalizePost(response.post));
                setEditorTab('content');
                setView('editor');
            })
            .catch((error) => {
                notify({ type: 'error', message: error.message, details: error.details || '' });
            })
            .finally(() => setSaving(false));
    };

    const createPost = () => {
        setWorking({ ...createDefaultPost(), id_lang: defaultContentLanguageId });
        setEditorTab('content');
        setView('editor');
    };

    const changePostLanguage = (languageId) => {
        const nextLanguageId = Number(languageId || defaultContentLanguageId);
        if (Number(working.id_post || 0) <= 0) {
            setWorking((current) => ({ ...current, id_lang: nextLanguageId }));
            return;
        }

        openPost(working, nextLanguageId);
    };

    const savePost = () => runRequest(
        () => apiFetch('/post/save', { body: JSON.stringify(normalizePostForPlan(working, isPro)) }),
        __('Post was saved.', 'cci-blog')
    ).then((response) => {
        const nextPost = normalizePost(response.post || working);
        setWorking(nextPost);
        setPosts((current) => upsertPostSummary(current, nextPost));
    });

    const deletePost = (post) => runRequest(
        () => apiFetch('/post/delete', { body: JSON.stringify({ id_post: post.id_post }) }),
        __('Post was deleted.', 'cci-blog')
    ).then(() => setPendingDelete(null));

    const saveCategory = (category) => runRequest(
        () => apiFetch('/category/save', { body: JSON.stringify(category) }),
        __('Category was saved.', 'cci-blog')
    ).then((response) => {
        if (response.categories) {
            setCategories(response.categories);
        }
        if (response.category) {
            setWorkingCategory(normalizeCategory(response.category));
        }
    });

    const createCategory = () => {
        setWorkingCategory({ ...createDefaultCategory(), id_lang: defaultContentLanguageId });
        setEditorTab('content');
        setView('category-editor');
    };

    const openCategory = (category, languageId = defaultContentLanguageId) => {
        const categoryId = Number(category?.id_category || 0);
        if (categoryId <= 0) {
            setWorkingCategory({ ...createDefaultCategory(), id_lang: languageId });
            setEditorTab('content');
            setView('category-editor');
            return;
        }

        setSaving(true);
        apiFetch('/category/get', { params: { id_category: categoryId, id_lang: languageId } })
            .then((response) => {
                setWorkingCategory(normalizeCategory(response.category));
                setEditorTab('content');
                setView('category-editor');
            })
            .catch((error) => {
                notify({ type: 'error', message: error.message, details: error.details || '' });
            })
            .finally(() => setSaving(false));
    };

    const changeCategoryLanguage = (languageId) => {
        const nextLanguageId = Number(languageId || defaultContentLanguageId);
        if (Number(workingCategory.id_category || 0) <= 0) {
            setWorkingCategory((current) => ({ ...current, id_lang: nextLanguageId }));
            return;
        }

        openCategory(workingCategory, nextLanguageId);
    };

    const saveWorkingCategory = () => saveCategory(workingCategory);

    const assignPostToShops = (shopIds) => runRequest(
        () => apiFetch('/multistore/posts/assign', {
            body: JSON.stringify({ id_post: working.id_post, shopIds }),
        }),
        __('Post was assigned to the selected shops.', 'cci-blog')
    );

    const assignCategoryToShops = (shopIds) => runRequest(
        () => apiFetch('/multistore/categories/assign', {
            body: JSON.stringify({ id_category: workingCategory.id_category, shopIds }),
        }),
        __('Category was assigned to the selected shops.', 'cci-blog')
    );

    const deleteCategory = (category) => runRequest(
        () => apiFetch('/category/delete', { body: JSON.stringify({ id_category: category.id_category }) }),
        __('Category was deleted.', 'cci-blog')
    ).then(() => setPendingDelete(null));

    const updateCommentStatus = (comment, status) => runRequest(
        () => apiFetch('/comment/update-status', { body: JSON.stringify({ id_comment: comment.id_comment, status }) }),
        __('Comment status was updated.', 'cci-blog')
    );

    const deleteComment = (comment) => runRequest(
        () => apiFetch('/comment/update-status', { body: JSON.stringify({ id_comment: comment.id_comment, status: 'deleted' }) }),
        __('Comment was deleted.', 'cci-blog')
    ).then(() => setPendingDelete(null));

    const requestDeletePost = (post) => setPendingDelete({
        itemLabel: post.title || __('(no title)', 'cci-blog'),
        title: __('Delete post?', 'cci-blog'),
        suffix: __('and related comments, category assignments, tags and product links from the blog.', 'cci-blog'),
        confirmLabel: __('Delete post', 'cci-blog'),
        onConfirm: () => deletePost(post),
    });

    const requestDeleteCategory = (category) => setPendingDelete({
        itemLabel: category.name || __('(no title)', 'cci-blog'),
        title: __('Delete category?', 'cci-blog'),
        suffix: __('from the blog taxonomy. Categories with posts or child categories are protected.', 'cci-blog'),
        confirmLabel: __('Delete category', 'cci-blog'),
        onConfirm: () => deleteCategory(category),
    });

    const requestDeleteComment = (comment) => setPendingDelete({
        itemLabel: comment.author_name || __('this comment', 'cci-blog'),
        title: __('Delete comment?', 'cci-blog'),
        suffix: __('from the moderation queue.', 'cci-blog'),
        confirmLabel: __('Delete comment', 'cci-blog'),
        onConfirm: () => deleteComment(comment),
    });

    if (view === 'list') {
        return (
            <>
                <BlogList
                    posts={posts}
                    postsPagination={postsPagination}
                    categories={categories}
                    comments={comments}
                    stats={stats}
                    isPro={isPro}
                    commentsProvider={pluginData.settings?.CCB_COMMENTS_PROVIDER === 'native' ? 'native' : 'disqus'}
                    disqusShortname={pluginData.settings?.CCB_DISQUS_SHORTNAME || ''}
                    activeSection={section}
                    onPostsResponse={applyServerState}
                    onCreatePost={createPost}
                    onOpenPost={openPost}
                    onDeletePost={requestDeletePost}
                    onSaveCategory={saveCategory}
                    onCreateCategory={createCategory}
                    onOpenCategory={openCategory}
                    onDeleteCategory={requestDeleteCategory}
                    onUpdateCommentStatus={updateCommentStatus}
                    onDeleteComment={requestDeleteComment}
                    setNotice={notify}
                    saving={saving}
                />
                <ConfirmDeleteModal
                    open={Boolean(pendingDelete)}
                    itemLabel={pendingDelete?.itemLabel || ''}
                    title={pendingDelete?.title || __('Delete item?', 'cci-blog')}
                    suffix={pendingDelete?.suffix || __('from the current blog draft.', 'cci-blog')}
                    confirmLabel={pendingDelete?.confirmLabel || __('Delete item', 'cci-blog')}
                    onCancel={() => setPendingDelete(null)}
                    onConfirm={() => pendingDelete?.onConfirm?.()}
                />
            </>
        );
    }

    if (view === 'category-editor') {
        return (
            <CategoryEditor
                category={workingCategory}
                authors={pluginData.authors || []}
                categories={categories}
                tab={editorTab}
                saving={saving}
                onBack={() => setView('list')}
                onTabChange={setEditorTab}
                onChange={setWorkingCategory}
                onSave={saveWorkingCategory}
                onOpenSettings={onOpenSettings}
                languages={contentLanguages}
                translationsEnabled={translationsEnabled}
                onLanguageChange={changeCategoryLanguage}
                multistore={pluginData.multistore}
                onAssignToShops={assignCategoryToShops}
            />
        );
    }

    return (
        <PostEditor
            post={working}
            authors={pluginData.authors || []}
            categories={categories}
            tab={editorTab}
            saving={saving}
            onBack={() => setView('list')}
            onSave={savePost}
            onTabChange={setEditorTab}
            onChange={setWorking}
            onOpenSettings={onOpenSettings}
            isPro={isPro}
            languages={contentLanguages}
            translationsEnabled={translationsEnabled}
            onLanguageChange={changePostLanguage}
            multistore={pluginData.multistore}
            onAssignToShops={assignPostToShops}
        />
    );
}

function BlogList({ posts, postsPagination, categories, comments, stats, isPro, commentsProvider, disqusShortname, activeSection, onPostsResponse, onCreatePost, onOpenPost, onDeletePost, onCreateCategory, onOpenCategory, onDeleteCategory, onUpdateCommentStatus, onDeleteComment, setNotice, saving }) {
    const section = ['categories', 'comments'].includes(activeSection) ? activeSection : 'dashboard';
    const usesDisqus = commentsProvider === 'disqus';

    return (
        <div className='tw-grid tw-gap-4'>
            <div className='tw-grid tw-grid-cols-1 tw-gap-3 lg:tw-grid-cols-3'>
                <MetricCard icon={FilePenLine} label={__('Posts', 'cci-blog')} value={stats.posts ?? postsPagination?.total ?? posts.length} description={`${stats.activePosts ?? 0} ${__('active', 'cci-blog')}`} tone={section === 'dashboard' ? 'primary' : 'default'} />
                <MetricCard icon={Tags} label={__('Categories', 'cci-blog')} value={stats.categories ?? categories.length} description={`${stats.activeCategories ?? categories.filter((category) => Number(category.active) === 1).length} ${__('active', 'cci-blog')}`} tone={section === 'categories' ? 'primary' : 'default'} />
                <MetricCard
                    icon={MessageSquare}
                    label={usesDisqus ? __('Comment provider', 'cci-blog') : __('Pending comments', 'cci-blog')}
                    value={usesDisqus ? 'Disqus' : (stats.pendingComments ?? 0)}
                    description={stats.commentsEnabled ? __('Comments enabled', 'cci-blog') : __('Comments disabled', 'cci-blog')}
                    tone={section === 'comments' ? 'primary' : 'default'}
                />
            </div>

            {section === 'dashboard' && (
                <ReviewPrompt setNotice={setNotice} />
            )}

            <Card>
                {section === 'dashboard' && (
                    <>
                    <ResourceListHeader title={__('Posts', 'cci-blog')}
                        description={__('Manage posts and open the block editor.', 'cci-blog')}
                        createLabel={__('New post', 'cci-blog')} onCreate={onCreatePost} />
                    <PostsTable
                        posts={posts}
                        pagination={postsPagination}
                        isPro={isPro}
                        onPostsResponse={onPostsResponse}
                        onNotice={setNotice}
                        onOpenPost={onOpenPost}
                        onDeletePost={onDeletePost}
                    />
                    </>
                )}

                {section === 'categories' && (
                    <CategoriesPanel categories={categories} saving={saving} onCreateCategory={onCreateCategory} onOpenCategory={onOpenCategory} onDeleteCategory={onDeleteCategory} />
                )}

                {section === 'comments' && (
                    <CommentsPanel
                        comments={comments}
                        commentsProvider={commentsProvider}
                        disqusShortname={disqusShortname}
                        saving={saving}
                        onUpdateStatus={onUpdateCommentStatus}
                        onDeleteComment={onDeleteComment}
                    />
                )}
            </Card>
        </div>
    );
}

function PostEditor({ post, authors, categories, tab, saving, onBack, onSave, onTabChange, onChange, onOpenSettings, isPro, languages, translationsEnabled, onLanguageChange, multistore, onAssignToShops }) {
    const blocks = Array.isArray(post.blocks) ? post.blocks : [];
    const [proFeature, setProFeature] = React.useState(null);
    const debouncedPost = useDebouncedValue(post, 350);
    const seoAudit = React.useMemo(() => calculateSeoAudit(debouncedPost), [debouncedPost]);
    const change = (patch) => onChange({ ...post, ...patch });
    const editorKey = `${post.id_post || 'new'}-${post.date_upd || post.date_published || ''}`;
    const selectedCategoryIds = normalizeCategoryIds(post.category_ids, post.id_category);
    const primaryCategoryId = Number(post.id_category || 0);
    const additionalCategoryIds = selectedCategoryIds.filter((id) => id !== primaryCategoryId);
    const authorOptions = React.useMemo(
        () => buildAuthorOptions(authors, post.id_author, post.author_name),
        [authors, post.id_author, post.author_name]
    );
    const setPrimaryCategory = (categoryId) => {
        const nextPrimaryId = Number(categoryId || 0);
        const nextAdditionalIds = isPro
            ? selectedCategoryIds.filter((id) => id !== nextPrimaryId)
            : [];
        change({
            id_category: nextPrimaryId,
            category_ids: nextPrimaryId > 0 ? [nextPrimaryId, ...nextAdditionalIds] : [],
        });
    };
    const toggleCategorySelection = (categoryId, checked) => {
        const nextCategoryId = Number(categoryId || 0);
        if (nextCategoryId <= 0) {
            return;
        }

        if (!isPro) {
            change({
                id_category: checked ? nextCategoryId : 0,
                category_ids: checked ? [nextCategoryId] : [],
            });
            return;
        }

        if (checked) {
            if (primaryCategoryId <= 0 || nextCategoryId === primaryCategoryId) {
                setPrimaryCategory(nextCategoryId);
                return;
            }

            change({
                category_ids: [primaryCategoryId, ...Array.from(new Set([...additionalCategoryIds, nextCategoryId]))],
            });
            return;
        }

        if (nextCategoryId === primaryCategoryId) {
            const remainingIds = selectedCategoryIds.filter((id) => id !== nextCategoryId);
            const nextPrimaryId = remainingIds[0] || 0;
            change({
                id_category: nextPrimaryId,
                category_ids: nextPrimaryId > 0 ? [nextPrimaryId, ...remainingIds.filter((id) => id !== nextPrimaryId)] : [],
            });
            return;
        }

        change({
            category_ids: [primaryCategoryId, ...additionalCategoryIds.filter((id) => id !== nextCategoryId)],
        });
    };
    const openProFeature = (feature) => setProFeature(feature);
    const closeProFeature = () => setProFeature(null);
    const activateLicense = () => {
        closeProFeature();
        onOpenSettings?.();
    };

    return (
        <>
        <Card className='tw-overflow-visible'>
            <CardHeader className='tw-flex tw-min-h-16 tw-flex-row tw-items-center tw-justify-between tw-gap-4 tw-border-0 tw-border-b tw-border-solid tw-border-cci-blog-border tw-bg-white tw-px-5 tw-py-4'>
                <Button variant='ghost' type='button' onClick={onBack}>
                    <ArrowLeft aria-hidden='true' />
                    {__('Back to all posts', 'cci-blog')}
                </Button>
                <div className='tw-flex tw-flex-wrap tw-items-center tw-justify-end tw-gap-2'>
                    <ContentLanguageSelect
                        languages={languages}
                        languageId={post.id_lang}
                        disabled={!translationsEnabled || saving}
                        onChange={onLanguageChange}
                    />
                    <Button variant='save' type='button' onClick={onSave} disabled={saving} aria-busy={saving}>
                        <Save aria-hidden='true' />
                        {saving ? __('Saving...', 'cci-blog') : __('Save', 'cci-blog')}
                    </Button>
                </div>
            </CardHeader>

            <div className='tw-grid tw-gap-3 tw-border-0 tw-border-b tw-border-solid tw-border-cci-blog-border tw-bg-white tw-px-5 tw-py-3'>
                <Input
                    className='tw-h-12 tw-text-base tw-font-semibold'
                    value={post.title || ''}
                    onChange={(event) => change({ title: event.target.value })}
                    placeholder={__('Post title', 'cci-blog')}
                />
                <SlugPermalink
                    slug={post.slug || ''}
                    title={post.title || ''}
                    onChange={(slug) => change({ slug })}
                />
            </div>

            <MultistoreAssignment
                context={multistore}
                entityId={Number(post.id_post || 0)}
                entityLabel={__('post', 'cci-blog')}
                saving={saving}
                onAssign={onAssignToShops}
            />

            <Tabs value={tab} onValueChange={onTabChange} className='tw-grid tw-gap-0'>
                <div className='tw-h-14 tw-border-0 tw-border-b tw-border-solid tw-border-cci-blog-border tw-bg-white tw-px-4'>
                    <TabsList flush className='tw-h-full tw-gap-6'>
                        {editorTabs.map((item) => {
                            const Icon = item.icon;
                            return (
                                <TabsTrigger key={item.value} value={item.value} className='cci-blog-tab-trigger !tw-h-full tw-mb-[-1px] tw-gap-2 tw-rounded-none tw-border-0 tw-border-b-2 tw-border-solid tw-border-transparent tw-bg-transparent tw-px-0 data-[state=active]:tw-border-cci-blog-brand data-[state=active]:tw-bg-transparent data-[state=active]:tw-text-cci-blog-brand'>
                                    <Icon aria-hidden='true' />
                                    {item.label}
                                </TabsTrigger>
                            );
                        })}
                    </TabsList>
                </div>

                <CardContent className='tw-bg-slate-50 tw-p-4'>
                    <TabsPanel value='content' transparent className='tw-m-0 tw-border-0'>
                        <div className='cci-blog-editor-layout-with-seo tw-grid tw-gap-4'>
                            <div className='tw-grid tw-gap-4'>
                                <section className='tw-rounded-md tw-border tw-border-solid tw-border-cci-blog-border tw-bg-white tw-p-4'>
                                    <div className='tw-grid tw-gap-3'>
                                        <div>
                                            <strong className='tw-block tw-text-base tw-font-semibold tw-leading-5 tw-text-cci-blog-text'>{__('Post settings', 'cci-blog')}</strong>
                                            <p className='tw-m-0 tw-mt-1 tw-text-xs tw-leading-5 tw-text-cci-blog-muted'>{__('Publication and storefront behaviour.', 'cci-blog')}</p>
                                        </div>
                                        <div className='cci-blog-post-settings-grid tw-grid tw-gap-3'>
                                            <Field
                                                label={__('Created', 'cci-blog')}
                                                note={post.date_add ? __('Set when the post was first saved.', 'cci-blog') : __('Will be set on first save.', 'cci-blog')}
                                            >
                                                <Input
                                                    value={formatAdminDateTime(post.date_add, pluginData.adminDate) || __('Not saved yet', 'cci-blog')}
                                                    readOnly
                                                />
                                            </Field>
                                            <Field label={__('Publication date', 'cci-blog')}>
                                                <Input
                                                    type='datetime-local'
                                                    value={toDateTimeLocalValue(post.date_published)}
                                                    onChange={(event) => change({ date_published: fromDateTimeLocalValue(event.target.value) })}
                                                />
                                            </Field>
                                            <Field label={__('Author', 'cci-blog')}>
                                                <Select
                                                    value={post.id_author ? String(post.id_author) : ''}
                                                    onValueChange={(value) => change({ id_author: Number(value) })}
                                                    options={authorOptions}
                                                    placeholder={__('Select author', 'cci-blog')}
                                                />
                                            </Field>
                                        </div>
                                        <PostCategoryTreeSelector
                                            categories={categories}
                                            isPro={isPro}
                                            primaryCategoryId={primaryCategoryId}
                                            selectedIds={selectedCategoryIds}
                                            onToggle={toggleCategorySelection}
                                            onSetPrimary={setPrimaryCategory}
                                            onProFeatureClick={() => openProFeature(blogProFeatures.additionalCategories)}
                                        />
                                        {!isPro ? (
                                            <ProFeatureInlineCard
                                                feature={blogProFeatures.translations}
                                                onClick={() => openProFeature(blogProFeatures.translations)}
                                            />
                                        ) : null}
                                    </div>
                                    <div className='tw-mt-3 tw-grid tw-gap-3 md:tw-grid-cols-3'>
                                        <ToggleField checked={Boolean(Number(post.active))} label={__('Published', 'cci-blog')} onChange={(checked) => change({ active: checked ? 1 : 0 })} />
                                        <ToggleField checked={Boolean(Number(post.featured))} label={__('Featured post', 'cci-blog')} onChange={(checked) => change({ featured: checked ? 1 : 0 })} />
                                        <ToggleField checked={Boolean(Number(post.allow_comments))} label={__('Allow comments', 'cci-blog')} onChange={(checked) => change({ allow_comments: checked ? 1 : 0 })} />
                                    </div>
                                    <div className='tw-mt-3'>
                                        <Field label={__('Intro', 'cci-blog')}>
                                            <Textarea value={post.intro || ''} onChange={(event) => change({ intro: event.target.value })} />
                                        </Field>
                                    </div>
                                    <div className='tw-mt-3'>
                                        <Field
                                            label={__('Cover image URL', 'cci-blog')}
                                            note={__('Paste an external URL or choose an existing local image with CCI Blog Pro.', 'cci-blog')}
                                        >
                                            <LocalMediaUrlInput
                                                value={post.cover_image || ''}
                                                onChange={(cover_image) => change({ cover_image })}
                                                onLockedClick={() => openProFeature(blogProFeatures.localMediaLibrary)}
                                                placeholder='/img/...'
                                            />
                                        </Field>
                                    </div>
                                </section>

                                <BlockNotePostEditor
                                    blocks={blocks}
                                    editorKey={editorKey}
                                    extensionBlocks={pluginData.blockExtensions || []}
                                    isPro={isPro}
                                    lockedFeatures={blogProFeatures}
                                    onLockedFeatureClick={openProFeature}
                                    onOpenSettings={onOpenSettings}
                                    onChange={(nextBlocks) => change({ blocks: nextBlocks })}
                                />
                            </div>

                            <aside className='cci-blog-seo-sidebar tw-z-10 tw-grid tw-content-start tw-gap-4'>
                                <SeoContentAssistPanel audit={seoAudit} compact onOpenSeo={() => onTabChange('seo')} />
                            </aside>
                        </div>
                    </TabsPanel>

                    <TabsPanel value='seo' className='tw-m-0 tw-border-0 tw-bg-transparent'>
                        <SeoEditorPanel post={post} audit={seoAudit} onChange={change} onLockedFeatureClick={openProFeature} />
                    </TabsPanel>
                </CardContent>
            </Tabs>
        </Card>
        <ProUpgradeModal
            open={Boolean(proFeature)}
            featureName={proFeature?.featureName || ''}
            description={proFeature?.description || ''}
            benefits={proFeature?.benefits || []}
            onActivateLicense={activateLicense}
            onClose={closeProFeature}
        />
        </>
    );
}

function SeoEditorPanel({ post, audit, onChange, onLockedFeatureClick }) {
    const previewTitle = post.meta_title || post.title || __('Untitled post', 'cci-blog');
    const previewDescription = post.meta_description || post.intro || __('Meta description will appear here.', 'cci-blog');
    const previewSlug = post.slug || normalizeAdminSlug(post.title || '') || normalizeAdminSlug(__('auto-generated from title', 'cci-blog'));
    const previewUrl = buildPostStorefrontUrl(previewSlug);
    const update = (patch) => onChange(patch);

    return (
        <div className='cci-blog-seo-tab-layout tw-grid tw-gap-4'>
            <div className='tw-grid tw-content-start tw-gap-4'>
                <section className='tw-rounded-md tw-border tw-border-solid tw-border-cci-blog-border tw-bg-white tw-p-4'>
                    <div className='tw-grid tw-gap-3 md:tw-grid-cols-2'>
                        <Field label={__('Focus keyword', 'cci-blog')}>
                            <Input
                                value={post.focus_keyword || ''}
                                onChange={(event) => update({ focus_keyword: event.target.value })}
                                placeholder={__('main search phrase', 'cci-blog')}
                            />
                        </Field>
                        <Field
                            label={__('Content type', 'cci-blog')}
                            note={__('SEO length thresholds adapt to the selected article type.', 'cci-blog')}
                        >
                            <Select
                                value={post.seo_content_type || 'article'}
                                onValueChange={(value) => update({ seo_content_type: value })}
                                options={seoContentTypeOptions()}
                            />
                        </Field>
                        <Field label={__('Tags', 'cci-blog')}>
                            <Input value={post.tags || ''} onChange={(event) => update({ tags: event.target.value })} placeholder={__('news, guide, sale', 'cci-blog')} />
                        </Field>
                        <Field label={__('Title tag', 'cci-blog')}>
                            <Input value={post.meta_title || ''} onChange={(event) => update({ meta_title: event.target.value })} placeholder={post.title || __('Post title fallback', 'cci-blog')} />
                        </Field>
                        <Field label={__('Meta description', 'cci-blog')}>
                            <Textarea value={post.meta_description || ''} onChange={(event) => update({ meta_description: event.target.value })} />
                        </Field>
                        <Field
                            label={__('Open Graph image URL', 'cci-blog')}
                            note={__('Leave empty to use the article cover image.', 'cci-blog')}
                        >
                            <LocalMediaUrlInput
                                value={post.og_image || ''}
                                onChange={(og_image) => update({ og_image })}
                                onLockedClick={() => onLockedFeatureClick?.(blogProFeatures.localMediaLibrary)}
                                placeholder='/img/...'
                            />
                        </Field>
                    </div>
                </section>

                <section className='tw-rounded-md tw-border tw-border-solid tw-border-cci-blog-border tw-bg-white tw-p-4'>
                    <div className='tw-grid tw-gap-2'>
                        <strong className='tw-text-base tw-font-semibold tw-text-cci-blog-text'>{__('Search preview', 'cci-blog')}</strong>
                        <div className='tw-rounded-md tw-border tw-border-solid tw-border-cci-blog-border tw-bg-cci-blog-surfaceSoft tw-p-4'>
                            <div className='tw-break-words tw-text-lg tw-font-semibold tw-leading-6 tw-text-blue-700'>{previewTitle}</div>
                            <div className='tw-mt-1 tw-break-all tw-text-xs tw-leading-5 tw-text-green-700'>{previewUrl}</div>
                            <p className='tw-m-0 tw-mt-2 tw-text-sm tw-leading-6 tw-text-cci-blog-muted'>{previewDescription}</p>
                        </div>
                    </div>
                </section>

                <SeoChecksPanel audit={audit} />
            </div>

            <aside className='cci-blog-seo-sidebar tw-grid tw-content-start tw-gap-4'>
                <SeoScorePanel audit={audit} />
            </aside>
        </div>
    );
}

function SeoContentAssistPanel({ audit, onOpenSeo, compact = false, description, openLabel }) {
    const tone = seoStatusTone(audit.overallStatus);
    const priorityChecks = getSeoPriorityChecks(audit.checks, compact ? 4 : 4);
    const panelDescription = description || __('SEO analysis', 'cci-blog');
    const seoButtonLabel = openLabel || __('Open SEO settings', 'cci-blog');

    if (compact) {
        return (
            <section className='tw-rounded-md tw-border tw-border-solid tw-border-cci-blog-border tw-bg-white tw-p-3'>
                <div className='tw-grid tw-gap-2.5'>
                    <div className='tw-flex tw-items-start tw-justify-between tw-gap-2'>
                        <div>
                            <strong className='tw-block tw-text-sm tw-font-semibold tw-leading-5 tw-text-cci-blog-text'>{__('SEO while writing', 'cci-blog')}</strong>
                            <p className='tw-m-0 tw-text-xs tw-leading-4 tw-text-cci-blog-muted'>{panelDescription}</p>
                        </div>
                        <span className={`tw-inline-flex tw-min-w-12 tw-shrink-0 tw-items-center tw-justify-center tw-rounded-md tw-border tw-border-solid tw-px-2 tw-py-1 tw-text-sm tw-font-semibold ${tone.badge}`}>
                            {audit.score}
                        </span>
                    </div>
                    <div className='tw-h-2 tw-overflow-hidden tw-rounded-full tw-bg-slate-100' aria-hidden='true'>
                        <div className={`tw-h-full tw-rounded-full ${tone.bar}`} style={{ width: `${audit.score}%` }} />
                    </div>
                    <div className='tw-flex tw-flex-wrap tw-gap-1'>
                        <SeoTinyMetric label={__('Words', 'cci-blog')} value={audit.metrics.wordCount} />
                        <SeoTinyMetric label={__('Title', 'cci-blog')} value={`${audit.metrics.titleLength} ${__('chars', 'cci-blog')}`} />
                        <SeoTinyMetric label={__('Meta', 'cci-blog')} value={`${audit.metrics.metaLength} ${__('chars', 'cci-blog')}`} />
                    </div>
                    <div className='tw-grid tw-gap-1.5'>
                        {priorityChecks.map((check) => (
                            <SeoSuggestionRow key={check.id} check={check} compact />
                        ))}
                    </div>
                    <Button variant='builder' size='sm' type='button' className='tw-w-full' onClick={onOpenSeo}>
                        <Link2 aria-hidden='true' />
                        {seoButtonLabel}
                    </Button>
                </div>
            </section>
        );
    }

    return (
        <section className='tw-rounded-md tw-border tw-border-solid tw-border-cci-blog-border tw-bg-white tw-p-4'>
            <div className='tw-grid tw-gap-4 xl:tw-grid-cols-[220px_minmax(0,1fr)_auto] xl:tw-items-start'>
                <div className='tw-grid tw-gap-2'>
                    <div className='tw-flex tw-items-center tw-justify-between tw-gap-3'>
                        <div>
                            <strong className='tw-block tw-text-base tw-font-semibold tw-leading-5 tw-text-cci-blog-text'>{__('SEO while writing', 'cci-blog')}</strong>
                            <p className='tw-m-0 tw-mt-1 tw-text-xs tw-leading-5 tw-text-cci-blog-muted'>{description || __('Live score from the visible article draft.', 'cci-blog')}</p>
                        </div>
                        <span className={`tw-inline-flex tw-min-w-14 tw-items-center tw-justify-center tw-rounded-md tw-border tw-border-solid tw-px-2.5 tw-py-1.5 tw-text-base tw-font-semibold ${tone.badge}`}>
                            {audit.score}
                        </span>
                    </div>
                    <div className='tw-h-2 tw-overflow-hidden tw-rounded-full tw-bg-slate-100' aria-hidden='true'>
                        <div className={`tw-h-full tw-rounded-full ${tone.bar}`} style={{ width: `${audit.score}%` }} />
                    </div>
                    <div className='tw-flex tw-flex-wrap tw-gap-1.5'>
                        <SeoTinyMetric label={__('Words', 'cci-blog')} value={audit.metrics.wordCount} />
                        <SeoTinyMetric label={__('Title', 'cci-blog')} value={`${audit.metrics.titleLength} ${__('chars', 'cci-blog')}`} />
                        <SeoTinyMetric label={__('Meta', 'cci-blog')} value={`${audit.metrics.metaLength} ${__('chars', 'cci-blog')}`} />
                    </div>
                </div>

                <div className='tw-grid tw-gap-2 md:tw-grid-cols-2'>
                    {priorityChecks.map((check) => (
                        <SeoSuggestionRow key={check.id} check={check} />
                    ))}
                </div>

                <div className='tw-flex tw-justify-start xl:tw-justify-end'>
                    <Button variant='builder' type='button' onClick={onOpenSeo}>
                        <Link2 aria-hidden='true' />
                        {seoButtonLabel}
                    </Button>
                </div>
            </div>
        </section>
    );
}

function SeoTinyMetric({ label, value }) {
    return (
        <span className='tw-inline-flex tw-items-center tw-gap-1 tw-rounded-full tw-border tw-border-solid tw-border-cci-blog-border tw-bg-cci-blog-surfaceSoft tw-px-1.5 tw-py-1 tw-text-[11px] tw-leading-none tw-text-cci-blog-muted'>
            {label}
            <strong className='tw-font-semibold tw-text-cci-blog-text'>{value}</strong>
        </span>
    );
}

function SeoSuggestionRow({ check, compact = false }) {
    const tone = seoStatusTone(check.status);

    if (compact) {
        return (
            <div className='tw-grid tw-grid-cols-[auto_minmax(0,1fr)] tw-gap-2 tw-rounded-md tw-border tw-border-solid tw-border-cci-blog-border tw-bg-cci-blog-surfaceSoft tw-px-2.5 tw-py-2'>
                <span className={`tw-mt-1 tw-h-2 tw-w-2 tw-rounded-full ${tone.dot}`} aria-hidden='true' />
                <div className='tw-min-w-0'>
                    <div className='tw-flex tw-min-w-0 tw-items-center tw-gap-2'>
                        <strong className='tw-min-w-0 tw-truncate tw-text-xs tw-font-semibold tw-leading-4 tw-text-cci-blog-text'>{check.label}</strong>
                    </div>
                    <p className='tw-m-0 tw-mt-0.5 tw-line-clamp-2 tw-text-[11px] tw-leading-4 tw-text-cci-blog-muted'>{compactSeoMessage(check)}</p>
                </div>
            </div>
        );
    }

    return (
        <div className='tw-grid tw-grid-cols-[auto_minmax(0,1fr)] tw-gap-2 tw-rounded-md tw-border tw-border-solid tw-border-cci-blog-border tw-bg-cci-blog-surfaceSoft tw-p-3'>
            <span className={`tw-mt-1.5 tw-h-2.5 tw-w-2.5 tw-rounded-full ${tone.dot}`} aria-hidden='true' />
            <div className='tw-min-w-0'>
                <div className='tw-flex tw-flex-wrap tw-items-center tw-gap-1.5'>
                    <strong className='tw-text-xs tw-font-semibold tw-leading-4 tw-text-cci-blog-text'>{check.label}</strong>
                </div>
                <p className='tw-m-0 tw-mt-1 tw-text-xs tw-leading-5 tw-text-cci-blog-muted'>{check.message}</p>
            </div>
        </div>
    );
}

function compactSeoMessage(check) {
    const message = String(check?.message || '');
    return message
        .replace(/^Set a /i, __('Add ', 'cci-blog'))
        .replace(/^Add the /i, __('Add ', 'cci-blog'))
        .replace(/^Title tag length:/i, __('Title:', 'cci-blog'))
        .replace(/^Meta description length:/i, __('Meta:', 'cci-blog'))
        .replace(/^Keyword density:/i, __('Density:', 'cci-blog'));
}

function SeoScorePanel({ audit, description }) {
    const tone = seoStatusTone(audit.overallStatus);

    return (
        <section className='tw-rounded-md tw-border tw-border-solid tw-border-cci-blog-border tw-bg-white tw-p-4'>
            <div className='tw-grid tw-gap-3'>
                <div className='tw-flex tw-items-start tw-justify-between tw-gap-3'>
                    <div>
                        <strong className='tw-text-base tw-font-semibold tw-text-cci-blog-text'>{__('SEO score', 'cci-blog')}</strong>
                        <p className='tw-m-0 tw-mt-1 tw-text-xs tw-leading-5 tw-text-cci-blog-muted'>{description || __('SEO analysis', 'cci-blog')}</p>
                    </div>
                    <span className={`tw-inline-flex tw-min-w-16 tw-items-center tw-justify-center tw-rounded-md tw-border tw-border-solid tw-px-3 tw-py-2 tw-text-lg tw-font-semibold ${tone.badge}`}>
                        {audit.score}
                    </span>
                </div>
                <div className='tw-h-2 tw-overflow-hidden tw-rounded-full tw-bg-slate-100' aria-hidden='true'>
                    <div className={`tw-h-full tw-rounded-full ${tone.bar}`} style={{ width: `${audit.score}%` }} />
                </div>
                <div className='tw-grid tw-grid-cols-1 tw-gap-2'>
                    <SeoMetricPill label={__('Words', 'cci-blog')} value={audit.metrics.wordCount} />
                    <SeoMetricPill label={__('Content type', 'cci-blog')} value={audit.metrics.contentTypeLabel} />
                    <SeoMetricPill label={__('Keyword density', 'cci-blog')} value={`${audit.metrics.keywordDensity}%`} />
                    <SeoMetricPill label={__('Title chars', 'cci-blog')} value={audit.metrics.titleLength} />
                    <SeoMetricPill label={__('Title width', 'cci-blog')} value={`${audit.metrics.titlePixels}px ${__('est.', 'cci-blog')}`} />
                    <SeoMetricPill label={__('Meta chars', 'cci-blog')} value={audit.metrics.metaLength} />
                    <SeoMetricPill label={__('Internal links', 'cci-blog')} value={audit.metrics.internalLinks} />
                    <SeoMetricPill label={__('Images', 'cci-blog')} value={audit.metrics.images} />
                </div>
            </div>
        </section>
    );
}

function SeoMetricPill({ label, value }) {
    return (
        <div className='tw-rounded-md tw-border tw-border-solid tw-border-cci-blog-border tw-bg-cci-blog-surfaceSoft tw-px-3 tw-py-2.5'>
            <span className='tw-block tw-text-xs tw-leading-4 tw-text-cci-blog-muted'>{label}</span>
            <strong className='tw-mt-1 tw-block tw-text-sm tw-font-semibold tw-text-cci-blog-text'>{value}</strong>
        </div>
    );
}

function SeoChecksPanel({ audit }) {
    const groups = groupSeoChecks(audit.checks);

    return (
        <div className='tw-grid tw-gap-4'>
            {groups.map((group) => (
                <section key={group.id} className='tw-rounded-md tw-border tw-border-solid tw-border-cci-blog-border tw-bg-white tw-p-4'>
                    <div className='tw-grid tw-gap-3'>
                        <div>
                            <strong className='tw-text-base tw-font-semibold tw-text-cci-blog-text'>{group.label}</strong>
                            <p className='tw-m-0 tw-mt-1 tw-text-xs tw-leading-5 tw-text-cci-blog-muted'>{seoGroupDescription(group.id)}</p>
                        </div>
                        <div className='tw-grid tw-gap-2'>
                            {group.checks.map((check) => (
                                <SeoCheckRow key={check.id} check={check} />
                            ))}
                        </div>
                    </div>
                </section>
            ))}
        </div>
    );
}

function seoGroupDescription(groupId) {
    const descriptions = {
        keyword: __('Keyword placement and usage across the editable content.', 'cci-blog'),
        title: __('Title length, estimated pixel width and keyword placement.', 'cci-blog'),
        meta: __('Meta description length, sentence quality and keyword usage.', 'cci-blog'),
        slug: __('URL slug length, stop words and keyword coverage.', 'cci-blog'),
        metadata: __('Title, meta description and URL quality checks.', 'cci-blog'),
        content: __('Length, headings, readability and content structure.', 'cci-blog'),
        links: __('Internal, external and image accessibility checks.', 'cci-blog'),
        media: __('Image alt text and file-name quality checks.', 'cci-blog'),
        readability: __('Sentence length and simple readability heuristics.', 'cci-blog'),
    };

    return descriptions[groupId] || __('SEO checks for this area.', 'cci-blog');
}

function SeoCheckRow({ check }) {
    const tone = seoStatusTone(check.status);

    return (
        <div
            className='tw-grid tw-grid-cols-[auto_minmax(0,1fr)] tw-gap-3 tw-rounded-md tw-border tw-border-solid tw-border-cci-blog-border tw-bg-cci-blog-surfaceSoft tw-p-3'
            title={check.help || ''}
        >
            <span className={`tw-mt-1 tw-h-2.5 tw-w-2.5 tw-rounded-full ${tone.dot}`} aria-hidden='true' />
            <div className='tw-min-w-0'>
                <div className='tw-flex tw-flex-wrap tw-items-center tw-gap-2'>
                    <strong className='tw-text-sm tw-font-semibold tw-text-cci-blog-text'>{check.label}</strong>
                    <span className={`tw-rounded-full tw-px-2 tw-py-1 tw-text-[11px] tw-font-semibold tw-leading-none ${tone.badge}`}>
                        {statusLabel(check.status)}
                    </span>
                </div>
                <p className='tw-m-0 tw-mt-1 tw-text-xs tw-leading-5 tw-text-cci-blog-muted'>{check.message}</p>
            </div>
        </div>
    );
}

function useDebouncedValue(value, delay = 400) {
    const [debounced, setDebounced] = React.useState(value);

    React.useEffect(() => {
        const timeout = window.setTimeout(() => setDebounced(value), delay);
        return () => window.clearTimeout(timeout);
    }, [delay, value]);

    return debounced;
}

function SlugPermalink({ slug, title, onChange, baseUrl }) {
    const [editing, setEditing] = React.useState(false);
    const [draft, setDraft] = React.useState(slug || '');
    const baseSlug = normalizeAdminSlug(pluginData.settings?.CCB_BASE_SLUG || 'blog') || 'blog';
    const autoSlugPlaceholder = normalizeAdminSlug(__('auto-generated from title', 'cci-blog')) || 'auto-generated-from-title';
    const suggestedSlug = normalizeAdminSlug(title);
    const visibleSlug = slug || suggestedSlug;
    const hasManualSlug = slug !== '';
    const editPermalinkLabel = __('Edit permalink', 'cci-blog');
    const storefrontBase = baseUrl || buildStorefrontBase(baseSlug);
    const postSuffix = !baseUrl && pluginData.settings?.CCB_URL_SUFFIX_HTML ? '.html' : '';

    React.useEffect(() => {
        if (!editing) {
            setDraft(slug || '');
        }
    }, [editing, slug]);

    const startEditing = () => {
        setDraft(slug || normalizeAdminSlug(title));
        setEditing(true);
    };

    const applyDraft = () => {
        onChange(normalizeAdminSlug(draft));
        setEditing(false);
    };

    const cancelDraft = () => {
        setDraft(slug || '');
        setEditing(false);
    };

    const handleKeyDown = (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            applyDraft();
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            cancelDraft();
        }
    };

    if (editing) {
        return (
            <div className='tw-grid tw-min-w-0 tw-gap-1.5 tw-text-xs tw-leading-5 sm:tw-flex sm:tw-flex-wrap sm:tw-items-center sm:tw-gap-x-2'>
                <span className='tw-font-semibold tw-text-cci-blog-text'>{__('Permalink:', 'cci-blog')}</span>
                <span className='tw-min-w-0 tw-break-all tw-text-cci-blog-muted'>{storefrontBase}</span>
                <Input
                    className='!tw-h-8 !tw-min-h-8 tw-w-full tw-px-2 tw-text-xs sm:tw-w-[28rem] sm:tw-max-w-full sm:tw-flex-none xl:tw-w-[32rem]'
                    value={draft}
                    onChange={(event) => setDraft(event.target.value)}
                    onKeyDown={handleKeyDown}
                    placeholder={autoSlugPlaceholder}
                    autoFocus
                />
                <div className='tw-flex tw-flex-wrap tw-gap-1.5'>
                    <Button size='xs' variant='save' onClick={applyDraft}>
                        <Save aria-hidden='true' />
                        {__('OK', 'cci-blog')}
                    </Button>
                    <Button size='xs' variant='secondary' onClick={cancelDraft}>
                        {__('Cancel', 'cci-blog')}
                    </Button>
                </div>
            </div>
        );
    }

    return (
        <div className='tw-flex tw-min-w-0 tw-flex-wrap tw-items-center tw-gap-x-2 tw-gap-y-1 tw-text-xs tw-leading-5'>
            <span className='tw-font-semibold tw-text-cci-blog-text'>{__('Permalink:', 'cci-blog')}</span>
            <span className='tw-min-w-0 tw-break-words tw-text-cci-blog-muted'>
                {storefrontBase}
                <strong className={hasManualSlug ? 'tw-font-semibold tw-text-cci-blog-brandStrong' : 'tw-font-semibold tw-text-cci-blog-muted'}>
                    {visibleSlug || autoSlugPlaceholder}
                </strong>
                {postSuffix}
            </span>
            <Button variant='builder' size='iconXs' type='button' onClick={startEditing} aria-label={editPermalinkLabel} title={editPermalinkLabel}>
                <Pencil aria-hidden='true' className='tw-h-3.5 tw-w-3.5' />
            </Button>
        </div>
    );
}

function buildStorefrontBase(baseSlug) {
    const origin = typeof window !== 'undefined' && window.location?.origin ? window.location.origin : '';
    const cleanBaseSlug = normalizeAdminSlug(baseSlug) || 'blog';

    return `${origin.replace(/\/$/, '')}/${cleanBaseSlug}/`;
}

function buildPostStorefrontUrl(slug) {
    const suffix = pluginData.settings?.CCB_URL_SUFFIX_HTML ? '.html' : '';

    return `${buildStorefrontBase(pluginData.settings?.CCB_BASE_SLUG || 'blog')}${slug}${suffix}`;
}

function buildCategoryStorefrontBase() {
    return `${buildStorefrontBase(pluginData.settings?.CCB_BASE_SLUG || 'blog')}category/`;
}

const blogProFeatures = {
    localMediaLibrary: {
        featureName: __('Local image library', 'cci-blog'),
        description: __('Browse existing PrestaShop images and select a local file without copying its URL manually.', 'cci-blog'),
        benefits: [
            __('Reuse images that are already stored by the shop.', 'cci-blog'),
            __('Choose image URLs from a safe read-only browser.', 'cci-blog'),
            __('Keep cover, category and Open Graph image selection consistent.', 'cci-blog'),
        ],
    },
    additionalCategories: {
        featureName: __('Additional categories', 'cci-blog'),
        description: __('Assign one post to several blog categories while keeping a primary category for canonical URLs and breadcrumbs.', 'cci-blog'),
        benefits: [
            __('Use a main category plus secondary categories for navigation.', 'cci-blog'),
            __('Build richer blog menus and category landing pages.', 'cci-blog'),
            __('Keep Free stores clean with one primary category per post.', 'cci-blog'),
        ],
    },
    translations: {
        featureName: __('Translations', 'cci-blog'),
        description: __('Edit translated post content, category labels, tags and SEO metadata for every shop language.', 'cci-blog'),
        benefits: [
            __('Translate titles, slugs, intro text and SEO fields per language.', 'cci-blog'),
            __('Keep tags and categories language-aware.', 'cci-blog'),
            __('Prepare multilingual storefront blog navigation.', 'cci-blog'),
        ],
    },
    columns: {
        featureName: __('Advanced layout blocks', 'cci-blog'),
        description: __('Build responsive article layouts with columns and richer content sections.', 'cci-blog'),
        benefits: [
            __('Use advanced column layouts inside long-form articles.', 'cci-blog'),
            __('Create more editorial landing pages without custom templates.', 'cci-blog'),
        ],
    },
    product: {
        featureName: __('Commerce content blocks', 'cci-blog'),
        description: __('Embed products and merchandising content directly inside articles.', 'cci-blog'),
        benefits: [
            __('Connect guides and inspiration articles with catalog products.', 'cci-blog'),
            __('Use product cards without manually copying storefront HTML.', 'cci-blog'),
        ],
    },
    product_carousel: {
        featureName: __('Product carousel', 'cci-blog'),
        description: __('Create draggable product carousels with responsive carousel settings.', 'cci-blog'),
        benefits: [
            __('Promote selected products inside buying guides.', 'cci-blog'),
            __('Control carousel behaviour from the editor.', 'cci-blog'),
        ],
    },
    hook: {
        featureName: __('Hook blocks', 'cci-blog'),
        description: __('Insert approved module hook content inside the article flow.', 'cci-blog'),
        benefits: [
            __('Place reusable module content between article sections.', 'cci-blog'),
            __('Keep hook usage controlled by the module allowlist.', 'cci-blog'),
        ],
    },
    module_block: {
        featureName: __('Extension blocks', 'cci-blog'),
        description: __('Use editor blocks registered by installed CCI Blog extensions.', 'cci-blog'),
        benefits: [
            __('Extend the editor without bloating the core module.', 'cci-blog'),
            __('Unlock add-on blocks from your store as your workflow grows.', 'cci-blog'),
        ],
    },
};

function ProFeatureInlineCard({ feature, onClick }) {
    return (
        <button
            type='button'
            className='tw-grid tw-w-full tw-cursor-pointer tw-gap-2 tw-rounded-md tw-border tw-border-solid tw-border-amber-300 tw-bg-amber-50 tw-p-3 tw-text-left tw-transition-colors hover:tw-border-amber-400 hover:tw-bg-amber-100 focus-visible:tw-outline focus-visible:tw-outline-2 focus-visible:tw-outline-amber-400'
            onClick={onClick}
        >
            <span className='tw-flex tw-min-w-0 tw-flex-wrap tw-items-center tw-gap-2'>
                <strong className='tw-text-sm tw-font-semibold tw-leading-5 tw-text-amber-950'>{feature.featureName}</strong>
                <ProBadge className='tw-px-1.5 tw-py-0.5'>{__('Pro', 'cci-blog')}</ProBadge>
            </span>
            <span className='tw-text-xs tw-leading-5 tw-text-amber-900'>
                {feature.description}
            </span>
        </button>
    );
}

function PostCategoryTreeSelector({ categories, isPro, primaryCategoryId, selectedIds, onToggle, onSetPrimary, onProFeatureClick }) {
    const rows = React.useMemo(() => buildCategoryTreeRows(categories), [categories]);
    const [query, setQuery] = React.useState('');
    const normalizedQuery = normalizeAdminSlug(query);
    const selectedSet = React.useMemo(() => (
        isPro
            ? new Set(selectedIds.map((id) => Number(id)))
            : new Set(primaryCategoryId > 0 ? [primaryCategoryId] : [])
    ), [isPro, primaryCategoryId, selectedIds]);
    const parentById = React.useMemo(() => new Map(rows.map((row) => [row.id, row.parentId])), [rows]);
    const matchingIds = React.useMemo(() => {
        if (!normalizedQuery) return null;

        const matches = new Set();
        rows.forEach((row) => {
            if (!normalizeAdminSlug(row.name).includes(normalizedQuery)
                && !normalizeAdminSlug(row.source.slug).includes(normalizedQuery)) return;

            // Keep a matching category's ancestors visible, including collapsed branches.
            let id = row.id;
            while (id > 0 && !matches.has(id)) {
                matches.add(id);
                id = parentById.get(id) || 0;
            }
        });
        return matches;
    }, [normalizedQuery, parentById, rows]);
    const [collapsedIds, setCollapsedIds] = React.useState(() => new Set());
    const toggleCollapsed = (categoryId) => {
        setCollapsedIds((current) => {
            const next = new Set(current);
            if (next.has(categoryId)) {
                next.delete(categoryId);
            } else {
                next.add(categoryId);
            }

            return next;
        });
    };
    const isVisible = (row) => {
        if (matchingIds) return matchingIds.has(row.id);

        let parentId = row.parentId;
        while (parentId > 0) {
            if (collapsedIds.has(parentId)) {
                return false;
            }
            parentId = parentById.get(parentId) || 0;
        }

        return true;
    };
    const visibleRows = rows.filter(isVisible);
    const note = isPro
        ? __('Choose one primary category and optionally assign secondary categories.', 'cci-blog')
        : __('Free version: select one primary category. Additional category assignments are a Pro feature.', 'cci-blog');

    return (
        <Field label={__('Categories', 'cci-blog')} note={note}>
            {!isPro ? (
                <button
                    type='button'
                    className='tw-mb-2 tw-grid tw-w-full tw-cursor-pointer tw-grid-cols-[auto_minmax(0,1fr)] tw-items-center tw-gap-2 tw-rounded-md tw-border tw-border-solid tw-border-amber-200 tw-bg-amber-50 tw-px-3 tw-py-2 tw-text-left tw-text-sm tw-leading-5 tw-text-amber-900 tw-transition-colors hover:tw-border-amber-300 hover:tw-bg-amber-100 focus-visible:tw-outline focus-visible:tw-outline-2 focus-visible:tw-outline-amber-400'
                    onClick={onProFeatureClick}
                >
                    <ProBadge className='tw-px-1.5 tw-py-0.5'>{__('Pro', 'cci-blog')}</ProBadge>
                    <span className='tw-min-w-0'>
                        <strong className='tw-font-semibold'>{__('Additional categories', 'cci-blog')}</strong>
                        <span className='tw-ml-1 tw-text-amber-800'>{__('Assign posts to multiple categories in Pro.', 'cci-blog')}</span>
                    </span>
                </button>
            ) : null}
            {rows.length ? (
                <div className='tw-grid tw-gap-3'>
                    <Input
                        type='search'
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder={__('Search categories by title or slug', 'cci-blog')}
                        aria-label={__('Search categories by title or slug', 'cci-blog')}
                    />
                    <div data-cci-blog-category-tree='true' className='tw-grid tw-max-h-80 tw-auto-rows-max tw-content-start tw-gap-1 tw-overflow-auto tw-rounded-md tw-border tw-border-solid tw-border-cci-blog-border tw-bg-white tw-p-1'>
                        {visibleRows.length ? visibleRows.map((row) => {
                            const checked = selectedSet.has(row.id);
                            const isPrimary = row.id === primaryCategoryId;
                            const isCollapsed = !matchingIds && collapsedIds.has(row.id);

                            return (
                                <CategoryTreeRow
                                    key={row.id}
                                    row={row}
                                    checked={checked}
                                    isPrimary={isPrimary}
                                    isPro={isPro}
                                    isCollapsed={isCollapsed}
                                    searching={Boolean(matchingIds)}
                                    onToggleCollapsed={toggleCollapsed}
                                    onToggle={onToggle}
                                    onSetPrimary={onSetPrimary}
                                />
                            );
                        }) : (
                            <span role='status' className='tw-p-2 tw-text-sm tw-text-cci-blog-muted'>
                                {__('No categories found.', 'cci-blog')}
                            </span>
                        )}
                    </div>
                </div>
            ) : (
                <div className='tw-rounded-md tw-border tw-border-solid tw-border-cci-blog-border tw-bg-cci-blog-surfaceSoft tw-p-3 tw-text-sm tw-text-cci-blog-muted'>
                    {__('Create a category before assigning posts.', 'cci-blog')}
                </div>
            )}
        </Field>
    );
}

function CategoryTreeRow({ row, checked, isPrimary, isPro, isCollapsed, searching, onToggleCollapsed, onToggle, onSetPrimary }) {
    const ToggleIcon = isCollapsed ? ChevronRight : ChevronDown;
    const canSetPrimary = isPro && checked && !isPrimary;

    return (
        <div
            data-cci-blog-category-row='true'
            data-cci-blog-category-depth={row.depth}
            className={[
                'tw-grid tw-min-h-11 tw-grid-cols-[auto_auto_minmax(0,1fr)] sm:tw-grid-cols-[auto_auto_minmax(0,1fr)_auto] tw-items-center tw-gap-2 tw-rounded-md tw-border tw-border-solid tw-py-2 tw-pr-2 tw-text-sm tw-leading-5 tw-transition-colors',
                checked ? 'tw-border-cci-blog-brandBorder tw-bg-cci-blog-brandSoft' : 'tw-border-transparent tw-bg-transparent hover:tw-border-cci-blog-brandBorder hover:tw-bg-cci-blog-brandSoft',
            ].join(' ')}
            style={{ paddingLeft: `${6 + Math.min(row.depth, 4) * 18}px` }}
        >
            <button
                type='button'
                className={[
                    'tw-inline-flex tw-h-7 tw-w-4 tw-items-center tw-justify-center tw-rounded tw-border-0 tw-p-0 tw-shadow-none tw-transition-colors',
                    row.hasChildren ? 'tw-cursor-pointer tw-bg-transparent tw-text-cci-blog-muted hover:tw-bg-cci-blog-brandSoft hover:tw-text-cci-blog-brand' : 'tw-cursor-default tw-bg-transparent tw-text-transparent',
                ].join(' ')}
                disabled={!row.hasChildren || searching}
                aria-label={`${isCollapsed ? __('Expand category', 'cci-blog') : __('Collapse category', 'cci-blog')}: ${row.name}`}
                aria-expanded={row.hasChildren ? !isCollapsed : undefined}
                onClick={() => row.hasChildren && onToggleCollapsed(row.id)}
            >
                {row.hasChildren ? <ToggleIcon aria-hidden='true' className='tw-h-4 tw-w-4' /> : null}
            </button>
            <Checkbox aria-label={row.name} checked={checked} onCheckedChange={(value) => onToggle(row.id, value === true)} />
            <button type='button' className='tw-grid tw-min-w-0 tw-cursor-pointer tw-grid-cols-[auto_minmax(0,1fr)] tw-items-center tw-gap-2 tw-border-0 tw-bg-transparent tw-p-0 tw-text-left' onClick={() => onToggle(row.id, !checked)}>
                <span className='tw-inline-flex tw-h-7 tw-w-7 tw-items-center tw-justify-center tw-rounded-md tw-bg-cci-blog-brandSoft tw-text-cci-blog-brand'>
                    <FolderTree aria-hidden='true' className='tw-h-4 tw-w-4' />
                </span>
                <span className='tw-min-w-0'>
                    <span title={row.name} className='tw-block tw-truncate tw-text-sm tw-font-semibold tw-leading-5 tw-text-cci-blog-text'>{row.name}</span>
                    <span title={row.source.slug} className='tw-block tw-truncate tw-text-xs tw-leading-5 tw-text-cci-blog-muted'>{row.source.slug || `#${row.id}`}</span>
                </span>
                {isPrimary ? (
                    <span className='tw-col-start-2 tw-justify-self-start tw-inline-flex tw-shrink-0 tw-rounded-full tw-border tw-border-solid tw-border-cci-blog-brandBorder tw-bg-white tw-px-2 tw-py-0.5 tw-text-[11px] tw-font-bold tw-uppercase tw-leading-none tw-text-cci-blog-brand'>
                        {__('Primary', 'cci-blog')}
                    </span>
                ) : checked ? (
                    <span className='tw-col-start-2 tw-justify-self-start tw-inline-flex tw-shrink-0 tw-rounded-full tw-border tw-border-solid tw-border-cci-blog-border tw-bg-white tw-px-2 tw-py-0.5 tw-text-[11px] tw-font-bold tw-uppercase tw-leading-none tw-text-cci-blog-muted'>
                        {__('Additional', 'cci-blog')}
                    </span>
                ) : null}
            </button>
            {canSetPrimary ? (
                <Button variant='secondary' size='xs' type='button' className='tw-col-start-3 tw-justify-self-start sm:tw-col-auto' onClick={() => onSetPrimary(row.id)}>
                    {__('Set primary', 'cci-blog')}
                </Button>
            ) : null}
        </div>
    );
}

function normalizeAdminSlug(value) {
    const replacements = {
        '\u0105': 'a',
        '\u0107': 'c',
        '\u0119': 'e',
        '\u0142': 'l',
        '\u0144': 'n',
        '\u00f3': 'o',
        '\u015b': 's',
        '\u017a': 'z',
        '\u017c': 'z',
        '\u0104': 'a',
        '\u0106': 'c',
        '\u0118': 'e',
        '\u0141': 'l',
        '\u0143': 'n',
        '\u00d3': 'o',
        '\u015a': 's',
        '\u0179': 'z',
        '\u017b': 'z',
    };

    return String(value || '')
        .replace(/[\u0104-\u017c]/g, (character) => replacements[character] || character)
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

const POST_TABLE_PAGE_SIZE_OPTIONS = [10, 20, 50];
const POST_TABLE_DEFAULT_COLUMNS = [
    { id: 'title', label: 'Title', sortable: true },
    { id: 'author', label: 'Author', sortable: true },
    { id: 'category', label: 'Category', sortable: true },
    { id: 'status', label: 'Status', sortable: true },
    { id: 'seo', label: 'SEO', sortable: true },
    { id: 'updated', label: 'Updated', sortable: true },
];

function PostsTable({ posts, pagination, isPro = false, onPostsResponse, onNotice, onOpenPost, onDeletePost }) {
    const [query, setQuery] = React.useState(pagination?.query || '');
    const [loading, setLoading] = React.useState(false);
    const [tableState, setTableState] = React.useState({
        page: Number(pagination?.page || 1),
        limit: Number(pagination?.limit || 10),
        sort: pagination?.sort || 'updated',
        direction: pagination?.direction || 'desc',
        query: pagination?.query || '',
    });
    const initialLoadSkipped = React.useRef(false);
    const proExtensions = React.useMemo(() => getPostTableProExtensions(isPro), [isPro]);
    const columns = React.useMemo(
        () => applyPostTableColumnExtensions(POST_TABLE_DEFAULT_COLUMNS, proExtensions),
        [proExtensions]
    );
    const totalRows = Number(pagination?.total ?? posts.length);
    const totalPages = Math.max(1, Number(pagination?.totalPages || 1));
    const safePage = Math.min(Number(pagination?.page || tableState.page || 1), totalPages);
    const pageSize = Number(pagination?.limit || tableState.limit || 10);

    React.useEffect(() => {
        if (!initialLoadSkipped.current) {
            initialLoadSkipped.current = true;
            return undefined;
        }

        const controller = new AbortController();
        setLoading(true);
        apiFetch('/posts/list', {
            body: JSON.stringify(buildPostTableRequest(tableState, proExtensions)),
            signal: controller.signal,
        })
            .then((response) => {
                if (typeof onPostsResponse === 'function') {
                    onPostsResponse(response);
                }
            })
            .catch((error) => {
                if (error.name === 'AbortError') {
                    return;
                }

                if (typeof onNotice === 'function') {
                    onNotice({ type: 'error', message: error.message, details: error.details || '' });
                }
            })
            .finally(() => setLoading(false));

        return () => controller.abort();
    }, [tableState, proExtensions, onPostsResponse, onNotice]);

    const changeSort = (columnId) => {
        setTableState((current) => {
            if (current.sort !== columnId) {
                return { ...current, page: 1, sort: columnId, direction: 'asc' };
            }

            return { ...current, page: 1, direction: current.direction === 'asc' ? 'desc' : 'asc' };
        });
    };
    const submitSearch = (event) => {
        event.preventDefault();
        const nextQuery = query.trim();

        setTableState((current) => (
            current.query === nextQuery ? current : { ...current, page: 1, query: nextQuery }
        ));
    };
    const tableColumns = columns
        .filter((column) => column.visible !== false)
        .map((column) => ({
            id: column.id,
            label: __(column.label, 'cci-blog'),
            sortable: column.sortable !== false,
            sortDirection: tableState.sort === column.id ? (tableState.direction === 'asc' ? 'ascending' : 'descending') : 'none',
            sortIcon: tableState.sort === column.id ? (tableState.direction === 'asc' ? '↑' : '↓') : '↕',
            onSort: () => changeSort(column.id),
        }));

    if (!posts.length && !query && !tableState.query) {
        return <EmptyState label={__('No posts yet.', 'cci-blog')} />;
    }

    return (
        <div className='tw-grid tw-min-w-0 tw-grid-cols-1 tw-gap-3'>
            <div className='tw-border-0 tw-border-b tw-border-solid tw-border-cci-blog-border tw-px-4 tw-py-4'>
                <form className='tw-flex tw-max-w-md tw-items-center tw-gap-2' role='search' onSubmit={submitSearch}>
                    <Input
                        value={query}
                        placeholder={__('Search by title, author or category...', 'cci-blog')}
                        aria-label={__('Search posts', 'cci-blog')}
                        className='!tw-h-10 !tw-rounded-md !tw-border-cci-blog-border !tw-bg-white !tw-px-3 !tw-text-sm !tw-shadow-none focus:!tw-border-cci-blog-brand focus:!tw-ring-2 focus:!tw-ring-cci-blog-brandSoft'
                        onChange={(event) => setQuery(event.target.value)}
                    />
                    <button
                        type='submit'
                        disabled={loading}
                        className='tw-inline-flex tw-h-10 tw-w-10 tw-shrink-0 tw-items-center tw-justify-center tw-rounded-md tw-border tw-border-solid tw-border-cci-blog-brand tw-bg-cci-blog-brand tw-text-white tw-shadow-sm tw-transition hover:tw-bg-cci-blog-brandStrong focus:tw-outline-none focus:tw-ring-2 focus:tw-ring-cci-blog-brandSoft'
                        aria-label={__('Search posts', 'cci-blog')}
                    >
                        <Search className='tw-h-4 tw-w-4' aria-hidden='true' />
                    </button>
                </form>
            </div>

            <div className='tw-relative'>
                {posts.length ? (
                    <DataTable
                        columns={tableColumns}
                        rows={posts.map((post) => postTableRow(post, columns, onOpenPost, onDeletePost))}
                    />
                ) : (
                    <EmptyState label={query ? __('No posts match your search.', 'cci-blog') : __('No posts yet.', 'cci-blog')} />
                )}
                {loading && <PostTableLoader />}
            </div>
            {totalRows > 0 && (
                <PostTablePagination
                    page={safePage}
                    totalPages={totalPages}
                    totalRows={totalRows}
                    pageSize={pageSize}
                    onPageSizeChange={(nextPageSize) => setTableState((current) => ({
                        ...current,
                        page: 1,
                        limit: nextPageSize,
                    }))}
                    onPageChange={(nextPage) => setTableState((current) => ({ ...current, page: nextPage }))}
                />
            )}
        </div>
    );
}

function ContentLanguageSelect({ languages, languageId, disabled, onChange }) {
    const options = normalizeContentLanguages(languages, pluginData.language).map((language) => ({
        value: String(language.id),
        label: language.label,
    }));
    const value = String(Number(languageId || pluginData.language?.id || options[0]?.value || 0));

    return (
        <div className='tw-grid tw-min-w-[210px] tw-gap-1'>
            <Select
                value={value}
                disabled={disabled}
                onValueChange={(nextValue) => onChange?.(Number(nextValue))}
                options={options}
            />
        </div>
    );
}

function PostTableLoader() {
    return (
        <div
            className='tw-absolute tw-inset-0 tw-z-10 tw-bg-white tw-px-4 tw-py-3'
            role='status'
            aria-live='polite'
            aria-label={__('Loading posts...', 'cci-blog')}
        >
            <div className='tw-grid tw-gap-3'>
                {Array.from({ length: 4 }).map((_, rowIndex) => (
                    <div
                        key={rowIndex}
                        className='tw-grid tw-grid-cols-[minmax(220px,1.8fr)_minmax(90px,0.7fr)_minmax(120px,0.9fr)_80px_70px_120px] tw-items-center tw-gap-4 tw-border-0 tw-border-b tw-border-solid tw-border-cci-blog-border tw-py-3'
                    >
                        <span className='tw-h-4 tw-animate-pulse tw-rounded-full tw-bg-slate-100' />
                        <span className='tw-h-4 tw-animate-pulse tw-rounded-full tw-bg-slate-100' />
                        <span className='tw-h-4 tw-animate-pulse tw-rounded-full tw-bg-slate-100' />
                        <span className='tw-h-6 tw-animate-pulse tw-rounded-full tw-bg-slate-100' />
                        <span className='tw-h-6 tw-animate-pulse tw-rounded-full tw-bg-slate-100' />
                        <span className='tw-h-4 tw-animate-pulse tw-rounded-full tw-bg-slate-100' />
                    </div>
                ))}
            </div>
        </div>
    );
}

function postTableRow(post, columns, onOpenPost, onDeletePost) {
    const renderers = {
        title: () => (
            <RowPrimary
                label={post.title || __('(no title)', 'cci-blog')}
                onOpen={() => onOpenPost(post)}
                actions={[
                    { label: coreString('edit', 'Edit'), onClick: () => onOpenPost(post) },
                    { label: coreString('delete', 'Delete'), tone: 'danger', onClick: () => onDeletePost(post) },
                ]}
                actionsLabel={__('Post actions', 'cci-blog')}
            />
        ),
        author: () => post.author_name || '-',
        category: () => post.category_names || post.category_name || '-',
        status: () => <PublicationStatusBadge published={Number(post.active) === 1} />,
        seo: () => <PostSeoStatus post={post} />,
        updated: () => formatAdminDateTime(post.date_upd || post.date_published, pluginData.adminDate) || '—',
    };

    return columns
        .filter((column) => column.visible !== false)
        .map((column) => {
            if (typeof column.render === 'function') {
                return column.render(post);
            }

            return renderers[column.id] ? renderers[column.id]() : '-';
        });
}

function buildPostTableRequest(tableState, proExtensions) {
    const request = {
        page: Number(tableState.page || 1),
        limit: Number(tableState.limit || 10),
        sort: tableState.sort || 'updated',
        direction: tableState.direction === 'asc' ? 'asc' : 'desc',
        query: tableState.query || '',
    };

    if (proExtensions && typeof proExtensions.buildRequest === 'function') {
        const extra = proExtensions.buildRequest(request);
        if (extra && typeof extra === 'object') {
            request.pro = extra;
        }
    }

    return request;
}

function PostTablePagination({ page, totalPages, totalRows, pageSize, onPageSizeChange, onPageChange }) {
    const firstRow = totalRows ? ((page - 1) * pageSize) + 1 : 0;
    const lastRow = Math.min(page * pageSize, totalRows);
    const hasPagination = totalPages > 1;

    return (
        <div className='tw-flex tw-flex-wrap tw-items-center tw-gap-2 tw-border-0 tw-border-t tw-border-solid tw-border-cci-blog-border tw-px-4 tw-py-3 tw-text-sm tw-text-cci-blog-muted'>
            <span>
                {__('Showing', 'cci-blog')} {firstRow}-{lastRow} {__('of', 'cci-blog')} {totalRows}
            </span>
            <div className='tw-w-20'>
                <Select
                    ariaLabel={__('Rows per page', 'cci-blog')}
                    options={POST_TABLE_PAGE_SIZE_OPTIONS.map((option) => ({
                        value: String(option),
                        label: String(option),
                    }))}
                    value={String(pageSize)}
                    onValueChange={(value) => onPageSizeChange?.(Number(value) || 10)}
                />
            </div>
            <div className='tw-ml-auto tw-flex tw-items-center tw-justify-end tw-gap-2'>
                {hasPagination && (
                    <>
                        <Button
                            variant='secondary'
                            size='sm'
                            disabled={page <= 1}
                            onClick={() => onPageChange(Math.max(1, page - 1))}
                        >
                            {__('Previous', 'cci-blog')}
                        </Button>
                        <span className='tw-min-w-20 tw-text-center'>
                            {page} / {totalPages}
                        </span>
                        <Button
                            variant='secondary'
                            size='sm'
                            disabled={page >= totalPages}
                            onClick={() => onPageChange(Math.min(totalPages, page + 1))}
                        >
                            {__('Next', 'cci-blog')}
                        </Button>
                    </>
                )}
            </div>
        </div>
    );
}

function getPostTableProExtensions(isPro) {
    if (!isPro || typeof window === 'undefined') {
        return {};
    }

    const registry = window.cciBlogProPostTableExtensions;
    if (!registry || typeof registry !== 'object') {
        return {};
    }

    return {
        columns: Array.isArray(registry.columns) ? registry.columns.filter((column) => column && typeof column === 'object') : [],
        filters: Array.isArray(registry.filters) ? registry.filters.filter((filter) => filter && typeof filter === 'object') : [],
        sorters: registry.sorters && typeof registry.sorters === 'object' ? registry.sorters : {},
        buildRequest: typeof registry.buildRequest === 'function' ? registry.buildRequest : null,
    };
}

function applyPostTableColumnExtensions(columns, proExtensions) {
    const proColumns = Array.isArray(proExtensions.columns) ? proExtensions.columns : [];

    return [
        ...columns,
        ...proColumns.map((column) => ({
            ...column,
            label: column.label || column.id || 'Column',
            sortable: column.sortable !== false,
        })),
    ];
}

function PostSeoStatus({ post }) {
    const serverScore = Number.isFinite(Number(post?.seo_score)) ? Number(post.seo_score) : null;
    const audit = React.useMemo(() => {
        if (serverScore !== null) {
            return {
                score: serverScore,
                overallStatus: serverScore >= 80 ? 'good' : serverScore >= 55 ? 'warning' : 'bad',
            };
        }

        return calculateSeoAudit(normalizePost(post));
    }, [post, serverScore]);

    return <SeoStatusBadge audit={audit} />;
}

function CategorySeoStatus({ category }) {
    const audit = React.useMemo(() => calculateSeoAudit(buildCategorySeoDraft(category)), [category]);

    return <SeoStatusBadge audit={audit} />;
}

function SeoStatusBadge({ audit }) {
    const tone = seoStatusTone(audit.overallStatus);

    return (
        <span
            className={`tw-inline-flex tw-min-w-14 tw-items-center tw-justify-center tw-rounded-full tw-border tw-border-solid tw-px-2 tw-py-1 tw-text-[11px] tw-font-bold tw-leading-none ${tone.badge}`}
            aria-label={`${__('SEO score', 'cci-blog')}: ${audit.score}/100, ${statusLabel(audit.overallStatus)}`}
            title={`${__('SEO score', 'cci-blog')}: ${audit.score}/100 — ${statusLabel(audit.overallStatus)}`}
        >
            {audit.score}/100
        </span>
    );
}

function CategoriesPanel({ categories, saving, onCreateCategory, onOpenCategory, onDeleteCategory }) {
    const categoryRows = buildCategoryOptions(categories);
    return (
        <>
            <ResourceListHeader
                title={__('Categories', 'cci-blog')}
                description={__('Manage nested blog categories and storefront taxonomy.', 'cci-blog')}
                createLabel={__('New category', 'cci-blog')}
                disabled={saving}
                onCreate={onCreateCategory}
            />
            {categories.length ? (
                <DataTable
                    columns={[__('Name', 'cci-blog'), __('Author', 'cci-blog'), __('Parent', 'cci-blog'), __('Posts', 'cci-blog'), __('Status', 'cci-blog'), __('SEO', 'cci-blog')]}
                    rows={categoryRows.map((category) => [
                        <RowPrimary
                            label={category.label}
                            onOpen={() => onOpenCategory(category.source)}
                            actions={[
                                { label: coreString('edit', 'Edit'), onClick: () => onOpenCategory(category.source) },
                                { label: coreString('delete', 'Delete'), tone: 'danger', onClick: () => onDeleteCategory(category.source) },
                            ]}
                            actionsLabel={__('Category actions', 'cci-blog')}
                        />,
                        category.source.author_name || '-',
                        category.source.parent_name || '-',
                        category.source.post_count || 0,
                        <StatusBadge active={Number(category.source.active) === 1} />,
                        <CategorySeoStatus category={category.source} />,
                    ])}
                />
            ) : <EmptyState label={__('No categories yet.', 'cci-blog')} />}
        </>
    );
}

function MultistoreAssignment({ context, entityId, entityLabel, saving, onAssign }) {
    const targetShops = React.useMemo(
        () => (Array.isArray(context?.shops) ? context.shops : []).filter((shop) => !shop.current && shop.active !== false),
        [context]
    );
    const [selectedShopIds, setSelectedShopIds] = React.useState([]);

    React.useEffect(() => {
        setSelectedShopIds([]);
    }, [entityId, context?.currentShopId]);

    if (!context?.enabled || !context?.featureActive || targetShops.length === 0 || typeof onAssign !== 'function') {
        return null;
    }

    const toggleShop = (shopId, checked) => {
        setSelectedShopIds((current) => checked
            ? Array.from(new Set([...current, shopId]))
            : current.filter((id) => id !== shopId));
    };
    const assign = () => {
        if (!entityId || selectedShopIds.length === 0) {
            return;
        }

        Promise.resolve(onAssign(selectedShopIds))
            .then(() => setSelectedShopIds([]))
            .catch(() => {});
    };

    return (
        <section className='tw-grid tw-gap-3 tw-border-0 tw-border-b tw-border-solid tw-border-cci-blog-border tw-bg-white tw-px-5 tw-py-4 lg:tw-grid-cols-[minmax(180px,0.65fr)_minmax(0,1fr)_auto] lg:tw-items-center'>
            <div>
                <strong className='tw-flex tw-items-center tw-gap-2 tw-text-sm tw-font-semibold tw-text-cci-blog-text'>
                    <Store aria-hidden='true' className='tw-h-4 tw-w-4 tw-text-cci-blog-brand' />
                    {__('Multistore', 'cci-blog')}
                </strong>
                <p className='tw-m-0 tw-mt-1 tw-text-xs tw-leading-5 tw-text-cci-blog-muted'>
                    {entityId
                        ? __('Assign this content to additional shops without overwriting existing shop content.', 'cci-blog')
                        : __('Save this content before assigning it to another shop.', 'cci-blog')}
                </p>
            </div>
            <div className='tw-flex tw-flex-wrap tw-gap-2'>
                {targetShops.map((shop) => {
                    const shopId = Number(shop.id || 0);
                    const checked = selectedShopIds.includes(shopId);

                    return (
                        <label key={shopId} className='tw-flex tw-cursor-pointer tw-items-center tw-gap-2 tw-rounded-md tw-border tw-border-solid tw-border-cci-blog-border tw-bg-slate-50 tw-px-3 tw-py-2 tw-text-sm tw-font-medium tw-text-cci-blog-text'>
                            <Checkbox checked={checked} onCheckedChange={(value) => toggleShop(shopId, value === true)} />
                            <span>{shop.name || `#${shopId}`}</span>
                        </label>
                    );
                })}
            </div>
            <Button variant='secondary' type='button' disabled={saving || !entityId || selectedShopIds.length === 0} onClick={assign}>
                <Store aria-hidden='true' />
                {saving
                    ? __('Assigning...', 'cci-blog')
                    : `${__('Assign', 'cci-blog')} ${entityLabel}`}
            </Button>
        </section>
    );
}

function CategoryEditor({ category, authors, categories, tab, saving, onBack, onChange, onSave, onTabChange, onOpenSettings, languages, translationsEnabled, onLanguageChange, multistore, onAssignToShops }) {
    const [proFeature, setProFeature] = React.useState(null);
    const parentOptions = React.useMemo(
        () => buildCategoryOptions(categories, { excludeId: Number(category.id_category || 0) }),
        [categories, category.id_category]
    );
    const debouncedCategory = useDebouncedValue(category, 350);
    const authorOptions = React.useMemo(
        () => buildAuthorOptions(authors, category.id_author, category.author_name),
        [authors, category.id_author, category.author_name]
    );
    const seoAudit = React.useMemo(() => calculateSeoAudit(buildCategorySeoDraft(debouncedCategory)), [debouncedCategory]);
    const categoryBaseUrl = buildCategoryStorefrontBase();
    const previewSlug = category.slug || normalizeAdminSlug(category.name || '') || normalizeAdminSlug(__('auto-generated from title', 'cci-blog'));
    const previewTitle = category.meta_title || category.name || __('Untitled category', 'cci-blog');
    const previewDescription = category.meta_description || category.description || __('Meta description will appear here.', 'cci-blog');
    const previewUrl = `${categoryBaseUrl}${previewSlug}`;
    const change = (patch) => onChange({ ...category, ...patch });
    const changeFocusKeyword = (value) => change({ focus_keyword: value, meta_keywords: value });

    return (
        <>
        <Card className='tw-overflow-visible'>
            <CardHeader className='tw-flex tw-min-h-16 tw-flex-row tw-items-center tw-justify-between tw-gap-4 tw-border-0 tw-border-b tw-border-solid tw-border-cci-blog-border tw-bg-white tw-px-5 tw-py-4'>
                <Button variant='ghost' type='button' onClick={onBack}>
                    <ArrowLeft aria-hidden='true' />
                    {__('Back to categories', 'cci-blog')}
                </Button>
                <div className='tw-flex tw-flex-wrap tw-items-center tw-justify-end tw-gap-2'>
                    <ContentLanguageSelect
                        languages={languages}
                        languageId={category.id_lang}
                        disabled={!translationsEnabled || saving}
                        onChange={onLanguageChange}
                    />
                    <Button variant='save' type='button' onClick={onSave} disabled={saving} aria-busy={saving}>
                        <Save aria-hidden='true' />
                        {saving ? __('Saving...', 'cci-blog') : __('Save category', 'cci-blog')}
                    </Button>
                </div>
            </CardHeader>

            <div className='tw-grid tw-gap-3 tw-border-0 tw-border-b tw-border-solid tw-border-cci-blog-border tw-bg-white tw-px-5 tw-py-3'>
                <Input
                    className='tw-h-12 tw-text-base tw-font-semibold'
                    value={category.name || ''}
                    onChange={(event) => change({ name: event.target.value })}
                    placeholder={__('Category title', 'cci-blog')}
                />
                <SlugPermalink
                    slug={category.slug || ''}
                    title={category.name || ''}
                    baseUrl={categoryBaseUrl}
                    onChange={(slug) => change({ slug })}
                />
            </div>

            <MultistoreAssignment
                context={multistore}
                entityId={Number(category.id_category || 0)}
                entityLabel={__('category', 'cci-blog')}
                saving={saving}
                onAssign={onAssignToShops}
            />

            <Tabs value={tab} onValueChange={onTabChange} className='tw-grid tw-gap-0'>
                <div className='tw-h-14 tw-border-0 tw-border-b tw-border-solid tw-border-cci-blog-border tw-bg-white tw-px-4'>
                    <TabsList flush className='tw-h-full tw-gap-6'>
                        {editorTabs.map((item) => {
                            const Icon = item.icon;
                            return (
                                <TabsTrigger key={item.value} value={item.value} className='cci-blog-tab-trigger !tw-h-full tw-mb-[-1px] tw-gap-2 tw-rounded-none tw-border-0 tw-border-b-2 tw-border-solid tw-border-transparent tw-bg-transparent tw-px-0 data-[state=active]:tw-border-cci-blog-brand data-[state=active]:tw-bg-transparent data-[state=active]:tw-text-cci-blog-brand'>
                                    <Icon aria-hidden='true' />
                                    {item.label}
                                </TabsTrigger>
                            );
                        })}
                    </TabsList>
                </div>

                <CardContent className='tw-bg-slate-50 tw-p-4'>
                    <TabsPanel value='content' transparent className='tw-m-0 tw-border-0'>
                        <div className='cci-blog-editor-layout-with-seo tw-grid tw-gap-4'>
                            <div className='tw-grid tw-content-start tw-gap-4'>
                                <section className='tw-rounded-md tw-border tw-border-solid tw-border-cci-blog-border tw-bg-white tw-p-4'>
                                    <div className='tw-grid tw-gap-3'>
                                        <div>
                                            <strong className='tw-block tw-text-base tw-font-semibold tw-text-cci-blog-text'>{__('Category details', 'cci-blog')}</strong>
                                            <p className='tw-m-0 tw-mt-1 tw-text-xs tw-leading-5 tw-text-cci-blog-muted'>{__('Organize posts in nested category pages and blog navigation.', 'cci-blog')}</p>
                                        </div>
                                        <div className='cci-blog-post-settings-grid tw-grid tw-gap-3'>
                                            <Field
                                                label={__('Parent category', 'cci-blog')}
                                                note={__('Leave empty for a top-level category.', 'cci-blog')}
                                            >
                                                <Select
                                                    value={String(Number(category.id_parent || 0))}
                                                    onValueChange={(value) => change({ id_parent: Number(value) })}
                                                    options={[
                                                        { value: '0', label: __('No parent category', 'cci-blog') },
                                                        ...parentOptions.map((option) => ({ value: String(option.id), label: option.label })),
                                                    ]}
                                                />
                                            </Field>
                                            <Field label={__('Position', 'cci-blog')}>
                                                <Input type='number' min='0' value={Number(category.position || 0)} onChange={(event) => change({ position: Number(event.target.value || 0) })} />
                                            </Field>
                                            <Field label={__('Author', 'cci-blog')}>
                                                <Select
                                                    value={category.id_author ? String(category.id_author) : ''}
                                                    onValueChange={(value) => change({ id_author: Number(value) })}
                                                    options={authorOptions}
                                                    placeholder={__('Select author', 'cci-blog')}
                                                />
                                            </Field>
                                        </div>
                                        <Field
                                            label={__('Category thumbnail URL', 'cci-blog')}
                                            note={__('Optional image used by blog category blocks in navigation and category listings.', 'cci-blog')}
                                        >
                                            <LocalMediaUrlInput
                                                value={category.image_url || ''}
                                                onChange={(image_url) => change({ image_url })}
                                                onLockedClick={() => setProFeature(blogProFeatures.localMediaLibrary)}
                                                placeholder='/img/category.jpg'
                                            />
                                        </Field>
                                        <Field
                                            label={__('Description', 'cci-blog')}
                                            note={__('Used on category pages and as source text for SEO checks.', 'cci-blog')}
                                        >
                                            <Textarea value={category.description || ''} onChange={(event) => change({ description: event.target.value })} />
                                        </Field>
                                    </div>
                                </section>

                                <section className='tw-rounded-md tw-border tw-border-solid tw-border-cci-blog-border tw-bg-white tw-p-4'>
                                    <div className='tw-grid tw-gap-3'>
                                        <div>
                                            <strong className='tw-block tw-text-base tw-font-semibold tw-text-cci-blog-text'>{__('Category settings', 'cci-blog')}</strong>
                                            <p className='tw-m-0 tw-mt-1 tw-text-xs tw-leading-5 tw-text-cci-blog-muted'>{__('Nested categories can group posts in blog navigation and category pages.', 'cci-blog')}</p>
                                        </div>
                                        <ToggleField checked={Boolean(Number(category.active))} label={__('Active', 'cci-blog')} onChange={(checked) => change({ active: checked ? 1 : 0 })} />
                                        <div className='tw-rounded-md tw-border tw-border-solid tw-border-cci-blog-border tw-bg-cci-blog-surfaceSoft tw-p-3 tw-text-xs tw-leading-5 tw-text-cci-blog-muted'>
                                            {__('A post can belong to many categories, but the primary category is selected in the post editor.', 'cci-blog')}
                                        </div>
                                    </div>
                                </section>
                            </div>

                            <aside className='cci-blog-seo-sidebar tw-z-10 tw-grid tw-content-start tw-gap-4'>
                                <SeoContentAssistPanel
                                    audit={seoAudit}
                                    compact
                                    description={__('SEO analysis', 'cci-blog')}
                                    openLabel={__('Open category SEO', 'cci-blog')}
                                    onOpenSeo={() => onTabChange('seo')}
                                />
                            </aside>
                        </div>
                    </TabsPanel>

                    <TabsPanel value='seo' className='tw-m-0 tw-border-0 tw-bg-transparent'>
                        <div className='cci-blog-seo-tab-layout tw-grid tw-gap-4'>
                            <div className='tw-grid tw-content-start tw-gap-4'>
                                <section className='tw-rounded-md tw-border tw-border-solid tw-border-cci-blog-border tw-bg-white tw-p-4'>
                                    <div className='tw-grid tw-gap-3'>
                                        <div>
                                            <strong className='tw-block tw-text-base tw-font-semibold tw-text-cci-blog-text'>{__('Category SEO', 'cci-blog')}</strong>
                                            <p className='tw-m-0 tw-mt-1 tw-text-xs tw-leading-5 tw-text-cci-blog-muted'>{__('Optimize the category landing page while editing its title, slug and description.', 'cci-blog')}</p>
                                        </div>
                                        <div className='tw-grid tw-gap-3 md:tw-grid-cols-2'>
                                            <Field
                                                label={__('Focus keyword', 'cci-blog')}
                                                note={__('Saved as category meta keywords for the main shop language.', 'cci-blog')}
                                            >
                                                <Input
                                                    value={category.focus_keyword || category.meta_keywords || ''}
                                                    onChange={(event) => changeFocusKeyword(event.target.value)}
                                                    placeholder={__('main search phrase', 'cci-blog')}
                                                />
                                            </Field>
                                            <Field label={__('Title tag', 'cci-blog')}>
                                                <Input value={category.meta_title || ''} onChange={(event) => change({ meta_title: event.target.value })} placeholder={category.name || __('Category title fallback', 'cci-blog')} />
                                            </Field>
                                            <Field label={__('Meta description', 'cci-blog')} className='md:tw-col-span-2'>
                                                <Textarea value={category.meta_description || ''} onChange={(event) => change({ meta_description: event.target.value })} />
                                            </Field>
                                        </div>
                                    </div>
                                </section>

                                <section className='tw-rounded-md tw-border tw-border-solid tw-border-cci-blog-border tw-bg-white tw-p-4'>
                                    <div className='tw-grid tw-gap-2'>
                                        <strong className='tw-text-base tw-font-semibold tw-text-cci-blog-text'>{__('Search preview', 'cci-blog')}</strong>
                                        <div className='tw-rounded-md tw-border tw-border-solid tw-border-cci-blog-border tw-bg-cci-blog-surfaceSoft tw-p-4'>
                                            <div className='tw-break-words tw-text-lg tw-font-semibold tw-leading-6 tw-text-blue-700'>{previewTitle}</div>
                                            <div className='tw-mt-1 tw-break-all tw-text-xs tw-leading-5 tw-text-green-700'>{previewUrl}</div>
                                            <p className='tw-m-0 tw-mt-2 tw-text-sm tw-leading-6 tw-text-cci-blog-muted'>{previewDescription}</p>
                                        </div>
                                    </div>
                                </section>

                                <SeoChecksPanel audit={seoAudit} />
                            </div>

                            <aside className='cci-blog-seo-sidebar tw-grid tw-content-start tw-gap-4'>
                                <SeoScorePanel
                                    audit={seoAudit}
                                    description={__('SEO analysis', 'cci-blog')}
                                />
                            </aside>
                        </div>
                    </TabsPanel>
                </CardContent>
            </Tabs>
        </Card>
        <ProUpgradeModal
            open={Boolean(proFeature)}
            featureName={proFeature?.featureName || ''}
            description={proFeature?.description || ''}
            benefits={proFeature?.benefits || []}
            onActivateLicense={() => {
                setProFeature(null);
                onOpenSettings?.();
            }}
            onClose={() => setProFeature(null)}
        />
        </>
    );
}

function CommentsPanel({ comments, commentsProvider, disqusShortname, saving, onUpdateStatus, onDeleteComment }) {
    if (commentsProvider === 'disqus') {
        const moderationUrl = disqusShortname
            ? `https://${encodeURIComponent(disqusShortname)}.disqus.com/admin/moderate/`
            : '';

        return (
            <>
                <CardHeader>
                    <div>
                        <h2>{__('Disqus comments', 'cci-blog')}</h2>
                        <p>{__('Reader comments are managed in the Disqus moderation panel.', 'cci-blog')}</p>
                    </div>
                </CardHeader>
                <CardContent className='tw-grid tw-gap-3'>
                    {moderationUrl ? (
                        <Button variant='primary' className='tw-w-fit' asChild>
                            <a href={moderationUrl} target='_blank' rel='noopener noreferrer'>
                                {__('Open Disqus moderation', 'cci-blog')}
                            </a>
                        </Button>
                    ) : (
                        <EmptyState label={__('Add the Disqus shortname in Settings to activate comments.', 'cci-blog')} />
                    )}
                </CardContent>
            </>
        );
    }

    if (!comments.length) {
        return (
            <>
                <CardHeader>
                    <div>
                        <h2>{__('Comments', 'cci-blog')}</h2>
                        <p>{__('Moderate reader comments without leaving the React admin.', 'cci-blog')}</p>
                    </div>
                </CardHeader>
                <EmptyState label={__('No comments yet.', 'cci-blog')} />
            </>
        );
    }

    return (
        <>
            <CardHeader>
                <div>
                    <h2>{__('Comments', 'cci-blog')}</h2>
                    <p>{__('Moderate reader comments without leaving the React admin.', 'cci-blog')}</p>
                </div>
            </CardHeader>
            <DataTable
                columns={[__('Author', 'cci-blog'), __('Post', 'cci-blog'), __('Comment', 'cci-blog'), __('Status', 'cci-blog')]}
                rows={comments.map((comment) => [
                    <span>
                        <span className='tw-font-semibold tw-text-cci-blog-text'>{comment.author_name || '-'}</span><br />
                        <span className='tw-text-xs tw-text-cci-blog-muted'>{comment.author_email}</span>
                        <RowActions
                            ariaLabel={__('Comment actions', 'cci-blog')}
                            actions={[
                                { label: __('Approve', 'cci-blog'), disabled: saving || comment.status === 'approved', onClick: () => onUpdateStatus(comment, 'approved') },
                                { label: __('Spam', 'cci-blog'), disabled: saving || comment.status === 'spam', onClick: () => onUpdateStatus(comment, 'spam') },
                                { label: coreString('delete', 'Delete'), tone: 'danger', disabled: saving, onClick: () => onDeleteComment(comment) },
                            ]}
                        />
                    </span>,
                    comment.post_title || `#${comment.id_post}`,
                    <span className='tw-line-clamp-2'>{comment.content}</span>,
                    <span className='tw-capitalize'>{comment.status}</span>,
                ])}
            />
        </>
    );
}

function ToggleField({ checked, label, icon, onChange }) {
    return <CheckboxField checked={checked} icon={icon} onCheckedChange={(value) => onChange(value === true)}>{label}</CheckboxField>;
}

function StatusBadge({ active }) {
    return <SharedStatusBadge active={active}>{active ? __('Active', 'cci-blog') : __('Inactive', 'cci-blog')}</SharedStatusBadge>;
}

function PublicationStatusBadge({ published }) {
    return <SharedStatusBadge active={published}>{published ? __('Published', 'cci-blog') : __('Draft', 'cci-blog')}</SharedStatusBadge>;
}

function createBlock(typeValue) {
    const [type, level] = String(typeValue || 'paragraph').split(':');

    if (type === 'heading') return { type, level: Number(level || 2), text: __('Heading', 'cci-blog') };
    if (type === 'link') return { type, label: __('Link label', 'cci-blog'), href: '', title: '', target: '', rel: '', variant: '', id: '', aria_label: '' };
    if (type === 'image') return { type, src: '', alt: '', caption: '', link: {} };
    if (type === 'columns') return { type, gap: '1rem', columns: [createColumn(), createColumn()] };
    if (type === 'product') return { type, id_product: '', label: __('Mentioned product', 'cci-blog') };
    if (type === 'product_carousel') return { type, title: __('Recommended products', 'cci-blog'), ids: '' };
    if (type === 'hook') return { type, hook: defaultContentHookName };
    return { type: 'paragraph', content: '', html: '' };
}

function normalizeColumnGap(value) {
    return normalizeCssLengthValue(value, {
        fallback: '1rem',
        units: columnGapUnits,
        min: 0,
        max: 1000,
    });
}

function normalizeLinkVariant(value) {
    const normalized = String(value || '').trim().toLowerCase().replace(/[^a-z0-9_-]+/g, '_');
    return ['button', 'muted'].includes(normalized) ? normalized : '';
}

function normalizeContentBlocks(blocks) {
    return (Array.isArray(blocks) ? blocks : [])
        .filter((block) => block && typeof block === 'object')
        .map((block) => {
            if (block.type === 'link') {
                const { class: ignoredClass, ...safeBlock } = block;
                return {
                    ...safeBlock,
                    variant: normalizeLinkVariant(block.variant || block.link_variant || ''),
                };
            }

            if (block.type === 'image' && block.link && typeof block.link === 'object') {
                const { class: ignoredClass, ...safeLink } = block.link;
                return {
                    ...block,
                    link: {
                        ...safeLink,
                        variant: normalizeLinkVariant(block.link.variant || block.link.link_variant || ''),
                    },
                };
            }

            if (block.type !== 'columns') {
                return block;
            }

            const columns = (Array.isArray(block.columns) ? block.columns : [])
                .map((column) => {
                    if (!column || typeof column !== 'object') {
                        return column;
                    }

                    const { class: ignoredClass, ...safeColumn } = column;

                    return {
                        ...safeColumn,
                        blocks: Array.isArray(column.blocks) ? normalizeContentBlocks(column.blocks) : column.blocks,
                    };
                });

            return {
                ...block,
                gap: normalizeColumnGap(block.gap || '1rem'),
                columns,
            };
        });
}

function getBlockText(block) {
    if (!block || typeof block !== 'object') {
        return '';
    }

    if (block.type === 'heading') return String(block.text || '').trim();
    if (block.type === 'paragraph') return String(block.content || htmlToPlainText(block.html || '')).trim();
    if (block.type === 'link') return String(block.label || block.href || '').trim();
    if (block.type === 'image') return String(block.caption || block.alt || block.src || '').trim();
    if (block.type === 'product') return String(block.label || '').trim();
    if (block.type === 'product_carousel') return String(block.title || '').trim();
    if (block.type === 'hook') return String(block.hook || '').trim();
    if (block.type === 'columns') {
        return (block.columns || [])
            .map((column) => {
                if (column && typeof column === 'object') {
                    return String(column.content || htmlToPlainText(column.html || '')).trim();
                }

                return String(column || '').trim();
            })
            .filter(Boolean)
            .join('\n\n');
    }

    return '';
}

function blocksToDraftHtml(blocks) {
    return (Array.isArray(blocks) ? blocks : [])
        .map((block) => {
            if (block.type === 'heading') {
                const level = Math.max(2, Math.min(4, Number(block.level || 2)));
                const text = String(block.text || '').trim();
                return text ? `<h${level}>${escapeHtml(text)}</h${level}>` : '';
            }
            if (block.type === 'paragraph') {
                return block.html || plainTextToHtml(block.content || '');
            }
            if (block.type === 'link') {
                const label = String(block.label || block.href || '').trim();
                if (!label && !block.href) {
                    return '';
                }

                return `<p><a${linkAttributesToHtml(block)}>${escapeHtml(label || block.href)}</a></p>`;
            }

            const text = getBlockText(block);
            return text ? `<p>${escapeHtml(text)}</p>` : '';
        })
        .filter(Boolean)
        .join('\n');
}

function parseDraftHtmlToBlocks(rawHtml) {
    const html = String(rawHtml || '').trim();
    if (!html) {
        return [];
    }
    if (typeof document === 'undefined' || !/<[a-z][\s\S]*>/i.test(html)) {
        return parsePlainTextToBlocks(html);
    }

    const template = document.createElement('template');
    template.innerHTML = sanitizeDraftHtml(html);
    const blocks = [];

    Array.from(template.content.childNodes).forEach((node) => {
        blocks.push(...nodeToBlocks(node));
    });

    return blocks.filter(Boolean);
}

function nodeToBlocks(node) {
    if (node.nodeType === Node.TEXT_NODE) {
        const text = normalizePlainText(node.textContent || '');
        return text ? [{ type: 'paragraph', content: text, html: plainTextToHtml(text) }] : [];
    }
    if (node.nodeType !== Node.ELEMENT_NODE) {
        return [];
    }

    const tagName = node.tagName.toLowerCase();
    const text = normalizePlainText(node.textContent || '');
    if (!text && tagName !== 'br') {
        return [];
    }

    if (/^h[2-4]$/.test(tagName)) {
        return [{ type: 'heading', level: Number(tagName.slice(1)), text }];
    }

    if (tagName === 'a') {
        return [anchorToLinkBlock(node, text)];
    }

    if ((tagName === 'p' || tagName === 'div') && isSingleAnchorBlock(node)) {
        const anchor = node.querySelector('a');
        return anchor ? [anchorToLinkBlock(anchor, text)] : [];
    }

    if (['p', 'div', 'ul', 'ol', 'blockquote'].includes(tagName)) {
        const richHtml = sanitizeDraftHtml(node.outerHTML);
        return hasRichMarkup(node)
            ? [{ type: 'paragraph', content: text, html: richHtml }]
            : [{ type: 'paragraph', content: text, html: plainTextToHtml(text) }];
    }

    return Array.from(node.childNodes).flatMap((child) => nodeToBlocks(child));
}

function anchorToLinkBlock(anchor, fallbackLabel = '') {
    return {
        type: 'link',
        label: normalizePlainText(anchor.textContent || fallbackLabel),
        href: anchor.getAttribute('href') || '',
        title: anchor.getAttribute('title') || '',
        target: anchor.getAttribute('target') || '',
        rel: anchor.getAttribute('rel') || '',
        variant: '',
        id: anchor.getAttribute('id') || '',
        aria_label: anchor.getAttribute('aria-label') || '',
    };
}

function parsePlainTextToBlocks(rawText) {
    const text = normalizePlainText(rawText);
    if (!text) {
        return [];
    }

    let chunks = text.split(/\n{2,}/).map((chunk) => chunk.trim()).filter(Boolean);
    if (chunks.length <= 1) {
        chunks = text.split('\n').map((chunk) => chunk.trim()).filter(Boolean);
    }

    return chunks.flatMap((chunk) => convertTextChunkToBlocks(chunk)).filter(Boolean);
}

function convertTextChunkToBlocks(chunk) {
    const lines = chunk.split('\n').map((line) => line.trim()).filter(Boolean);
    if (!lines.length) {
        return [];
    }

    const firstLine = lines[0];
    const markdownHeading = firstLine.match(/^(#{1,4})\s+(.+)$/);
    if (markdownHeading && lines.length === 1) {
        return [{
            type: 'heading',
            level: Math.max(2, Math.min(4, markdownHeading[1].length)),
            text: markdownHeading[2].trim(),
        }];
    }

    if (lines.length === 1) {
        return [isLikelyHeading(firstLine)
            ? { type: 'heading', level: 2, text: firstLine }
            : { type: 'paragraph', content: firstLine, html: plainTextToHtml(firstLine) }];
    }

    if (isLikelyHeading(firstLine)) {
        return [
            { type: 'heading', level: 2, text: firstLine },
            { type: 'paragraph', content: lines.slice(1).join('\n'), html: plainTextToHtml(lines.slice(1).join('\n')) },
        ];
    }

    return [{ type: 'paragraph', content: lines.join('\n'), html: plainTextToHtml(lines.join('\n')) }];
}

function normalizePlainText(text) {
    return String(text || '')
        .replace(/\r\n?/g, '\n')
        .replace(/\u00a0/g, ' ')
        .replace(/[ \t]+\n/g, '\n')
        .replace(/\n[ \t]+/g, '\n')
        .trim();
}

function isLikelyHeading(line) {
    const text = String(line || '').trim();
    if (!text || text.length > 90) {
        return false;
    }
    if (/^(?:[-*+]|\d+[.)])\s+/.test(text)) {
        return false;
    }
    if (/[.!?]$/.test(text)) {
        return false;
    }

    return text.split(/\s+/).filter(Boolean).length <= 10;
}

function createColumn() {
    return { content: '', html: '', span_desktop: 6, span_tablet: 6, span_mobile: 12, id: '' };
}

function normalizeColumns(columns) {
    const source = Array.isArray(columns) && columns.length ? columns : [createColumn(), createColumn()];

    return source.map((column) => {
        const data = column && typeof column === 'object' ? column : { content: column || '' };
        const { class: ignoredClass, ...safeData } = data;

        return {
            ...createColumn(),
            ...safeData,
            span_desktop: normalizeSpan(data.span_desktop, 6),
            span_tablet: normalizeSpan(data.span_tablet, 6),
            span_mobile: normalizeSpan(data.span_mobile, 12),
        };
    });
}

function normalizeSpan(value, fallback) {
    const span = Number(value || fallback);
    return Math.max(1, Math.min(12, Number.isFinite(span) ? span : fallback));
}

function plainTextToHtml(text) {
    const normalized = normalizePlainText(text);
    if (!normalized) {
        return '';
    }

    return normalized
        .split(/\n{2,}/)
        .map((paragraph) => `<p>${escapeHtml(paragraph).replace(/\n/g, '<br>')}</p>`)
        .join('');
}

function htmlToPlainText(html) {
    if (typeof document === 'undefined') {
        return String(html || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
    }

    const template = document.createElement('template');
    template.innerHTML = sanitizeDraftHtml(html || '');
    return normalizePlainText(template.content.textContent || '');
}

function sanitizeDraftHtml(html) {
    if (typeof document === 'undefined') {
        return String(html || '');
    }

    const template = document.createElement('template');
    template.innerHTML = String(html || '');
    const allowedTags = new Set(['P', 'BR', 'STRONG', 'B', 'EM', 'I', 'U', 'UL', 'OL', 'LI', 'BLOCKQUOTE', 'A', 'H2', 'H3', 'H4', 'DIV']);
    const walker = document.createTreeWalker(template.content, NodeFilter.SHOW_ELEMENT);
    const elements = [];

    while (walker.nextNode()) {
        elements.push(walker.currentNode);
    }

    elements.reverse().forEach((element) => {
        if (!allowedTags.has(element.tagName)) {
            element.replaceWith(document.createTextNode(element.textContent || ''));
            return;
        }

        Array.from(element.attributes).forEach((attribute) => {
            const name = attribute.name.toLowerCase();
            const value = attribute.value || '';

            if (element.tagName === 'A' && ['href', 'title', 'target', 'rel', 'class', 'id', 'aria-label'].includes(name)) {
                if (name === 'href' && !isSafeUrl(value)) {
                    element.removeAttribute(name);
                }
                return;
            }

            element.removeAttribute(attribute.name);
        });
    });

    return template.innerHTML;
}

function hasRichMarkup(node) {
    return Boolean(node.querySelector('a,strong,b,em,i,u,ul,ol,li,blockquote,br,h2,h3,h4'));
}

function isSingleAnchorBlock(node) {
    const anchors = node.querySelectorAll('a');
    return anchors.length === 1 && normalizePlainText(node.textContent || '') === normalizePlainText(anchors[0].textContent || '');
}

function linkAttributesToHtml(link) {
    const variant = normalizeLinkVariant(link.variant || link.link_variant || '');
    const attributes = [
        ['href', link.href || '#'],
        ['title', link.title || ''],
        ['target', link.target || ''],
        ['rel', link.rel || ''],
        ['class', variant ? `cci-blog-content-link cci-blog-content-link-${variant}` : ''],
        ['id', link.id || ''],
        ['aria-label', link.aria_label || ''],
    ];

    return attributes
        .filter(([name, value]) => value && (name !== 'href' || isSafeUrl(value) || value === '#'))
        .map(([name, value]) => ` ${name}="${escapeAttribute(value)}"`)
        .join('');
}

function isSafeUrl(value) {
    const url = String(value || '').trim().toLowerCase();
    return url === '' || url.startsWith('#') || url.startsWith('/') || url.startsWith('http://') || url.startsWith('https://') || url.startsWith('mailto:') || url.startsWith('tel:');
}

function escapeHtml(value) {
    return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function escapeAttribute(value) {
    return escapeHtml(value).replace(/`/g, '&#096;');
}

const seoStopWordsByLanguage = {
    en: new Set(['a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from', 'in', 'is', 'it', 'of', 'on', 'or', 'the', 'to', 'with', 'you', 'your']),
    pl: new Set(['i', 'a', 'ale', 'albo', 'bez', 'bo', 'czy', 'dla', 'do', 'jak', 'jest', 'lub', 'na', 'nad', 'od', 'oraz', 'po', 'pod', 'przez', 'sie', 'się', 'to', 'u', 'w', 'we', 'z', 'za', 'ze']),
    de: new Set(['aber', 'als', 'am', 'an', 'auch', 'auf', 'aus', 'bei', 'das', 'dem', 'den', 'der', 'des', 'die', 'ein', 'eine', 'fur', 'für', 'im', 'in', 'ist', 'mit', 'und', 'von', 'zu']),
};

const seoContentTypeConfig = {
    article: {
        label: __('Article', 'cci-blog'),
        description: __('Standard commerce article.', 'cci-blog'),
        warning: 120,
        ok: 300,
        good: 600,
    },
    guide: {
        label: __('Guide', 'cci-blog'),
        description: __('Longer educational or how-to content.', 'cci-blog'),
        warning: 300,
        ok: 700,
        good: 1200,
    },
    news: {
        label: __('News', 'cci-blog'),
        description: __('Short announcement or update.', 'cci-blog'),
        warning: 80,
        ok: 180,
        good: 350,
    },
    landing: {
        label: __('Landing article', 'cci-blog'),
        description: __('Commercial landing-style content with focused copy.', 'cci-blog'),
        warning: 180,
        ok: 400,
        good: 700,
    },
    category: {
        label: __('Category page', 'cci-blog'),
        description: __('Blog category landing and listing content.', 'cci-blog'),
        warning: 40,
        ok: 80,
        good: 160,
        hiddenInPost: true,
    },
};

function seoContentTypeOptions() {
    return Object.entries(seoContentTypeConfig)
        .filter(([, config]) => !config.hiddenInPost)
        .map(([value, config]) => ({
            value,
            label: config.label,
        }));
}

function buildCategorySeoDraft(category = {}) {
    const title = String(category.name || '').trim();
    const description = String(category.description || '').trim();
    const focusKeyword = String(category.focus_keyword || category.meta_keywords || '').split(',')[0].trim();

    return {
        title,
        slug: category.slug || normalizeAdminSlug(title),
        intro: description,
        meta_title: category.meta_title || title,
        meta_description: category.meta_description || description,
        focus_keyword: focusKeyword,
        seo_content_type: 'category',
        blocks: title ? [{ type: 'heading', level: 2, text: title }] : [],
    };
}

function calculateSeoAudit(post = {}) {
    const keyword = String(post.focus_keyword || '').trim();
    const languageIso = getSeoLanguageIso();
    const stopWords = getSeoStopWords(languageIso);
    const contentType = normalizeSeoContentType(post.seo_content_type);
    const contentTypeConfig = seoContentTypeConfig[contentType] || seoContentTypeConfig.article;
    const isCategoryContent = contentType === 'category';
    const keywordSearch = normalizeSearchText(keyword);
    const keywordSlug = normalizeAdminSlug(keyword);
    const titleTag = String(post.meta_title || post.title || '').trim();
    const metaDescription = String(post.meta_description || '').trim();
    const slug = normalizeAdminSlug(post.slug || post.title || '');
    const content = extractSeoContent(post);
    const bodyText = content.text;
    const bodyWords = wordsFromText(bodyText);
    const firstParagraph = wordsFromText(content.paragraphs[0] || '').slice(0, 100).join(' ');
    const h1Count = (post.title ? 1 : 0) + content.headings.filter((heading) => Number(heading.level) === 1).length;
    const h2Headings = content.headings.filter((heading) => Number(heading.level) === 2);
    const keywordOccurrences = countKeywordOccurrences(bodyText, keyword, stopWords);
    const keywordDensity = bodyWords.length ? Number(((keywordOccurrences / bodyWords.length) * 100).toFixed(2)) : 0;
    const sentenceStats = calculateSentenceStats(bodyText);
    const titlePixels = Math.round(measureTextWidth(titleTag));
    const titleLength = titleTag.length;
    const metaLength = metaDescription.length;
    const internalLinks = content.links.filter((link) => isInternalLink(link.href)).length;
    const externalLinks = content.links.filter((link) => isExternalLink(link.href)).length;
    const imageAltMissing = content.images.filter((image) => !String(image.alt || '').trim()).length;
    const randomImageNames = content.images.filter((image) => imageFilenameLooksRandom(image.src)).length;
    const slugWords = slug.split('-').filter(Boolean);
    const slugStopWords = slugWords.filter((word) => stopWords.has(word)).length;
    const checks = [];
    const add = (check) => checks.push(check);
    const keywordNotSetMessage = __('Set a focus keyword to run this check.', 'cci-blog');
    const titleWidthMessage = `${__('Estimated search width:', 'cci-blog')} ${titlePixels}px.`;

    add({
        id: 'focus_keyword_set',
        group: 'keyword',
        label: __('Focus keyword', 'cci-blog'),
        status: keyword ? 'good' : 'bad',
        message: keyword ? `${__('Focus keyword:', 'cci-blog')} ${keyword}` : (isCategoryContent ? __('Add the main search phrase for this category.', 'cci-blog') : __('Add the main search phrase for this article.', 'cci-blog')),
        weight: 8,
    });

    add({
        id: 'keyword_in_title',
        group: 'keyword',
        label: __('Keyword in title tag', 'cci-blog'),
        status: keyword ? (containsKeyword(titleTag, keyword, stopWords) ? 'good' : 'bad') : 'bad',
        message: keyword ? (containsKeyword(titleTag, keyword, stopWords) ? __('The focus keyword appears in the title tag.', 'cci-blog') : __('Add the focus keyword to the title tag.', 'cci-blog')) : keywordNotSetMessage,
        weight: 12,
    });

    add({
        id: 'keyword_in_meta',
        group: 'keyword',
        label: __('Keyword in meta description', 'cci-blog'),
        status: keyword ? (containsKeyword(metaDescription, keyword, stopWords) ? 'good' : 'warning') : 'bad',
        message: keyword ? (containsKeyword(metaDescription, keyword, stopWords) ? __('The focus keyword appears in the meta description.', 'cci-blog') : __('Use the focus keyword naturally in the meta description.', 'cci-blog')) : keywordNotSetMessage,
        weight: 8,
    });

    add({
        id: 'keyword_in_slug',
        group: 'keyword',
        label: __('Keyword in URL', 'cci-blog'),
        status: keyword ? (slugContainsKeyword(slug, keyword, stopWords) ? 'good' : 'warning') : 'bad',
        message: keyword ? (slugContainsKeyword(slug, keyword, stopWords) ? __('The URL contains the focus keyword.', 'cci-blog') : __('Add the focus keyword to the slug when it reads naturally.', 'cci-blog')) : keywordNotSetMessage,
        weight: 8,
    });

    add({
        id: 'keyword_in_first_paragraph',
        group: 'keyword',
        label: __('Keyword in first paragraph', 'cci-blog'),
        status: keyword ? (containsKeyword(firstParagraph, keyword, stopWords) ? 'good' : 'warning') : 'bad',
        message: keyword ? (containsKeyword(firstParagraph, keyword, stopWords) ? __('The focus keyword appears in the first 100 words.', 'cci-blog') : __('Use the focus keyword in the opening paragraph.', 'cci-blog')) : keywordNotSetMessage,
        weight: 8,
    });

    add({
        id: 'keyword_in_h2',
        group: 'keyword',
        label: __('Keyword in H2', 'cci-blog'),
        status: keyword ? (h2Headings.some((heading) => containsKeyword(heading.text, keyword, stopWords)) ? 'good' : 'warning') : 'bad',
        message: keyword ? (h2Headings.some((heading) => containsKeyword(heading.text, keyword, stopWords)) ? __('At least one H2 contains the focus keyword or a close variant.', 'cci-blog') : __('Add the focus keyword or a close variant to one H2.', 'cci-blog')) : keywordNotSetMessage,
        weight: 8,
    });

    add({
        id: 'keyword_in_image_alt',
        group: 'keyword',
        label: __('Keyword in image alt', 'cci-blog'),
        status: keyword ? (content.images.some((image) => containsKeyword(image.alt, keyword, stopWords)) ? 'good' : content.images.length ? 'warning' : 'info') : 'bad',
        message: keyword ? (content.images.some((image) => containsKeyword(image.alt, keyword, stopWords)) ? __('At least one image alt contains the focus keyword.', 'cci-blog') : content.images.length ? __('Use the focus keyword in one image alt when it describes the image.', 'cci-blog') : __('No images are available for this check.', 'cci-blog')) : keywordNotSetMessage,
        weight: 5,
    });

    add({
        id: 'title_length',
        group: 'title',
        label: __('Title tag length', 'cci-blog'),
        status: titleLength >= 45 && titleLength <= 65 && titlePixels <= 600 ? 'good' : titleLength >= 30 && titlePixels <= 680 ? 'warning' : 'bad',
        message: `${__('Title tag length:', 'cci-blog')} ${titleLength} ${__('characters', 'cci-blog')}. ${titleWidthMessage}`,
        help: __('Length is counted in characters. Pixel width is an estimate of how much space the title may take in search results.', 'cci-blog'),
        weight: 12,
    });

    add({
        id: 'keyword_near_title_start',
        group: 'title',
        label: __('Keyword near title start', 'cci-blog'),
        status: keyword ? (keywordStartsEarly(titleTag, keyword, stopWords) ? 'good' : containsKeyword(titleTag, keyword, stopWords) ? 'warning' : 'bad') : 'bad',
        message: keyword ? (keywordStartsEarly(titleTag, keyword, stopWords) ? __('The focus keyword appears near the start of the title tag.', 'cci-blog') : __('Move the focus keyword closer to the start of the title tag.', 'cci-blog')) : keywordNotSetMessage,
        weight: 6,
    });

    add({
        id: 'meta_length',
        group: 'meta',
        label: __('Meta description length', 'cci-blog'),
        status: metaLength >= 120 && metaLength <= 158 ? 'good' : metaLength >= 90 && metaLength <= 175 ? 'warning' : 'bad',
        message: `${__('Meta description length:', 'cci-blog')} ${metaLength}/120-158 ${__('characters', 'cci-blog')}.`,
        weight: 10,
    });

    add({
        id: 'meta_sentence',
        group: 'meta',
        label: __('Meta description sentence', 'cci-blog'),
        status: metaDescription && /[.!?]$/.test(metaDescription) ? 'good' : metaDescription ? 'warning' : 'bad',
        message: metaDescription && /[.!?]$/.test(metaDescription) ? __('The meta description ends with a complete sentence.', 'cci-blog') : __('End the meta description with a complete sentence.', 'cci-blog'),
        weight: 4,
    });

    add({
        id: 'slug_short',
        group: 'slug',
        label: __('Short slug', 'cci-blog'),
        status: slug && slug.length <= 75 && slugWords.length <= 8 ? 'good' : slug ? 'warning' : 'bad',
        message: slug ? `${__('Slug:', 'cci-blog')} ${slug}` : __('Create a readable slug for this post.', 'cci-blog'),
        weight: 6,
    });

    add({
        id: 'slug_stop_words',
        group: 'slug',
        label: __('Slug stop-words', 'cci-blog'),
        status: slugStopWords === 0 ? 'good' : slugStopWords <= 1 ? 'warning' : 'bad',
        message: slugStopWords === 0 ? __('The slug does not contain obvious stop-words.', 'cci-blog') : `${__('Stop-words found in slug:', 'cci-blog')} ${slugStopWords}.`,
        weight: 4,
    });

    add({
        id: 'content_type',
        group: 'content',
        label: __('Content type', 'cci-blog'),
        status: 'good',
        message: `${contentTypeConfig.label}: ${contentTypeConfig.description}`,
        weight: 2,
    });

    add({
        id: 'content_length',
        group: 'content',
        label: __('Content length', 'cci-blog'),
        status: contentLengthStatus(bodyWords.length, contentTypeConfig),
        message: `${__('Word count:', 'cci-blog')} ${bodyWords.length}. ${__('Targets:', 'cci-blog')} ${contentTypeConfig.warning}/${contentTypeConfig.ok}/${contentTypeConfig.good}. ${contentLengthMessage(bodyWords.length, contentTypeConfig)}`,
        weight: 8,
    });

    add({
        id: 'single_h1',
        group: 'content',
        label: __('Single H1', 'cci-blog'),
        status: h1Count === 1 ? 'good' : 'bad',
        message: h1Count === 1 ? (isCategoryContent ? __('The category title is the only H1.', 'cci-blog') : __('The article title is the only H1.', 'cci-blog')) : `${__('H1 count:', 'cci-blog')} ${h1Count}. ${__('Use exactly one H1.', 'cci-blog')}`,
        weight: 8,
    });

    add({
        id: 'heading_hierarchy',
        group: 'content',
        label: __('Heading hierarchy', 'cci-blog'),
        status: hasValidHeadingHierarchy(content.headings) ? 'good' : 'warning',
        message: hasValidHeadingHierarchy(content.headings) ? __('Heading levels do not skip hierarchy.', 'cci-blog') : __('Avoid jumps such as H2 to H4 or H3 before the first H2.', 'cci-blog'),
        weight: 8,
    });

    add({
        id: 'keyword_density',
        group: 'content',
        label: __('Keyword density', 'cci-blog'),
        status: keyword ? (keywordDensity >= 0.3 && keywordDensity <= 2.5 ? 'good' : keywordDensity > 0 ? 'warning' : 'bad') : 'bad',
        message: keyword ? `${__('Keyword density:', 'cci-blog')} ${keywordDensity}%.` : keywordNotSetMessage,
        help: __('Keyword density is not a ranking factor. Use it only to avoid unnatural repetition.', 'cci-blog'),
        weight: 4,
    });

    add({
        id: 'internal_link',
        group: 'links',
        label: __('Internal links', 'cci-blog'),
        status: internalLinks > 0 ? 'good' : 'warning',
        message: internalLinks > 0 ? `${__('Internal links:', 'cci-blog')} ${internalLinks}.` : __('Add at least one internal link to another post, category or page.', 'cci-blog'),
        weight: 6,
    });

    add({
        id: 'external_link',
        group: 'links',
        label: __('External links', 'cci-blog'),
        status: externalLinks > 0 ? 'good' : 'info',
        message: externalLinks > 0 ? `${__('External links:', 'cci-blog')} ${externalLinks}.` : __('No external authority link found.', 'cci-blog'),
        weight: 4,
    });

    add({
        id: 'image_alt',
        group: 'media',
        label: __('Image alt text', 'cci-blog'),
        status: content.images.length === 0 ? 'info' : imageAltMissing === 0 ? 'good' : 'bad',
        message: content.images.length === 0 ? __('No images in content.', 'cci-blog') : imageAltMissing === 0 ? __('Every image has alt text.', 'cci-blog') : `${__('Images missing alt:', 'cci-blog')} ${imageAltMissing}.`,
        weight: 6,
    });

    add({
        id: 'image_filename',
        group: 'media',
        label: __('Image filenames', 'cci-blog'),
        status: content.images.length === 0 ? 'info' : randomImageNames === 0 ? 'good' : 'warning',
        message: content.images.length === 0 ? __('No image filenames to audit.', 'cci-blog') : randomImageNames === 0 ? __('Image filenames look readable.', 'cci-blog') : `${__('Possibly random image filenames:', 'cci-blog')} ${randomImageNames}.`,
        weight: 4,
    });

    add({
        id: 'readability_sentences',
        group: 'readability',
        label: __('Sentence length', 'cci-blog'),
        status: sentenceStats.count === 0 ? 'info' : sentenceStats.average <= 20 && sentenceStats.longPercent <= 25 ? 'good' : sentenceStats.average <= 26 ? 'warning' : 'bad',
        message: sentenceStats.count === 0 ? __('No complete sentences detected yet.', 'cci-blog') : `${__('Average sentence:', 'cci-blog')} ${sentenceStats.average} ${__('words', 'cci-blog')}. ${sentenceStats.longPercent}% ${__('over 20 words', 'cci-blog')}.`,
        weight: 5,
    });

    const totalWeight = checks.reduce((sum, check) => sum + check.weight, 0);
    const earnedWeight = checks.reduce((sum, check) => sum + check.weight * seoStatusFactor(check.status), 0);
    const score = totalWeight > 0 ? Math.max(0, Math.min(100, Math.round((earnedWeight / totalWeight) * 100))) : 0;

    return {
        score,
        overallStatus: score >= 80 ? 'good' : score >= 55 ? 'warning' : 'bad',
        checks,
        metrics: {
            wordCount: bodyWords.length,
            keywordDensity,
            titleLength,
            titlePixels,
            metaLength,
            internalLinks,
            externalLinks,
            images: content.images.length,
            headings: content.headings.length,
            keywordOccurrences,
            keyword: keywordSearch,
            keywordSlug,
            contentType,
            contentTypeLabel: contentTypeConfig.label,
            languageIso,
        },
    };
}

function getSeoPriorityChecks(checks, limit = 4) {
    const rank = { bad: 0, warning: 1, info: 2, good: 3 };
    const filtered = (Array.isArray(checks) ? checks : [])
        .filter((check) => check.id !== 'content_type')
        .sort((a, b) => {
            const statusDelta = (rank[a.status] ?? 2) - (rank[b.status] ?? 2);
            if (statusDelta !== 0) {
                return statusDelta;
            }

            return Number(b.weight || 0) - Number(a.weight || 0);
        });

    const needsWork = filtered.filter((check) => check.status !== 'good').slice(0, limit);
    if (needsWork.length > 0) {
        return needsWork;
    }

    return filtered.slice(0, limit);
}

function extractSeoContent(post = {}) {
    const content = {
        textParts: [],
        paragraphs: [],
        headings: [],
        images: [],
        links: [],
        text: '',
    };

    const intro = normalizePlainText(post.intro || '');
    if (intro) {
        content.paragraphs.push(intro);
        content.textParts.push(intro);
    }

    collectSeoBlocks(Array.isArray(post.blocks) ? post.blocks : [], content);
    content.text = normalizePlainText(content.textParts.join('\n\n'));

    return content;
}

function collectSeoBlocks(blocks, content) {
    (Array.isArray(blocks) ? blocks : []).forEach((block) => collectSeoBlock(block, content));
}

function collectSeoBlock(block, content) {
    if (!block || typeof block !== 'object') {
        return;
    }

    if (block.type === 'heading') {
        const text = normalizePlainText(block.text || '');
        if (text) {
            content.headings.push({ level: Number(block.level || 2), text });
            content.textParts.push(text);
        }
        return;
    }

    if (block.type === 'paragraph') {
        const html = String(block.html || '').trim();
        if (html) {
            const details = extractHtmlSeoDetails(html);
            content.paragraphs.push(...details.paragraphs);
            content.headings.push(...details.headings);
            content.images.push(...details.images);
            content.links.push(...details.links);
            if (details.text) {
                content.textParts.push(details.text);
            }
            return;
        }

        const text = normalizePlainText(block.content || '');
        if (text) {
            content.paragraphs.push(text);
            content.textParts.push(text);
        }
        return;
    }

    if (block.type === 'link') {
        const label = normalizePlainText(block.label || block.href || '');
        content.links.push({ href: block.href || '', text: label });
        if (label) {
            content.textParts.push(label);
        }
        return;
    }

    if (block.type === 'image') {
        content.images.push({ src: block.src || '', alt: block.alt || '' });
        const caption = normalizePlainText(block.caption || block.alt || '');
        if (caption) {
            content.paragraphs.push(caption);
            content.textParts.push(caption);
        }
        if (block.link?.href) {
            content.links.push({ href: block.link.href, text: block.link.title || caption });
        }
        return;
    }

    if (block.type === 'columns') {
        (Array.isArray(block.columns) ? block.columns : []).forEach((column) => {
            if (Array.isArray(column?.blocks)) {
                collectSeoBlocks(column.blocks, content);
                return;
            }

            const html = String(column?.html || '').trim();
            if (html) {
                const details = extractHtmlSeoDetails(html);
                content.paragraphs.push(...details.paragraphs);
                content.headings.push(...details.headings);
                content.images.push(...details.images);
                content.links.push(...details.links);
                if (details.text) {
                    content.textParts.push(details.text);
                }
                return;
            }

            const text = normalizePlainText(column?.content || '');
            if (text) {
                content.paragraphs.push(text);
                content.textParts.push(text);
            }
        });
        return;
    }

    const text = normalizePlainText(block.label || block.title || block.caption || '');
    if (text) {
        content.textParts.push(text);
    }
}

function extractHtmlSeoDetails(html) {
    if (typeof document === 'undefined') {
        const text = htmlToPlainText(html);
        return { text, paragraphs: text ? [text] : [], headings: [], images: [], links: [] };
    }

    const template = document.createElement('template');
    template.innerHTML = String(html || '');
    const root = template.content;
    const paragraphs = Array.from(root.querySelectorAll('p, li, blockquote, figcaption'))
        .map((node) => normalizePlainText(node.textContent || ''))
        .filter(Boolean);
    const text = normalizePlainText(root.textContent || '');

    return {
        text,
        paragraphs: paragraphs.length ? paragraphs : (text ? [text] : []),
        headings: Array.from(root.querySelectorAll('h1, h2, h3, h4, h5, h6'))
            .map((node) => ({ level: Number(node.tagName.slice(1)), text: normalizePlainText(node.textContent || '') }))
            .filter((heading) => heading.text),
        images: Array.from(root.querySelectorAll('img')).map((image) => ({
            src: image.getAttribute('src') || '',
            alt: image.getAttribute('alt') || '',
        })),
        links: Array.from(root.querySelectorAll('a[href]')).map((anchor) => ({
            href: anchor.getAttribute('href') || '',
            text: normalizePlainText(anchor.textContent || ''),
        })),
    };
}

function groupSeoChecks(checks) {
    const labels = {
        keyword: __('Focus keyword', 'cci-blog'),
        title: __('Title tag', 'cci-blog'),
        meta: __('Meta description', 'cci-blog'),
        slug: __('Slug and URL', 'cci-blog'),
        content: __('Content structure', 'cci-blog'),
        links: __('Links', 'cci-blog'),
        media: __('Images', 'cci-blog'),
        readability: __('Readability', 'cci-blog'),
    };
    const order = Object.keys(labels);

    return order
        .map((id) => ({
            id,
            label: labels[id],
            checks: checks.filter((check) => check.group === id),
        }))
        .filter((group) => group.checks.length > 0);
}

function seoStatusFactor(status) {
    if (status === 'good' || status === 'info') return 1;
    if (status === 'warning') return 0.5;
    return 0;
}

function seoStatusTone(status) {
    const tones = {
        good: {
            dot: 'tw-bg-green-500',
            badge: 'tw-border-green-200 tw-bg-green-50 tw-text-green-700',
            bar: 'tw-bg-green-500',
            text: 'tw-text-green-700',
        },
        warning: {
            dot: 'tw-bg-amber-500',
            badge: 'tw-border-amber-200 tw-bg-amber-50 tw-text-amber-700',
            bar: 'tw-bg-amber-500',
            text: 'tw-text-amber-700',
        },
        bad: {
            dot: 'tw-bg-red-500',
            badge: 'tw-border-red-200 tw-bg-red-50 tw-text-red-700',
            bar: 'tw-bg-red-500',
            text: 'tw-text-red-700',
        },
        info: {
            dot: 'tw-bg-blue-500',
            badge: 'tw-border-blue-200 tw-bg-blue-50 tw-text-blue-700',
            bar: 'tw-bg-blue-500',
            text: 'tw-text-blue-700',
        },
    };

    return tones[status] || tones.info;
}

function statusLabel(status) {
    if (status === 'good') return __('Good', 'cci-blog');
    if (status === 'warning') return __('Warning', 'cci-blog');
    if (status === 'bad') return __('Needs work', 'cci-blog');
    return __('Info', 'cci-blog');
}

function wordsFromText(text) {
    const normalized = normalizeSearchText(text);
    return normalized.match(/[a-z0-9]+/g) || [];
}

function getSeoLanguageIso() {
    const fromPayload = pluginData.language?.isoCode || pluginData.diagnostics?.prestashop?.language?.isoCode || '';
    const fromDocument = typeof document !== 'undefined' ? (document.documentElement.lang || '') : '';

    return normalizeSearchText(fromPayload || fromDocument || 'en').split(' ')[0].slice(0, 2) || 'en';
}

function getSeoStopWords(languageIso = getSeoLanguageIso()) {
    return seoStopWordsByLanguage[languageIso] || seoStopWordsByLanguage.en;
}

function normalizeSeoContentType(value) {
    const type = String(value || '').trim();

    return seoContentTypeConfig[type] ? type : 'article';
}

function contentLengthStatus(wordCount, config) {
    if (wordCount >= config.ok) {
        return 'good';
    }

    if (wordCount >= config.warning) {
        return 'warning';
    }

    return 'bad';
}

function contentLengthMessage(wordCount, config) {
    if (wordCount >= config.good) {
        return __('Good for long-form depth.', 'cci-blog');
    }

    if (wordCount >= config.ok) {
        return __('Meets the baseline for this content type.', 'cci-blog');
    }

    if (wordCount >= config.warning) {
        return __('Useful start, but consider expanding it.', 'cci-blog');
    }

    return __('Consider expanding the content.', 'cci-blog');
}

function normalizeSearchText(value) {
    return String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

function keywordTerms(keyword, stopWords = getSeoStopWords()) {
    return wordsFromText(keyword).filter((word) => word.length > 2 && !stopWords.has(word));
}

function containsKeyword(value, keyword, stopWords = getSeoStopWords()) {
    const haystack = normalizeSearchText(value);
    const needle = normalizeSearchText(keyword);
    if (!haystack || !needle) {
        return false;
    }

    if (haystack.includes(needle)) {
        return true;
    }

    const terms = keywordTerms(keyword, stopWords);
    return terms.length > 1 && terms.every((term) => keywordTermMatches(haystack, term));
}

function slugContainsKeyword(slug, keyword, stopWords = getSeoStopWords()) {
    const cleanSlug = normalizeAdminSlug(slug);
    const cleanKeyword = normalizeAdminSlug(keyword);
    if (!cleanSlug || !cleanKeyword) {
        return false;
    }

    if (cleanSlug.includes(cleanKeyword)) {
        return true;
    }

    const slugWords = cleanSlug.split('-').filter(Boolean);
    const terms = keywordTerms(keyword, stopWords);
    return terms.length > 1 && terms.every((term) => slugWords.some((word) => keywordTermMatches(word, term)));
}

function keywordStartsEarly(value, keyword, stopWords = getSeoStopWords()) {
    const haystack = normalizeSearchText(value);
    const needle = normalizeSearchText(keyword);
    if (!haystack || !needle) {
        return false;
    }

    const index = haystack.indexOf(needle);
    if (index >= 0) {
        return index <= Math.max(30, Math.floor(haystack.length * 0.35));
    }

    const firstTerm = keywordTerms(keyword, stopWords)[0] || '';
    const variantIndex = firstTerm ? keywordTermIndex(haystack, firstTerm) : -1;

    return variantIndex >= 0 && variantIndex <= Math.max(30, Math.floor(haystack.length * 0.35));
}

function countKeywordOccurrences(value, keyword, stopWords = getSeoStopWords()) {
    const haystack = normalizeSearchText(value);
    const needle = normalizeSearchText(keyword);
    if (!haystack || !needle) {
        return 0;
    }

    const escaped = needle.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const exact = (haystack.match(new RegExp(`(^|\\s)${escaped}(?=\\s|$)`, 'g')) || []).length;
    if (exact > 0) {
        return exact;
    }

    const terms = keywordTerms(keyword, stopWords);
    if (terms.length < 2) {
        return 0;
    }

    return Math.min(...terms.map((term) => countKeywordTermOccurrences(haystack, term)));
}

function keywordTermMatches(haystack, term) {
    return keywordTermIndex(haystack, term) >= 0;
}

function keywordTermIndex(haystack, term) {
    const variants = keywordTermVariants(term);

    return variants.reduce((best, variant) => {
        const index = haystack.indexOf(variant);
        if (index < 0) {
            return best;
        }

        return best < 0 ? index : Math.min(best, index);
    }, -1);
}

function countKeywordTermOccurrences(haystack, term) {
    return keywordTermVariants(term).reduce((count, variant) => {
        const escaped = variant.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

        return Math.max(count, (haystack.match(new RegExp(`(^|\\s)${escaped}`, 'g')) || []).length);
    }, 0);
}

function keywordTermVariants(term) {
    const normalized = normalizeSearchText(term);
    if (normalized.length <= 4) {
        return [normalized];
    }

    const stemLength = normalized.length > 8 ? normalized.length - 3 : normalized.length - 2;
    const stem = normalized.slice(0, Math.max(4, stemLength));

    return Array.from(new Set([normalized, stem]));
}

function measureTextWidth(text) {
    if (typeof document === 'undefined') {
        return String(text || '').length * 8;
    }

    const canvas = measureTextWidth.canvas || (measureTextWidth.canvas = document.createElement('canvas'));
    const context = canvas.getContext('2d');
    if (!context) {
        return String(text || '').length * 8;
    }

    context.font = '20px Arial, sans-serif';
    return context.measureText(String(text || '')).width;
}

function hasValidHeadingHierarchy(headings) {
    let previousLevel = 1;
    let hasH2 = false;

    return headings.every((heading) => {
        const level = Number(heading.level || 2);
        if (level === 2) {
            hasH2 = true;
        }
        if (level > 2 && !hasH2) {
            return false;
        }
        const valid = level <= previousLevel + 1;
        previousLevel = level;
        return valid;
    });
}

function isInternalLink(href) {
    const value = String(href || '').trim();
    if (!value) {
        return false;
    }
    if (value.startsWith('/') || value.startsWith('#')) {
        return true;
    }
    if (!/^https?:\/\//i.test(value)) {
        return true;
    }

    try {
        const url = new URL(value, window.location.origin);
        return url.origin === window.location.origin;
    } catch (error) {
        return false;
    }
}

function isExternalLink(href) {
    const value = String(href || '').trim();
    if (!/^https?:\/\//i.test(value)) {
        return false;
    }

    try {
        const url = new URL(value, window.location.origin);
        return url.origin !== window.location.origin;
    } catch (error) {
        return false;
    }
}

function imageFilenameLooksRandom(src) {
    const value = String(src || '').trim();
    if (!value) {
        return false;
    }

    let pathname = value;
    try {
        pathname = new URL(value, window.location.origin).pathname;
    } catch (error) {
        pathname = value.split('?')[0];
    }

    const filename = pathname.split('/').pop() || '';
    const base = filename.replace(/\.[a-z0-9]{2,6}$/i, '').replace(/[-_]+/g, '');
    if (base.length < 8) {
        return false;
    }

    if (/^[a-f0-9]{10,}$/i.test(base) || /^[0-9]{6,}$/.test(base)) {
        return true;
    }

    const letters = base.replace(/[^a-z]/gi, '');
    if (letters.length >= 10) {
        const vowels = (letters.match(/[aeiouy]/gi) || []).length;
        return vowels / letters.length < 0.18;
    }

    return false;
}

function calculateSentenceStats(text) {
    const sentences = String(text || '')
        .split(/[.!?]+/)
        .map((sentence) => wordsFromText(sentence).length)
        .filter((length) => length > 0);

    if (!sentences.length) {
        return { count: 0, average: 0, longPercent: 0 };
    }

    const total = sentences.reduce((sum, length) => sum + length, 0);
    const long = sentences.filter((length) => length > 20).length;

    return {
        count: sentences.length,
        average: Number((total / sentences.length).toFixed(1)),
        longPercent: Math.round((long / sentences.length) * 100),
    };
}

function normalizeCategoryIds(rawIds, primaryCategoryId = 0) {
    const values = Array.isArray(rawIds)
        ? rawIds
        : String(rawIds || '').split(',');
    const ids = values
        .map((value) => Number(value))
        .filter((value) => Number.isFinite(value) && value > 0);
    const primaryId = Number(primaryCategoryId || 0);

    if (primaryId > 0) {
        ids.unshift(primaryId);
    }

    return Array.from(new Set(ids));
}

function normalizeCategory(category) {
    const languageId = Number(category?.id_lang || pluginData.language?.id || 0);

    return {
        ...defaultCategory,
        ...(category || {}),
        id_lang: languageId,
        id_category: Number(category?.id_category || 0),
        id_parent: Number(category?.id_parent || 0),
        id_author: Number(category?.id_author || pluginData.currentEmployeeId || 0),
        active: Number(category?.active ?? 1),
        position: Number(category?.position || 0),
        image_url: String(category?.image_url || ''),
        focus_keyword: String(category?.focus_keyword ?? category?.meta_keywords ?? ''),
    };
}

function buildCategoryOptions(categories, options = {}) {
    const excludeId = Number(options.excludeId || 0);
    const normalized = (Array.isArray(categories) ? categories : [])
        .map(normalizeCategory)
        .filter((category) => Number(category.id_category) > 0);
    const childrenByParent = new Map();

    normalized.forEach((category) => {
        const parentId = Number(category.id_parent || 0);
        if (!childrenByParent.has(parentId)) {
            childrenByParent.set(parentId, []);
        }
        childrenByParent.get(parentId).push(category);
    });

    childrenByParent.forEach((children) => {
        children.sort((a, b) => Number(a.position || 0) - Number(b.position || 0) || String(a.name || '').localeCompare(String(b.name || '')));
    });

    const excludedIds = new Set();
    const collectExcluded = (categoryId) => {
        if (!categoryId || excludedIds.has(categoryId)) {
            return;
        }
        excludedIds.add(categoryId);
        (childrenByParent.get(categoryId) || []).forEach((child) => collectExcluded(Number(child.id_category)));
    };
    collectExcluded(excludeId);

    const rows = [];
    const seen = new Set();
    const walk = (parentId, depth) => {
        (childrenByParent.get(parentId) || []).forEach((category) => {
            const categoryId = Number(category.id_category);
            if (seen.has(categoryId) || excludedIds.has(categoryId)) {
                return;
            }
            seen.add(categoryId);
            rows.push({
                id: categoryId,
                label: `${'-- '.repeat(depth)}${category.name || `#${categoryId}`}`,
                depth,
                source: category,
            });
            walk(categoryId, depth + 1);
        });
    };

    walk(0, 0);
    normalized.forEach((category) => {
        const categoryId = Number(category.id_category);
        if (!seen.has(categoryId) && !excludedIds.has(categoryId)) {
            rows.push({
                id: categoryId,
                label: `${'-- '.repeat(0)}${category.name || `#${categoryId}`}`,
                depth: 0,
                source: category,
            });
        }
    });

    return rows;
}

function buildCategoryTreeRows(categories, options = {}) {
    const excludeId = Number(options.excludeId || 0);
    const normalized = (Array.isArray(categories) ? categories : [])
        .map(normalizeCategory)
        .filter((category) => Number(category.id_category) > 0);
    const childrenByParent = new Map();

    normalized.forEach((category) => {
        const parentId = Number(category.id_parent || 0);
        if (!childrenByParent.has(parentId)) {
            childrenByParent.set(parentId, []);
        }
        childrenByParent.get(parentId).push(category);
    });

    childrenByParent.forEach((children) => {
        children.sort((a, b) => Number(a.position || 0) - Number(b.position || 0) || String(a.name || '').localeCompare(String(b.name || '')));
    });

    const excludedIds = new Set();
    const collectExcluded = (categoryId) => {
        if (!categoryId || excludedIds.has(categoryId)) {
            return;
        }
        excludedIds.add(categoryId);
        (childrenByParent.get(categoryId) || []).forEach((child) => collectExcluded(Number(child.id_category)));
    };
    collectExcluded(excludeId);

    const rows = [];
    const seen = new Set();
    const pushRow = (category, depth, parentId) => {
        const categoryId = Number(category.id_category);
        if (seen.has(categoryId) || excludedIds.has(categoryId)) {
            return;
        }

        seen.add(categoryId);
        rows.push({
            id: categoryId,
            parentId: Number(parentId || 0),
            name: category.name || `#${categoryId}`,
            depth,
            hasChildren: (childrenByParent.get(categoryId) || []).some((child) => !excludedIds.has(Number(child.id_category))),
            source: category,
        });

        (childrenByParent.get(categoryId) || []).forEach((child) => pushRow(child, depth + 1, categoryId));
    };

    (childrenByParent.get(0) || []).forEach((category) => pushRow(category, 0, 0));
    normalized.forEach((category) => {
        const categoryId = Number(category.id_category);
        if (!seen.has(categoryId) && !excludedIds.has(categoryId)) {
            pushRow(category, 0, 0);
        }
    });

    return rows;
}

function normalizePost(post) {
    const primaryCategoryId = Number(post?.id_category || 0);
    const languageId = Number(post?.id_lang || pluginData.language?.id || 0);

    return {
        ...defaultPost,
        ...(post || {}),
        id_lang: languageId,
        id_category: primaryCategoryId,
        category_ids: normalizeCategoryIds(post?.category_ids, primaryCategoryId),
        blocks: normalizeContentBlocks(Array.isArray(post?.blocks) && post.blocks.length ? post.blocks : createDefaultPost().blocks),
    };
}

function normalizeContentLanguages(languages, fallbackLanguage = {}) {
    const normalized = (Array.isArray(languages) ? languages : [])
        .map((language) => {
            const id = Number(language?.id || language?.id_lang || 0);
            const isoCode = String(language?.isoCode || language?.iso_code || '').trim();
            const name = normalizeLanguageName(language?.name, isoCode);

            return {
                id,
                isoCode,
                label: isoCode ? `${name} (${isoCode.toUpperCase()})` : name || `#${id}`,
            };
        })
        .filter((language) => language.id > 0);

    if (normalized.length) {
        return normalized;
    }

    const fallbackId = Number(fallbackLanguage?.id || fallbackLanguage?.id_lang || 0);
    if (fallbackId <= 0) {
        return [];
    }

    const fallbackIso = String(fallbackLanguage?.isoCode || fallbackLanguage?.iso_code || '').trim();
    const fallbackName = normalizeLanguageName(fallbackLanguage?.name, fallbackIso);

    return [{
        id: fallbackId,
        isoCode: fallbackIso,
        label: fallbackIso ? `${fallbackName} (${fallbackIso.toUpperCase()})` : fallbackName || `#${fallbackId}`,
    }];
}

function normalizeLanguageName(name, isoCode = '') {
    const rawName = String(name || '').trim().replace(/\s*\([^)]*\)\s*$/, '');
    const rawIso = String(isoCode || '').trim();
    if (rawName && rawName.toLowerCase() !== rawIso.toLowerCase()) {
        return rawName;
    }

    if (rawIso && typeof Intl !== 'undefined' && Intl.DisplayNames) {
        const documentLanguage = typeof document !== 'undefined' ? document.documentElement?.lang : '';
        const navigatorLanguage = typeof navigator !== 'undefined' ? navigator.language : '';
        const locale = String(documentLanguage || navigatorLanguage || 'en').trim() || 'en';
        const displayName = new Intl.DisplayNames([locale], { type: 'language' }).of(rawIso.toLowerCase());
        if (displayName) {
            return displayName.charAt(0).toUpperCase() + displayName.slice(1);
        }
    }

    return rawName || rawIso;
}

function buildAuthorOptions(authors, selectedAuthorId, selectedAuthorName) {
    const selectedId = Number(selectedAuthorId || 0);
    const normalized = (Array.isArray(authors) ? authors : [])
        .map((author) => ({
            id: Number(author?.id || 0),
            name: String(author?.name || '').trim(),
            active: Boolean(author?.active),
        }))
        .filter((author) => author.id > 0);

    if (selectedId > 0 && !normalized.some((author) => author.id === selectedId)) {
        normalized.push({
            id: selectedId,
            name: String(selectedAuthorName || '').trim() || `#${selectedId}`,
            active: false,
        });
    }

    return normalized.map((author) => ({
        value: String(author.id),
        label: author.active ? author.name : `${author.name} (${__('Inactive', 'cci-blog')})`,
        disabled: !author.active && author.id !== selectedId,
    }));
}

function toDateTimeLocalValue(value) {
    const normalized = String(value || '').trim().replace(' ', 'T');
    return /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(normalized) ? normalized.slice(0, 16) : '';
}

function fromDateTimeLocalValue(value) {
    const normalized = String(value || '').trim();
    return /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(normalized)
        ? `${normalized.replace('T', ' ')}:00`
        : '';
}

function normalizePostForPlan(post, isPro) {
    const normalized = normalizePost(post);
    if (isPro) {
        return normalized;
    }

    const primaryCategoryId = Number(normalized.id_category || 0);
    return {
        ...normalized,
        category_ids: primaryCategoryId > 0 ? [primaryCategoryId] : [],
    };
}

function upsertPostSummary(posts, post) {
    const summary = {
        ...post,
        has_blocks: Array.isArray(post.blocks) && post.blocks.length ? 1 : 0,
    };
    const index = posts.findIndex((item) => Number(item.id_post) === Number(summary.id_post));

    if (index === -1) {
        return [summary, ...posts];
    }

    return posts.map((item, itemIndex) => (itemIndex === index ? { ...item, ...summary } : item));
}
