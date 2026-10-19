# CCI Blog developer guide

CCI Blog includes a visual, block-based WYSIWYG editor. Authors work with the
article structure—headings, rich text, links, linked images and video—without
editing HTML. Pro extends the same canvas with columns, products, carousels,
hooks and blocks supplied by installed modules.

## Choose what you are extending

| Goal | Recommended mechanism | Guide |
| --- | --- | --- |
| Theme markup | PrestaShop override for article, list or widget templates | [Storefront template overrides](#storefront-template-overrides) |
| Theme styling | Scoped CSS for colors, spacing or typography | [Storefront template overrides](#storefront-template-overrides) |
| Existing module output | Hook block and a safe display hook | [Register a custom article hook](#register-a-custom-article-hook) |
| New editor block | Separate installable extension module owning its data | [Create an installable editor-block extension](#create-an-installable-editor-block-extension) |
| Public integration | Separate module implementing documented CCI Blog hooks | [Hooks reference](HOOKS_REFERENCE.md) |

Start with the workflow above. The architecture and PHP method sections are
reference material; extension modules should use documented hooks instead of
subclassing CCI Blog controllers or services.

## PHP API and naming

The documentation uses product roles first—article service, feature registry,
SEO helper—instead of presenting implementation class names as product names.

The naming layers are intentionally different:

- **CCI Blog** is the product name.
- `cci_blog` is the PrestaShop technical module ID and folder name.
- `Cci_Blog` and `Cci_Blog*ModuleFrontController` are framework adapter names
  derived from that module ID; extension authors do not subclass them.
- Services and ObjectModels use the `CciBlog*` PascalCase prefix. These remain
  implementation services, not extension or marketplace names. Public
  integrations should use documented PrestaShop hooks.

The complete method documentation is split into readable method cards instead
of a wide table: [PHP method reference](API_REFERENCE.md).

## Front controllers

```text
controllers/front/list.php
controllers/front/post.php
controllers/front/category.php
controllers/front/tag.php
controllers/front/author.php
controllers/front/search.php
controllers/front/feed.php
```

Each controller prepares Smarty variables and renders a template from `views/templates/front/`.

## Admin controller

```text
controllers/admin/AdminCciBlogConfigurationController.php
```

This controller prepares the React admin payload, settings, post data, extension metadata, asset registration and server-side save validation.

## Hook contract

Stable hooks:

| Hook | Direction | Purpose |
| --- | --- | --- |
| `displayHome` | PrestaShop calls CCI Blog | Renders the latest posts home widget when enabled. |
| `displayLeftColumn` / `displayRightColumn` | CCI Blog renders the matching PrestaShop hook | Renders modules assigned to the selected blog sidebar position. |
| `actionFrontControllerSetMedia` | PrestaShop calls CCI Blog | Loads front CSS and JS on blog pages. |
| `moduleRoutes` | PrestaShop calls CCI Blog | Registers blog URLs. |
| `displayCciBlogFeatures` | Pro/add-on returns data | Adds feature definitions. |
| `displayCciBlogAdminExtensions` | Add-on returns data | Lists installed blog add-ons. |
| `displayCciBlogAdminBlockExtensions` | Add-on returns data | Registers article editor block types. |
| `displayCciBlogContentHookOptions` | Add-on returns data | Adds safe hook names to the Hook block picker. |
| `displayCciBlogRenderContentBlock` | CCI Blog calls add-on | Renders Pro or add-on content blocks. |

Default article hook block targets:

```text
displayCciBlogPostTop
displayCciBlogPostMiddle
displayCciBlogPostBottom
```

### Attach a module to the blog sidebar

Register both standard PrestaShop column hooks when the module should follow
the sidebar position selected in CCI Blog settings:

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

Add the escaped template at
`views/templates/hook/blog_sidebar.tpl`, install or reset the module and order
it under **Design > Positions**. The controller guard keeps the widget off
unrelated pages that also use PrestaShop columns.

Do not use arbitrary `action*`, admin, checkout, authentication or
header/footer hooks as sidebar content. Registering a column hook does not add
a card to **Installed extensions**; that still requires
`displayCciBlogAdminExtensions` metadata.

## Installed extensions versus the store

CCI Blog does not scan arbitrary module or theme folders for catalog cards.

- **Installed extensions** is built only from metadata returned by installed
  modules through `displayCciBlogAdminExtensions`.
- Registering an editor block or article hook makes that feature work, but does
  not create a card unless the same module also returns extension metadata.
- A Smarty theme override changes storefront output and never appears as an
  extension.
- **Available in store** is populated only by the remote CCI Blog marketplace
  feed. A local ZIP is not uploaded or published automatically.

## Register a custom article hook

```php
public function install(): bool
{
    return parent::install()
        && $this->registerHook('displayCciBlogContentHookOptions')
        && $this->registerHook('displayMyModuleBlogCta');
}

public function hookDisplayCciBlogContentHookOptions(array $params): array
{
    return [[
        'name' => 'displayMyModuleBlogCta',
        'label' => 'Blog CTA',
        'description' => 'Renders a campaign call-to-action inside an article.',
        'moduleName' => $this->name,
    ]];
}

public function hookDisplayMyModuleBlogCta(array $params): string
{
    return $this->fetch('module:' . $this->name . '/views/templates/hook/blog_cta.tpl');
}
```

Unknown hook names are ignored on the storefront.

## Create an installable editor-block extension

Create this structure:

```text
modules/my_blog_blocks/
  my_blog_blocks.php
  views/templates/hook/campaign_cta.tpl
```

`my_blog_blocks.php` registers the editor block, its storefront renderer and the
metadata required for an **Installed extensions** card:

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
        $this->description = $this->l('Adds a campaign CTA block to CCI Blog.');
    }

    public function install(): bool
    {
        return parent::install()
            && $this->registerHook('displayCciBlogAdminExtensions')
            && $this->registerHook('displayCciBlogAdminBlockExtensions')
            && $this->registerHook('displayCciBlogContentBlock');
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
            'payload' => ['buttonLabel' => $this->l('Read more'), 'url' => ''],
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
}
```

`views/templates/hook/campaign_cta.tpl`:

```smarty
<aside class="my-blog-campaign" aria-label="{$label|escape:'htmlall':'UTF-8'}">
  <a href="{$url|escape:'htmlall':'UTF-8'}">
    {$buttonLabel|escape:'htmlall':'UTF-8'}
  </a>
</aside>
```

Install the module, activate CCI Blog Pro, insert **Campaign CTA** in the visual
editor, provide its payload, save and verify the article in the same shop.

## Storefront template overrides

Front CSS:

```text
modules/cci_blog/views/css/cci_blog_front.css
```

Smarty templates:

```text
modules/cci_blog/views/templates/front/
modules/cci_blog/views/templates/hook/
```

Theme override path follows PrestaShop module override rules:

```text
themes/{theme}/modules/cci_blog/views/templates/front/post.tpl
themes/{theme}/modules/cci_blog/views/templates/front/_post_card.tpl
themes/{theme}/modules/cci_blog/views/templates/hook/home_widget.tpl
```

For a custom post-card template, copy the shipped `_post_card.tpl` to the theme
path above and replace only its card markup while preserving assigned data:

```smarty
<article class="cci-blog-card my-theme-blog-card">
  <h2 class="cci-blog-card-title">
    <a href="{$post.url|escape:'htmlall':'UTF-8'}">
      {$post.title|escape:'htmlall':'UTF-8'}
    </a>
  </h2>
  {if $post.intro}
    <p>{$post.intro|strip_tags|escape:'htmlall':'UTF-8'}</p>
  {/if}
</article>
```

Clear PrestaShop cache and verify list, category, tag, author, search and home
widget routes. A theme template is selected by PrestaShop and does not appear in
**Installed extensions**.

Keep custom CSS scoped to public blog classes such as `.cci-blog-*`.

### Change the date format

Dates are stored independently from their visual format. Override the relevant
Smarty template in the active theme instead of editing the module files.

For the date on a single article, copy:

```text
modules/cci_blog/views/templates/front/post.tpl
```

to:

```text
themes/{theme}/modules/cci_blog/views/templates/front/post.tpl
```

Keep the ISO value in `datetime` and change only the visible expression:

```smarty
<time
  class="cci-blog-meta-date"
  datetime="{$ccb_post.date_published|date_format:'%Y-%m-%dT%H:%M:%S'}"
  itemprop="datePublished"
>
  {$ccb_post.date_published|date_format:'%d.%m.%Y'}
</time>
```

This example renders `18.06.2026`. Use `$post.date_published` in
`_post_card.tpl` to change listing cards and `$comment.date_add` in
`_comment.tpl` to change comment dates. Useful tokens include `%d` (day), `%m`
(month), `%Y` (four-digit year), `%H` (hour) and `%M` (minutes). Names produced
by `%b` or `%B` depend on the server/Smarty locale.

After changing a template, clear the PrestaShop cache and verify every active
shop language.

### Change the single-post sidebar

The admin **Sidebar position** setting controls list, category, tag, author,
search and single-post pages. Choose `left`, `right` or `none`. On mobile the
sidebar is rendered below the article.

The article sidebar receives:

```text
$ccb_sidebar
$ccb_categories
$ccb_tag_cloud
$ccb_feed_enabled
$link
```

The shipped `_sidebar.tpl` renders category and tag archive links plus RSS.
These links help visitors and crawlers reach related blog archives; they do not
replace unique article content, metadata or a sitemap.

The same partial executes the standard PrestaShop hook matching the selected
position:

```text
left  -> displayLeftColumn
right -> displayRightColumn
none  -> no sidebar and no column hook output
```

Attach compatible modules through **Design > Positions**. The sidebar does not
execute arbitrary `action*`, admin, checkout or header/footer hooks. Column
modules use the current PrestaShop controller context.

To change its widgets without replacing the whole article template, copy:

```text
modules/cci_blog/views/templates/front/_sidebar.tpl
```

to:

```text
themes/{theme}/modules/cci_blog/views/templates/front/_sidebar.tpl
```

Then extend the copied partial, for example:

```smarty
<section class="cci-blog-widget my-theme-blog-widget">
  <h2 class="cci-blog-widget-title">{l s='Buying guides' d='Modules.Cciblog.Shop'}</h2>
  <a href="{$link->getModuleLink('cci_blog', 'category', ['slug' => 'guides'])|escape:'htmlall':'UTF-8'}">
    {l s='Browse all guides' d='Modules.Cciblog.Shop'}
  </a>
</section>
```

Replace `guides` with a category slug present in each target shop and language.
Never hard-code a staging domain.

### Override another storefront view

Use the same theme path for any shipped template, preserving its assigned
variables and semantic/SEO output:

| Page or component | Module template | Theme override |
| --- | --- | --- |
| Single article | `front/post.tpl` | `front/post.tpl` |
| Main list | `front/list.tpl` | `front/list.tpl` |
| Category archive | `front/category.tpl` | `front/category.tpl` |
| Tag archive | `front/tag.tpl` | `front/tag.tpl` |
| Author archive | `front/author.tpl` | `front/author.tpl` |
| Search results | `front/search.tpl` | `front/search.tpl` |
| Article card | `front/_post_card.tpl` | `front/_post_card.tpl` |
| Blog sidebar | `front/_sidebar.tpl` | `front/_sidebar.tpl` |
| Home widget | `hook/home_widget.tpl` | `hook/home_widget.tpl` |

All frontend overrides start under
`themes/{theme}/modules/cci_blog/views/templates/`. Keep escaping, canonical
URLs, `BlogPosting` schema and machine-readable dates when replacing markup.

## Pro feature keys

```text
translations
additional_categories
advanced_layout_blocks
commerce_blocks
product_carousel
hook_blocks
extension_blocks
advanced_seo
```

The base module keeps these locked until `cci_blog_pro` validates the license.


## Change colors and card titles without replacing templates

Put the following CSS in the active theme's custom stylesheet, loaded after
CCI Blog. With Classic, use the theme's assets/css/custom.css. Other themes may
have their own Custom CSS setting or asset build; use that supported mechanism.
Never edit modules/cci_blog/views/css/cci_blog_front.css in place.

```css
.cci-blog-listing,
.cci-blog-home-widget {
  --cci-blog-primary: #2458a6;
  --cci-blog-radius: 0.75rem;
  --cci-blog-gap: 1.5rem;
}

.cci-blog-card .cci-blog-card-title a:hover,
.cci-blog-card .cci-blog-card-title a:focus-visible {
  text-decoration: underline;
  text-underline-offset: 0.2em;
}
```

Clear the theme/PrestaShop cache. Check a listing and the homepage widget, then
use keyboard Tab to verify the link focus. If the change is absent, inspect the
loaded CSS and computed style before increasing specificity. Styling the list
does not change the post editor or the employee author-profile form.

## Change behavior without overriding PHP classes

Do not copy controllers, subclass internal services or replace methods in module
files. Use a theme template for markup, the two editor-block hooks for a new
content block, or an allowlisted display hook for existing module output.
A public PHP method is not automatically a supported override point. See
[PHP API](API_REFERENCE.md) for the limited callable integration helpers.

## Installed extension card metadata

Use the add-on's own `$this->author` and `$this->version`, as shown in the hook reference. Both are displayed on the shared catalog card. They are not the base module's version or the version of a connected service. Keep the same stable `id` after updates so the installed card can match its store entry.

The metadata hook adds a card; it does not create a settings form, install the add-on or load its renderer. Keep configuration in your add-on's own Module Manager configuration page. Register its rendering and asset hooks separately. Theme overrides do not create an installed-extension entry.

After installation, open **Extensions → Installed extensions** and check the name, author and version. If the card is missing, confirm that the add-on is active in the current shop, that `install()` registered `displayCciBlogAdminExtensions`, and that its callback returns an array with non-empty `id` and `title`. On an existing add-on, register a newly added hook in an upgrade script; editing `install()` alone does not update an already installed module. Do not uninstall an add-on just to register the hook if that would erase its settings.
