export const pluginData = (() => {
  const cfg = window.CCIBlogConfig || {};
  const initialPayload = cfg.initialPayload || {};
  const rawLicense = initialPayload.license || cfg.license || {};
  const defaultI18n = {
    dashboard: 'Posts',
    extensions: 'Extensions',
    diagnostics: 'Diagnostics',
    settings: 'Settings',
    menu: 'Menu',
    save: 'Save',
    active: 'Active',
    inactive: 'Inactive',
    version: 'Version',
  };
  const license = {
    ...rawLicense,
    apiBase: cfg.apiBase || '',
    canManage: Boolean(rawLicense.canManage && rawLicense.proModuleAvailable),
    status: rawLicense.status || (rawLicense.canUse ? 'active' : 'inactive'),
    label: rawLicense.label || (rawLicense.canUse ? 'PRO' : 'Inactive'),
  };
  const features = Array.isArray(initialPayload.features)
    ? initialPayload.features
    : (Array.isArray(rawLicense.availableFeatures) ? rawLicense.availableFeatures : []);
  const enabledFeatures = Array.isArray(initialPayload.enabledFeatures)
    ? initialPayload.enabledFeatures
    : (Array.isArray(rawLicense.enabledFeatures) ? rawLicense.enabledFeatures : (Array.isArray(rawLicense.features) ? rawLicense.features : []));

  const runtime = getCciBlogProRuntime();
  const licenseIsPro = isCciBlogProLicenseActive(license, runtime);
  if (licenseIsPro && !runtime.canUse) {
    syncCciBlogProRuntimeFromLicense(license, { features, enabledFeatures });
  }

  return {
    apiBase: cfg.apiBase || '',
    modulePath: cfg.modulePath || '',
    moduleVersion: cfg.moduleVersion || '1.0.0',
    ...initialPayload,
    features,
    enabledFeatures,
    pluginVersion: cfg.pluginVersion || cfg.moduleVersion || initialPayload.moduleVersion || '1.0.0',
    marketplaceFeedEndpoint: cfg.marketplaceFeedEndpoint || initialPayload.marketplaceFeedEndpoint || '',
    templateStoreEndpoint: cfg.templateStoreEndpoint || cfg.marketplaceTemplatesEndpoint || initialPayload.templateStoreEndpoint || '',
    extensionStoreEndpoint: cfg.extensionStoreEndpoint || cfg.marketplaceExtensionsEndpoint || initialPayload.extensionStoreEndpoint || cfg.integrationStoreEndpoint || initialPayload.integrationStoreEndpoint || cfg.templateStoreEndpoint || cfg.marketplaceTemplatesEndpoint || initialPayload.templateStoreEndpoint || '',
    blockExtensions: [
      ...normalizeBlockExtensions(initialPayload.blockExtensions || cfg.blockExtensions || []),
      ...normalizeBlockExtensions(window.cciBlogBlockExtensions || []),
    ],
    hookOptions: normalizeHookOptions(initialPayload.hookOptions || cfg.hookOptions || []),
    upgradeUrl: cfg.upgradeUrl || initialPayload.upgradeUrl || initialPayload.adminLinks?.store || initialPayload.adminLinks?.documentation || '#',
    reviewUrl: cfg.reviewUrl || cfg.wordpressReviewUrl || initialPayload.reviewUrl || initialPayload.adminLinks?.documentation || '#',
    wordpressReviewUrl: cfg.wordpressReviewUrl || cfg.reviewUrl || initialPayload.reviewUrl || initialPayload.adminLinks?.documentation || '#',
    reviewEndpoint: cfg.reviewEndpoint || '/review-feedback',
    reviewFeedbackEndpoint: cfg.reviewFeedbackEndpoint || cfg.reviewEndpoint || '/review-feedback',
    documentationUrl: cfg.documentationUrl || initialPayload.adminLinks?.documentation || '#',
    license,
    isPro: licenseIsPro,
    i18n: {
      ...defaultI18n,
      ...(initialPayload.i18n || cfg.i18n || {}),
    },
  };
})();

