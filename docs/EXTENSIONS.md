# CCI Blog Extension Contract

This document describes the extension points that are currently implemented by
`cci_blog`. Keep it in sync with the PHP renderer, React admin payload and
diagnostics whenever an extension hook changes.

## Stable Extension Points

| Area | Hook | Direction | Purpose |
| --- | --- | --- | --- |
| Editor block registry | `displayCciBlogAdminBlockExtensions` | extension module returns metadata | Adds external blocks to the article editor payload. |
| Pro content dispatcher | `displayCciBlogRenderContentBlock` | Free renderer calls Pro/add-on dispatcher | Delegates supported external block types with `type`, `block` and `context`. |
| Extension block render | `displayCciBlogContentBlock` | Pro `module_block` renderer calls extension module | Renders a saved extension block with normalized `module`, `block`, `label` and `payload`. |
| Hook block allowlist | `displayCciBlogContentHookOptions` | extension module returns metadata | Adds custom article hook names to the core Hook block. |
| Hook block render | blog, extension or safe storefront display hook | CCI Blog executes the selected hook | Renders dynamic output for a saved Hook block. |
| Feature registry | `displayCciBlogFeatures` | Pro/add-on module returns capability flags | Unlocks supported Pro editor features. |
| Admin extensions | `displayCciBlogAdminExtensions` | add-on module returns metadata | Lists truly installed blog add-ons in the Extensions panel. |
| Admin assets | `displayCciBlogAdminAssets` | add-on module returns local scripts | Loads extension editor fields before the React bundle starts. |
| Blog sidebar modules | `displayLeftColumn`, `displayRightColumn` | PrestaShop executes attached modules | Adds standard PrestaShop module output to the selected blog sidebar position. |

For complete installable examples, see
[Extension cookbook](./EXTENSION_COOKBOOK.md).

## Marketplace Feed

The Extensions panel reads the public catalog from
`/api/products/cci-blog/plugin-feed/`. Release code uses the single production
API base `https://coolcatideas.com`. Local environments may override only that
base with `CCI_MARKETPLACE_API_BASE`; the product slug and endpoint path remain
fixed. Installed entries still come exclusively from the module extension
hooks and are reconciled with store products by their stable catalog identity.

A local add-on is not inferred from its folder name. It appears under
**Installed extensions** only after the module is installed, registered to
`displayCciBlogAdminExtensions`, and returns valid `id` and `title` metadata.
Registering a block or render hook alone does not create a card. Theme Smarty
overrides never appear in the catalog. **Available in store** is remote feed
data and is not a filesystem scan.

The core Hook block always exposes:

- `displayCciBlogPostTop`
- `displayCciBlogPostMiddle`
- `displayCciBlogPostBottom`

The editor also lists safe storefront `display*` hooks that have active modules
attached in the current shop. It does not list `action*`, admin, back office,
header/footer or template override hooks, because those are not safe article
content embeds.

Unknown hook names saved in existing post JSON are shown in admin as missing and
are ignored on the storefront until an extension declares them again or a
matching storefront hook becomes available.

## Attach A Module To The Blog Sidebar

CCI Blog uses PrestaShop's standard `displayLeftColumn` and
`displayRightColumn` hooks. Register both when the module should follow the
sidebar position selected by the merchant:

```php
public function install(): bool
{
    return parent::install()
        && $this->registerHook('displayLeftColumn')
        && $this->registerHook('displayRightColumn');
}

public function hookDisplayLeftColumn(array $params): string
{
    return $this->renderCciBlogSidebarWidget($params);
}

public function hookDisplayRightColumn(array $params): string
{
    return $this->renderCciBlogSidebarWidget($params);
}

private function renderCciBlogSidebarWidget(array $params): string
{
    unset($params);

    $controllerModule = $this->context->controller->module->name ?? '';
    if ($controllerModule !== 'cci_blog') {
        return '';
    }

    $this->context->smarty->assign([
        'widgetTitle' => $this->l('Need help choosing?'),
    ]);

    return $this->fetch('module:' . $this->name . '/views/templates/hook/blog_sidebar.tpl');
}
```

The guard prevents the widget from appearing on unrelated pages that also use
PrestaShop columns. Create `views/templates/hook/blog_sidebar.tpl`, escape
dynamic output and install or reset the module so PrestaShop registers both
positions. The merchant can reorder the module under **Design > Positions**.

Do not execute `action*`, admin, checkout, authentication or header/footer
hooks from the sidebar. A module attached only to these standard column hooks
does not become a card in **Installed extensions**; return extension metadata
through `displayCciBlogAdminExtensions` when a catalog card is also required.

## Editor Block Registry

Register an extension module on `displayCciBlogAdminBlockExtensions` and return
one block or a list of blocks. JSON strings are accepted for legacy interop, but
arrays are preferred.

Supported fields:

