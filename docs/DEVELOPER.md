# CCI Blog Developer Guide

## Choose What You Are Extending

| Goal | Recommended mechanism | Guide |
| --- | --- | --- |
| Theme markup | PrestaShop override for article, list or widget templates | [Templates and styling](./TEMPLATES_AND_STYLING.md) |
| Theme styling | Scoped theme CSS | [Extension cookbook](./EXTENSION_COOKBOOK.md#override-the-storefront-post-card) |
| Existing module output | Hook block and safe display hook | [Extension cookbook](./EXTENSION_COOKBOOK.md#render-an-existing-prestashop-hook-in-an-article) |
| New editor block | Separate installable extension module | [Extension cookbook](./EXTENSION_COOKBOOK.md) |
| Public integration | Documented CCI Blog hooks | [Hooks reference](./HOOKS_REFERENCE.md) |

The directory and method sections below are reference material. Extensions
should use documented hooks instead of subclassing controllers or services.

## Directory Structure

```text
cci_blog/
  cci_blog.php                              Module entrypoint, hooks, routes.
  classes/
    CciBlogFeatureRegistry.php             Free/Pro feature and save gating.
    CciBlogCategory.php                Category ObjectModel.
    CciBlogComment.php                 Comment ObjectModel.
    CciBlogFrontController.php          Shared canonical pagination redirects.
    CciBlogPagination.php              Pagination helper.
    CciBlogPost.php                    Post ObjectModel.
    CciBlogSeo.php                     SEO helper.
    CciBlogTag.php                     Tag ObjectModel.
  controllers/admin/
    AdminCciBlogConfigurationController.php React admin payload and API.
    AdminCciBlogController.php              Legacy redirect/controller shell.
  controllers/front/
    list.php
    post.php
    category.php
    tag.php
    author.php
    search.php
    feed.php
  sql/
    install.sql
    uninstall.sql
    reset_all_blog.sql                     Local cleanup helper.
    seed_demo_content.php                  Local demo data seed.
  src/admin-v2/                            React admin source.
  views/css/
    front.css                              Storefront CSS.
    cci_blog_admin.css                     Generated admin CSS.
  views/js/
    front.js                               Storefront runtime.
    cci-blog-admin.js                      Generated React admin bundle.
  views/templates/front/                   Storefront Smarty templates.
  views/templates/hook/home_widget.tpl     Home widget template.
  translations/
  docs/
```

Development source, `node_modules`, reset scripts and demo seed scripts should
not be shipped in production release ZIPs unless the release checklist says so.

## Main Public PHP Methods

### `cci_blog.php`

- `install()` / `uninstall()`: install database tables, configuration, tabs and
  hooks.
- `ensureIntegrationHooks()`: ensure internal content hooks and native sitemap
  hooks are registered for existing installations.
- `hookModuleRoutes()`: register `/blog` front routes.
- `hookDisplayHome()`: render optional featured/latest home widget.
- `hookDisplayCciBlogFeatures()`: base Free feature hook.
- `hookDisplayCciBlogAdminExtensions()`: admin extension metadata hook.
- `hookDisplayCciBlogAdminBlockExtensions()`: editor block extension metadata
  hook.
- `hookDisplayCciBlogContentHookOptions()`: hook block allowlist metadata hook.
- `hookActionFrontControllerSetMedia()`: load blog frontend assets and optional
  Owl assets.
- `hookActionModifyFrontendSitemap($params)`: append a Blog group to
  PrestaShop's HTML sitemap. It mutates the `urls` array passed by reference and
  returns no value.
- `hookGSitemapAppendUrls($params)`: return XML sitemap entries to PrestaShop's
  `gsitemap` module for the language in `$params['lang']`. Each item contains a
  generated `link`, page type, `lastmod` and an optional cover `image`.
- `getContent()`: legacy Module manager entry that renders the React admin
  shell.

### `CciBlogSeo`

- `getSitemapEntries(int $langId, int $shopId): array`: return active,
  published posts assigned directly to the requested shop and language. A
  category relation is not required.
- `getSitemapCategoryEntries(int $langId, int $shopId): array`: return active
  categories assigned to the requested shop and language in storefront order.

The sitemap methods return data only. URL generation stays in the module
entrypoint so it uses PrestaShop's current routes, shop domain, language prefix
and SSL configuration. See [SITEMAPS.md](./SITEMAPS.md).

### `CciBlogTableOfContents`

- `build(string $html, int $minimumHeadings = 2): array`: parse `h2`–`h6`
  elements, assign unique stable IDs and return `content` plus a nested `items`
  tree. The input is returned unchanged and `items` is empty when the minimum
  is not met or DOM parsing is unavailable.

The post controller runs this transformation after content blocks are rendered
and before Smarty receives the article. Theme overrides should render the
provided tree instead of reparsing HTML in JavaScript.

### `AdminCciBlogConfigurationController`

- `setMedia()`: enqueue admin CSS/JS and add extension admin scripts.
- `initContent()`: render React admin shell or answer AJAX actions.

The rest of the controller methods are private implementation details and may
change. Extend the module through documented hooks instead of subclassing this
controller.

### `CciBlogFeatureRegistry`

- `integrationHooks()`: return module integration hooks.
- `getFeatures($license)`: build feature map from Free, Pro and add-ons.
- `decorateLicensePayload($license)`: add feature metadata to the admin license
  payload.
- `getAdminExtensions($features)`: build the Extensions panel data.
- `getPostPayloadRequiredFeatures($payload)`: calculate feature requirements
  for post saves.
- `requirementsMet($requirements, $features)`: check whether a payload can be
  saved.
- `missingRequirementLabels($requirements, $features)`: return labels for
  blocked requirements.
- `featureForBlockType($type)`: map a content block type to a feature key.
- `proFeatureKeys()`: return reserved Pro keys.

### ObjectModels

The ObjectModel classes expose PrestaShop's standard `ObjectModel` public API
for load/save/delete. Their custom contract is the table definition:

| Class | Table |
| --- | --- |
| `CciBlogPost` | `cci_blog_post`, `cci_blog_post_lang`, `cci_blog_post_shop` |
| `CciBlogCategory` | `cci_blog_category`, `cci_blog_category_lang`, `cci_blog_category_shop` |
| `CciBlogTag` | `cci_blog_tag` |
| `CciBlogComment` | `cci_blog_comment` |

## Admin API Actions

The React admin sends `ajaxAction` to
`AdminCciBlogConfigurationController`.

| Action | Purpose |
| --- | --- |
| `dashboard:get` | Initial payload: stats, posts, categories, comments, settings, license and extensions. |
| `diagnostics:get` | Diagnostics for tables, assets, hooks and content readiness. |
| `settings:save` | Save `CCB_*` settings. |
| `posts:list` | List admin posts. |
| `post:get` | Load one post payload. |
| `post:save` | Save post data and content blocks after feature gating. |
| `post:delete` | Delete one post. |
| `redirect:update` | Edit the old slug of an existing post/category redirect. |
| `redirect:delete` | Delete a historical slug redirect after confirmation. |
| `categories:list` | List categories for admin screens and pickers. |
| `category:save` | Save category details, parent and SEO fields. |
| `category:delete` | Delete one category if no child/post constraint blocks it. |
| `multistore:context` | List accessible shops and current Pro Multistore state. |
| `multistore:post:assign-shops` | Associate a post with selected shops and copy missing language/SEO rows. |
| `multistore:category:assign-shops` | Associate a category with selected shops and copy missing language/SEO rows. |
| `comments:list` | List comments. |
| `comment:update-status` | Move a comment to pending/approved/spam/deleted. |
| `catalog:products` | Search products for editor blocks. |
| `media:list` | Pro-only image catalog. Accepts `query` and `source`; returns validated public image metadata, never filesystem paths or HTML. |
| `media:upload` | Pro-only multipart JPEG/PNG/WebP upload. Re-encodes the image into managed responsive variants in `img/cci_blog`; never accepts a client filesystem path. |
| `license:get` / `license:activate` / `license:check` / `license:deactivate` | Pro license actions delegated to `cci_blog_pro` when installed. |
| `review-feedback:save` | Validate the rating and required consent, then create a pending product review through the configured marketplace API. Payload stores the exact consent text, version, locale and acceptance time with the review. The action returns an error when the remote service does not confirm the write. |

Cross-shop actions delegate to `CciBlogProMultistoreManager` and are rejected
unless the encoded Pro runtime authorizes `multistore`. Existing target rows
are never overwritten.

## Database Tables

Installed tables:

```text
cci_blog_category
cci_blog_category_lang
cci_blog_category_shop
cci_blog_post
cci_blog_post_lang
cci_blog_post_shop
cci_blog_post_category
cci_blog_tag
cci_blog_post_tag
cci_blog_post_image
cci_blog_comment
cci_blog_post_product
cci_blog_slug_redirect
cci_blog_author
cci_blog_author_lang
```

Important content storage fields:

- `cci_blog_post_lang.content_blocks`: structured editor JSON.
- `cci_blog_post_lang.content`: rendered article HTML/text used on the
  storefront.
- `cci_blog_post_lang.focus_keyword`: SEO focus phrase for live checks.
- `cci_blog_post.id_category`: primary category.
- `cci_blog_post_category`: additional category relation.

## Routes

`hookModuleRoutes()` registers:

```text
/blog
/blog/page/{page}
/blog/{slug}
/blog/category/{slug}
/blog/category/{slug}/page/{page}
/blog/tag/{slug}
/blog/tag/{slug}/page/{page}
/blog/author/{slug}
/blog/author/{slug}/page/{page}
/blog/search
/blog/search/page/{page}
/blog/feed.xml
```

The base path comes from `CCB_BASE_SLUG`. The route set also registers the
configured `.html` form and its alternate form so the shared front controller
can issue a canonical redirect instead of serving duplicate archive URLs.

`CciBlogPost::getAdjacentPosts()` returns the chronologically previous and next
published posts for one shop/language context. The post controller assigns the
result to `$ccb_previous_post` and `$ccb_next_post`; `_post_navigation.tpl`
renders only available directions.

`CciBlogSeo::recordSlugRedirect()` and
`CciBlogSeo::recordCategorySlugRedirect()` persist language-aware history in
`cci_blog_slug_redirect`. Post and category front controllers resolve these
rows and issue permanent redirects. Admin dashboard payloads include the
current shop's redirect list; update and delete operations remain server-side
validated.

## Build Workflow

```bash
cd modules/cci_blog
npm run build:admin
```

Then check:

```bash
php -l cci_blog.php
php -l controllers/admin/AdminCciBlogConfigurationController.php
php -l classes/CciBlogFeatureRegistry.php
node --check views/js/cci-blog-admin.js
```

For UI changes, verify the real PrestaShop admin and storefront with
Playwright or a browser. In this local environment the shop usually runs at:

```text
http://localhost:4090
```

## Adding A Content Block

For a base Free block:

1. Add editor UI and normalization in `src/admin-v2/components/BlockNotePostEditor.jsx`.
2. Add save validation/feature checks in `CciBlogFeatureRegistry` if needed.
3. Add storefront rendering in the PHP renderer path used by post templates.
4. Add CSS in `views/css/cci_blog_front.css` or the editor CSS source.
5. Add documentation in `EXTENSIONS.md` and
   `TEMPLATES_AND_STYLING.md`.

For Pro or add-on blocks, use the module extension contract in
[EXTENSIONS.md](./EXTENSIONS.md). The base module may show locked controls, but
Pro-only rendering should come from the Pro layer or a gated add-on.
