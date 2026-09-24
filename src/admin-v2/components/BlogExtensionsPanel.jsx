import React, { useEffect, useMemo, useState } from 'react';
import { reconcileCatalogItems } from '@cci/admin-ui';
import { BadgeCheck, ShoppingBag } from 'lucide-react';
import { pluginData } from '../api';
import { __ } from '../i18n';
import {
    collectionFromResponse,
    isPaidProduct,
    mediaUrl,
    normalizeTags,
    productUrl,
    readableValue,
    useMarketplaceJson,
} from '../marketplace';
import { CatalogEmptyState, CatalogGrid, CatalogProductCard } from './CatalogCards';
import LoadingState from './LoadingState';
import LicensePanel from './LicensePanel';
import MarketplaceStatus from './MarketplaceStatus';
import MetricCard from './MetricCard';
import ProUpgradeModal from './ProUpgradeModal';
import ReviewPrompt from './ReviewPrompt';
import { Card } from './ui/card';
import { Tabs, TabsList, TabsTrigger } from './ui/tabs';

const installedTab = 'installed';
const storeTab = 'store';

export default function BlogExtensionsPanel({ license, onLicenseChange, onOpenSettings, setNotice }) {
    const [activeTab, setActiveTab] = useState(installedTab);
    const [storeExtensions, setStoreExtensions] = useState([]);
    const [proFeature, setProFeature] = useState(null);
    const installedExtensions = useMemo(() => normalizeInstalledExtensions(pluginData), []);
    const extensionStoreEndpoint = pluginData.extensionStoreEndpoint || '';
    const storeFeed = useMarketplaceJson(extensionStoreEndpoint, {
        enabled: Boolean(extensionStoreEndpoint),
    });

    useEffect(() => {
        if (!storeFeed.data) {
            setStoreExtensions([]);
            return;
        }

        const extensions = collectionFromResponse(storeFeed.data, [
            'extensions',
            'addons',
            'modules',
            'productCatalogItems',
            'catalogItems',
            'items',
        ]);

        setStoreExtensions(extensions.map(normalizeStoreExtension).filter(Boolean));
    }, [storeFeed.data]);

    const reconciledStoreExtensions = useMemo(
        () => reconcileCatalogItems(storeExtensions, installedExtensions),
        [installedExtensions, storeExtensions]
    );
    const installedCount = installedExtensions.length;
    const storeLoading = activeTab === storeTab && storeFeed.loading;
    const storeCount = reconciledStoreExtensions.length;
    const activeExtensions = activeTab === installedTab ? installedExtensions : reconciledStoreExtensions;
    const storeStatus = (
        <MarketplaceStatus
            error={storeFeed.error}
            retrying={storeFeed.retrying}
            label={__('Extension store is temporarily unavailable.', 'cci-blog')}
            description={__('The module will retry automatically. No placeholder extensions will be shown while the store is unavailable.', 'cci-blog')}
            showDetails={false}
        />
    );

    return (
        <div className='tw-grid tw-grid-cols-1 tw-items-start tw-gap-4 xl:tw-grid-cols-[minmax(0,1fr)_320px]'>
            <div className='tw-grid tw-content-start tw-gap-4 tw-self-start'>
                <div className='tw-grid tw-grid-cols-1 tw-gap-3 lg:tw-grid-cols-2'>
                    <MetricCard
                        icon={BadgeCheck}
                        label={__('Installed', 'cci-blog')}
                        value={installedCount}
                        description={__('Registered through installed modules', 'cci-blog')}
                        tone='primary'
                    />
                    <MetricCard
                        icon={ShoppingBag}
                        label={__('Store', 'cci-blog')}
                        value={storeFeed.loading ? '-' : storeCount}
                        description={
                            extensionStoreEndpoint
                                ? __('Loaded from your store', 'cci-blog')
                                : __('Connect a store endpoint', 'cci-blog')
                        }
                    />
                </div>

                <ReviewPrompt section='extensions' setNotice={setNotice} />

                <Tabs value={activeTab} onValueChange={setActiveTab}>
                    <TabsList aria-label={__('Extension sections', 'cci-blog')}>
                        <TabsTrigger value={installedTab}>
                            <BadgeCheck aria-hidden='true' />
                            {__('Installed extensions', 'cci-blog')}
                            <Counter>{installedCount}</Counter>
                        </TabsTrigger>
                        <TabsTrigger value={storeTab}>
                            <ShoppingBag aria-hidden='true' />
                            {__('Available in store', 'cci-blog')}
                            <Counter>{storeCount}</Counter>
                        </TabsTrigger>
                    </TabsList>

                    <Card as='div' transparent>
                        {storeLoading ? (
                            <LoadingState
                                label={__('Loading store extensions...', 'cci-blog')}
                                variant='block'
                                rows={4}
                            />
                        ) : (
                            <ExtensionGrid
                                extensions={activeExtensions}
                                mode={activeTab}
                                notice={activeTab === storeTab ? storeStatus : null}
                                hasStoreEndpoint={Boolean(extensionStoreEndpoint)}
                                onGoToStore={() => setActiveTab(storeTab)}
                                onLockedExtension={setProFeature}
                            />
                        )}
                    </Card>
                </Tabs>
            </div>

            <aside className='tw-grid tw-content-start tw-gap-4 tw-self-start'>
                <LicensePanel
                    license={license}
                />
            </aside>
            <ProUpgradeModal
                open={Boolean(proFeature)}
                featureName={proFeature?.featureName || ''}
                description={proFeature?.description || ''}
                productUrl={proFeature?.productUrl || ''}
                benefits={[
                    __('Unlock installed and store add-ons for this domain.', 'cci-blog'),
                    __('Keep the core module lighter and extend it only when needed.', 'cci-blog'),
                ]}
                onActivateLicense={() => {
                    setProFeature(null);
                    onOpenSettings?.();
                }}
                onClose={() => setProFeature(null)}
            />
        </div>
    );
}

