# CCI Blog User Guide

This guide starts with the shortest route to a published article. Come back to
the other sections when you need authors, comments, sitemaps or menu
integration.

## Requirements

- PrestaShop 9.0.0-9.1.5.
- PHP version supported by the target PrestaShop installation.
- Optional: CCI Nice Menu when blog content should be embedded in menu
  dropdowns.
- Optional: CCI Blog Pro for Pro editing features such as all-language
  translation editing, additional categories and advanced content blocks.
- Optional: PrestaShop's Google sitemap (`gsitemap`) module when the shop
  publishes an XML sitemap. The public PrestaShop HTML sitemap works without
  that optional module.

## Installation

1. Make a backup of the shop files and database.
2. Open **Modules > Module Manager > Upload a module**.
3. Upload the CCI Blog ZIP and wait for the installation confirmation.
4. Open **Improve > Cool Cat Ideas > CCI Blog**.
5. Confirm that the sidebar shows Posts, Categories, Comments, Slug redirects,
   Extensions and Settings.
6. Clear the PrestaShop cache.

If CCI Blog Pro is installed and enabled, its license controls appear in the
module sidebar. A shop with only the base module does not need a key and should
not show a license form.

## Create And Publish The First Article

1. Open **Categories** and add a category such as `Guides`.
2. Open **Posts** and click **New post**.
3. Enter a title. Check the generated slug directly below it.
4. Choose the category you just created.
5. Add at least one paragraph and one `H2` heading in the visual editor.
6. Set the article to active and choose a publication date that is not in the
   future.
7. Save the article.
8. Use the storefront link to open it in a private browser window.

You should see the title, article content and category. If the table of
contents is enabled and the article has enough headings, you should also see
**In this article** above the content.

If the article is missing, check its active state, date, shop, language and
category. Then clear the PrestaShop cache and try the generated URL again.

## Configure The Blog

Open **Settings** and review the blog URL prefix, posts per page, layout,
sidebar position, table of contents, comments, related content, breadcrumbs,
social previews and RSS feed options.

The sidebar position applies to blog listings and individual articles. Choose
**Left**, **Right** or **None**. When enabled, the sidebar exposes category and
tag archive links plus RSS, improving navigation and internal discovery of
related blog content.

Keep carousel assets set to **Module** unless the active theme already provides
a compatible carousel library.

## Create Posts

CCI Blog includes a visual, block-based WYSIWYG editor. Authors compose the
final article directly with headings, paragraphs, lists, rich text, links,
images and video instead of editing HTML. Pro adds responsive columns,
products, product carousels, PrestaShop hook output and blocks supplied by
installed extensions to the same editor canvas.

1. Open **Posts**.
2. Click **New post**.
3. Enter title and slug. Slug sits directly below the title in the editor.
4. Choose a primary category.
5. In the base module, edit the default shop language. In Pro, edit every shop
   language.
6. Build the article in the visual block editor. Use slash commands or the
   toolbar to insert and reorder content without writing HTML.
7. Use the SEO tab for meta title, meta description, focus keyword and social
   metadata.
8. Save.

With CCI Blog Pro, use **Choose local image** beside cover, Open Graph,
category and linked-image URL fields. The picker lists existing images from
approved PrestaShop and module directories. You can still paste an external
HTTP(S) URL. In the CCI Blog images source you can upload a JPEG, PNG or WebP
file up to 12 MB. Pro validates and re-encodes it into responsive variants in
`img/cci_blog`; the Store images source remains read-only.

Choose block types and link styles from the editor controls. Normal publishing
does not require CSS class names or HTML element IDs.

## Author Profiles And Biographies

The base CCI Blog module extends the native PrestaShop employee form. Open **Advanced
Parameters > Team > Employees**, edit the employee assigned as an article
author and complete **CCI Blog author profile**. You can enable the public
profile, set a public display name, add a biography, provide an avatar URL and
optionally add an X (Twitter) username and LinkedIn details. Free saves the
biography in the default shop language. Pro lets you edit a separate biography
for every shop language.

The profile is shared by every article assigned to that employee. The author
box below an article is displayed only when **Show author** is enabled in CCI
Blog settings, the employee's public author profile is active and a biography
exists for the current storefront language. Leaving the public name empty uses
the employee's first and last name. Avatar and social fields are optional.

## Article Structured Data

Open **Settings** and keep **Schema.org structured data** enabled if you want
article pages to include JSON-LD.

CCI Blog adds this data automatically on single article pages:

- article title and description,
- publication and update dates,
- cover image when the article has one,
- author name, biography, avatar and public social links when they are filled,
- article category and tags,
- reading time and word count,
- breadcrumb trail from the home page to the article.

There is no extra field to fill only for structured data. Complete the normal
article fields instead: title, intro or SEO description, cover image, author
profile, category and tags. Then open the article source and look for
`application/ld+json` if you want to confirm that the script is present.

If the JSON-LD is missing, check **Settings > Schema.org structured data**,
clear the PrestaShop cache and make sure the article is active, published and
assigned to the current shop.

## Categories

Categories are managed on their own screen, not inline on the list. A category
can have:

- parent category,
- assigned author,
- position,
- active state,
- translated name and description,
- slug,
- SEO metadata,
- optional icon or image fields when enabled by the current module version.

