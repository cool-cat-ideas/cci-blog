# CCI Blog extension cookbook

This guide contains complete workflows for theme overrides, article hooks and
installable CCI Blog extensions. It documents behavior implemented in 1.0.0.

## Choose the correct mechanism

- Use a theme override to change list, post, category or widget markup.
- Use theme CSS to restyle existing `.cci-blog-*` storefront components.
- Use a Hook block to place output from a safe PrestaShop display hook in an article.
- Use an Extension block when an installed module owns saved JSON and rendering.
- Use feature and extension hooks only for capabilities supplied by a real module.

Do not edit module templates or generated admin assets in production.

## Override the storefront post card

### 1. Copy the partial into the active theme

Copy:

```text
modules/cci_blog/views/templates/front/_post_card.tpl
```

to:

```text
themes/{your-theme}/modules/cci_blog/views/templates/front/_post_card.tpl
```

### 2. Keep the assigned data contract

Start from the shipped template and preserve the existing variables and links.
Escape text and attributes:

```smarty
<article class="cci-blog-card my-theme-blog-card">
  <h2 class="cci-blog-card-title">
    <a href="{$post.url|escape:'htmlall':'UTF-8'}">
      {$post.title|escape:'htmlall':'UTF-8'}
    </a>
  </h2>
</article>
```

### 3. Add scoped theme CSS

```css
.cci-blog-listing .my-theme-blog-card {
  border: 1px solid #dfe4ea;
  border-radius: 14px;
  background: #fff;
}
```

### 4. Clear cache and verify every route

Clear **Advanced Parameters > Performance**, then test the blog list, post,
category, tag, author, search and home widget. A post-card change can affect
several controllers.

## Render an existing PrestaShop hook in an article

1. Confirm an active module is registered to a safe `display*` hook.
2. Edit a post and insert **Hook**.
3. Select the hook from the allowlisted picker.
4. Save and view the post in the same shop and language.

CCI Blog rejects action, admin, back-office, header/footer and recursive blog
hooks. A valid display hook can still return no HTML when its module requires a
product, cart or checkout context.

The selected hook receives:

```php
[
    'block' => $savedBlock,
]
```

## Create a dedicated article hook

The module must declare the option and implement the hook.

```php
public function install(): bool
{
    return parent::install()
        && $this->registerHook('displayCciBlogContentHookOptions')
        && $this->registerHook('displayMyCompanyArticleNotice');
}

public function hookDisplayCciBlogContentHookOptions(array $params): array
{
    unset($params);

    return [[
        'name' => 'displayMyCompanyArticleNotice',
        'label' => $this->l('Article notice'),
        'description' => $this->l('Shows the current campaign notice in a post.'),
        'moduleName' => $this->name,
    ]];
}

public function hookDisplayMyCompanyArticleNotice(array $params): string
{
    $block = is_array($params['block'] ?? null) ? $params['block'] : [];
    $this->context->smarty->assign([
        'label' => (string) ($block['label'] ?? $this->l('Current campaign')),
    ]);

    return $this->fetch('module:' . $this->name . '/views/templates/hook/article_notice.tpl');
}
```

The hook name must match `/^[A-Za-z][A-Za-z0-9_]*$/`. After installation it
appears in the Hook block picker and diagnostics.

## Create an Extension block module

Extension blocks require the `extension_blocks` capability. CCI Blog Pro first
handles `displayCciBlogRenderContentBlock`; its `module_block` renderer then
executes `displayCciBlogContentBlock` with the normalized saved fields.

### 1. Create the module files

```text
modules/my_blog_blocks/
  my_blog_blocks.php
  views/templates/hook/campaign_cta.tpl
  views/css/front.css
```

### 2. Register the hooks

```php
<?php
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

class My_Blog_Blocks extends Module
{
    public function __construct()
    {
        $this->name = 'my_blog_blocks';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'My Company';
        $this->bootstrap = true;
        parent::__construct();
        $this->displayName = $this->l('My Blog Blocks');
        $this->description = $this->l('Adds campaign blocks to CCI Blog.');
    }

    public function install(): bool
    {
        return parent::install()
            && $this->registerHook('displayCciBlogAdminBlockExtensions')
            && $this->registerHook('displayCciBlogAdminExtensions')
            && $this->registerHook('displayCciBlogContentBlock')
            && $this->registerHook('actionFrontControllerSetMedia');
    }

    public function hookDisplayCciBlogAdminExtensions(array $params): array
    {
        unset($params);

        return [[
            'id' => $this->name,
            'title' => $this->displayName,
            'description' => $this->description,
            'version' => $this->version,
            'author' => $this->author,
            'tags' => ['content block', 'campaign'],
            'requires' => ['extension_blocks'],
            'pro' => true,
        ]];
    }

    public function hookDisplayCciBlogAdminBlockExtensions(array $params): array
    {
        unset($params);

        return [[
            'moduleName' => $this->name,
            'blockName' => 'campaign_cta',
            'label' => $this->l('Campaign CTA'),
            'payload' => [
                'buttonLabel' => $this->l('Read more'),
                'url' => '',
            ],
            'requiredFeatures' => ['extension_blocks'],
            'requiresPro' => true,
        ]];
    }

    public function hookDisplayCciBlogContentBlock(array $params): string
    {
        if (($params['module'] ?? '') !== $this->name
            || ($params['block'] ?? '') !== 'campaign_cta') {
            return '';
        }

        $payload = is_array($params['payload'] ?? null) ? $params['payload'] : [];
        $url = filter_var((string) ($payload['url'] ?? ''), FILTER_VALIDATE_URL);
        if ($url === false) {
            return '';
        }

        $this->context->smarty->assign([
            'label' => (string) ($params['label'] ?? $this->l('Campaign')),
            'buttonLabel' => (string) ($payload['buttonLabel'] ?? $this->l('Read more')),
            'url' => $url,
        ]);

        return $this->fetch('module:' . $this->name . '/views/templates/hook/campaign_cta.tpl');
    }

    public function hookActionFrontControllerSetMedia(array $params): void
    {
        unset($params);

        if (($this->context->controller->module->name ?? '') !== 'cci_blog') {
            return;
        }

        $this->context->controller->registerStylesheet(
            'my-blog-blocks-front',
            'modules/' . $this->name . '/views/css/front.css',
            ['priority' => 200]
        );
    }
}
```

