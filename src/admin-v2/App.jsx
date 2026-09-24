import { AdminShell, AdminWorkspace, AdminPageHeader, AdminColumns } from '@cci/admin-ui/layout';
import React, { useCallback, useEffect, useState } from 'react';
import { BookOpen } from 'lucide-react';
import BlogBuilderPanel from './components/BlogBuilderPanel';
import BlogExtensionsPanel from './components/BlogExtensionsPanel';
import BlogSettingsPanel from './components/BlogSettingsPanel';
import DashboardSidebar from './components/DashboardSidebar';
import LicensePanel from './components/LicensePanel';
import { ProductNewsPanel } from '@cci/admin-ui/product-panels';
import Notice from './components/Notice';
import ReviewPrompt from './components/ReviewPrompt';
import SlugRedirectsPanel from './components/SlugRedirectsPanel';
import { Button } from './components/ui/button';
import { TooltipProvider } from './components/ui/tooltip';
import { isCciBlogProLicenseActive, pluginData } from './api';
import { __ } from './i18n';

const SIDEBAR_STORAGE_KEY = 'cci_blog_admin_sidebar_collapsed';

function getInitialSidebarCollapsed() {
    if (typeof window === 'undefined') {
        return false;
    }

    const stored = window.localStorage?.getItem(SIDEBAR_STORAGE_KEY);

    if (stored === '1' || stored === '0') {
        return stored === '1';
    }

    return window.matchMedia?.('(max-width: 1180px)').matches || false;
}

function normalizeLicense(license = {}) {
    return {
        ...license,
        apiBase: pluginData.apiBase,
        canManage: Boolean(license.canManage && license.proModuleAvailable),
        status: license.status || (license.canUse ? 'active' : 'inactive'),
        label: license.label || (license.canUse ? 'PRO' : 'Inactive'),
    };
}

export default function App() {
    const [activeSection, setActiveSection] = useState('dashboard');
    const [license, setLicense] = useState(() => normalizeLicense(pluginData.license));
    const [notices, setNotices] = useState([]);
    const [sidebarCollapsed, setSidebarCollapsed] = useState(getInitialSidebarCollapsed);
    const [proRuntimeVersion, setProRuntimeVersion] = useState(0);
    const pageHeader = getPageHeader(activeSection);
    const isPro = isCciBlogProLicenseActive(license);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        window.localStorage?.setItem(SIDEBAR_STORAGE_KEY, sidebarCollapsed ? '1' : '0');
    }, [sidebarCollapsed]);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return undefined;
        }

        const refreshProRuntime = () => setProRuntimeVersion((version) => version + 1);
        window.addEventListener('cci-blog:pro-runtime-ready', refreshProRuntime);

        return () => window.removeEventListener('cci-blog:pro-runtime-ready', refreshProRuntime);
    }, []);

    useEffect(() => {
        if (typeof window === 'undefined' || !window.matchMedia) {
            return undefined;
        }

        const media = window.matchMedia('(max-width: 960px)');
        const collapseForCompactViewport = () => {
            if (media.matches) {
                setSidebarCollapsed(true);
            }
        };

        collapseForCompactViewport();
        media.addEventListener?.('change', collapseForCompactViewport);
        media.addListener?.(collapseForCompactViewport);

        return () => {
            media.removeEventListener?.('change', collapseForCompactViewport);
            media.removeListener?.(collapseForCompactViewport);
        };
    }, []);

    const setNotice = useCallback((notice) => {
        if (!notice) {
            setNotices([]);
            return;
        }

        setNotices((current) => [
            ...current.slice(-3),
            {
                id: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
                type: notice.type || 'info',
                message: notice.message || notice,
                details: notice.details || '',
                duration: Number(notice.duration || 0),
                sticky: Boolean(notice.sticky),
            },
        ]);
    }, []);

    const closeNotice = useCallback((id) => {
        setNotices((current) => current.filter((notice) => notice.id !== id));
    }, []);

    const handleLicenseChange = useCallback((nextLicense) => {
        setLicense(normalizeLicense(nextLicense));
    }, []);

    const handleSidebarSelect = useCallback((section) => {
        setActiveSection(section);
    }, []);

    const handleOpenSettings = useCallback(() => {
        setActiveSection('settings');
    }, []);

    return (
        <TooltipProvider delayDuration={200} skipDelayDuration={100}>
            <AdminShell
                className='cci-blog-admin-shell tw-grid tw-min-h-[calc(100vh-32px)] tw-items-stretch tw-bg-cci-blog-bg tw-font-sans tw-text-sm tw-leading-[1.45] tw-text-cci-blog-text'
                collapsed={sidebarCollapsed}
            >
                <DashboardSidebar
                    activeSection={activeSection}
                    collapsed={sidebarCollapsed}
                    isPro={isPro}
                    license={license}
                    onCollapsedChange={setSidebarCollapsed}
                    onLicenseChange={handleLicenseChange}
                    onSelect={handleSidebarSelect}
                    setNotice={setNotice}
                />

                <AdminWorkspace>
                    <AdminPageHeader>
                        <div>
                            <h1 className='tw-mb-0 tw-mt-0 tw-text-3xl tw-font-medium tw-leading-tight tw-text-cci-blog-text'>
                                {pageHeader.title}
                            </h1>
                            {pageHeader.description && (
                                <p className='tw-mb-0 tw-mt-1.5 tw-text-sm tw-leading-5 tw-text-cci-blog-muted'>
                                    {pageHeader.description}
                                </p>
                            )}
                        </div>
                        {pluginData.documentationUrl && (
                            <div className='tw-flex tw-flex-wrap tw-items-center tw-gap-2.5'>
                                <Button asChild variant='secondary'>
                                    <a href={pluginData.documentationUrl} target='_blank' rel='noreferrer'>
                                        <BookOpen aria-hidden='true' />
                                        {__('Documentation', 'cci-blog')}
                                    </a>
                                </Button>
                            </div>
                        )}
                    </AdminPageHeader>

                    <Notice notices={notices} onClose={closeNotice} />

                    <DashboardSection
                        activeSection={activeSection}
                        setNotice={setNotice}
                        isPro={isPro}
                        license={license}
                        onLicenseChange={handleLicenseChange}
                        onOpenSettings={handleOpenSettings}
                    />
                </AdminWorkspace>
            </AdminShell>
        </TooltipProvider>
    );
}

