# CCI Blog routes and SEO

CCI Blog registers storefront routes through PrestaShop `moduleRoutes`.

## Default routes

| View | Default URL |
| --- | --- |
| Article list | `/blog` |
| Single article | `/blog/{slug}` |
| Category | `/blog/category/{slug}` |
| Tag | `/blog/tag/{slug}` |
| Author | `/blog/author/{slug}` |
| Search | `/blog/search` |
| RSS | `/blog/feed.xml` |

The base slug is controlled by:

```text
CCB_BASE_SLUG
```

Clear PrestaShop cache after changing it.

## SEO fields

Free article SEO includes:

- slug,
- SEO title,
- SEO description,
- Open Graph fields,
- Schema.org data,
- reading time,
- view count.

Free provides article content and basic SEO fields in the default shop language.
Editing translated article and SEO content for the remaining shop languages
requires Pro.

## Canonical and Open Graph

Use one canonical article URL per article. Avoid publishing the same article
under multiple slugs.

Open Graph content should match the article promise:

- image that appears in the article or campaign,
- clear title,
- concise description.

## Schema.org and JSON-LD

When **Schema.org structured data** is enabled in CCI Blog settings, every
single article page prints one JSON-LD script. It contains:

- `BlogPosting` data for the article,
- `BreadcrumbList` data for the visible path to the article,
- title, SEO description or intro,
- publication and update dates,
- cover image when available,
- author profile data when available,
- category, tags, word count and reading time.

The post model exposes this output through:

```php
CciBlogPost::getSchemaOrg()
```

You do not fill a separate schema form. Keep normal article fields complete:
title, intro or SEO description, cover image, category, tags and author profile.
Then view the article source and search for `application/ld+json` if you need
to confirm the output.

If the script is missing, check that the article is active, published, assigned
to the current shop and that the Schema.org setting is enabled. Clear the
PrestaShop cache after changing settings.

## Hreflang

The SEO helper exposes hreflang data through:

```php
CciBlogSeo::hreflangTags()
```

Publish translated SEO fields only when the article has content in the current
shop language.

## HTML and XML sitemaps

The blog adds its public URLs to PrestaShop's HTML sitemap through
`actionModifyFrontendSitemap` and to Google sitemap through
`gSitemapAppendUrls`. You do not need a custom PHP integration for these cases.

1. Open the standard storefront **Sitemap** page and check the Blog section.
2. In the **Google sitemap** module, regenerate the XML sitemap files.
3. Open the generated XML and confirm a published post and category are present.
4. Repeat for the shop and language you are configuring.

Inactive, future-dated and other-shop content must not appear. XML files are
cached output: publishing a post does not replace an already generated file.
If URLs are absent, check module hook registration, publication state, shop
assignment and language before regenerating.

## Changing a published slug

When an article or category slug changes, the module records its previous
value and redirects it to the current URL with HTTP 301. Open **Slug redirects**
to review, edit or delete those mappings. Test the old URL in a private window.
Do not delete a mapping while external links still use it. A change to the blog
base prefix is a separate routing change and is not covered by an article-slug
mapping alone.

## Previous and next articles

Single articles link to chronological published neighbours in the current shop
and language. At the beginning or end, only the available direction appears.
Override `front/_post_navigation.tpl` in the active theme to change its markup;
keep the supplied destinations and escaping.

## RSS

RSS is available at:

```text
/blog/feed.xml
```

After changing the base slug, the feed URL changes with it.
