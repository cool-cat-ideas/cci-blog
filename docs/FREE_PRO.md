# CCI Blog Free / Pro Feature Matrix

Use this table as the source copy for product pages and comparison blocks. It
describes shipped capabilities for the initial 1.0.0 release.

| Feature | Free | Pro |
| --- | --- | --- |
| React admin workspace | Yes | Yes |
| Posts dashboard, categories, comments and settings | Yes | Yes |
| Storefront blog routes: list, post, category, search, author and RSS | Yes | Yes |
| Public employee author profiles with biography, display name, avatar and social links | Yes | Yes |
| Author biography language scope | Default shop language only | All shop languages |
| Draft/unpublished posts through active state and publish date | Yes | Yes |
| Primary category per post | Yes | Yes |
| Additional categories per post | No | Yes |
| Nested category tree | Yes | Yes |
| Tags | Yes, default shop language | Yes, per editable shop language |
| Visual block-based WYSIWYG editor: headings, rich text, lists, links, images, video and standard BlockNote blocks | Yes | Yes |
| Advanced link block with rel/class/id/title controls | Yes | Yes |
| Linked image block | Yes | Yes |
| Local image library with optimized upload and responsive variants for cover, social, category and linked images | No | Yes |
| Responsive columns and nested layout blocks | No | Yes |
| Product block | No | Yes |
| Product carousel block | No | Yes |
| Article hook block | No | Yes |
| Extension blocks registered by add-on modules | No | Yes |
| Basic SEO fields: slug, meta title, meta description, OG title and OG description | Yes, default shop language | Yes, every editable shop language |
| JSON-LD structured data for article pages: BlogPosting and breadcrumb trail | Yes | Yes |
| Native PrestaShop HTML sitemap and `gsitemap` XML integration | Yes | Yes |
| Live SEO audit and advanced writing hints | No | Yes |
| Multilingual post/category/tag/author biography/SEO editing | Default shop language only | All shop languages |
| Global comments enable/disable | Yes | Yes |
| Per-post comments setting | Yes | Yes |
| Disqus comment provider and moderation link | Yes | Yes |
| Legacy native comment provider and local moderation | Yes | Yes |
| Responsive table of contents generated from heading hierarchy | Yes | Yes |
| Blog content in CCI Nice Menu: sticky post, latest list, popular list, carousel and category listing | Yes, when both modules are installed | Yes, with Pro-only menu/editor features when available |
| Extension marketplace panel | Browse/read-only | Installed Pro and add-on features can unlock editor capabilities |
| Server-side feature enforcement during save | Yes | Yes |
| PrestaShop Multistore assignment for posts and categories | No | Yes |

## Pro Capability Keys

`cci_blog_pro` registers these feature keys:

- `translations`
- `additional_categories`
- `advanced_layout_blocks`
- `commerce_blocks`
- `product_carousel`
- `hook_blocks`
- `extension_blocks`
- `advanced_seo`
- `local_media_library`
- `multistore`

Future paid add-ons should register their own keys through
`displayCciBlogFeatures` instead of adding unrelated features to the base Pro
module.

Reserved Pro keys are unlocked only by the separate `cci_blog_pro` module. The
Free module may show locked controls and read add-on metadata, but it must not
ship authoritative Pro implementations or accept a third-party add-on as the
source for reserved Pro capability keys. Paid add-ons remain unusable without
the encoded core Pro layer.
