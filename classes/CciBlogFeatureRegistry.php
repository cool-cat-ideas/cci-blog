<?php
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

class CciBlogFeatureRegistry
{
    private const PRO_MODULE_NAME = 'cci_blog_pro';
    private const RESERVED_PRO_FEATURE_KEYS = [
        'cci_blog_pro',
        'translations',
        'additional_categories',
        'advanced_layout_blocks',
        'commerce_blocks',
        'product_carousel',
        'hook_blocks',
        'extension_blocks',
        'advanced_seo',
        'multistore',
        'local_media_library',
    ];

    private Module $module;

    public function __construct(Module $module)
    {
        $this->module = $module;
    }

    public function integrationHooks(): array
    {
        return [
            'displayCciBlogFeatures',
            'displayCciBlogAdminExtensions',
            'displayCciBlogAdminBlockExtensions',
            'displayCciBlogContentHookOptions',
        ];
    }

    public function getFeatures(array $license = []): array
    {
        $proActive = $this->isProActive($license);
        $features = [];

        foreach ($this->baseFeatureDefinitions() as $key => $definition) {
            $features[$key] = $this->normalizeFeature($key, $definition, true, 'core');
        }

        foreach ($this->featureContributions($features, $license) as $key => $definition) {
            if ($key === '') {
                continue;
            }

            $current = $features[$key] ?? [];
            $source = $this->normalizeFeatureSource($definition, $current);
            if (isset($features[$key]) && !$this->canOverrideFeature($key, $source)) {
                continue;
            }

            $mergedDefinition = array_merge($current, $definition);
            $requirements = $this->normalizeRequirements(
                $mergedDefinition['requires'] ?? $mergedDefinition['requiredFeatures'] ?? $mergedDefinition['required_features'] ?? []
            );
            $enabled = (bool) ($definition['enabled'] ?? $current['enabled'] ?? false);

            if (!empty($requirements) && !$this->requirementsMet($requirements, $features)) {
                $enabled = false;
            }

            if ($this->contributionRequiresCorePro($mergedDefinition, $key, $requirements) && !$proActive) {
                $enabled = false;
            }

            $features[$key] = $this->normalizeFeature(
                $key,
                $mergedDefinition,
                $enabled,
                $source
            );
        }

        return $features;
    }

    public function decorateLicensePayload(array $license): array
    {
        $features = $this->getFeatures($license);
        $enabledFeatures = array_values(array_keys(array_filter($features, static fn(array $feature): bool => !empty($feature['enabled']))));
        $proInstalled = $this->isProModuleInstalled();
        $isPro = $this->isProActive($license);
        $status = (string) ($license['status'] ?? 'inactive');
        $inactiveStatus = in_array($status, ['pending', 'expired'], true) ? $status : 'inactive';

        return array_merge($license, [
            'status' => $isPro ? 'active' : $inactiveStatus,
            'label' => $isPro ? 'PRO' : ((string) ($license['label'] ?? $this->module->l('Free'))),
            'canUse' => $isPro,
            'runtimeAllowed' => $proInstalled && !empty($license['runtimeAllowed']),
            'isActive' => $isPro,
            'isPro' => $isPro,
            'proInstalled' => $proInstalled,
            'proModuleAvailable' => $proInstalled,
            'features' => $enabledFeatures,
            'enabledFeatures' => $enabledFeatures,
            'availableFeatures' => array_values($features),
            'canManage' => $proInstalled,
        ]);
    }

