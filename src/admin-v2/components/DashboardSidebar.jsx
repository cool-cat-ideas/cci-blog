import React from 'react';
import {
    ChevronLeft,
    ChevronRight,
    Crown,
    FilePenLine,
    MessageSquare,
    Route,
    Settings,
    ShieldCheck,
    ShoppingBag,
    Tags,
} from 'lucide-react';
import { apiFetch, isCciBlogProLicenseActive, pluginData } from '../api';
import { __, coreString } from '../i18n';
import { cn } from '../lib/cn';
import { compactLicenseError } from '../utils';
import ProductLogo from './ProductLogo';
import ProBadge from './ProBadge';
import ConfirmLicenseDeactivateModal from './ConfirmLicenseDeactivateModal';
import { Button } from './ui/button';
import { Input } from './ui/input';

const items = [
    { id: 'dashboard', label: 'Posts', icon: FilePenLine },
    { id: 'categories', label: 'Categories', icon: Tags },
    { id: 'comments', label: 'Comments', icon: MessageSquare },
    { id: 'redirects', label: 'Slug redirects', icon: Route },
    { id: 'extensions', label: 'Extensions', icon: ShoppingBag, core: true },
    { id: 'settings', label: 'Settings', icon: Settings, core: true },
];

const sidebarItemBase = [
    '!tw-appearance-none !tw-shadow-none !tw-outline-none !tw-no-underline',
    '!tw-m-0 tw-flex tw-min-h-[42px] tw-w-full tw-cursor-pointer tw-items-center tw-gap-2.5',
    'tw-rounded-md !tw-border tw-border-solid tw-px-3 tw-py-0 tw-text-left tw-text-sm tw-font-semibold tw-leading-none',
    'tw-transition-colors tw-duration-150',
    '[&_svg]:tw-h-[18px] [&_svg]:tw-w-[18px] [&_svg]:tw-shrink-0',
    'focus-visible:!tw-outline focus-visible:!tw-outline-2 focus-visible:tw-outline-offset-1 focus-visible:tw-outline-cci-blog-brand',
].join(' ');

