import { ProductStatusPanel, compareProductVersions } from '@cci/admin-ui/product-panels';
import React, { useState } from 'react';
import { ArrowRight, CheckCircle2, Crown, RefreshCw, ShieldCheck } from 'lucide-react';
import { formatAdminDateTime } from '@cci/admin-ui/blog';
import { apiFetch, isCciBlogProLicenseActive, pluginData } from '../api';
import { __ } from '../i18n';
import { readableValue, useMarketplaceJson } from '../marketplace';
import { compactLicenseError } from '../utils';
import { Button } from './ui/button';
import { Field } from './ui/field';
import { Input } from './ui/input';
import { Card, CardHeader } from './ui/card';
import ConfirmLicenseDeactivateModal from './ConfirmLicenseDeactivateModal';

export default function LicensePanel({ license: controlledLicense, onLicenseChange, setNotice, showManage = true }) {
    const [localLicense, setLocalLicense] = useState(pluginData.license || {});
    const [licenseKey, setLicenseKey] = useState('');
    const [saving, setSaving] = useState(false);
    const [deactivateModalOpen, setDeactivateModalOpen] = useState(false);
    const productFeed = useMarketplaceJson(pluginData.marketplaceFeedEndpoint, {
        enabled: Boolean(pluginData.marketplaceFeedEndpoint),
    });
    const license = controlledLicense || localLicense;
    const isPro = isCciBlogProLicenseActive(license);
    const hasProductFeed = Boolean(productFeed.data?.product) && productFeed.data?._meta?.source !== 'empty';
    const product = hasProductFeed ? productFeed.data?.product || {} : {};
    const upgradeUrl = readableValue(product?.pro?.url || pluginData.upgradeUrl);
    const installedVersion = readableValue(pluginData.pluginVersion);
    const latestVersion = readableValue(product?.latestVersion || product?.free?.version);
    const updateUrl = readableValue(
        product?.free?.urls?.download || product?.pro?.url || pluginData.upgradeUrl
    );
    const hasUpdate = compareProductVersions(latestVersion, installedVersion) > 0;
    const apiBase = license.apiBase || '';
    const canManage = Boolean(license.canManage && license.proModuleAvailable && apiBase);
    const ctaLabel = __('See CCI Blog Pro', 'cci-blog');
    const ctaIcon = <ArrowRight aria-hidden='true' />;
    const licenseMascotUrl = pluginData.modulePath
        ? `${pluginData.modulePath}views/img/cci-working.png`
        : '';
    const licenseActivationStyle = licenseMascotUrl
        ? {
              backgroundImage: `linear-gradient(100deg, rgba(255,255,255,0.98) 0%, rgba(255,255,255,0.96) 54%, rgba(255,255,255,0.78) 100%), url("${licenseMascotUrl}")`,
              backgroundPosition: 'right 1.25rem top 4.5rem',
              backgroundRepeat: 'no-repeat',
              backgroundSize: 'clamp(8rem, 24vw, 13rem) auto',
          }
        : undefined;

    const applyLicense = (nextLicense) => {
        const normalized = nextLicense || {};

        pluginData.license = normalized;
        pluginData.isPro = isCciBlogProLicenseActive(normalized);
        setLocalLicense(normalized);

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

                if (successMessage && setNotice) {
                    setNotice({
                        type: response.warning ? 'warning' : 'success',
                        message: successMessage,
                        details: response.warning || '',
                    });
                }
            })
            .catch((error) => {
                const message = compactLicenseError(
                    error,
                    __('License server is temporarily unavailable. Please try again later.', 'cci-blog'),
                    __('License request failed.', 'cci-blog')
                );

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
        sendLicenseRequest('/license/activate', { licenseKey }, __('Pro license was activated.', 'cci-blog'));
    };

    const checkLicense = () => {
        sendLicenseRequest('/license/check', {}, __('License status was refreshed.', 'cci-blog'));
    };

    const deactivateLicense = () => {
        setDeactivateModalOpen(true);
    };

    const confirmDeactivateLicense = () => {
        setDeactivateModalOpen(false);
        sendLicenseRequest('/license/deactivate', {}, __('Pro license was deactivated.', 'cci-blog'));
    };

    return (
        <div className='tw-grid tw-content-start tw-gap-4 tw-self-start'>
            <ConfirmLicenseDeactivateModal
                open={deactivateModalOpen}
                saving={saving}
                onCancel={() => setDeactivateModalOpen(false)}
                onConfirm={confirmDeactivateLicense}
            />
            <ProductStatusPanel
                t={text => __(text, 'cci-blog')}
                isPro={isPro}
                licenseStatus={license.status}
                licenseMessage={license.message}
                installedVersion={installedVersion}
                latestVersion={latestVersion}
                updateUrl={updateUrl}
                description={hasUpdate ? __('Review and install the latest release when you are ready.', 'cci-blog') : isPro ? __('Pro access is active and premium features can be used on this site.', 'cci-blog') : __('This site is using the base blog module.', 'cci-blog')}
                checkedAt={license.checkedAt && formatLicenseDate(license.checkedAt)}
                nextCheckAt={license.nextCheckAt && formatLicenseDate(license.nextCheckAt)}
                expires={license.expires && formatLicenseDate(license.expires, false)}
            />

            {canManage && showManage && (
                <Card className='tw-overflow-hidden tw-border-amber-200 tw-bg-gradient-to-br tw-from-amber-50 tw-via-white tw-to-white'>
                    <div className='tw-grid tw-gap-4 tw-p-4 sm:tw-p-5' style={licenseActivationStyle}>
                        <CardHeader className='tw-border-0 tw-p-0'>
                            <div className='tw-max-w-[28rem]'>
                                <span className='tw-inline-flex tw-items-center tw-gap-1.5 tw-text-[11px] tw-font-bold tw-uppercase tw-leading-none tw-text-cci-blog-brand'>
                                    <ShieldCheck aria-hidden='true' />
                                    {__('License activation', 'cci-blog')}
                                </span>
                                <h2 className='tw-mt-2'>
                                    {isPro
                                        ? __('Manage Pro access', 'cci-blog')
                                        : __('Activate Pro', 'cci-blog')}
                                </h2>
                                <p className='tw-mb-0 tw-mt-2 tw-max-w-md tw-text-sm tw-leading-6 tw-text-cci-blog-muted'>
                                    {isPro
                                        ? __('Your Pro license is active for this site.', 'cci-blog')
                                        : __(
                                              'Activate your license to unlock Pro add-ons and premium tools on this site.',
                                              'cci-blog'
                                          )}
                                </p>
                            </div>
                        </CardHeader>

                        <div className='tw-grid tw-max-w-[28rem] tw-gap-3'>
                            {!isPro && (
                                <Field label={__('License key', 'cci-blog')}>
                                    <Input
                                        type='password'
                                        value={licenseKey}
                                        placeholder='CCI-XXXX-XXXX'
                                        onChange={(event) => setLicenseKey(event.target.value)}
                                    />
                                </Field>
                            )}

                            {license.message && (
                                <p className='tw-m-0 tw-text-xs tw-leading-5 tw-text-cci-blog-muted'>{license.message}</p>
                            )}
                            {license.lastError && (
                                <p className='tw-m-0 tw-text-xs tw-font-semibold tw-leading-5 tw-text-cci-blog-danger'>
                                    {license.lastError}
                                </p>
                            )}

                            <div className='tw-flex tw-flex-wrap tw-gap-2'>
                                {isPro ? (
                                    <>
                                        <Button variant='outlineAccent' onClick={checkLicense} disabled={saving}>
                                            <RefreshCw aria-hidden='true' />
                                            {saving
                                                ? __('Checking...', 'cci-blog')
                                                : __('Check license', 'cci-blog')}
                                        </Button>
                                        <Button variant='danger' onClick={deactivateLicense} disabled={saving}>
                                            {__('Deactivate', 'cci-blog')}
                                        </Button>
                                    </>
                                ) : (
                                    <Button
                                        variant='primary'
                                        className='tw-w-full'
                                        onClick={activateLicense}
                                        disabled={saving || !licenseKey.trim()}
                                    >
                                        {saving
                                            ? __('Activating...', 'cci-blog')
                                            : __('Activate license', 'cci-blog')}
                                    </Button>
                                )}
                            </div>

                            {!isPro && (
                                <p className='tw-m-0 tw-text-sm tw-leading-6 tw-text-cci-blog-muted'>
                                    {__(
                                        'Already have a license key? Paste it above and activate Pro in a few seconds.',
                                        'cci-blog'
                                    )}
                                </p>
                            )}
                        </div>
                    </div>
                </Card>
            )}

            {!isPro && (
                <Card className='tw-overflow-hidden tw-border-cci-blog-brandBorder tw-bg-white'>
                    <div className='tw-grid tw-gap-4 tw-p-4'>
                        <div className='tw-flex tw-items-start tw-gap-3'>
                            <span
                                className='tw-inline-flex tw-h-10 tw-w-10 tw-shrink-0 tw-items-center tw-justify-center tw-rounded-md tw-border tw-border-solid tw-border-amber-200 tw-bg-amber-50 tw-text-amber-700 [&_svg]:tw-h-5 [&_svg]:tw-w-5'
                                aria-hidden='true'
                            >
                                <Crown />
                            </span>
                            <div className='tw-grid tw-min-w-0 tw-gap-1'>
                                <span className='tw-text-sm tw-font-semibold tw-leading-5 tw-text-amber-700'>
                                    {__('CCI Blog Pro', 'cci-blog')}
                                </span>
                                <span className='tw-m-0 tw-block tw-text-lg tw-font-semibold tw-leading-tight tw-text-cci-blog-text'>
                                    {__('Sell from your articles', 'cci-blog')}
                                </span>
                            </div>
                        </div>

                        <p className='tw-m-0 tw-text-sm tw-leading-5 tw-text-cci-blog-muted'>
                            {__(
                                'Publish in several languages, add products to articles and give editors more layout choices.',
                                'cci-blog'
                            )}
                        </p>

                        <ul className='tw-m-0 tw-grid tw-list-none tw-gap-2.5 tw-border-0 tw-p-0'>
                            {[
                                __('Edit content and SEO for each shop language separately', 'cci-blog'),
                                __('Place products and product carousels inside articles', 'cci-blog'),
                                __('Build responsive columns and extra content blocks', 'cci-blog'),
                                __('Use Multistore assignment and a local image library', 'cci-blog'),
                            ].map((feature) => (
                                <li
                                    key={feature}
                                    className='tw-grid tw-grid-cols-[auto_minmax(0,1fr)] tw-items-start tw-gap-2 tw-text-sm tw-leading-5 tw-text-cci-blog-muted'
                                >
                                    <CheckCircle2
                                        className='tw-mt-0.5 tw-h-4 tw-w-4 tw-text-amber-500'
                                        aria-hidden='true'
                                    />
                                    <span>{feature}</span>
                                </li>
                            ))}
                        </ul>

                        {upgradeUrl ? (
                            <Button variant='primary' className='cci-blog-state-full' asChild>
                                <a href={upgradeUrl} target='_blank' rel='noreferrer'>
                                    {ctaLabel}
                                    {ctaIcon}
                                </a>
                            </Button>
                        ) : (
                            <Button variant='primary' className='cci-blog-state-full' disabled>
                                {ctaLabel}
                                {ctaIcon}
                            </Button>
                        )}
                    </div>
                </Card>
            )}
        </div>
    );
}

function formatLicenseDate(value, withTime = true) {
    if (typeof value === 'number' && value > 0 && value < 100000000000) {
        return formatAdminDateTime(value * 1000, pluginData.adminDate, withTime);
    }

    if (typeof value === 'string' && /^\d+$/.test(value.trim())) {
        const numericValue = Number(value);
        return formatAdminDateTime(numericValue > 0 && numericValue < 100000000000 ? numericValue * 1000 : numericValue, pluginData.adminDate, withTime);
    }

    return formatAdminDateTime(value, pluginData.adminDate, withTime);
}