    public function getAdminExtensions(array $features): array
    {
        $raw = Hook::exec('displayCciBlogAdminExtensions', [
            'features' => $features,
            'module' => $this->module->name,
        ], null, true);

        if (!is_array($raw)) {
            return [];
        }

        $extensions = [];
        foreach ($raw as $group) {
            foreach ($this->normalizeContributionGroup($group) as $extension) {
                $id = $this->sanitizeKey((string) ($extension['id'] ?? $extension['moduleName'] ?? $extension['module'] ?? ''));
                $title = trim((string) ($extension['title'] ?? $extension['name'] ?? $extension['moduleName'] ?? $extension['module'] ?? ''));
                if ($id === '' || $title === '') {
                    continue;
                }

                $requirements = $this->normalizeRequirements($extension['requires'] ?? $extension['requiredFeatures'] ?? $extension['feature'] ?? []);
                $locked = !empty($requirements) && !$this->requirementsMet($requirements, $features);
                $extensions[] = [
                    'id' => $id,
                    'title' => $title,
                    'description' => trim((string) ($extension['description'] ?? '')),
                    'version' => trim((string) ($extension['version'] ?? '')),
                    'author' => trim((string) ($extension['author'] ?? $title)),
                    'status' => 'installed',
                    'pro' => !empty($requirements) || !empty($extension['pro']) || !empty($extension['requiresPro']),
                    'locked' => $locked,
                    'requiredFeatures' => $requirements,
                    'availabilityMessage' => $locked
                        ? $this->module->l('Install or activate CCI Blog Pro to use this extension.')
                        : '',
                    'tags' => is_array($extension['tags'] ?? null) ? array_values($extension['tags']) : [],
                    'url' => trim((string) ($extension['url'] ?? '')),
                    'screenshot' => trim((string) ($extension['screenshot'] ?? '')),
                ];
            }
        }

        return $extensions;
    }

    public function getPostPayloadRequiredFeatures(array $payload): array
    {
        $requirements = [];
        $this->collectBlockRequirements(is_array($payload['blocks'] ?? null) ? $payload['blocks'] : [], $requirements);

        return array_values(array_unique($requirements));
    }

    public function requirementsMet(array $requirements, array $features): bool
    {
        foreach ($requirements as $requirement) {
            $key = $this->sanitizeKey((string) $requirement);
            if ($key === '') {
                continue;
            }

            if (empty($features[$key]['enabled'])) {
                return false;
            }
        }

        return true;
    }

    public function missingRequirementLabels(array $requirements, array $features): array
    {
        $labels = [];
        foreach ($requirements as $requirement) {
            $key = $this->sanitizeKey((string) $requirement);
            if ($key === '' || !empty($features[$key]['enabled'])) {
                continue;
            }

            $labels[] = (string) ($features[$key]['label'] ?? $key);
        }

        return array_values(array_unique($labels));
    }

    public function featureForBlockType(string $type): string
    {
        return match ($this->sanitizeKey($type)) {
            'columns' => 'advanced_layout_blocks',
            'product' => 'commerce_blocks',
            'product_carousel' => 'product_carousel',
            'hook' => 'hook_blocks',
            'module_block' => 'extension_blocks',
            default => '',
        };
    }

    public function proFeatureKeys(): array
    {
        return self::RESERVED_PRO_FEATURE_KEYS;
    }

    private function baseFeatureDefinitions(): array
    {
        return [
            'posts' => [
                'label' => $this->module->l('Posts'),
                'description' => $this->module->l('Create and publish blog posts.'),
            ],
            'categories' => [
                'label' => $this->module->l('Categories'),
                'description' => $this->module->l('Organize posts by category.'),
            ],
            'comments' => [
                'label' => $this->module->l('Comments'),
                'description' => $this->module->l('Use Disqus comments or moderate native comments.'),
            ],
            'block_editor_basic' => [
                'label' => $this->module->l('Basic block editor'),
                'description' => $this->module->l('Use text, headings, links, images and video blocks.'),
            ],
            'basic_seo' => [
                'label' => $this->module->l('Basic SEO fields'),
                'description' => $this->module->l('Edit slug, meta title, meta description and Open Graph fields.'),
            ],
            'frontend_runtime' => [
                'label' => $this->module->l('Frontend runtime'),
                'description' => $this->module->l('Render blog posts on the storefront.'),
            ],
        ];
    }

    private function collectBlockRequirements(array $blocks, array &$requirements): void
    {
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }

            $feature = $this->featureForBlockType((string) ($block['type'] ?? ''));
            if ($feature !== '') {
                $requirements[] = $feature;
            }

            if (isset($block['blocks']) && is_array($block['blocks'])) {
                $this->collectBlockRequirements($block['blocks'], $requirements);
            }

