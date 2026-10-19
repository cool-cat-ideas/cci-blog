# CCI Blog HTML And XML Sitemaps

CCI Blog registers its public content in PrestaShop's native sitemap contracts.
The integration is part of the Free base module and is also available when Pro
is active.

## Included URLs

For each shop and language, the module includes:

- the blog home;
- active blog categories assigned to that shop;
- active posts assigned directly to that shop whose publication date is empty
  or has passed.

Post-to-shop assignment is evaluated independently from the primary category.
An uncategorized post therefore remains in both sitemaps. Drafts, inactive
categories, future posts and content belonging only to another shop are not
published.

## HTML Sitemap

PrestaShop's public sitemap controller executes
`actionModifyFrontendSitemap`. CCI Blog appends a **Blog** group containing the
blog home, a nested category tree and a flat list of all published articles.
The flat article list deliberately includes posts without a category.

1. Install or update CCI Blog.
2. Clear the PrestaShop cache.
3. Open the storefront **Sitemap** page in each shop and language.
4. Confirm that the **Blog** group links use the current shop domain and
   language prefix.

The HTML sitemap is built per request, so publishing a post does not require a
separate sitemap rebuild.

## XML Sitemap

PrestaShop's Google sitemap module executes `gSitemapAppendUrls` while it
generates XML files. CCI Blog returns the blog home, category and post URLs.
Entries contain `lastmod`; posts also expose a cover image when it resolves to
a safe public HTTP(S) URL.

1. Install and enable PrestaShop's **Google sitemap** (`gsitemap`) module.
2. Install or update CCI Blog and clear the PrestaShop cache.
3. Open the Google sitemap configuration page.
4. Generate sitemap files for the required shops and languages.
5. Open the generated sitemap index and confirm that blog URLs occur in its
   child XML files.
6. Submit the generated sitemap index to search engines as usual.

`gsitemap` writes static XML files. Regenerate them after publishing, moving or
unpublishing blog content, or configure its normal scheduled generation.

## Multistore And Language Isolation

Both hooks use the current PrestaShop shop context and the language being
rendered. Database queries join the explicit post/category shop relation and
the matching language row. This prevents one shop's article URLs from leaking
into another shop's sitemap.

## Developer Contract

- `CciBlogSeo::getSitemapEntries($langId, $shopId)` supplies published posts.
- `CciBlogSeo::getSitemapCategoryEntries($langId, $shopId)` supplies active
  categories.
- `hookActionModifyFrontendSitemap()` builds the HTML sitemap structure.
- `hookGSitemapAppendUrls()` builds the `gsitemap` entry arrays.

Third-party sitemap modules should use these public PrestaShop hooks or their
own URL discovery. CCI Blog does not write another vendor's sitemap files and
does not expose drafts or admin URLs.
