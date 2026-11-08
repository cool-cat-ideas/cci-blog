# Editorial blocks

## Image and caption destinations

An image can link to one destination while its caption links to another.
**Link the image** controls the image wrapper; **Link the caption** controls
the caption. Choose **Same as image link** to keep following changes to that
destination, or **Separate caption link** to edit the caption's own URL and
attributes. A caption-only link remains caption-only when reopened and saved.

The saved `image` block keeps `link` and `caption_link` as separate objects.
Caption IDs, titles, rel values and targets are preserved. Publication checks
read the actual caption link and advanced-link IDs through the same static
editorial renderer used by the storefront. Rendering never requires the editor
to merge an independent caption destination into the image destination.

## In brief (Free)

Insert **In brief** in the article editor. Write a title and one factual
conclusion per line, up to eight points. This is author-written content; CCI Blog
does not generate a summary or claim to measure its accuracy. Empty points are
omitted. Each point is plain text, limited to 500 characters. The storefront uses
a semantic section, heading and list. The publication audit reads the same
normalized content as the renderer.

Stored shape:

```json
{
  "type": "summary",
  "title": "In brief",
  "items": ["Check available sizes before ordering.", "Compare materials on the product page."]
}
```

## Product comparison (Pro)

Select **Product comparison**, choose two to four products, then select up to
twelve rows. Supported data: current displayed price, reference, brand and
native PrestaShop product features. Feature labels/values use the storefront
language. No price, rating or product claim is copied into the article. The
normal product presenter resolves current customer/currency/tax price context.
Hidden prices display **Not shown**; missing values display **Not provided**.
No stock or discount inference is made from an empty value.

```json
{
  "type": "product_comparison",
  "title": "Compare the collection",
  "ids": [1, 2, 3],
  "fields": ["price", "reference", "feature:1"]
}
```

The `feature:N` ID belongs to the shop catalogue. Select actual available
features in the editor rather than copying IDs between unrelated shops.
Deleted feature rows are omitted. Inactive/unavailable products are omitted;
with one visible product only its native card remains, without a misleading
one-product comparison. Extra configured products/rows are capped at four/twelve
and reported by the publication audit. Deleted/disabled/unassigned selected
fields are identified in the editor and publication report. If no comparison
rows remain, the storefront shows native product cards without an empty table
or a table-scroll hint.

Cards use the active theme's native product miniature, as article product blocks
do. Changing the theme changes these cards. The table has column/row headings
and a keyboard-focusable region. At small widths the table scrolls horizontally
inside that region, preserving comparable columns without widening the page.

Override the wrapper/table at:

```
themes/YOUR_THEME/modules/cci_blog_pro/views/templates/front/blocks/product-comparison.tpl
```

Copy the module template and adapt its named Smarty blocks:
`cci_blog_product_comparison`, `_title`, `_head`, `_rows`, `_cell`, `_cards`
(the native-card fallback when there are no usable rows). Keep captions,
scope attributes, region labels and the focusable scroll container. Product card
HTML is trusted native rendered output; field labels and values must stay
escaped. CSS hooks are `.cci-blog-product-comparison`, `.cci-blog-comparison-scroll`,
`.cci-blog-comparison-table`, `.cci-blog-comparison-label`,
`.cci-blog-comparison-product` and `.cci-blog-comparison-card`. Styles inherit the
existing Blog/theme tokens. Generated comparison/card headings are excluded
from the article table of contents; put an editorial heading before the block
when it should be a navigation destination.

This uses the existing `commerce_blocks` entitlement. License expiry preserves
saved storefront rendering under the normal runtime license policy and blocks
Pro content edits. No new review-date field or module version is introduced.