function getPageHeader(activeSection) {
    const pages = {
        dashboard: {
            title: __('Posts', 'cci-blog'),
            description: __('Create, manage and publish content commerce articles.', 'cci-blog'),
        },
        categories: {
            title: __('Categories', 'cci-blog'),
            description: __('Organize posts by topic and storefront structure.', 'cci-blog'),
        },
        comments: {
            title: __('Comments', 'cci-blog'),
            description: __('Moderate reader feedback from the blog workspace.', 'cci-blog'),
        },
        redirects: {
            title: __('Slug redirects', 'cci-blog'),
            description: __('Manage historical post and category URLs and permanent redirects.', 'cci-blog'),
        },
        extensions: {
            title: __('Blog extensions', 'cci-blog'),
            description: __('Manage installed blog extensions and browse add-ons from your store.', 'cci-blog'),
        },
        settings: {
            title: __('Blog settings', 'cci-blog'),
            description: __('Configure display, SEO, feeds, comments and module diagnostics.', 'cci-blog'),
        },
    };

    return pages[activeSection] || pages.dashboard;
}

function DashboardSection({ activeSection, setNotice, isPro, license, onLicenseChange, onOpenSettings }) {
    const [editorActive, setEditorActive] = useState(false);

    if (activeSection === 'settings') {
        return (
            <AdminColumns>
                <div className='tw-grid tw-min-w-0 tw-content-start tw-gap-4 tw-self-start'>
                    <BlogSettingsPanel setNotice={setNotice} />
                </div>
                <aside className='tw-grid tw-min-w-0 tw-content-start tw-gap-4 tw-self-start'>
                    <LicensePanel license={license} />
                    <ReviewPrompt setNotice={setNotice} compact />
                </aside>
            </AdminColumns>
        );
    }

    if (activeSection === 'redirects') {
        return <SlugRedirectsPanel setNotice={setNotice} />;
    }

    if (activeSection === 'extensions') {
        return (
            <BlogExtensionsPanel
                license={license}
                onLicenseChange={onLicenseChange}
                onOpenSettings={onOpenSettings}
                setNotice={setNotice}
            />
        );
    }

    const contentSection = ['dashboard', 'categories', 'comments'].includes(activeSection)
        ? activeSection
        : 'dashboard';

    return (
        <AdminColumns fullWidth={editorActive}>
            <div className='tw-grid tw-min-w-0 tw-content-start tw-gap-4 tw-self-start'>
                <BlogBuilderPanel isPro={isPro} onLicenseNotice={setNotice} setNotice={setNotice} onOpenSettings={onOpenSettings} onEditorStateChange={setEditorActive} section={contentSection} />
            </div>
            {!editorActive ? (
                <aside className='tw-grid tw-min-w-0 tw-content-start tw-gap-4 tw-self-start'>
                    <LicensePanel license={license} />
                    <ProductNewsPanel endpoint={pluginData.newsEndpoint || pluginData.marketplaceFeedEndpoint} />
                </aside>
            ) : null}
        </AdminColumns>
    );
}
