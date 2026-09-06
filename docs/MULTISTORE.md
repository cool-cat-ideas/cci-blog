# PrestaShop Multistore (Pro)

CCI Blog Pro uses the native PrestaShop shop context. Posts, categories,
translations, slugs, SEO fields and storefront queries are resolved for the
current `id_shop`; module settings and the Pro license token are also saved in
that shop context.

Posts have their own direct `cci_blog_post_shop` association. Shop membership
is never inferred from a category. A post without a category is supported and
remains isolated by its post-shop association and shop-language rows.

## Configure separate blogs

1. Enable Multistore in PrestaShop and create the required shops.
2. Install `cci_blog` and `cci_blog_pro` and activate Pro for every production
   shop/domain covered by the purchased license.
3. Select one shop in the PrestaShop header shop selector. Do not use **All
   shops** while editing content.
4. Configure Blog settings for that shop and create its posts/categories.
5. Switch to another shop and repeat. The admin lists and storefront only read
   rows associated with the selected shop.

## Assign existing content to another shop

Open the saved post or category in CCI Blog. The **Multistore** section below
the title lists the shops available to the current employee. Select the target
shops and use **Assign post** or **Assign category**. New content must be saved
before this action becomes available.

The Pro admin API exposes the target shops in the initial `multistore` payload
and through `multistore:context`. It accepts authenticated JSON requests:

```http
POST {adminAjaxEndpoint}&ajax=1&ajaxAction=multistore:post:assign-shops
Content-Type: application/json

{"id_post":42,"shopIds":[2,3]}
```

For a category use `multistore:category:assign-shops` with `id_category`.
The operation creates missing `*_shop` associations and copies the source
shop language/SEO rows. Existing target-shop content is not overwritten. If a
slug is already used in a target shop, the copied slug receives a numeric
suffix. Assigning a post also assigns any missing categories used by that post
and their parent categories, so the target shop does not receive broken
category navigation.

The post/category base record is shared by PrestaShop associations. Shop
language fields remain separate. Product blocks are resolved against the
target shop catalogue, so verify product availability after assignment.

## License and troubleshooting

- `multistore` is a reserved Pro feature key. The Free module exposes the
  contract but cannot authorize or execute the operation.
- Every storefront domain consumes an activation according to the purchased
  license tier. A Multistore feature does not turn a one-domain license into an
  unlimited license.
- If the target list is empty, verify that Multistore is enabled and the
  employee can access the target shops.
- If copied content is not visible, check the selected shop, language, active
  state, publication date and product associations, then clear PrestaShop
  cache.
