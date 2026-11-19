<a href="https://coolcatideas.com/products/cci-blog/">
  <img src="views/img/cci-blog-logo-small.png" alt="CCI Blog" width="200">
</a>

# CCI Blog

Publish buying guides, store news and answers to customer questions under your shop's address. CCI Blog keeps writing, publication and comment moderation in PrestaShop. The visual editor supports headings, images and video; categories, author profiles and tables of contents help readers find their way through the articles.

Download the module ZIP from the [CCI Blog product page](https://coolcatideas.com/products/cci-blog/).

## See what you can build

### Bring buying guides onto the shop homepage

Show a featured article and more guides alongside your store. Readers can open an article or browse all posts from the homepage block.

![CCI Blog homepage block with a featured buying guide and four more articles](docs/screenshots/cci-blog-storefront-overview-20261108.png)

### Start with the work waiting in your blog

Review publication dates and article titles in a month grid, then open an article directly. The selected day shows its full article list. Dashboard also keeps recent work and the last recorded editors together.

![CCI Blog Dashboard with the publication plan and version numbers for both editions](docs/screenshots/cci-blog-publication-planning-20261119.png)

### Review saved article versions

See who saved an article, when it was saved and which fields changed. Compare a selected version with the current saved article or open its full content and settings. History stays within the article's shop and language.

![Browsing saved article versions and comparing their text](docs/screenshots/cci-blog-article-versions.gif)

### Keep the last publication check in the article list

See the saved result alongside each article. A changed dependency marks the result as needing refresh; saving updates it automatically. Opening the list does not run the checks again.

![Filtered CCI Blog article list with saved publication results](docs/screenshots/cci-blog-saved-results-20261108.png)

### Check publication details before publishing

One server-side engine checks metadata, headings, links and publication settings. It explains what needs attention and distinguishes unavailable checks. The result does not predict search rankings. Add your own rules through the documented extension hook.

![Publication checks and search settings in the article editor](docs/screenshots/cci-blog-publication-checks-20261108.png)

### Connect related guides

Choose a published article or blog category from the internal link picker. Ordinary links can also lead to products and other shop pages.

![Choosing an existing article or blog category without copying its address](docs/screenshots/cci-blog-internal-links.png)

### Recommend products in context with PRO

Place a product beside the text or between sections. Grids, carousels and comparisons use the active theme's product cards. A theme change therefore also changes the cards used in the article.

![Theme-native product card with article text wrapping around it, with PRO active](docs/screenshots/cci-blog-wrapped-product-20261108.png)

### Choose a reading layout with PRO

Keep the table of contents above the article, in the sidebar or in a sticky sidebar. On phones it returns above the text. Optional blog navigation and permitted hook content can fill the sidebar below it.

![Inline, sidebar and sticky tables of contents in an article](docs/screenshots/cci-blog-toc-layouts-20261108.gif)

The base edition includes article versions and comparisons, publication checks, summaries and an inline table of contents. The publication calendar and usefulness ratings require CCI Blog 1.1.1 or later. Pro adds product blocks, multilingual content, recommendations, article series and private reader messages.

[Explore CCI Blog and compare editions](https://coolcatideas.com/products/cci-blog/) · [Read the documentation](https://coolcatideas.com/docs/cci-blog/1.1.0/)

### Protect comments and article feedback

In **CCI Blog → Settings → Form protection**, choose **Google reCAPTCHA v2** or **Cloudflare Turnstile**. Add your shop domain in the provider's dashboard, then copy the site and secret keys into Blog and enable protection. Test a comment and an article rating on your shop.

You can save keys for both services and use one at a time. Protection is off until you enable a provider. Disqus manages its own comment form.

### Help readers find the next useful guide

Choose up to six recommended articles, set a heading and introduction, then drag
articles into reading order. Filter the picker by publication status and identify
results by their author and date. Automatic suggestions fill remaining places
when enabled.

![Recommendations and series ordering in the article editor](docs/screenshots/cci-blog-pro-recommendations-20261112.png)

Connect a longer topic into a series with its own page and previous/next links.
Only published parts appear to readers. The article editor's main Save button
saves recommendations, membership and the complete series order together.

![A series page using the active PrestaShop theme](docs/screenshots/cci-blog-pro-article-series-20261112.png)

### Find explanations that need more work

Readers can vote on usefulness without an account. Pro adds an optional private
message and reports by article and primary category. Shop staff can remove a
message without deleting its anonymous vote. Text is kept for 90 days and votes
for one year.

![Private reader messages and topic reports](docs/screenshots/cci-blog-pro-reader-feedback-20261112.png)

![Usefulness voting with an optional private message](docs/screenshots/cci-blog-reader-usefulness-20261112.png)

[Reader engagement workflows](docs/READER_ENGAGEMENT.md)

## Compare editions

| Capability | Base edition | Pro |
| --- | --- | --- |
| Block editor, publication checks and article history | Included | Included |
| Automatic related articles | Included | Included |
| Publication calendar and usefulness votes | From 1.1.1 | Included |
| Up to six manually chosen recommendations | No | Included |
| Ordered article series and public series pages | No | Included |
| Private reader messages and reports by topic | No | Included |
| Theme-native product blocks and multilingual content | No | Included |

CCI Blog Pro 1.0.0 requires CCI Blog 1.1.1 or later.

## Start publishing

1. Download the current CCI Blog ZIP from the product page and install it in **Modules > Module Manager**.
2. Open **Cool Cat Ideas > CCI Blog**.
3. Go to **Categories** and add the first category.
4. Go to **Posts**, create an article and assign its main category.
5. Add headings and text in the visual editor, then publish the article.
6. Open the article on the storefront and check it on desktop and mobile.

To show an author box, edit the employee in **Advanced Parameters > Team > Employees**, open **CCI Blog author profile**, enable the public profile and add a biography. The base edition saves it in the default shop language. Pro adds a separate biography for every shop language.

If an article does not appear, check its active state, publication date, shop assignment, language and category. Then clear the PrestaShop cache.

The base module works without a license key. CCI Blog Pro is optional and adds more layout, language, Multistore and product-content tools.

[Full documentation](https://coolcatideas.com/docs/cci-blog/1.1.0/)

## Configure and customize

- [Publish an article and configure the blog](docs/USER_GUIDE.md).
- [Change styles, card markup, date format and sidebar](docs/TEMPLATES_AND_STYLING.md).
- [Build an installable editor-block extension](docs/DEVELOPER.md#create-an-installable-editor-block-extension).
- [Hook arguments and examples](docs/HOOKS_REFERENCE.md) and [supported PHP helpers](docs/API_REFERENCE.md).
- [Slugs, redirects and sitemap setup](docs/ROUTES_SEO.md).
- [Publication checks, saved results and custom rules](docs/PUBLICATION_AUDIT.md).
- [Article versions, comparisons and storage](docs/ARTICLE_VERSIONS.md).
- [Summaries, links and product comparisons](docs/EDITORIAL_BLOCKS.md).
- [Table-of-contents layouts and sidebar hooks](docs/TABLE_OF_CONTENTS.md).

Keep changes in a theme or separate module. Do not replace core PHP methods or edit installed module files.

## Support

Use the product repository under https://github.com/orgs/cool-cat-ideas/repositories for public questions and bug reports. This channel has no guaranteed response time. When you need a private request with an agreed scope, buy a support service on the product page; the request and further messages are then handled in the Cool Cat Ideas customer panel.

Release history is available in the [changelog](CHANGELOG.md).

## Building and contributing

See [Build from source](docs/building-from-source.md) for the npm workflow. Shared components, styles and API adapters are maintained in [CCI Admin UI](https://github.com/cool-cat-ideas/cci-admin-ui).
