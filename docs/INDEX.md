# CCI Blog Documentation

Use CCI Blog to publish guides, news and buying advice directly in PrestaShop
without editing HTML. Start with
[Create and publish the first article](./USER_GUIDE.md#create-and-publish-the-first-article).
The same guide shows where to add an author biography, how to turn on comments
and what to check when an article is missing.

Source documentation for the public CCI Blog website docs, version 1.0.0. Keep
these files aligned with the PHP controllers, ObjectModels, React editor
payload, storefront templates and Nice Menu integration.

Release ZIP packages exclude this `docs/` directory. Marketplace README files
must stay limited to product description and changelog content.

## Start Here

| Document | Use it for |
| --- | --- |
| [User guide](./USER_GUIDE.md) | Install the module, publish the first article, add an author, turn on comments and fix common publishing problems. |
| [Developer guide](./DEVELOPER.md) | Directory structure, public methods, admin API actions, database model and build workflow. |
| [Hooks reference](./HOOKS_REFERENCE.md) | Public PrestaShop hooks with parameters, return contracts, side effects and working examples. |
| [Templates and styling](./TEMPLATES_AND_STYLING.md) | Smarty overrides, frontend CSS classes, editor CSS and template rules. |
| [Extension contract](./EXTENSIONS.md) | Editor block extensions, hook blocks, Pro feature keys and add-on rules. |
| [Extension cookbook](./EXTENSION_COOKBOOK.md) | Step-by-step theme overrides, article hooks, extension modules, block rendering and admin assets. |
| [Demo seed](./DEMO_SEED.md) | Local demo content package and Nice Menu demo integration. |
| [Free/Pro matrix](./FREE_PRO.md) | Feature split for pricing tables and product pages. |
| [Multistore (Pro)](./MULTISTORE.md) | Shop isolation, assigning posts/categories and license rules. |
| [Local image library (Pro)](./LOCAL_MEDIA_LIBRARY.md) | Safely select existing PrestaShop images without typing URLs. |
| [HTML and XML sitemaps](./SITEMAPS.md) | Native PrestaShop HTML sitemap and `gsitemap` XML integration. |
| [Release 1.0.0](./RELEASE_1.0.0.md) | Responsive table of contents, Disqus comments, navigation, redirects and installation checks. |
| [Architecture direction](./ARCHITECTURE.md) | Historical architecture decisions for the React editor direction. |

## Product Scope

`cci_blog` is the Free base module. It owns installation, post/category/comment
storage, front controllers, routes, feeds, templates, the React admin shell and
safe extension hooks.

`cci_blog_pro` is a separate Pro layer. It owns reserved Pro capability
unlocking and Pro-only content renderers. Paid add-ons should depend on the
base module and, where needed, on the Pro layer.

## Documentation Rules

- Do not claim AI-assisted writing, AI support or automated content generation
  until those features ship.
- Any new content block needs documentation in `EXTENSIONS.md`, editor docs and
  the Free/Pro matrix.
- Any new frontend template part, CSS class or CSS variable needs an entry in
  `TEMPLATES_AND_STYLING.md`.
- Any new admin action, public PHP method, database field or route needs an
  entry in `DEVELOPER.md`.
- Any new or changed public hook needs an entry in `HOOKS_REFERENCE.md` with
  exact parameters, return semantics and a working example.
- Demo seed copy should describe what is implemented and visible in the current
  module only.