### 3. Create the Smarty partial

```smarty
<aside class="my-blog-campaign" aria-label="{$label|escape:'htmlall':'UTF-8'}">
  <a class="my-blog-campaign__button"
     href="{$url|escape:'htmlall':'UTF-8'}">
    {$buttonLabel|escape:'htmlall':'UTF-8'}
  </a>
</aside>
```

### 4. Install and test

Install CCI Blog, CCI Blog Pro and the add-on. Activate the license, insert the
new block, save a post, reload it, and confirm the block also renders after a
cache clear. Test malformed and empty payload values.

Because the module also implements `displayCciBlogAdminExtensions`, its card is
shown in **Extensions > Installed extensions**. The block-registration hook by
itself would add a block to the editor but would not create that card.

## Understand Installed extensions and Available in store

CCI Blog deliberately keeps local discovery separate from the marketplace:

- **Installed extensions** contains metadata returned by installed PrestaShop
  modules through `displayCciBlogAdminExtensions`.
- An installed module that only registers an article hook or editor block works,
  but has no catalog card until it also implements the metadata hook.
- A theme override is not a module and is never shown as an extension.
- **Available in store** contains only records received from the remote CCI Blog
  marketplace feed. Installing a local module does not publish it there.
- Stable `id`/module names let the UI mark a matching store record as installed.

## Load an admin support script

`displayCciBlogAdminAssets` can load scripts before the React bundle. Return
only local script URLs:

```php
public function install(): bool
{
    return parent::install()
        && $this->registerHook('displayCciBlogAdminAssets');
}

public function hookDisplayCciBlogAdminAssets(array $params): array
{
    unset($params);
    $file = $this->getLocalPath() . 'views/js/admin-fields.js';

    return [
        'scripts' => [[
            'src' => $this->getPathUri() . 'views/js/admin-fields.js',
            'version' => (string) (@filemtime($file) ?: $this->version),
        ]],
    ];
}
```

The editor exposes `window.CCIBlogProEditorFields` with `register(kind,
renderer)`, `get(kind)` and `all()`, but the current registry is the boundary
used by CCI Blog Pro for implemented advanced block kinds. There is no public
per-extension React field registry in 1.0.0. Third-party `module_block` entries
use the generic JSON payload editor; do not replace the shared `module_block`
renderer. Use an admin asset for independent diagnostics or support behavior,
and keep the server hook as the source of block availability and defaults.

## List an installed add-on in Extensions

```php
public function hookDisplayCciBlogAdminExtensions(array $params): array
{
    return [[
        'id' => $this->name,
        'title' => $this->displayName,
        'description' => $this->description,
        'version' => $this->version,
        'author' => $this->author,
        'url' => 'https://example.com/my-blog-blocks',
        'requires' => ['extension_blocks'],
        'pro' => true,
        'tags' => [$this->l('Content block')],
    ]];
}
```

Register `displayCciBlogAdminExtensions` during install. Do not list a preset,
theme override or uninstalled store product as an installed extension.

## Add a feature key

Add-ons may create namespaced feature keys, but cannot unlock reserved Pro
keys. A paid feature remains disabled until the encoded core Pro module is
active and licensed.

```php
public function hookDisplayCciBlogFeatures(array $params): array
{
    return [[
        'key' => 'my_company_campaign_blocks',
        'label' => $this->l('Campaign blocks'),
        'description' => $this->l('Adds campaign content blocks.'),
        'enabled' => true,
        'source' => $this->name,
        'plan' => 'pro',
        'requiresPro' => true,
    ]];
}
```

## Style the CCI Blog carousel shown in Nice Menu

CCI Nice Menu Pro owns that renderer and its `.cci-nice-menu-blog-*` classes. Do not
put those rules in CCI Blog `front.css`. Use the active theme stylesheet or a
Nice Menu add-on through `displayCciNiceMenuFrontAssets`. The complete selector
contract and examples are in CCI Nice Menu
`docs/TEMPLATES_AND_STYLING.md#cci-blog-carousel-inside-nice-menu`.

## Verification checklist

1. Reinstall or reset the module after changing registered hooks.
2. Check CCI Blog diagnostics for listeners and saved block usage.
3. Confirm extension metadata appears only under installed extensions.
4. Save, reload and render the block in every supported shop/language.
5. Test invalid payloads and verify escaped HTML.
6. Clear PrestaShop cache and check the actual storefront, not only admin.
