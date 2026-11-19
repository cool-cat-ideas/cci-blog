import { ProductStatusPanel, compareProductVersions } from '@cci/admin-ui/product-panels';
import React from 'react';
import { ArrowRight, CheckCircle2, Crown } from 'lucide-react';
import { isCciBlogProLicenseActive, pluginData } from '../../api/api';
import { __ } from '../../i18n/i18n';
import { readableValue, useMarketplaceJson } from '../../catalog/marketplace';
import { Button } from '../ui/button';
import { Card } from '../ui/card';

export default function LicensePanel({ license: controlledLicense }) {
  const productFeed = useMarketplaceJson(pluginData.marketplaceFeedEndpoint, {
    enabled: Boolean(pluginData.marketplaceFeedEndpoint),
  });
  const license = controlledLicense || pluginData.license || {};
  const isPro = isCciBlogProLicenseActive(license);
  const hasProductFeed =
    Boolean(productFeed.data?.product) && productFeed.data?._meta?.source !== 'empty';
  const product = hasProductFeed ? productFeed.data?.product || {} : {};
  const upgradeUrl = readableValue(product?.pro?.url || pluginData.upgradeUrl);
  const versionFeedStale = Boolean(productFeed.error || productFeed.data?._meta?.stale);
  const editions = [
    {
      name: 'CCI Blog',
      installed: readableValue(pluginData.pluginVersion),
      latest: readableValue(product.free?.version),
      updateUrl: readableValue(
        product.free?.urls?.download || product.free?.urls?.wordpress || pluginData.upgradeUrl,
      ),
    },
    {
      name: 'CCI Blog Pro',
      installed: readableValue(pluginData.proVersion),
      latest: readableValue(product.pro?.version),
      updateUrl: upgradeUrl,
      pro: true,
    },
  ];
  const ctaLabel = __('See CCI Blog Pro', 'cci-blog');
  const ctaIcon = <ArrowRight aria-hidden="true" />;
  return (
    <div className="tw-grid tw-content-start tw-gap-4 tw-self-start">
      {editions.map(({ name, installed, latest, updateUrl, pro }) => {
        const aheadOfCatalog = compareProductVersions(latest, installed) < 0;
        const labels = {
          'Module status': name,
          Installed: __('Installed version', 'cci-blog'),
          Latest: __('Latest version', 'cci-blog'),
          ...(aheadOfCatalog && {
            'You are up to date': __('Installed version is newer', 'cci-blog'),
            'Your installed version is current.': __(
              'The product catalog lists an older version.',
              'cci-blog',
            ),
          }),
          ...(pro &&
            !installed && {
              'Version feed is unavailable': __('Pro is not installed', 'cci-blog'),
              'Version information is temporarily unavailable. Please try again later.': __(
                'Install Pro to use its features.',
                'cci-blog',
              ),
            }),
        };

        return (
          <ProductStatusPanel
            key={name}
            t={(text) => labels[text] || __(text, 'cci-blog')}
            isPro={Boolean(pro)}
            licenseStatus={pro ? license.status : undefined}
            licenseMessage={pro ? license.message : undefined}
            installedVersion={installed || __('Not installed', 'cci-blog')}
            latestVersion={latest}
            versionFeedStale={versionFeedStale}
            updateUrl={updateUrl}
            checkedAt={pro ? license.checkedAt : undefined}
            nextCheckAt={pro ? license.nextCheckAt : undefined}
            adminDate={pluginData.adminDate}
            expires={pro ? license.expires : undefined}
          />
        );
      })}

      {!isPro && (
        <Card className="tw-overflow-hidden tw-border-cci-blog-brandBorder tw-bg-white">
          <div className="tw-grid tw-gap-4 tw-p-4">
            <div className="tw-flex tw-items-start tw-gap-3">
              <span
                className="tw-inline-flex tw-h-10 tw-w-10 tw-shrink-0 tw-items-center tw-justify-center tw-rounded-md tw-border tw-border-solid tw-border-amber-200 tw-bg-amber-50 tw-text-amber-700 [&_svg]:tw-h-5 [&_svg]:tw-w-5"
                aria-hidden="true"
              >
                <Crown />
              </span>
              <div className="tw-grid tw-min-w-0 tw-gap-1">
                <span className="tw-text-sm tw-font-semibold tw-leading-5 tw-text-amber-700">
                  {__('CCI Blog Pro', 'cci-blog')}
                </span>
                <span className="tw-m-0 tw-block tw-text-lg tw-font-semibold tw-leading-tight tw-text-cci-blog-text">
                  {__('Sell from your articles', 'cci-blog')}
                </span>
              </div>
            </div>

            <p className="tw-m-0 tw-text-sm tw-leading-5 tw-text-cci-blog-muted">
              {__(
                'Publish in several languages, add products to articles and give editors more layout choices.',
                'cci-blog',
              )}
            </p>

            <ul className="tw-m-0 tw-grid tw-list-none tw-gap-2.5 tw-border-0 tw-p-0">
              {[
                __('Edit content and SEO for each shop language separately', 'cci-blog'),
                __('Place products and product carousels inside articles', 'cci-blog'),
                __('Build responsive columns and extra content blocks', 'cci-blog'),
                __('Use Multistore assignment and a local image library', 'cci-blog'),
              ].map((feature) => (
                <li
                  key={feature}
                  className="tw-grid tw-grid-cols-[auto_minmax(0,1fr)] tw-items-start tw-gap-2 tw-text-sm tw-leading-5 tw-text-cci-blog-muted"
                >
                  <CheckCircle2
                    className="tw-mt-0.5 tw-h-4 tw-w-4 tw-text-amber-500"
                    aria-hidden="true"
                  />
                  <span>{feature}</span>
                </li>
              ))}
            </ul>

            {upgradeUrl ? (
              <Button variant="primary" className="cci-blog-state-full" asChild>
                <a href={upgradeUrl} target="_blank" rel="noreferrer">
                  {ctaLabel}
                  {ctaIcon}
                </a>
              </Button>
            ) : (
              <Button variant="primary" className="cci-blog-state-full" disabled>
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