function ExtensionGrid({ extensions, mode, notice, hasStoreEndpoint, onGoToStore, onLockedExtension }) {
    return (
        <CatalogGrid notice={notice}>
            {!extensions.length ? (
            <CatalogEmptyState
                embedded
                icon={ShoppingBag}
                title={
                    mode === storeTab && !hasStoreEndpoint
                        ? __('Extension store is not connected yet.', 'cci-blog')
                        : mode === installedTab
                          ? __('No blog extensions are installed for this site yet.', 'cci-blog')
                          : __('No extensions found.', 'cci-blog')
                }
                description={
                    mode === storeTab && !hasStoreEndpoint
                        ? __('The extension store is temporarily unavailable. Installed module extensions will remain visible when registered.', 'cci-blog')
                        : mode === installedTab
                          ? __('External blog extensions registered by installed modules will appear here.', 'cci-blog')
                          : __('Extensions from your store will appear here.', 'cci-blog')
                }
                actionLabel={
                    mode === installedTab && onGoToStore && hasStoreEndpoint
                        ? __('Go to available extensions', 'cci-blog')
                        : ''
                }
                onAction={mode === installedTab && hasStoreEndpoint ? onGoToStore : null}
            />
            ) : (
                extensions.map((extension) => (
                    <ExtensionCard key={extension.id} extension={extension} mode={mode} onLockedExtension={onLockedExtension} />
                ))
            )}
        </CatalogGrid>
    );
}

function ExtensionCard({ extension, mode, onLockedExtension }) {
    return (
        <CatalogProductCard
            item={{
                title: extension.title,
                version: extension.version,
                description: extension.description || __('Blog editor extension.', 'cci-blog'),
                screenshot: extension.screenshot,
                tags: extension.tags,
                pro: extension.pro,
                locked: extension.locked,
                lockedMessage:
                    extension.availabilityMessage ||
                    __('This extension requires an active CCI Blog Pro license for this domain.', 'cci-blog'),
      metaSecondary: extension.price,
                url: extension.url,
                installed: extension.installed || mode !== storeTab,
            }}
            mode={mode}
            fallbackIcon={ShoppingBag}
            lockedActionUrl={pluginData.upgradeUrl}
            lockedActionLabel={__('Requires Pro', 'cci-blog')}
            installedLabel={__('Installed', 'cci-blog')}
            availableLabel={__('Available', 'cci-blog')}
            storeLabel={__('View in store', 'cci-blog')}
            onLockedClick={onLockedExtension}
        />
    );
}