export function getCciBlogProRuntime() {
  if (typeof window === 'undefined') {
    return { canUse: false, features: [], blockKinds: [] };
  }

  const runtime = window.cciBlogProRuntime || {};
  const license = runtime.license || {};
  const features = Array.isArray(runtime.features) ? runtime.features.map((feature) => String(feature)) : [];
  const blockKinds = Array.isArray(runtime.blockKinds) && runtime.blockKinds.length
    ? runtime.blockKinds.map((kind) => String(kind))
    : cciBlogBlockKindsForFeatures(features);

  return {
    ...runtime,
    canUse: Boolean(runtime.canUse || license.canUse),
    features,
    blockKinds,
    source: String(runtime.source || license.source || ''),
  };
}

function cciBlogBlockKindsForFeatures(features) {
  const enabled = new Set(Array.isArray(features) ? features.map((feature) => String(feature)) : []);
  const kinds = [];

  if (enabled.has('advanced_layout_blocks')) kinds.push('columns');
  if (enabled.has('commerce_blocks')) kinds.push('product');
  if (enabled.has('product_carousel')) kinds.push('product_carousel');
  if (enabled.has('hook_blocks')) kinds.push('hook');
  if (enabled.has('extension_blocks')) kinds.push('module_block');

  return kinds;
}

export function isCciBlogProRuntimeActive() {
  const runtime = getCciBlogProRuntime();

  return runtime.canUse === true && runtime.source === 'cci_blog_pro';
}

export function isCciBlogProLicenseActive(license = {}, runtime = null) {
  const normalized = license && typeof license === 'object' ? license : {};
  const source = String(normalized.source || runtime?.source || '');
  const hasProSource = source === 'cci_blog_pro' || Boolean(normalized.proModuleAvailable);
  if (normalized.writeLocked || normalized.locked) return false;
  const hasActiveState = normalized.status === 'active'
    || Boolean(normalized.canUse)
    || Boolean(normalized.runtimeAllowed)
    || Boolean(normalized.isPro);

  return hasProSource && hasActiveState;
}

export function isCciBlogProFeatureEnabled(feature) {
  if (!feature || !isCciBlogProRuntimeActive()) {
    return false;
  }

  return getCciBlogProRuntime().features.includes(String(feature));
}

export function isCciBlogProBlockKindEnabled(kind) {
  if (!kind || !isCciBlogProRuntimeActive()) {
    return false;
  }

  return getCciBlogProRuntime().blockKinds.includes(String(kind));
}

function normalizeBlockExtensions(extensions) {
  if (!Array.isArray(extensions)) {
    return [];
  }

  return extensions
    .filter((extension) => extension && typeof extension === 'object')
    .map((extension) => ({
      ...extension,
      moduleName: extension.moduleName || extension.module || '',
      blockName: extension.blockName || extension.block || '',
    }));
}

function normalizeHookOptions(options) {
  if (!Array.isArray(options)) {
    return [];
  }

  const seen = new Set();
  return options
    .filter((option) => option && typeof option === 'object')
    .map((option) => ({
      name: String(option.name || option.hook || '').trim(),
      label: String(option.label || option.title || option.name || option.hook || '').trim(),
      description: String(option.description || '').trim(),
      moduleName: String(option.moduleName || option.module || '').trim(),
      moduleCount: Number(option.moduleCount || 0),
      sourceType: String(option.sourceType || option.source || '').trim(),
    }))
    .filter((option) => {
      if (!option.name || seen.has(option.name)) {
        return false;
      }
      seen.add(option.name);
      return true;
    });
}

const actionMap = {
  '/dashboard/get': 'dashboard:get',
  '/diagnostics/get': 'diagnostics:get',
  '/settings/save': 'settings:save',
  '/posts/list': 'posts:list',
  '/post/get': 'post:get',
  '/post/save': 'post:save',
  '/post/delete': 'post:delete',
  '/categories/list': 'categories:list',
  '/category/get': 'category:get',
  '/category/save': 'category:save',
  '/category/delete': 'category:delete',
  '/multistore/context': 'multistore:context',
  '/multistore/posts/assign': 'multistore:post:assign-shops',
  '/multistore/categories/assign': 'multistore:category:assign-shops',
  '/comments/list': 'comments:list',
  '/comment/update-status': 'comment:update-status',
  '/catalog/products': 'catalog:products',
  '/media/list': 'media:list',
  '/license/get': 'license:get',
  '/license/activate': 'license:activate',
  '/license/check': 'license:get',
  '/license/deactivate': 'license:deactivate',
  '/review-feedback': 'review-feedback:save',
};

