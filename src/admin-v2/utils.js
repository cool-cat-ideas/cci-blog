import { useMemo } from 'react';

export function useQueryParam(key) {
    return useMemo(() => new URLSearchParams(window.location.search).get(key), [key]);
}

export function carouselDefaults() {
    return {
        title: '',
        status: 'draft',
        renderer: 'php',
        carousel_type: 'owl-carousel',
        template: 'default',
        post_selection_options: {
            post_types: [{ value: 'post', label: 'post' }],
            taxonomies: {},
            order_by: 'id',
            ordering: 'desc',
            posts: [],
            exclude: [],
            relation: 'or',
            all_items: 10,
        },
        carousel_options: {},
        display_options: {},
        curation_pattern: {
            rhythm: [],
            preset: '',
        },
    };
}

export function normalizePostOption(item) {
    const type = item.type || 'post';

    return {
        ...item,
        type,
        id: item.id || item.value || `custom-${Date.now()}`,
        value: String(item.value || item.id || ''),
        post_type: item.post_type || item.postType || (type === 'custom' ? 'custom' : 'post'),
        postType: item.postType || item.post_type || (type === 'custom' ? 'custom' : 'post'),
        thumbnail: item.thumbnail || item.image_url || '',
        thumbnail_alt: item.thumbnail_alt || item.label || '',
        image_url: item.image_url || item.thumbnail || '',
        media_kind: item.media_kind || 'image',
        video_url: item.video_url || '',
        video_poster: item.video_poster || '',
        description: item.description || '',
        url: item.url || '',
        button_label: item.button_label || '',
        span: Number(item.span || 1),
        group: item.group || '',
        crop_x: Number(item.crop_x || 0),
        crop_y: Number(item.crop_y || 0),
    };
}

export function normalizeTaxonomyGroups(groups) {
    if (!Array.isArray(groups)) {
        return [];
    }

    return groups
        .map((group) => ({
            post_type: group.post_type || group.postType || '',
            taxonomies: Array.isArray(group.taxonomies)
                ? group.taxonomies.map(normalizeTaxonomyFilter).filter(Boolean)
                : [],
        }))
        .filter((group) => group.post_type && group.taxonomies.length);
}

export function normalizeTaxonomyFilter(taxonomy) {
    if (!taxonomy || !taxonomy.name) {
        return null;
    }

    return {
        name: taxonomy.name,
        label: taxonomy.label || taxonomy.name,
        operator: taxonomy.operator || 'in',
        terms: Array.isArray(taxonomy.terms)
            ? taxonomy.terms.map(normalizeTermOption).filter((term) => term.value)
            : [],
    };
}

export function normalizeTermOption(term) {
    return {
        ...term,
        value: String(term.value || term.id || ''),
        label: term.label || term.name || '',
    };
}

export function displayDefaultsFromDefinitions(definitions) {
    return Object.keys(definitions || {}).reduce((result, groupName) => {
        result[groupName] = (definitions[groupName] || []).reduce((values, field) => {
            if (field.type === 'group') {
                (field.fields || []).forEach((child) => {
                    values[child.name] = child.default_value ?? '';
                });
            } else {
                values[field.name] = field.default_value ?? '';
            }

            return values;
        }, {});

        return result;
    }, {});
}

export function mergeDisplayOptions(definitions, defaults = {}, values = {}) {
    const definitionDefaults = displayDefaultsFromDefinitions(definitions);
    const groupNames = new Set([
        ...Object.keys(definitionDefaults),
        ...Object.keys(defaults || {}),
        ...Object.keys(values || {}),
    ]);

    return Array.from(groupNames).reduce((result, groupName) => {
        result[groupName] = {
            ...(definitionDefaults[groupName] || {}),
            ...(defaults[groupName] || {}),
            ...(values[groupName] || {}),
        };

        return result;
    }, {});
}

export function selectedValues(options) {
    return (options || []).map((option) => option.value || option);
}

export function moveItem(items, from, to) {
    const next = [...items];
    const [item] = next.splice(from, 1);
    next.splice(to, 0, item);

    return next;
}

export function compactLicenseError(error, unavailableMessage, fallbackMessage) {
    const message = error?.message || fallbackMessage || '';

    if (/Connection attempts:|cURL error|Failed to connect|ECONNREFUSED|server is unavailable/i.test(message)) {
        return unavailableMessage || fallbackMessage || message;
    }

    return message;
}
