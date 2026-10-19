# CCI Blog 1.0.0 Release Notes

## Article Navigation And Comments

- Generates a nested article table of contents from rendered `h2`–`h6`
  headings and adds stable, unique anchors to those headings.
- Uses responsive native disclosure markup: expanded on desktop and collapsed
  on small screens.
- Uses Disqus as the default comments provider and loads its embed only on
  eligible posts after a valid shortname is configured.
- Keeps native comments as an explicit compatibility option without deleting
  existing local comments.
- Links the Comments admin page to Disqus moderation whenever Disqus is
  selected.

## Storefront Navigation And URLs

- Single articles expose chronological previous and next navigation for active,
  published posts in the current shop and language.
- Blog, category, tag, author and search archives use canonical `/page/{page}`
  routes, including the configured optional `.html` suffix.
- Post and category slug changes create language-aware permanent redirects.
  Migrated numeric SmartBlog slugs and `.html` paths can resolve through the
  same redirect table.

## Back Office

- The native PrestaShop employee form includes a separate **CCI Blog author
  profile** card with a public name, biography, avatar and social links.
- Free edits the biography in the default shop language. Pro adds a separate
  biography for every shop language.
- **Slug redirects** is a separate sidebar page using the shared table, row
  action and confirmation-modal patterns.
- Employees can review, edit and delete historical post/category slugs.
- Redirect targets remain scoped to the current shop and language.

## Theme Integration

- `_post_navigation.tpl` is a dedicated override point for previous/next links.
- Frontend classes use the `cci-blog-` namespace with lowercase words and
  single hyphens.
- Theme overrides should change presentation only; canonical routing and
  redirect decisions remain in the module controllers.

## Installation Checks

1. Clear PrestaShop cache to register module routes.
2. Open a paginated archive and confirm `/page/2` links.
3. Change a test post/category slug and verify a 301 from the old URL.
4. Open a middle article and verify both previous and next links.
5. Verify the table of contents on desktop and mobile.
6. Configure a Disqus shortname and verify the embed and moderation link.
7. Edit an employee, complete the CCI Blog author profile and verify the author
   box below an assigned article in the current storefront language.
