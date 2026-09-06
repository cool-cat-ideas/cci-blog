import { pluginData } from './api';
import { __ } from './i18n';

export const defaultContentHookName = 'displayCciBlogPostMiddle';

const fallbackHookOptions = [
    {
        name: 'displayCciBlogPostTop',
        label: 'displayCciBlogPostTop',
        description: __('Rendered before the main article content.', 'cci-blog'),
        moduleName: 'cci_blog',
        moduleCount: 0,
        sourceType: 'blog',
    },
    {
        name: defaultContentHookName,
        label: 'displayCciBlogPostMiddle',
        description: __('Rendered inside the article flow where the Hook block is placed.', 'cci-blog'),
        moduleName: 'cci_blog',
        moduleCount: 0,
        sourceType: 'blog',
    },
    {
        name: 'displayCciBlogPostBottom',
        label: 'displayCciBlogPostBottom',
        description: __('Rendered after the main article content.', 'cci-blog'),
        moduleName: 'cci_blog',
        moduleCount: 0,
        sourceType: 'blog',
    },
];

export function getContentHookOptions(currentValue = '') {
    const source = Array.isArray(pluginData.hookOptions) && pluginData.hookOptions.length
        ? pluginData.hookOptions
        : fallbackHookOptions;
    const seen = new Set();
    const currentHook = String(currentValue || '').trim();

    const options = source
        .map((option) => {
            const name = option.name;
            const label = option.label || name;

            return {
                value: name,
                label,
                name,
                description: option.description || '',
                moduleName: option.moduleName || '',
                moduleCount: Number(option.moduleCount || 0),
                sourceType: option.sourceType || 'extension',
            };
        })
        .filter((option) => {
            if (!option.value || seen.has(option.value)) {
                return false;
            }
            seen.add(option.value);
            return true;
        });

    if (currentHook && !seen.has(currentHook)) {
        return [
            {
                value: currentHook,
                label: `${__('Missing hook:', 'cci-blog')} ${currentHook}`,
                name: currentHook,
                description: __('This hook is saved in the post but is no longer available in the editor.', 'cci-blog'),
                moduleName: '',
                moduleCount: 0,
                sourceType: 'missing',
                missing: true,
            },
            ...options,
        ];
    }

    return options;
}

export function getContentHookFieldNote(hookName) {
    const option = getContentHookOptions(hookName).find((item) => item.value === hookName) || null;
    const extensionInfo = __('Blog extensions can add article hooks through displayCciBlogContentHookOptions. Storefront hooks come from installed modules attached to safe display hooks.', 'cci-blog');

    if (!option) {
        return extensionInfo;
    }

    if (option.missing) {
        return `${option.description} ${__('It will not render on the storefront until the extension or storefront hook becomes available again.', 'cci-blog')} ${extensionInfo}`;
    }

    return [option.description, hookSourceLabel(option), option.moduleName ? `${__('Modules:', 'cci-blog')} ${option.moduleName}` : '', extensionInfo]
        .filter(Boolean)
        .join(' ');
}

export function getContentHookHelpText() {
    return __('Use Blog hooks for CCI Blog extension output, or Storefront hooks to embed output from installed PrestaShop modules. Only safe display hooks with listeners are listed; unknown hook names are ignored on the storefront.', 'cci-blog');
}

export function hookSourceLabel(option = {}) {
    const sourceType = String(option.sourceType || '').toLowerCase();
    if (sourceType === 'blog') {
        return __('Source: CCI Blog article hook', 'cci-blog');
    }
    if (sourceType === 'storefront') {
        return __('Source: Storefront hook', 'cci-blog');
    }
    if (sourceType === 'missing') {
        return __('Source: Missing hook', 'cci-blog');
    }

    return __('Source: Blog extension', 'cci-blog');
}
