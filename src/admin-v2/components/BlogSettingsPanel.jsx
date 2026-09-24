import React from 'react';
import { formatAdminDateTime } from '@cci/admin-ui';
import { Bug, Clipboard, RefreshCw, Save, Settings } from 'lucide-react';
import { apiFetch, pluginData } from '../api';
import { __ } from '../i18n';
import { Button } from './ui/button';
import { CardHeader } from './ui/card';
import { Checkbox } from './ui/checkbox';
import { Field, FieldLabel } from './ui/field';
import { Input, Textarea } from './ui/input';
import { Select } from './ui/select';
import { Tabs, TabsList, TabsPanel, TabsTrigger } from './ui/tabs';

export default function BlogSettingsPanel({ setNotice }) {
    const [activeTab, setActiveTab] = React.useState('settings');
    const [settings, setSettings] = React.useState(pluginData.settings || {});
    const [saving, setSaving] = React.useState(false);
    const [diagnostics, setDiagnostics] = React.useState(() => pluginData.diagnostics || buildFallbackDiagnostics(pluginData));
    const [diagnosticsLoading, setDiagnosticsLoading] = React.useState(false);
    const [diagnosticsError, setDiagnosticsError] = React.useState('');
    const diagnosticsJson = React.useMemo(() => JSON.stringify(diagnostics || {}, null, 2), [diagnostics]);
    const diagnosticsLogs = Array.isArray(diagnostics?.logs) ? diagnostics.logs : [];
    const diagnosticsHooks = Array.isArray(diagnostics?.hooks) ? diagnostics.hooks : [];
    const diagnosticsAssets = Array.isArray(diagnostics?.assets) ? diagnostics.assets : [];
    const diagnosticsTables = diagnostics?.database?.tables && typeof diagnostics.database.tables === 'object'
        ? Object.values(diagnostics.database.tables)
        : [];

    const update = (key, value) => {
        setSettings((current) => ({ ...current, [key]: value }));
    };

    const commentsProvider = settings.CCB_COMMENTS_PROVIDER === 'native' ? 'native' : 'disqus';
    const owlAssetsSource = settings.CCB_OWL_ASSETS_SOURCE || ((settings.CCB_LOAD_OWL_LIBRARY || settings.CCB_LOAD_OWL_STYLES) ? 'module' : 'theme');
    const owlAssetsHelp = owlAssetsSource === 'module'
        ? __('The blog loads Owl Carousel JS and CSS from module files on blog pages.', 'cci-blog')
        : __('Use this when the active theme or another module already loads Owl Carousel JS and CSS.', 'cci-blog');
    const updateOwlAssetsSource = (value) => {
        setSettings((current) => ({
            ...current,
            CCB_OWL_ASSETS_SOURCE: value,
            CCB_LOAD_OWL_LIBRARY: value === 'module',
            CCB_LOAD_OWL_STYLES: value === 'module',
        }));
    };

    const save = () => {
        setSaving(true);
        apiFetch('/settings/save', {
            method: 'POST',
            body: JSON.stringify(settings),
        })
            .then((response) => {
                const nextSettings = response.settings || settings;
                pluginData.settings = nextSettings;
                setSettings(nextSettings);
                setNotice?.({
                    type: 'success',
                    message: response.message || __('Settings were saved.', 'cci-blog'),
                });
            })
            .catch((error) => {
                setNotice?.({
                    type: 'error',
                    message: __('Could not save blog settings.', 'cci-blog'),
                    details: error.message,
                });
            })
            .finally(() => setSaving(false));
    };

    const copyDiagnostics = () => {
        if (!navigator.clipboard) {
            setNotice?.({ type: 'error', message: __('Clipboard is not available in this browser.', 'cci-blog') });
            return;
        }

        navigator.clipboard
            .writeText(diagnosticsJson)
            .then(() => setNotice?.({ type: 'success', message: __('Diagnostics copied to clipboard.', 'cci-blog') }))
            .catch(() => setNotice?.({ type: 'error', message: __('Failed copying diagnostics.', 'cci-blog') }));
    };

    const refreshDiagnostics = () => {
        setDiagnosticsLoading(true);
        setDiagnosticsError('');

        apiFetch('/diagnostics/get')
            .then((response) => {
                const nextDiagnostics = response.diagnostics || response.payload?.diagnostics;
                if (!nextDiagnostics) {
                    throw new Error(__('Diagnostics response was empty.', 'cci-blog'));
                }

                pluginData.diagnostics = nextDiagnostics;
                setDiagnostics(nextDiagnostics);
                setNotice?.({ type: 'success', message: __('Diagnostics were refreshed.', 'cci-blog') });
            })
            .catch((error) => {
                setDiagnosticsError(error.message || __('Could not refresh diagnostics.', 'cci-blog'));
                setNotice?.({
                    type: 'error',
                    message: __('Could not refresh diagnostics.', 'cci-blog'),
                    details: error.details || error.message || '',
                });
            })
            .finally(() => setDiagnosticsLoading(false));
    };

    return (
        <Tabs value={activeTab} onValueChange={setActiveTab}>
            <TabsList aria-label={__('Settings sections', 'cci-blog')}>
                <TabsTrigger value='settings'>
                    <Settings aria-hidden='true' />
                    {__('Settings', 'cci-blog')}
                </TabsTrigger>
                <TabsTrigger value='diagnostics'>
                    <Bug aria-hidden='true' />
                    {__('Diagnostics', 'cci-blog')}
                </TabsTrigger>
            </TabsList>

            <TabsPanel value='settings'>
                <CardHeader>
                    <div>
                        <h2>{__('Display and SEO', 'cci-blog')}</h2>
                        <p>{__('Control blog storefront, SEO and comment options.', 'cci-blog')}</p>
                    </div>
                    <Button variant='save' className='tw-shrink-0 tw-whitespace-nowrap' onClick={save} disabled={saving} aria-busy={saving}>
                        <Save aria-hidden='true' />
                        {saving ? __('Saving...', 'cci-blog') : __('Save settings', 'cci-blog')}
                    </Button>
                </CardHeader>

                <div className='tw-grid tw-grid-cols-1 tw-gap-3 tw-p-4 lg:tw-grid-cols-3'>
                    <Field label={__('Posts per page', 'cci-blog')}>
                        <Input type='number' value={settings.CCB_POSTS_PER_PAGE || 9} onChange={(event) => update('CCB_POSTS_PER_PAGE', Number(event.target.value))} />
                    </Field>
                    <Field label={__('Layout', 'cci-blog')}>
                        <Select
                            value={settings.CCB_LAYOUT || 'grid'}
                            onValueChange={(value) => update('CCB_LAYOUT', value)}
                            options={[
                                { value: 'grid', label: __('Grid', 'cci-blog') },
                                { value: 'list', label: __('List', 'cci-blog') },
                            ]}
                        />
                    </Field>
                    <Field label={__('Sidebar position', 'cci-blog')}>
                        <Select
                            value={settings.CCB_SIDEBAR_POSITION || 'right'}
                            onValueChange={(value) => update('CCB_SIDEBAR_POSITION', value)}
                            options={[
                                { value: 'right', label: __('Right', 'cci-blog') },
                                { value: 'left', label: __('Left', 'cci-blog') },
                                { value: 'none', label: __('No sidebar', 'cci-blog') },
                            ]}
                        />
                    </Field>
                    <Field label={__('Blog base URL slug', 'cci-blog')}>
                        <Input value={settings.CCB_BASE_SLUG || 'blog'} onChange={(event) => update('CCB_BASE_SLUG', event.target.value)} />
                    </Field>
                    <Field label={__('Related posts', 'cci-blog')}>
                        <Input type='number' value={settings.CCB_RELATED_POSTS || 0} onChange={(event) => update('CCB_RELATED_POSTS', Number(event.target.value))} />
                    </Field>
                    <Field label={__('Feed items', 'cci-blog')}>
                        <Input type='number' value={settings.CCB_FEED_ITEMS || 20} onChange={(event) => update('CCB_FEED_ITEMS', Number(event.target.value))} />
                    </Field>
                    <Field label={__('Minimum headings for table of contents', 'cci-blog')}>
                        <Input
                            type='number'
                            min='1'
                            max='20'
                            value={settings.CCB_TABLE_OF_CONTENTS_MIN_HEADINGS || 2}
                            onChange={(event) => update('CCB_TABLE_OF_CONTENTS_MIN_HEADINGS', Number(event.target.value))}
                        />
                    </Field>
                </div>

                <div className='tw-grid tw-grid-cols-1 tw-gap-3 tw-border-0 tw-border-t tw-border-solid tw-border-cci-blog-border tw-bg-white tw-p-4 lg:tw-grid-cols-3'>
                    <div className='lg:tw-col-span-1'>
                        <Field label={__('Comment provider', 'cci-blog')}>
                            <Select
                                value={commentsProvider}
                                onValueChange={(value) => update('CCB_COMMENTS_PROVIDER', value)}
                                options={[
                                    { value: 'disqus', label: __('Disqus', 'cci-blog') },
                                    { value: 'native', label: __('Native comments', 'cci-blog') },
                                ]}
                            />
                        </Field>
                    </div>
                    {commentsProvider === 'disqus' ? (
                        <div className='lg:tw-col-span-2'>
                            <Field label={__('Disqus shortname', 'cci-blog')}>
                                <Input
                                    value={settings.CCB_DISQUS_SHORTNAME || ''}
                                    onChange={(event) => update('CCB_DISQUS_SHORTNAME', event.target.value)}
                                    placeholder='your-community-shortname'
                                />
                                <p className='tw-m-0 tw-mt-1.5 tw-text-xs tw-leading-relaxed tw-text-cci-blog-muted'>
                                    {__('Use the shortname from Disqus, not a full URL. Disqus stays disabled until this value is valid.', 'cci-blog')}
                                </p>
                            </Field>
                        </div>
                    ) : (
                        <SettingCheckbox
                            checked={Boolean(settings.CCB_COMMENTS_MODERATION)}
                            label={__('Require moderation', 'cci-blog')}
                            onChange={(checked) => update('CCB_COMMENTS_MODERATION', checked)}
                        />
                    )}
                    {[
                        ['CCB_COMMENTS_ENABLED', __('Enable comments globally', 'cci-blog')],
                        ['CCB_TABLE_OF_CONTENTS_ENABLED', __('Show table of contents in articles', 'cci-blog')],
                        ['CCB_SHOW_AUTHOR', __('Show author', 'cci-blog')],
                        ['CCB_SHOW_DATE', __('Show date', 'cci-blog')],
                        ['CCB_SHOW_VIEWS', __('Show views', 'cci-blog')],
                        ['CCB_SHOW_READ_TIME', __('Show reading time', 'cci-blog')],
                        ['CCB_BREADCRUMB', __('Show breadcrumb', 'cci-blog')],
                        ['CCB_SOCIAL_SHARE', __('Social share', 'cci-blog')],
                        ['CCB_SCHEMA_ORG', __('Schema.org JSON-LD', 'cci-blog')],
                        ['CCB_OG_TAGS', __('Open Graph tags', 'cci-blog')],
                        ['CCB_RELATED_PRODUCTS', __('Related products', 'cci-blog')],
                        ['CCB_FEED_ENABLED', __('RSS feed', 'cci-blog')],
                        ['CCB_HIGHLIGHT_SYNTAX', __('Syntax highlighting', 'cci-blog')],
                        ['CCB_URL_SUFFIX_HTML', __('Use .html suffix in post and pagination URLs', 'cci-blog')],
                        ['CCB_SHOW_FEATURED_WIDGET', __('Home page widget', 'cci-blog')],
                    ].map(([key, label]) => (
                        <SettingCheckbox
                            key={key}
                            checked={Boolean(settings[key])}
                            label={label}
                            onChange={(checked) => update(key, checked)}
                        />
                    ))}
                    <div className='lg:tw-col-span-3'>
                        <Field label={__('Owl Carousel assets', 'cci-blog')}>
                            <Select
                                value={owlAssetsSource}
                                onValueChange={updateOwlAssetsSource}
                                options={[
                                    { value: 'module', label: __('Load from module', 'cci-blog') },
                                    { value: 'theme', label: __('Use existing site assets', 'cci-blog') },
                                ]}
                            />
                            <p className='tw-m-0 tw-mt-1.5 tw-text-xs tw-leading-relaxed tw-text-cci-blog-muted'>
                                {owlAssetsHelp}
                            </p>
                        </Field>
                    </div>
                </div>
            </TabsPanel>

            <TabsPanel value='diagnostics'>
                <CardHeader>
                    <div>
                        <h2>{__('Diagnostics and logs', 'cci-blog')}</h2>
                        <p>{__('Copy this report when you contact support. It excludes license keys and tokens.', 'cci-blog')}</p>
                    </div>
                    <div className='tw-flex tw-flex-wrap tw-gap-2'>
                        <Button onClick={refreshDiagnostics} disabled={diagnosticsLoading}>
                            <RefreshCw aria-hidden='true' className={diagnosticsLoading ? 'tw-animate-spin' : ''} />
                            {diagnosticsLoading ? __('Refreshing...', 'cci-blog') : __('Refresh', 'cci-blog')}
                        </Button>
                        <Button variant='outlineAccent' onClick={copyDiagnostics} disabled={diagnosticsLoading}>
                            <Clipboard aria-hidden='true' />
                            {__('Copy report', 'cci-blog')}
                        </Button>
                    </div>
                </CardHeader>
                <div className='tw-grid tw-gap-4 tw-p-4'>
                    {diagnosticsError && (
                        <div role='alert' className='tw-rounded-md tw-border tw-border-solid tw-border-cci-blog-dangerBorder tw-bg-cci-blog-dangerBg tw-p-3 tw-text-sm tw-font-semibold tw-text-cci-blog-dangerText'>
                            {diagnosticsError}
                        </div>
                    )}

                    <div className='tw-rounded-md tw-border tw-border-solid tw-border-cci-blog-brandBorder tw-bg-cci-blog-brandSoft tw-p-3 tw-text-sm tw-leading-5 tw-text-cci-blog-brandStrong'>
                        {__('Use this report to check module health before debugging storefront, editor or extension issues.', 'cci-blog')}
                    </div>

                    <div className='tw-grid tw-gap-3 lg:tw-grid-cols-3'>
                        <DiagnosticCard
                            label={__('Module', 'cci-blog')}
                            values={[
                                [__('Name', 'cci-blog'), diagnostics?.module?.name || 'cci_blog'],
                                [__('Version', 'cci-blog'), diagnostics?.module?.version || pluginData.moduleVersion],
                                [__('Enabled', 'cci-blog'), formatBoolean(diagnostics?.module?.enabled)],
                                [__('Block schema', 'cci-blog'), formatBoolean(diagnostics?.module?.blockSchemaReady)],
                                [__('Extension blocks', 'cci-blog'), diagnostics?.module?.extensionBlocks ?? 0],
                            ]}
                        />
                        <DiagnosticCard
                            label={__('Content', 'cci-blog')}
                            values={[
                                [__('Posts', 'cci-blog'), diagnostics?.content?.posts ?? pluginData.stats?.posts ?? 0],
                                [__('Active posts', 'cci-blog'), diagnostics?.content?.activePosts ?? pluginData.stats?.activePosts ?? 0],
                                [__('Categories', 'cci-blog'), diagnostics?.content?.categories ?? pluginData.stats?.categories ?? 0],
                                [__('Pending comments', 'cci-blog'), diagnostics?.content?.pendingComments ?? pluginData.stats?.pendingComments ?? 0],
                                [__('Comments', 'cci-blog'), formatBoolean(diagnostics?.content?.commentsEnabled)],
                            ]}
                        />
                        <DiagnosticCard
                            label={__('Environment', 'cci-blog')}
                            values={[
                                [__('PrestaShop', 'cci-blog'), diagnostics?.prestashop?.version || '-'],
                                [__('PHP', 'cci-blog'), diagnostics?.server?.phpVersion || '-'],
                                [__('Debug mode', 'cci-blog'), formatBoolean(diagnostics?.prestashop?.debugMode)],
                                [__('Memory limit', 'cci-blog'), diagnostics?.server?.memoryLimit || '-'],
                                [__('Timezone', 'cci-blog'), diagnostics?.server?.timezone || '-'],
                            ]}
                        />
                    </div>

                    <div className='tw-grid tw-gap-3 lg:tw-grid-cols-3'>
                        <DiagnosticCard
                            label={__('Shop', 'cci-blog')}
                            values={[
                                [__('Shop ID', 'cci-blog'), diagnostics?.shop?.id ?? diagnostics?.prestashop?.contextShopId ?? '-'],
                                [__('Shop name', 'cci-blog'), diagnostics?.shop?.name || diagnostics?.prestashop?.contextShopName || '-'],
                                [__('Domain', 'cci-blog'), diagnostics?.shop?.domain || '-'],
                                [__('Base URI', 'cci-blog'), diagnostics?.shop?.baseUri || '-'],
                            ]}
                        />
                        <DiagnosticCard
                            label={__('Database', 'cci-blog')}
                            values={[
                                [__('Server', 'cci-blog'), diagnostics?.database?.serverVersion || '-'],
                                [__('Prefix', 'cci-blog'), diagnostics?.database?.prefix || '-'],
                                [__('Tables checked', 'cci-blog'), diagnosticsTables.length],
                                [__('Missing tables', 'cci-blog'), diagnosticsTables.filter((table) => !table.exists).length],
                            ]}
                        />
                        <DiagnosticCard
                            label={__('Settings', 'cci-blog')}
                            values={[
                                [__('Base slug', 'cci-blog'), diagnostics?.module?.baseSlug || settings.CCB_BASE_SLUG || 'blog'],
                                [__('Layout', 'cci-blog'), diagnostics?.settings?.layout || settings.CCB_LAYOUT || 'grid'],
                                [__('Sidebar', 'cci-blog'), diagnostics?.settings?.sidebarPosition || settings.CCB_SIDEBAR_POSITION || 'right'],
                                [__('Comment provider', 'cci-blog'), diagnostics?.settings?.commentsProvider || commentsProvider],
                                [__('Table of contents', 'cci-blog'), formatBoolean(diagnostics?.settings?.tableOfContentsEnabled ?? settings.CCB_TABLE_OF_CONTENTS_ENABLED)],
                                [__('Owl JS by module', 'cci-blog'), formatBoolean(diagnostics?.settings?.owlLibraryLoadedByModule)],
                                [__('Owl CSS by module', 'cci-blog'), formatBoolean(diagnostics?.settings?.owlStylesLoadedByModule)],
                            ]}
                        />
                    </div>

                    <div className='tw-grid tw-items-start tw-gap-4 lg:tw-grid-cols-2'>
                        <DiagnosticList
                            title={__('Hooks', 'cci-blog')}
                            empty={__('No hook diagnostics are available.', 'cci-blog')}
                            items={diagnosticsHooks.map((hook) => ({
                                title: hook.label && hook.label !== hook.name ? `${hook.label} (${hook.name || '-'})` : hook.name || '-',
                                message: [
                                    hook.required ? __('Required', 'cci-blog') : __('Extension point', 'cci-blog'),
                                    `${__('ID', 'cci-blog')}: ${hook.id || '-'}`,
                                    hook.exists ? __('Exists', 'cci-blog') : __('Not installed yet', 'cci-blog'),
                                    hook.required ? (hook.registered ? __('Registered by CCI Blog', 'cci-blog') : __('Not registered by CCI Blog', 'cci-blog')) : '',
                                    hook.availableInEditor ? __('Available in editor', 'cci-blog') : '',
                                    `${__('Listeners', 'cci-blog')}: ${hook.moduleCount ?? 0}`,
                                    `${__('Used in posts', 'cci-blog')}: ${hook.usedInPosts ?? 0}`,
                                    `${__('Used blocks', 'cci-blog')}: ${hook.usedBlocks ?? 0}`,
                                    Array.isArray(hook.modules) && hook.modules.length
                                        ? `${__('Modules', 'cci-blog')}: ${hook.modules.map((module) => module.name).filter(Boolean).join(', ')}`
                                        : '',
                                ].filter(Boolean).join(' · '),
                            }))}
                        />
                        <DiagnosticList
                            title={__('Asset files', 'cci-blog')}
                            empty={__('No asset diagnostics are available.', 'cci-blog')}
                            items={diagnosticsAssets.map((asset) => ({
                                title: asset.relativePath || '-',
                                message: [
                                    asset.exists ? __('Found', 'cci-blog') : __('Missing', 'cci-blog'),
                                    `${__('Size', 'cci-blog')}: ${asset.size ?? 0} B`,
                                    formatAdminDateTime(asset.modifiedAt, pluginData.adminDate) || '—',
                                ].join(' · '),
                            }))}
                        />
                    </div>

                    <div className='tw-grid tw-items-start tw-gap-4 lg:tw-grid-cols-2'>
                        <DiagnosticList
                            title={__('Database tables', 'cci-blog')}
                            empty={__('No table diagnostics are available.', 'cci-blog')}
                            items={diagnosticsTables.map((table) => ({
                                title: table.name || '-',
                                message: [
                                    table.exists ? __('Found', 'cci-blog') : __('Missing', 'cci-blog'),
                                    `${__('Rows', 'cci-blog')}: ${table.rows ?? 0}`,
                                ].join(' · '),
                            }))}
                        />
                        <DiagnosticList
                            title={__('Recent checks', 'cci-blog')}
                            empty={__('No warnings were reported.', 'cci-blog')}
                            items={diagnosticsLogs.map((log) => ({
                                title: log.type || __('Check', 'cci-blog'),
                                message: [log.message || '-', log.date ? formatAdminDateTime(log.date, pluginData.adminDate) : ''].filter(Boolean).join(' · '),
                            }))}
                        />
                    </div>

                    <Field label={__('Raw JSON report', 'cci-blog')}>
                        <Textarea readOnly rows={18} value={diagnosticsJson} className='tw-min-h-64 tw-resize-y tw-font-mono tw-text-xs' />
                    </Field>
                </div>
            </TabsPanel>
        </Tabs>
    );
}

