# CCI Blog hooks reference

This page lists the public PrestaShop hooks implemented or consumed by CCI Blog 1.0.0. Use a separate module for integrations. Register every hook your module implements in its own `install()` method.

Hooks described as contribution hooks are executed with PrestaShop's array-return mode. A module may return one descriptor or a list; CCI Blog normalizes all module results.

## Start with the right extension path

| Goal | Extension surface | Guide |
| --- | --- | --- |
| Theme markup | PrestaShop override for article, listing or widget templates | [Extension cookbook](./EXTENSION_COOKBOOK.md#override-the-storefront-post-card) |
| Theme styling | CSS scoped to `.cci-blog-*` | [Templates and styling](./TEMPLATES_AND_STYLING.md) |
| Existing module output | Hook block with an allowlisted display hook | [Extension cookbook](./EXTENSION_COOKBOOK.md#render-an-existing-prestashop-hook-in-an-article) |
| New editor block | Separate PrestaShop module owning saved data and storefront HTML | [Extension cookbook](./EXTENSION_COOKBOOK.md) |
| Extension metadata | Feature and installed-extension hooks | Continue with this document |

An editor extension normally uses a pair of hooks: one registers the block in
the editor and the other renders its saved payload on the storefront.

## Choose hooks by the workflow they complete

| Workflow | Public hooks | Result |
| --- | --- | --- |
| Declare a capability | `displayCciBlogFeatures` | Namespaced feature descriptor |
| Show an installed extension | `displayCciBlogAdminExtensions` | Installed-extension metadata |
| Add and render an editor block | `displayCciBlogAdminBlockExtensions` plus `displayCciBlogContentBlock` | Editor metadata and storefront HTML |
| Add a Hook-block target | `displayCciBlogContentHookOptions` plus the selected `display*` hook | Selectable hook metadata and article HTML |
| Render a delegated block | `displayCciBlogRenderContentBlock` | HTML for one supported advanced block type |
| Load admin JavaScript | `displayCciBlogAdminAssets` | Scripts for the React workspace |

## Feature contributions

### `displayCciBlogFeatures`

Use this hook to contribute a namespaced capability from Pro or an independent extension.

- **Parameters:** `$params['features']` (`array<string,array>`) contains features already known to the registry; `$params['license']` (`array`) contains the current public license state; `$params['module']` (`string`) is `cci_blog`.
- **Returns:** `array<int,array>|array` containing feature descriptors. Each descriptor needs `key`; it may contain `label`, `description`, `enabled`, `source`, `requires`, `plan` and `requiresPro`.
- **Side effects/HTML:** none. Reserved core Pro keys cannot be unlocked by an add-on.

```php
public function hookDisplayCciBlogFeatures(array $params): array
{
    return [[
        'key' => 'acme_campaign_block',
        'label' => 'Campaign block',
        'description' => 'Adds a campaign CTA block.',
        'enabled' => true,
        'source' => $this->name,
        'requires' => ['extension_blocks'],
    ]];
}
```

## Installed extension metadata

### `displayCciBlogAdminExtensions`

Use this hook to show a genuinely installed module in **Installed extensions**.

- **Parameters:** `$params['features']` (`array<string,array>`) is the normalized feature map; `$params['module']` (`string`) is `cci_blog`.
- **Returns:** a descriptor or list with `id`, `title`, and optional `description`, `version`, `author`, `url`, `requires`, `pro` and `tags`.
- **Side effects/HTML:** none. CCI Blog recalculates `locked` from `requires`.

```php
public function hookDisplayCciBlogAdminExtensions(array $params): array
{
    return [[
        'id' => $this->name,
        'title' => $this->displayName,
        'version' => $this->version,
        'author' => $this->author,
        'requires' => ['acme_campaign_block'],
    ]];
}
```

## Editor block registration

### `displayCciBlogAdminBlockExtensions`

Use this hook to add a block type to the visual editor. The storefront renderer must be supplied through `displayCciBlogContentBlock`.

- **Parameters:** currently an empty array. Do not depend on undocumented context values.
- **Returns:** a descriptor or list with `moduleName`/`module`, `blockName`/`block`, `label`, optional `description`, `icon`, `defaultPayload` and `requires`.
- **Side effects/HTML:** none; it only extends editor metadata.

```php
public function hookDisplayCciBlogAdminBlockExtensions(array $params): array
{
    return [[
        'moduleName' => $this->name,
        'blockName' => 'campaign_cta',
        'label' => 'Campaign CTA',
        'defaultPayload' => ['label' => 'Learn more', 'url' => ''],
        'requires' => ['extension_blocks'],
    ]];
}
```

### `displayCciBlogContentBlock`

Use this hook to render a saved external `module_block`.

- **Parameters:** `$params['module']` (`string`) identifies the owning module; `$params['block']` (`string`) is the block name; `$params['label']` (`string`) is the saved editor label; `$params['payload']` (`array`) contains the block data.
- **Returns:** sanitized/scoped storefront HTML as `string`, or `''` when the descriptor is not owned by the module.
- **Side effects:** output is inserted into article content.

```php
public function hookDisplayCciBlogContentBlock(array $params): string
{
    if (($params['module'] ?? '') !== $this->name || ($params['block'] ?? '') !== 'campaign_cta') {
        return '';
    }

    $payload = is_array($params['payload'] ?? null) ? $params['payload'] : [];
    $this->context->smarty->assign([
        'label' => (string) ($payload['label'] ?? ''),
        'url' => (string) ($payload['url'] ?? ''),
    ]);

    return $this->fetch('module:' . $this->name . '/views/templates/hook/campaign_cta.tpl');
}
```

## Hook block picker and output

### `displayCciBlogContentHookOptions`

Use this hook to expose a safe display hook in the editor's Hook block picker.

- **Parameters:** currently an empty array.
- **Returns:** a descriptor or list with `name`, `label`, optional `description` and `module`.
- **Side effects/HTML:** none. The named display hook must also be registered and implemented.

```php
public function hookDisplayCciBlogContentHookOptions(array $params): array
{
    return [[
        'name' => 'displayAcmeBlogCta',
        'label' => 'Acme blog CTA',
        'description' => 'Campaign CTA rendered inside an article.',
        'module' => $this->name,
    ]];
}
```

### Selected article display hook

The selected hook, including built-ins `displayCciBlogPostTop`, `displayCciBlogPostMiddle` and `displayCciBlogPostBottom`, receives `$params['block']` (`array`) with the saved Hook block.

- **Returns:** storefront HTML as `string`.
- **Side effects:** output is inserted at the saved block position.

```php
public function hookDisplayAcmeBlogCta(array $params): string
{
    $this->context->smarty->assign('block', (array) ($params['block'] ?? []));

    return $this->fetch('module:' . $this->name . '/views/templates/hook/blog_cta.tpl');
}
```

## Content renderer delegation

### `displayCciBlogRenderContentBlock`

This lower-level renderer is used by the Pro bridge for advanced block types. Prefer the paired admin/content hooks above for an independent extension.

- **Parameters:** `$params['type']` (`string`) is the sanitized saved type; `$params['block']` (`array`) is the saved block; `$params['context']` (`Context`) is the active PrestaShop context.
- **Returns:** rendered HTML as `string`, or `''` when unsupported.
- **Side effects:** returned HTML becomes part of the article.

```php
public function hookDisplayCciBlogRenderContentBlock(array $params): string
{
    if (($params['type'] ?? '') !== 'acme_notice') {
        return '';
    }

    return '<aside class="cci-blog-acme-notice">'
        . Tools::safeOutput((string) (($params['block']['text'] ?? '')))
        . '</aside>';
}
```

## Admin assets

### `displayCciBlogAdminAssets`

Use this hook when an installed extension needs JavaScript in the CCI Blog React workspace.

- **Parameters:** `$params['shop_id']` (`int`) is the active shop.
- **Returns:** `['scripts' => [['src' => string, 'version' => string], ...]]`.
- **Side effects/HTML:** accepted scripts are loaded by the admin controller. The current contract does not consume a styles list.

```php
public function hookDisplayCciBlogAdminAssets(array $params): array
{
    return ['scripts' => [[
        'src' => $this->getPathUri() . 'views/js/admin-extension.js',
        'version' => $this->version,
    ]]];
}
```

## Standard storefront hooks implemented by CCI Blog

- `moduleRoutes` / `hookModuleRoutes(): array` returns route definitions for the list, post, category, tag, author, search and RSS controllers.
- `displayHome` / `hookDisplayHome(): string` receives the standard PrestaShop hook payload and returns the latest-post widget HTML or `''`.
- `displayLeftColumn` and `displayRightColumn` are safe sidebar placements used by article layouts. Modules assigned there receive standard PrestaShop page context and return HTML.
- `displayGDPRConsent` is executed on the comment form with `['id_module' => int]`; consent modules return HTML.
- `registerGDPRConsent` is registered by CCI Blog so compatible consent modules can discover it.

For public PHP methods and their arguments, see [PHP API](api-reference). For complete extension packages and template overrides, see [Developer guide](developer-guide).