export default function DashboardSidebar({
    activeSection,
    collapsed = false,
    onCollapsedChange,
    onSelect,
    isPro,
    license,
    onLicenseChange,
    setNotice,
}) {
    const visibleItems = items;
    const showLicenseBox = Boolean(license?.canManage && license?.proModuleAvailable && license?.apiBase);
    const showUpgradeBanner = !showLicenseBox && !isPro;
    const toggleLabel = collapsed
        ? __('Expand sidebar', 'cci-blog')
        : __('Collapse sidebar', 'cci-blog');
    const renderItem = (item) => {
        const Icon = item.icon;
        const itemLabel = item.core ? coreString(item.id, item.label) : __(item.label, 'cci-blog');
        const proLocked = Boolean(item.proOnly && !isPro);

        return (
            <Button variant="unstyled"
                type='button'
                key={item.id}
                aria-label={proLocked ? `${itemLabel} ${__('Pro', 'cci-blog')}` : itemLabel}
                title={collapsed ? (proLocked ? `${itemLabel} ${__('Pro', 'cci-blog')}` : itemLabel) : undefined}
                data-pro-locked={proLocked ? 'true' : undefined}
                className={cn(
                    sidebarItemBase,
                    collapsed && 'tw-justify-center tw-gap-0 tw-px-0',
                    activeSection === item.id
                        ? '!tw-border-cci-blog-brandBorder !tw-bg-cci-blog-brandSoft !tw-text-cci-blog-brandStrong'
                        : '!tw-border-transparent !tw-bg-transparent !tw-text-slate-700 hover:!tw-border-cci-blog-brandBorder hover:!tw-bg-cci-blog-brandSoft hover:!tw-text-cci-blog-brandStrong',
                    proLocked && '!tw-border-cci-blog-brandBorder !tw-bg-cci-blog-brandSoft !tw-text-cci-blog-brandStrong'
                )}
                onClick={() => onSelect(proLocked ? 'settings' : item.id)}
            >
                <Icon aria-hidden='true' />
                {!collapsed && (
                    <>
                        <span className='tw-min-w-0 tw-flex-1 tw-truncate'>{itemLabel}</span>
                        {proLocked ? <ProBadge className='tw-px-1.5 tw-py-0.5'>{__('Pro', 'cci-blog')}</ProBadge> : null}
                    </>
                )}
            </Button>
        );
    };

    return (
        <aside
            className={cn(
                'cci-blog-admin-sidebar tw-grid tw-h-auto tw-min-h-full tw-self-stretch tw-grid-rows-[auto_minmax(0,1fr)] tw-overflow-hidden tw-border-0 tw-border-r tw-border-solid tw-border-cci-blog-border tw-bg-white',
                collapsed ? 'tw-gap-4 tw-px-3 tw-py-5' : 'tw-gap-5 tw-px-4 tw-py-6'
            )}
        >
            <div className={cn(collapsed ? 'tw-grid tw-justify-items-center tw-gap-2' : 'tw-flex tw-items-start tw-justify-between tw-gap-2')}>
                <ProductLogo
                    compact={collapsed}
                    className={collapsed ? 'tw-min-h-[42px]' : ''}
                    onClick={(event) => {
                        event.preventDefault();
                        onSelect('dashboard');
                    }}
                />
                <Button
                    type='button'
                    variant='builder'
                    size='iconSm'
                    aria-label={toggleLabel}
                    title={toggleLabel}
                    onClick={() => onCollapsedChange?.(!collapsed)}
                >
                    {collapsed ? <ChevronRight aria-hidden='true' /> : <ChevronLeft aria-hidden='true' />}
                </Button>
            </div>

            <div className='tw-grid tw-min-h-0 tw-content-start tw-gap-2.5 tw-overflow-y-auto tw-pr-1'>
                {!collapsed && (
                    <span className='tw-px-2.5 tw-text-[11px] tw-font-bold tw-uppercase tw-leading-none tw-tracking-normal tw-text-cci-blog-muted'>
                        {coreString('menu', 'Menu')}
                    </span>
                )}
                <nav className='tw-grid tw-content-start tw-gap-1.5' aria-label={coreString('menu', 'Menu')}>
                    {visibleItems.map(renderItem)}
                </nav>
                {!collapsed && showLicenseBox && (
                    <SidebarLicenseBox
                        isPro={isPro}
                        license={license}
                        onLicenseChange={onLicenseChange}
                        setNotice={setNotice}
                    />
                )}
                {!collapsed && showUpgradeBanner && (
                    <div className='tw-mt-2 tw-grid tw-gap-3 tw-rounded-md tw-border tw-border-solid tw-border-amber-200 tw-bg-gradient-to-br tw-from-amber-50 tw-via-white tw-to-white tw-p-3.5 tw-text-cci-blog-text'>
                        <div className='tw-grid tw-gap-1.5'>
                            <strong className='tw-text-sm tw-font-semibold tw-leading-5 tw-text-cci-blog-text'>
                                {__('Need more blog options?', 'cci-blog')}
                            </strong>
                            <p className='tw-m-0 tw-text-xs tw-leading-5 tw-text-cci-blog-muted'>
                                {__('This shop is using the base module. After you install CCI Blog Pro, license activation will appear here.', 'cci-blog')}
                            </p>
                        </div>
                    </div>
                )}
            </div>
        </aside>
    );
}