function buildFallbackDiagnostics(data) {
    return {
        generatedAt: new Date().toISOString(),
        module: {
            name: 'cci_blog',
            version: data.moduleVersion,
            blockSchemaReady: Boolean(data.stats?.blockReady),
        },
        content: {
            posts: data.stats?.posts || 0,
            activePosts: data.stats?.activePosts || 0,
            categories: data.stats?.categories || 0,
            pendingComments: data.stats?.pendingComments || 0,
            commentsEnabled: Boolean(data.stats?.commentsEnabled),
            blockReady: Boolean(data.stats?.blockReady),
        },
        logs: [],
        hooks: [],
        assets: [],
    };
}

function DiagnosticCard({ label, values }) {
    return (
        <div className='tw-rounded-md tw-border tw-border-solid tw-border-cci-blog-border tw-bg-white tw-p-4'>
            <strong className='tw-text-sm tw-font-semibold tw-text-cci-blog-text'>{label}</strong>
            <dl className='tw-m-0 tw-mt-3 tw-grid tw-gap-2'>
                {values.map(([name, value]) => {
                    const renderedValue = formatDiagnosticValue(value);
                    const valueWrapClass = renderedValue.length <= 12 ? 'tw-whitespace-nowrap' : 'tw-break-words';

                    return (
                        <div className='tw-flex tw-items-center tw-justify-between tw-gap-3' key={name}>
                            <dt className='tw-text-xs tw-text-cci-blog-muted'>{name}</dt>
                            <dd className={`tw-m-0 tw-min-w-0 tw-text-right tw-text-xs tw-font-semibold tw-leading-5 tw-text-cci-blog-text ${valueWrapClass}`} title={String(renderedValue)}>
                                {renderedValue}
                            </dd>
                        </div>
                    );
                })}
            </dl>
        </div>
    );
}