function normalizeInstalledExtensions(data) {
    const extensions = Array.isArray(data.extensions) ? data.extensions : [];
    const normalized = extensions.map(normalizeInstalledExtension).filter(Boolean);

    if (normalized.length) {
        return uniqueById(normalized);
    }

    return uniqueById(groupBlockExtensions(data.blockExtensions || []));
}

function groupBlockExtensions(blockExtensions) {
    if (!Array.isArray(blockExtensions)) {
        return [];
    }

    const groups = new Map();

    blockExtensions.forEach((blockExtension) => {
        const moduleName = readableValue(blockExtension.moduleName || blockExtension.module);
        const blockName = readableValue(blockExtension.blockName || blockExtension.block);

        if (!moduleName || !blockName) {
            return;
        }

        if (!groups.has(moduleName)) {
            groups.set(moduleName, {
                id: moduleName.toLowerCase().replace(/[\s_]+/g, '-'),
                title: moduleName,
                description: __('External editor blocks registered by this module.', 'cci-blog'),
                version: readableValue(blockExtension.version),
                author: moduleName,
                tags: [],
                price: '',
                pro: false,
                locked: false,
                screenshot: '',
                url: '',
            });
        }

        const group = groups.get(moduleName);
        const label = readableValue(blockExtension.label || blockExtension.title || blockName);
        if (label) {
            group.tags.push(label);
        }
    });

    return Array.from(groups.values()).map((extension) => ({
        ...extension,
        tags: normalizeTags(extension.tags),
        installed: true,
    }));
}

function normalizeInstalledExtension(extension) {
    if (!extension || typeof extension !== 'object') {
        return null;
    }

    const title = readableValue(extension.title || extension.name || extension.moduleName || extension.module);
    if (!title) {
        return null;
    }

    const blocks = Array.isArray(extension.blocks)
        ? extension.blocks
              .map((block) => readableValue(block.label || block.title || block.name || block.blockName || block.block))
              .filter(Boolean)
        : [];

    return {
        id: extension.id || extension.slug || title.toLowerCase().replace(/[\s_]+/g, '-'),
        title,
        description: readableValue(extension.description, __('External editor blocks registered by this module.', 'cci-blog')),
        version: readableValue(extension.version),
        author: readableValue(extension.author || extension.vendor || extension.moduleName || extension.module),
        tags: normalizeTags(extension.tags || blocks),
        price: readableValue(extension.price),
        pro: Boolean(extension.pro || extension.premium || extension.requiresPro),
        locked: Boolean(extension.locked),
        availabilityMessage: readableValue(extension.availability?.message),
        screenshot: mediaUrl(extension.screenshot || extension.image || extension.thumbnail || extension.media),
        url: productUrl(extension),
        installed: true,
    };
}

function normalizeStoreExtension(extension) {
    if (!extension || typeof extension !== 'object') {
        return null;
    }

    const title = readableValue(extension.title || extension.name || extension.moduleName || extension.module);
    const price = readableValue(
        extension.priceFormatted ||
            extension.priceLabel ||
            extension.price ||
            extension.regularPrice ||
            extension.pricing?.price
    );
    const pro = Boolean(extension.requiresPro) || isPaidProduct(extension);

    return {
        id: extension.id || extension.slug || extension.name || title,
        title: title || __('Untitled extension', 'cci-blog'),
        description: readableValue(extension.description || extension.excerpt || extension.summary || extension.shortDescription),
        version: readableValue(extension.version),
        author: readableValue(extension.author || extension.vendor),
        tags: normalizeTags(extension.tags || extension.categories),
        price,
        pro,
        locked: false,
        screenshot: mediaUrl(
            extension.screenshot ||
                extension.image ||
                extension.cover ||
                extension.thumbnail ||
                extension.preview ||
                extension.featuredImage ||
                extension.media
        ),
        url: productUrl(extension),
    };
}

function uniqueById(extensions) {
    const seen = new Set();

    return extensions.filter((extension) => {
        if (!extension?.id || seen.has(extension.id)) {
            return false;
        }

        seen.add(extension.id);
        return true;
    });
}

function Counter({ children }) {
    return (
        <span className='tw-inline-flex tw-min-w-6 tw-items-center tw-justify-center tw-rounded-full tw-border tw-border-solid tw-border-cci-blog-border tw-bg-white tw-px-2 tw-py-1 tw-text-[11px] tw-leading-none tw-text-cci-blog-muted'>
            {children}
        </span>
    );
}