function resolveAction(path) {
  const rawPath = String(path || '');
  const match = Object.keys(actionMap).find((endpoint) => rawPath.endsWith(endpoint) || rawPath.includes(endpoint));

  return match ? actionMap[match] : rawPath.replace(/^\//, '').replace(/\//g, ':');
}

function buildAdminUrl(action, params = {}) {
  const separator = pluginData.apiBase.includes('?') || pluginData.apiBase.includes('&') ? '&' : '?';
  const query = new URLSearchParams({
    ajax: '1',
    ajaxAction: action,
    ...params,
  });

  return `${pluginData.apiBase}${separator}${query.toString()}`;
}

export function apiFetch(path, options = {}) {
  const action = resolveAction(path);
  const requestOptions = {
    credentials: 'same-origin',
    method: options.method || 'POST',
    headers: {
      'Content-Type': 'application/json',
      ...(options.headers || {}),
    },
    ...options,
  };
  const url = buildAdminUrl(action, options.params || {});

  return fetch(url, requestOptions)
    .then((response) => response.json()
      .catch(() => ({}))
      .then((payload) => {
        const normalized = normalizeLicensePayload(payload || {});

        if (!response.ok || normalized.success === false) {
          const error = new Error(normalized.error || normalized.message || `CCI Blog request failed (${response.status}).`);
          error.details = payload.details || payload.warning || '';
          error.data = normalized;
          throw error;
        }

        return normalized;
      }));
}

function normalizeLicensePayload(response = {}) {
  if (!response.license) {
    return response;
  }

  const license = {
    ...response.license,
    apiBase: pluginData.apiBase,
    canManage: Boolean(response.license.canManage && response.license.proModuleAvailable),
    status: response.license.status || (response.license.canUse ? 'active' : 'inactive'),
    label: response.license.label || (response.license.canUse ? 'PRO' : 'Inactive'),
  };

  if (Array.isArray(response.features)) {
    pluginData.features = response.features;
  } else if (Array.isArray(license.availableFeatures)) {
    pluginData.features = license.availableFeatures;
  }
  if (Array.isArray(response.enabledFeatures)) {
    pluginData.enabledFeatures = response.enabledFeatures;
  } else if (Array.isArray(license.enabledFeatures)) {
    pluginData.enabledFeatures = license.enabledFeatures;
  } else if (Array.isArray(license.features)) {
    pluginData.enabledFeatures = license.features;
  }
  syncCciBlogProRuntimeFromLicense(license, response);

  pluginData.license = license;
  pluginData.isPro = isCciBlogProLicenseActive(license);

  return {
    ...response,
    license,
  };
}

function syncCciBlogProRuntimeFromLicense(license = {}, response = {}) {
  if (typeof window === 'undefined') {
    return;
  }

  const runtimeController = window.CCIBlogProRuntimeController || null;
  if (runtimeController && typeof runtimeController.syncFromLicense === 'function') {
    runtimeController.syncFromLicense(license, response);
    return;
  }

  const features = Array.isArray(response.features)
    ? response.features.map((feature) => String(feature))
    : (Array.isArray(license.availableFeatures)
      ? license.availableFeatures.map((feature) => String(feature))
      : (Array.isArray(license.features) ? license.features.map((feature) => String(feature)) : []));

  window.cciBlogProRuntime = {
    source: String(license.source || 'cci_blog_pro'),
    canUse: isCciBlogProLicenseActive(license, { source: 'cci_blog_pro' }),
    features,
    blockKinds: cciBlogBlockKindsForFeatures(features),
    license,
  };
  window.dispatchEvent(new CustomEvent('cci-blog:pro-runtime-ready', { detail: window.cciBlogProRuntime }));
}