function DiagnosticList({ title, items, empty }) {
    return (
        <div className='tw-grid tw-min-w-0 tw-content-start tw-self-start tw-gap-3'>
            <div className='tw-text-sm tw-font-semibold tw-text-cci-blog-text'>{title}</div>
            {items.length ? (
                <ul className='tw-m-0 tw-grid tw-min-w-0 tw-list-none tw-gap-2 tw-p-0'>
                    {items.map((item, index) => (
                        <li className='tw-grid tw-min-w-0 tw-gap-1 tw-rounded-md tw-border tw-border-solid tw-border-cci-blog-border tw-bg-cci-blog-surfaceSoft tw-p-3' key={`${item.title}-${index}`}>
                            <strong className='tw-min-w-0 tw-break-words tw-text-sm tw-font-semibold tw-text-cci-blog-text'>{item.title}</strong>
                            <span className='tw-min-w-0 tw-break-words tw-text-xs tw-leading-5 tw-text-cci-blog-muted'>{item.message}</span>
                        </li>
                    ))}
                </ul>
            ) : (
                <p className='tw-m-0 tw-text-sm tw-text-cci-blog-muted'>{empty}</p>
            )}
        </div>
    );
}

function formatBoolean(value) {
    if (value === undefined || value === null) {
        return '-';
    }

    return value ? __('Yes', 'cci-blog') : __('No', 'cci-blog');
}

