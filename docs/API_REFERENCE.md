# CCI Blog PHP API

Use hooks for behavior changes and theme templates for HTML. The helpers below
are callable integration surfaces after PrestaShop and CCI Blog have loaded.
They are not base classes to subclass. Installers, admin controllers, license
services and repository mutations are internal and are not documented as an API.

## Read published articles for a storefront integration

```php
CciBlogPost::getList(
    int $langId,
    int $shopId,
    int $page = 1,
    int $perPage = 9,
    ?int $categoryId = null,
    ?int $tagId = null,
    ?int $authorId = null,
    string $search = ''
): array
```

Use this when another module needs published blog entries in the active shop.
IDs are numeric PrestaShop IDs; page starts at 1. Null filters mean no restriction.
The result is an array of prepared post rows; an empty result is []. It does not
render HTML or publish content. Scope both shop and language explicitly.

```php
$blog = Module::getInstanceByName('cci_blog');
$posts = [];
if ($blog && $blog->active && class_exists('CciBlogPost')) {
    $context = Context::getContext();
    $posts = CciBlogPost::getList(
        (int) $context->language->id,
        (int) $context->shop->id,
        1,
        3
    );
}
// Assign $posts to your module's Smarty template and escape displayed fields.
```

## Build a table of contents from prepared HTML

`CciBlogTableOfContents::build(string $html, int $minimumHeadings = 2): array`
accepts trusted/sanitized article HTML and a minimum heading count (clamped to
1–20). It returns content with stable heading anchors and an items tree. Each
item contains id, title, level and children. With insufficient headings or no
DOM extension, items is empty. This helper does not sanitize arbitrary HTML.

```php
$result = CciBlogTableOfContents::build(
    '<h2>Choose a size</h2><p>Measure first.</p><h2>Care guide</h2>',
    2
);
$articleHtml = $result['content'];
$navigationItems = $result['items'];
```

## Supply URLs to a custom sitemap integration

`CciBlogSeo::getSitemapEntries(int $langId, int $shopId): array`
returns published post rows scoped to shop and language. Each row contains
id_post, id_category, date_upd, cover_image, title and slug; it does not include
a prebuilt URL. It performs no HTML rendering. Use it only for a sitemap provider other
than the standard PrestaShop HTML sitemap or Google sitemap module: those are
already integrated. Do not scrape generated pages or query blog tables directly.

```php
$context = Context::getContext();
$entries = CciBlogSeo::getSitemapEntries(
    (int) $context->language->id,
    (int) $context->shop->id
);
foreach ($entries as $entry) {
    $url = $context->link->getModuleLink(
        'cci_blog', 'post', ['slug' => $entry['slug']], null,
        (int) $context->language->id, (int) $context->shop->id
    );
    // Supply $url and $entry['date_upd'] to your sitemap provider.
}
```

For registered content blocks and sidebar output use [Hooks reference](HOOKS_REFERENCE.md).
For changes to templates use [Developer guide](DEVELOPER.md).