function SidebarLicenseBox({ isPro, license, onLicenseChange, setNotice }) {
    const [licenseKey, setLicenseKey] = React.useState('');
    const [saving, setSaving] = React.useState(false);
    const [touched, setTouched] = React.useState(false);
    const [error, setError] = React.useState('');
    const [deactivateModalOpen, setDeactivateModalOpen] = React.useState(false);
    const apiBase = license?.apiBase || '';
    const licenseLabel = license?.label || (isPro ? 'PRO' : coreString('inactive', 'Inactive'));
    const licensedSite = license?.domain || license?.customerEmail || '';
    const environmentLabel = license?.environment ? String(license.environment) : '';
    const mascotUrl = pluginData.modulePath
        ? `${pluginData.modulePath}views/img/cci-working.png`
        : '';
    const boxStyle = mascotUrl
        ? {
              backgroundImage: `linear-gradient(115deg, rgba(255,255,255,0.99) 0%, rgba(255,255,255,0.96) 58%, rgba(255,255,255,0.62) 100%), url("${mascotUrl}")`,
              backgroundPosition: 'right -1.25rem top 3.25rem',
              backgroundRepeat: 'no-repeat',
              backgroundSize: '6.75rem auto',
          }
        : undefined;

    const validateLicenseKey = (key) => {
        const value = key.trim();

        if (!value) {
            return __('Enter your license key.', 'cci-blog');
        }

        if (value.length < 8) {
            return __('License key looks too short.', 'cci-blog');
        }

        return '';
    };

    const applyLicense = (nextLicense) => {
        const normalized = nextLicense || {};

        pluginData.license = normalized;
        pluginData.isPro = isCciBlogProLicenseActive(normalized);

        if (onLicenseChange) {
            onLicenseChange(normalized);
        }
    };

    const sendLicenseRequest = (endpoint, body = {}, successMessage = '') => {
        setSaving(true);

        apiFetch(`${apiBase}${endpoint}`, {
            method: 'POST',
            body: JSON.stringify(body),
        })
            .then((response) => {
                applyLicense(response.license);
                setError(response.warning || '');

                if (response.license?.status === 'active') {
                    setLicenseKey('');
                }

                if (successMessage && setNotice) {
                    setNotice({
                        type: response.warning ? 'warning' : 'success',
                        message: successMessage,
                        details: response.warning || '',
                    });
                }
            })
            .catch((requestError) => {
                const message = compactLicenseError(
                    requestError,
                    __('License server is temporarily unavailable. Please try again later.', 'cci-blog'),
                    __('License request failed.', 'cci-blog')
                );

                setError(message);

                if (setNotice) {
                    setNotice({
                        type: 'error',
                        message: __('License request failed.', 'cci-blog'),
                        details: message,
                    });
                }
            })
            .finally(() => setSaving(false));
    };

    const activateLicense = () => {
        const validationError = validateLicenseKey(licenseKey);

        setTouched(true);
        setError(validationError);

        if (validationError) {
            return;
        }

        sendLicenseRequest(
            '/license/activate',
            { licenseKey: licenseKey.trim() },
            __('Pro license was activated.', 'cci-blog')
        );
    };

    const deactivateLicense = () => {
        setDeactivateModalOpen(true);
    };

    const confirmDeactivateLicense = () => {
        setDeactivateModalOpen(false);
        sendLicenseRequest('/license/deactivate', {}, __('Pro license was deactivated.', 'cci-blog'));
    };

    return (
        <div
            className={cn(
                'tw-relative tw-grid tw-overflow-hidden tw-rounded-md tw-border tw-border-solid tw-bg-white tw-p-3.5',
                isPro
                    ? 'tw-border-cci-blog-brandBorder tw-bg-cci-blog-brandSoft'
                    : 'tw-border-amber-200 tw-bg-gradient-to-br tw-from-amber-50 tw-via-white tw-to-white'
            )}
            style={boxStyle}
        >
            <ConfirmLicenseDeactivateModal
                open={deactivateModalOpen}
                saving={saving}
                onCancel={() => setDeactivateModalOpen(false)}
                onConfirm={confirmDeactivateLicense}
            />
            <div className='tw-relative tw-z-10 tw-flex tw-items-start tw-gap-2.5'>
                <span
                    className={cn(
                        'tw-inline-flex tw-h-9 tw-w-9 tw-shrink-0 tw-items-center tw-justify-center tw-rounded-md tw-border tw-border-solid [&_svg]:tw-h-4 [&_svg]:tw-w-4',
                        isPro
                            ? 'tw-border-amber-200 tw-bg-amber-50 tw-text-amber-700'
                            : 'tw-border-amber-200 tw-bg-amber-50 tw-text-amber-700'
                    )}
                    aria-hidden='true'
                >
                    {isPro ? <Crown /> : <ShieldCheck />}
                </span>
                <div className='tw-min-w-0'>
                    <strong className='tw-block tw-text-sm tw-font-semibold tw-leading-tight tw-text-cci-blog-text'>
                        {__('CCI Blog Pro', 'cci-blog')}
                    </strong>
                    <span className='tw-mt-1 tw-inline-flex tw-rounded-full tw-border tw-border-solid tw-border-amber-200 tw-bg-white tw-px-2 tw-py-0.5 tw-text-[10px] tw-font-bold tw-uppercase tw-leading-none tw-text-amber-700'>
                        {isPro ? licenseLabel : __('Pro upgrade', 'cci-blog')}
                    </span>
                </div>
            </div>

            {isPro ? (
                <div className='tw-relative tw-z-10 tw-grid tw-gap-2'>
                    <p className='tw-m-0 tw-text-xs tw-leading-5 tw-text-cci-blog-muted'>
                        {__('Premium features are active on this site.', 'cci-blog')}
                    </p>
                    {(licensedSite || environmentLabel) && (
                        <div className='tw-grid tw-gap-1.5 tw-rounded-md tw-border tw-border-solid tw-border-cci-blog-brandBorder tw-bg-white/70 tw-p-2.5 tw-text-xs'>
                            {licensedSite && (
                                <span className='tw-flex tw-justify-between tw-gap-2'>
                                    <span className='tw-text-cci-blog-muted'>{__('Site', 'cci-blog')}</span>
                                    <strong className='tw-truncate tw-text-cci-blog-text'>{licensedSite}</strong>
                                </span>
                            )}
                            {environmentLabel && (
                                <span className='tw-flex tw-justify-between tw-gap-2'>
                                    <span className='tw-text-cci-blog-muted'>{__('Environment', 'cci-blog')}</span>
                                    <strong className='tw-text-cci-blog-text'>{environmentLabel}</strong>
                                </span>
                            )}
                        </div>
                    )}
                    <Button variant='danger' size='sm' onClick={deactivateLicense} disabled={saving}>
                        {saving
                            ? __('Deactivating...', 'cci-blog')
                            : __('Deactivate license', 'cci-blog')}
                    </Button>
                </div>
            ) : (
                <div className='tw-relative tw-z-10 tw-grid tw-gap-2.5'>
                    <p className='tw-m-0 tw-text-xs tw-leading-5 tw-text-cci-blog-muted'>
                        {__('Enter your license key to unlock Pro add-ons on this domain.', 'cci-blog')}
                    </p>
                    <label className='tw-grid tw-gap-1.5'>
                        <span className='tw-text-[11px] tw-font-semibold tw-leading-none tw-text-slate-600'>
                            {__('License key', 'cci-blog')}
                        </span>
                        <Input
                            type='password'
                            value={licenseKey}
                            placeholder='CCI-XXXX-XXXX'
                            invalid={Boolean(error)}
                            aria-invalid={Boolean(error)}
                            onBlur={() => {
                                setTouched(true);
                                setError(validateLicenseKey(licenseKey));
                            }}
                            onChange={(event) => {
                                const nextValue = event.target.value;

                                setLicenseKey(nextValue);

                                if (touched) {
                                    setError(validateLicenseKey(nextValue));
                                }
                            }}
                        />
                    </label>
                    <Button variant='primary' size='sm' className='cci-blog-state-full' onClick={activateLicense} disabled={saving}>
                        {saving
                            ? __('Activating...', 'cci-blog')
                            : __('Activate license', 'cci-blog')}
                    </Button>
                    <p className='tw-m-0 tw-text-[11px] tw-leading-4 tw-text-cci-blog-muted'>
                        {__('Already have a key? Paste it here and activate Pro in seconds.', 'cci-blog')}
                    </p>
                </div>
            )}

            {error && <p className='tw-relative tw-z-10 tw-m-0 tw-text-xs tw-leading-5 tw-text-cci-blog-danger'>{error}</p>}
        </div>
    );
}