| Field | Alias | Required | Notes |
| --- | --- | --- | --- |
| `moduleName` | `module` | yes | PrestaShop module technical name. |
| `blockName` | `block` | yes | Stable block key inside that module. |
| `label` | `title` | no | Human-readable editor label. |
| `payload` | - | no | Default JSON payload saved into the article. |
| `requiredFeatures` | `requires`, `feature` | no | Feature keys that must be enabled before the block can be saved. Defaults to `extension_blocks`. |
| `requiresPro` | `pro` | no | Marks the block as a Pro/add-on block in admin UI. |

Saved content uses this shape:

```json
{
  "type": "module_block",
  "module": "my_blog_blocks",
  "block": "cta",
  "label": "Campaign CTA",
  "payload": {
    "campaignId": 42
  }
}
```

## Storefront Block Render

The Free content renderer first executes `displayCciBlogRenderContentBlock`
with the saved `type`, complete `block` and current `Context`. CCI Blog Pro
checks the relevant capability and, for `module_block`, executes
`displayCciBlogContentBlock` with:

```php
[
    'module' => 'my_blog_blocks',
    'block' => 'cta',
    'label' => 'Campaign CTA',
    'payload' => [
        'campaignId' => 42,
    ],
]
```

The receiving module must verify that `module` and `block` belong to it before
returning HTML. Return an empty string for unknown blocks.

Do not register an add-on directly as a replacement for the Pro dispatcher.
Normal extension blocks implement `displayCciBlogContentBlock`; the dispatcher
also owns built-in Pro block types such as columns, products and hooks.

## Hook Block Sources

The Hook block is intentionally allowlisted. It has two supported content
sources:

- Blog article hooks and hooks declared by CCI Blog extensions.
- Safe storefront `display*` hooks with installed module output.

Use extension hooks when you own the integration and need a stable article-level
contract. Use storefront hooks when the merchant wants to embed output already
provided by installed PrestaShop modules. Storefront hook output can be empty if
the module expects a product, cart or checkout context that the article page does
not provide.

Extension modules can add custom article hooks through
`displayCciBlogContentHookOptions`.

Supported fields:

| Field | Alias | Required | Notes |
| --- | --- | --- | --- |
| `name` | `hook` | yes | Hook name. Must match `/^[A-Za-z][A-Za-z0-9_]*$/`. |
| `label` | `title` | no | Admin picker label. |
| `description` | - | no | Helper text shown in the editor. |
| `moduleName` | `module` | no | Source module shown in admin diagnostics. |

The extension module must also register and implement the actual render hook.
CCI Blog executes it with:

```php
[
    'block' => $savedBlock,
]
```

## Minimal PrestaShop Extension Module

```php
public function install(): bool
{
    return parent::install()
        && $this->registerHook('displayCciBlogAdminBlockExtensions')
        && $this->registerHook('displayCciBlogContentBlock')
        && $this->registerHook('displayCciBlogContentHookOptions')
        && $this->registerHook('displayMyModuleBlogCta');
}

public function hookDisplayCciBlogAdminBlockExtensions(array $params): array
{
    return [
        [
            'moduleName' => $this->name,
            'blockName' => 'campaign_cta',
            'label' => $this->l('Campaign CTA'),
            'payload' => [
                'campaignId' => 0,
                'buttonLabel' => $this->l('Read more'),
            ],
        ],
    ];
}

public function hookDisplayCciBlogContentBlock(array $params): string
{
    if (($params['module'] ?? '') !== $this->name || ($params['block'] ?? '') !== 'campaign_cta') {
        return '';
    }

    $payload = is_array($params['payload'] ?? null) ? $params['payload'] : [];

    $this->context->smarty->assign([
        'buttonLabel' => (string) ($payload['buttonLabel'] ?? ''),
    ]);

    return $this->fetch('module:' . $this->name . '/views/templates/hook/campaign_cta.tpl');
}

public function hookDisplayCciBlogContentHookOptions(array $params): array
{
    return [
        [
            'name' => 'displayMyModuleBlogCta',
            'label' => $this->l('Blog CTA hook'),
            'description' => $this->l('Renders a campaign CTA inside post content.'),
            'moduleName' => $this->name,
        ],
    ];
}

public function hookDisplayMyModuleBlogCta(array $params): string
{
    return $this->fetch('module:' . $this->name . '/views/templates/hook/blog_cta.tpl');
}
```