The category list shows the assigned author and the current SEO score, using
the same color scale as the post list. Open a category to change its author or
to improve its SEO metadata.

Posts require one primary category. Pro can unlock additional categories per
post.

## Comments

CCI Blog uses Disqus for reader comments by default. In **Settings**, select
**Disqus**, enter the exact shortname assigned to the site in Disqus and keep
comments enabled. The shortname is not the site title or domain. Each post's
**Allow comments** setting can still disable the embed for that article.

Disqus stores and moderates comments outside PrestaShop. Open **Comments** in
CCI Blog to follow the moderation link to the configured Disqus site. Review
the Disqus privacy and consent configuration before enabling it in a live
shop, because the embed loads third-party resources.

The legacy **Native** provider remains available for upgraded shops that need
their existing local comments. Select it explicitly to restore the built-in
form and moderation queue. Use **Settings** to disable comments globally when
the shop does not use blog comments.

## Automatic Table Of Contents

Enable **Show table of contents in articles** in **Settings**. CCI Blog scans
the rendered article headings from `h2` through `h6`, creates stable unique
anchors and builds a nested table of contents that follows their hierarchy.
Use **Minimum headings** to avoid showing the component in short posts.

The table is expanded on desktop and collapsed on small screens. Authors only
need to choose the correct heading levels in the visual editor; do not type
manual IDs or duplicate a separate contents block.

## Storefront URLs

Default routes use the blog URL prefix configured in **Settings**, for example:

```text
/blog
/blog/{post-slug}
/blog/category/{category-slug}
/blog/tag/{tag-slug}
/blog/author/{author-slug}
/blog/search?q=keyword
/blog/feed.xml
```

After changing the base slug, clear PrestaShop cache and verify the generated
routes.

Paginated archives use canonical path URLs instead of query-only links:

```text
/blog/page/2
/blog/category/{category-slug}/page/2
/blog/tag/{tag-slug}/page/2
/blog/author/{author-slug}/page/2
/blog/search/page/2?q=keyword
```

When the optional `.html` suffix is enabled, the same routes end in `.html`.
Requests using the non-canonical pagination form are redirected to the active
format.

## Slug Redirects

When a post or category slug changes, CCI Blog stores the previous value and
returns a permanent 301 redirect to the current URL. Open **Slug redirects** to
review the old slug, current slug, target and creation date. Existing entries
can be edited or deleted; deleting one means links using that historical URL
will stop resolving through the module.

Redirects are scoped by entity and language. The module also accepts migrated
legacy SmartBlog paths that contain a numeric prefix or `.html`, provided a
matching redirect exists. Keep the generated redirect rows when changing a
live URL structure.

## Previous And Next Articles

Single-post pages include previous and next article links below the article.
The neighbours are selected chronologically from active, published posts in
the current shop and language. At either end of the archive only the available
direction is displayed. Themes can override the navigation partial without
replacing the complete post template.

## HTML And XML Sitemaps

CCI Blog integrates with both sitemap mechanisms provided by PrestaShop:

- the public HTML sitemap automatically receives a **Blog** section containing
  the blog home, active categories and every active, published post;
- the Google sitemap (`gsitemap`) module receives the same URLs for its XML
  output, with article/category update dates and article cover images where
  available.

Posts are related to shops independently of categories, so a published post
without a category is still included. Drafts, inactive entries, future-dated
posts and content assigned to another shop are excluded. Links are generated
separately for the current shop and language.

Open the standard PrestaShop **Sitemap** page to verify the HTML section. After
installing CCI Blog or changing published content, regenerate the XML files in
the **Google sitemap** module because `gsitemap` stores generated files rather
than rebuilding them on every request. See [SITEMAPS.md](./SITEMAPS.md) for the
full contract and troubleshooting steps.

## CCI Nice Menu Integration

When both modules are installed, Nice Menu can render blog content blocks:

- sticky selected post,
- latest post list,
- popular post list based on view counters,
- article carousel,
- blog categories list.

Use the Blog category block when you need automatic or selected category links
inside a dropdown. Category lists can hide empty categories, show post counts
and sort by position, title or post count.

## Domain Setup

CCI Blog does not connect domains. Domain routing belongs to PrestaShop, the
web server, SSL and DNS.

Before launch:

1. Point DNS records to the production host.
2. Configure PrestaShop shop URL in **Shop Parameters > Traffic & SEO**.
3. Enable SSL.
4. Clear PrestaShop cache.
5. Open blog list, post, category, tag, author, search and feed URLs.
6. Check that manual links inside posts do not point to staging domains.
7. Verify the Blog group on PrestaShop's HTML sitemap and regenerate XML files
   in the native Google sitemap module.

## Support

When requesting support, include:

- product name and version,
- PrestaShop version,
- PHP version,
- shop domain and environment,
- whether CCI Blog Pro is installed and activated,
- screenshots of the admin screen and storefront issue,
- affected post/category ID or slug,
- browser console errors,
- relevant server/PHP error log excerpt.

Product page:

```text
https://coolcatideas.com/products/cci-blog
```

Support entry point:

```text
https://coolcatideas.com/support
```