            if (isset($block['columns']) && is_array($block['columns'])) {
                foreach ($block['columns'] as $column) {
                    if (is_array($column) && isset($column['blocks']) && is_array($column['blocks'])) {
                        $this->collectBlockRequirements($column['blocks'], $requirements);
                    }
                }
            }
        }
    }

    private function isProActive(array $license): bool
    {
        if (!empty($license['locked']) || !empty($license['writeLocked'])) {
            return false;
        }

        return $this->isProModuleInstalled()
            && (
                !empty($license['canUse'])
                || !empty($license['runtimeAllowed'])
                || !empty($license['isActive'])
                || (string) ($license['status'] ?? '') === 'active'
            );
    }

    private function isProModuleInstalled(): bool
    {
        if (!class_exists('Module')) {
            return false;
        }

        $installed = Module::isInstalled(self::PRO_MODULE_NAME);
        $enabled = method_exists(Module::class, 'isEnabled') ? Module::isEnabled(self::PRO_MODULE_NAME) : $installed;

        if (!$installed || !$enabled) {
            return false;
        }

        $module = Module::getInstanceByName(self::PRO_MODULE_NAME);

        return $module instanceof Module && !empty($module->active);
    }

    private function featureContributions(array $features, array $license): array
    {
        $raw = Hook::exec('displayCciBlogFeatures', [
            'features' => $features,
            'license' => $license,
            'module' => $this->module->name,
        ], null, true);

        if (!is_array($raw)) {
            return [];
        }

        $result = [];
        foreach ($raw as $group) {
            foreach ($this->normalizeContributionGroup($group) as $feature) {
                $key = $this->sanitizeKey((string) ($feature['key'] ?? $feature['id'] ?? $feature['feature'] ?? ''));
                if ($key === '') {
                    continue;
                }

                $result[$key] = $feature;
            }
        }

        return $result;
    }

    private function normalizeContributionGroup($group): array
    {
        if (is_string($group)) {
            $decoded = json_decode($group, true);
            $group = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }

        if (!is_array($group)) {
            return [];
        }

        if (isset($group['key']) || isset($group['id']) || isset($group['feature']) || isset($group['module']) || isset($group['moduleName'])) {
            return [$group];
        }

        return array_values(array_filter($group, static fn($item): bool => is_array($item)));
    }

    private function normalizeFeature(string $key, array $definition, bool $enabled, string $source): array
    {
        $source = $this->sanitizeKey($source);
        $plan = $this->sanitizeKey((string) ($definition['plan'] ?? ($source === 'core' ? 'free' : 'pro')));

        return [
            'key' => $this->sanitizeKey($key),
            'label' => trim((string) ($definition['label'] ?? $key)),
            'description' => trim((string) ($definition['description'] ?? '')),
            'enabled' => $enabled,
            'source' => $source,
            'plan' => $plan,
            'requiresPro' => !empty($definition['requiresPro']) || $plan === 'pro' || $plan === 'paid' || ($source !== 'core' && $source !== 'free'),
        ];
    }

    private function normalizeFeatureSource(array $definition, array $current = []): string
    {
        return $this->sanitizeKey((string) (
            $definition['source']
            ?? $definition['moduleName']
            ?? $definition['module']
            ?? $current['source']
            ?? 'extension'
        ));
    }

    private function canOverrideFeature(string $key, string $source): bool
    {
        if (!$this->isReservedProFeature($key) && !isset($this->baseFeatureDefinitions()[$key])) {
            return true;
        }

        return $this->isCoreProSource($source);
    }

    private function contributionRequiresCorePro(array $definition, string $key, array $requirements = []): bool
    {
        if ($this->isReservedProFeature($key)) {
            return true;
        }

        $plan = $this->sanitizeKey((string) ($definition['plan'] ?? ''));
        if (!empty($definition['requiresPro']) || !empty($definition['requires_core_pro']) || $plan === 'pro' || $plan === 'paid') {
            return true;
        }

        foreach ($requirements as $requirement) {
            if ($this->isReservedProFeature($requirement)) {
                return true;
            }
        }

        return false;
    }

    private function isReservedProFeature(string $key): bool
    {
        return in_array($this->sanitizeKey($key), $this->proFeatureKeys(), true);
    }

    private function isCoreProSource(string $source): bool
    {
        return $this->sanitizeKey($source) === self::PRO_MODULE_NAME;
    }

    private function normalizeRequirements($requirements): array
    {
        if (is_string($requirements)) {
            $requirements = preg_split('/[,|]/', $requirements) ?: [];
        } elseif (!is_array($requirements)) {
            $requirements = [];
        }

        return array_values(array_unique(array_filter(array_map([$this, 'sanitizeKey'], $requirements))));
    }

    private function sanitizeKey(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_\\-]+/', '_', $value);
        $value = is_string($value) ? trim($value, '_-') : '';

        return str_replace('-', '_', $value);
    }
}