function formatDiagnosticValue(value) {
    if (value === undefined || value === null || value === '') {
        return '-';
    }

    if (typeof value === 'boolean') {
        return formatBoolean(value);
    }

    return String(value);
}

function SettingCheckbox({ checked, label, onChange }) {
    return (
        <Field
            data-checked={Boolean(checked)}
            orientation='horizontal'
            className='tw-flex tw-min-h-9 tw-cursor-pointer tw-items-center tw-gap-2 tw-rounded-md tw-border tw-border-solid tw-border-cci-blog-border tw-bg-white tw-px-2.5 tw-text-sm tw-font-medium tw-text-cci-blog-text tw-transition-colors tw-duration-150 hover:tw-border-slate-300 hover:tw-bg-slate-50 focus-within:tw-outline focus-within:tw-outline-2 focus-within:tw-outline-offset-1 focus-within:tw-outline-cci-blog-brand data-[checked=true]:tw-border-cci-blog-brandBorder data-[checked=true]:tw-bg-cci-blog-brandSoft'
        >
            <Checkbox checked={Boolean(checked)} onCheckedChange={(value) => onChange(value === true)} />
            <FieldLabel className='tw-min-w-0 tw-truncate tw-text-sm tw-font-medium tw-normal-case tw-leading-none tw-tracking-normal tw-text-cci-blog-text'>
                {label}
            </FieldLabel>
        </Field>
    );
}