This abbreviated example assumes a normal PrestaShop `Module` class. A full
module entrypoint, asset loading and verification workflow are in
[Extension cookbook](./EXTENSION_COOKBOOK.md#create-an-extension-block-module).

## Admin Assets And Field Registry

`displayCciBlogAdminAssets` receives the current `shop_id` and returns:

```php
[
    'scripts' => [[
        'src' => $this->getPathUri() . 'views/js/admin-fields.js',
        'version' => '1.0.0',
    ]],
]
```

Only script entries with `src` are loaded. Use a local module asset and a
file/version cache key. The editor exposes `window.CCIBlogProEditorFields` with
`register(kind, renderer)`, `get(kind)` and `all()`, but in 1.0.0 this is the
CCI Blog Pro boundary for implemented advanced block kinds. There is no public
per-extension React field registry. Third-party `module_block` entries use the
generic JSON payload editor and must not replace its shared renderer.
Server-side block metadata and save validation remain authoritative.

## Admin Extension Metadata

`displayCciBlogAdminExtensions` receives the current feature map and base
module name. Each returned extension supports `id`, `title`, `description`,
`version`, `author`, `url`, `screenshot`, `tags`, `requires` and `pro`.
Requirements are recalculated by the base module; an add-on cannot force its
own locked state to false.

## Payload And Security Rules

- Treat `payload` as untrusted JSON. Normalize types before rendering.
- Escape all template output with Smarty escaping or PrestaShop helpers.
- Do not store raw access tokens, customer data or credentials in block payloads.
- Keep block keys stable. If a block is renamed, keep an alias in the extension.
- Keep payload migrations inside the extension module that owns the block.
- Register only hooks that are safe to execute inside article content.

## Free / Pro Capability Contract

The base `cci_blog` module is the Free runtime and editor shell. Free supports
post/category/comment management, basic SEO fields, text, heading, link, image
and video content. Pro blocks are guarded server-side during `post:save`; React
locks the matching quick buttons only as a usability aid.

The separate `cci_blog_pro` module registers `displayCciBlogFeatures`. It is
the only trusted source allowed to unlock the reserved core Pro feature keys.
The Free module does not unlock those keys from third-party hooks or from a
plain license status flag.

Current Pro feature keys:

- `translations`
- `additional_categories`
- `advanced_layout_blocks`
- `commerce_blocks`
- `product_carousel`
- `hook_blocks`
- `extension_blocks`
- `advanced_seo`

## Internal Pro Extension Unit Format

Bundled Pro behavior must not be added directly to `cci_blog_pro.php`.
The Pro entrypoint stays small and encoded: license activation, public keys,
capability checks and registry bootstrap only.

Use one file per feature or renderer:

```text
cci_blog_pro/
  cci_blog_pro.php
  src/Extension/
    CciBlogProExtensionRegistry.php
    CciBlogProBlockRendererInterface.php
    CciBlogProProductBlockRenderer.php
    CciBlogProProductCarouselBlockRenderer.php
```

For a new editor block renderer:

1. Create `src/Extension/CciBlogProMyBlockRenderer.php`.
2. Implement `CciBlogProBlockRendererInterface::render(array $block, Context $context): string`.
3. Register the renderer in `CciBlogProExtensionRegistry`.
4. Map the saved block `type` to a Pro feature in `featureForBlockType()`.
5. Add the feature metadata to `featureDefinitions()` if it is a new capability.
6. Keep all output escaping and payload normalization inside the renderer or a
   small helper class owned by that feature.

The renderer files may remain plain in the Pro source package so future paid
blocks are easy to review and copy. They still cannot unlock paid behavior by
themselves because `renderContentBlock()` calls the encoded Pro capability gate
before a renderer is executed.

An add-on can declare its own feature key:

```php
public function hookDisplayCciBlogFeatures(array $params): array
{
    return [[
        'key' => 'my_blog_block',
        'label' => $this->l('My blog block'),
        'description' => $this->l('Adds a premium editor block.'),
        'enabled' => true,
        'source' => $this->name,
        'plan' => 'pro',
        'requiresPro' => true,
    ]];
}
```

Installed add-ons shown in the Extensions panel should be returned from
`displayCciBlogAdminExtensions`. Do not show bundled core blocks as installed
extensions. Storefront rendering must not make remote license calls.

Do not reuse or override reserved core Pro keys from an add-on. Paid add-ons
must use their own namespaced feature key and set `plan => 'pro'` or
`requiresPro => true`. The base module will keep that feature disabled until
the encoded `cci_blog_pro` core is installed and active on the shop.

`multistore` is also reserved by the Pro core. Add-ons must not use it to
authorize cross-shop writes. Use the authenticated base admin actions described
in `MULTISTORE.md`; they delegate the operation to the Pro manager and retain
the current `id_shop` as the source scope.

## Versioning

Use semantic versions for extension modules. Breaking changes include:

- removing a registered `blockName`;
- changing required payload fields without a migration;
- removing a hook declared through `displayCciBlogContentHookOptions`;
- changing the HTML contract consumed by existing CSS/JS.

For breaking changes, keep a compatibility renderer for at least one minor
release and expose a diagnostic warning before removing the old key.

## Diagnostics

The CCI Blog diagnostics screen reports:

- whether extension hooks exist in PrestaShop;
- whether CCI Blog is registered to its required hooks;
- which modules listen to declared content hooks;
- whether a hook is available in the editor;
- how many saved posts/blocks use each hook.

When adding an extension hook, verify the diagnostics payload before shipping the
module.
