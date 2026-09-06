# CCI Blog Templates And Styling

## Smarty Templates

Storefront templates live in:

```text
views/templates/front/
  list.tpl
  post.tpl
  category.tpl
  tag.tpl
  author.tpl
  search.tpl
  _post_card.tpl
  _post_navigation.tpl
  _table_of_contents_items.tpl
  _comment.tpl
  _sidebar.tpl
  _assets_head.tpl
  _pagination_script.tpl

views/templates/hook/
  home_widget.tpl
```

To override a template in a theme, copy it to:

```text
themes/{your-theme}/modules/cci_blog/views/templates/front/{template}.tpl
themes/{your-theme}/modules/cci_blog/views/templates/hook/home_widget.tpl
```

Example:

```text
themes/hummingbird/modules/cci_blog/views/templates/front/_post_card.tpl
```

Clear PrestaShop cache after adding or changing theme overrides.

A complete post-card override with escaped Smarty output and route checks is in
[Extension cookbook](./EXTENSION_COOKBOOK.md#override-the-storefront-post-card).

## Change The Storefront Date Format

CCI Blog stores dates in the database and formats them only in Smarty. Keep the
machine-readable `datetime` attribute unchanged and override the visible value
in the active theme.

For a single post, copy:

```text
modules/cci_blog/views/templates/front/post.tpl
```

to:

```text
themes/{your-theme}/modules/cci_blog/views/templates/front/post.tpl
```

Then change only the visible date expression. For example, this renders
`18.06.2026`:

```smarty
<time
  class="cci-blog-meta-date"
  datetime="{$ccb_post.date_published|date_format:'%Y-%m-%dT%H:%M:%S'}"
  itemprop="datePublished"
>
  {$ccb_post.date_published|date_format:'%d.%m.%Y'}
</time>
```

Use `$post.date_published` in `_post_card.tpl` for listing cards and
`$comment.date_add` in `_comment.tpl` for comments. Common tokens are `%d` for
day, `%m` for month, `%Y` for four-digit year, `%H` for hour and `%M` for
minutes. Text month names such as `%b` follow the server/Smarty locale. Clear
PrestaShop cache and verify the result in every active shop language.

## Single Post Sidebar

The **Sidebar position** setting applies to list, category, tag, author, search
and single-post pages. Supported values are `left`, `right` and `none`.

The post controller provides these variables to the sidebar:

```text
$ccb_sidebar
$ccb_categories
$ccb_tag_cloud
$ccb_feed_enabled
$link
```

`post.tpl` includes `_sidebar.tpl`, which adds contextual internal links to
blog categories, tag archives and the RSS feed. It also executes the standard
PrestaShop column hook that matches the selected side:

```text
left  -> displayLeftColumn
right -> displayRightColumn
none  -> no sidebar and no column hook output
```

Attach compatible modules through **Design > Positions**. Do not execute
`action*`, admin or unrelated checkout hooks in a storefront sidebar. Modules
on the standard column hook receive the current PrestaShop controller context.

To change the sidebar contents without replacing the article markup, copy only:

```text
modules/cci_blog/views/templates/front/_sidebar.tpl
```

to:

```text
themes/{your-theme}/modules/cci_blog/views/templates/front/_sidebar.tpl
```

For example, add a theme-owned widget after the existing widgets:

```smarty
<section class="cci-blog-widget cci-blog-theme-buying-guides">
  <h2 class="cci-blog-widget-title">{l s='Buying guides' d='Modules.Cciblog.Shop'}</h2>
  <a href="{$link->getModuleLink('cci_blog', 'category', ['slug' => 'guides'])|escape:'htmlall':'UTF-8'}">
    {l s='Browse all guides' d='Modules.Cciblog.Shop'}
  </a>
</section>
```

Use a real category slug available in every target shop and language. Do not
hard-code a staging domain. The sidebar stacks below the article on mobile,
including when its desktop position is `left`.

## Creating A New Storefront Template Variant

The current module does not use a manifest-based template registry like the WP
Carousel product. For PrestaShop Blog, a "new template" means one of these
approaches:

1. Theme override for an existing controller template.
2. New partial included from an overridden template.
3. New front controller and route if the URL/page type is genuinely new.

Recommended workflow:

1. Copy the closest existing `.tpl` into the theme override path.
2. Keep existing assigned variable names such as `$ccb_posts`, `$ccb_post`,
   `$ccb_category`, `$ccb_sidebar`, `$ccb_layout` and `$link`.
3. Scope new CSS under `.cci-blog-*` or a theme-specific wrapper.
4. Keep schema, canonical/meta and route behavior in PHP controllers.
5. Clear cache and test list, post, category, author and search pages.

## CSS Files

| File | Purpose |
| --- | --- |
| `views/css/cci_blog_front.css` | Storefront blog styles. |
| `src/admin-v2/admin.css` | Source React admin styles. |
| `src/admin-v2/blocknote.css` | BlockNote editor styling. |
| `views/css/cci_blog_admin.css` | Generated admin CSS. |

Do not edit `views/css/cci_blog_admin.css` manually. Change the source CSS and
rebuild the admin bundle.

## Public Frontend Classes

CCI Blog owns the `cci-blog-` namespace. New module and theme-override classes
must use lowercase ASCII letters, digits and single hyphens, for example
`cci-blog-card-meta` or `cci-blog-card-state-featured`. Do not introduce short
prefixes, underscores, BEM separators or standalone state classes such as
`is-active`. PrestaShop, theme and third-party classes remain unchanged.

Main listing and cards:

```text
.cci-blog-listing
.cci-blog-listing-main
.cci-blog-listing-header
.cci-blog-listing-title
.cci-blog-listing-count
.cci-blog-posts-grid
.cci-blog-posts-grid-grid
.cci-blog-posts-grid-list
.cci-blog-card
.cci-blog-card-image
.cci-blog-card-body
.cci-blog-card-title
.cci-blog-card-intro
.cci-blog-card-meta
.cci-blog-card-cta
```

Post page:

```text
.cci-blog-post-layout
.cci-blog-post-layout-with-sidebar
.cci-blog-post-layout-sidebar-left
.cci-blog-post-layout-sidebar-right
.cci-blog-post
.cci-blog-post-header
.cci-blog-post-title
.cci-blog-post-meta
.cci-blog-post-cover
.cci-blog-post-body
.cci-blog-post-table-of-contents
.cci-blog-table-of-contents-summary
.cci-blog-table-of-contents-toggle
.cci-blog-table-of-contents-list
.cci-blog-table-of-contents-item
.cci-blog-table-of-contents-link
.cci-blog-post-tags
.cci-blog-post-share
.cci-blog-post-author-bio
.cci-blog-post-products
.cci-blog-post-related
.cci-blog-post-comments
.cci-blog-post-navigation
.cci-blog-post-navigation-item
.cci-blog-post-navigation-item-previous
.cci-blog-post-navigation-item-next
.cci-blog-post-navigation-label
.cci-blog-post-navigation-icon
.cci-blog-post-navigation-title
```

`post.tpl` includes `_post_navigation.tpl` after the article content. Override
that partial when a theme needs different previous/next presentation; keep the
controller-provided URLs and escaped titles intact.

`post.tpl` renders the table of contents before the article body when
`$ccb_table_of_contents` contains items. The recursive
`_table_of_contents_items.tpl` partial owns the nested ordered lists. Keep its
links pointed at controller-generated heading IDs. The native `<details>`
element is open on desktop and collapsed on viewports up to 768 px by
`views/js/front.js`.

Shared UI:

```text
.cci-blog-breadcrumb
.cci-blog-badge
.cci-blog-badge-category
.cci-blog-badge-sub
.cci-blog-tag
.cci-blog-tag-cloud
.cci-blog-sidebar
.cci-blog-sidebar-platform-hook
.cci-blog-widget
.cci-blog-category-list
.cci-blog-search-form
.cci-blog-alert
.cci-blog-comment
.cci-blog-comment-form
.cci-blog-home-widget
```

Author pages:

```text
.cci-blog-author-profile
.cci-blog-author-profile-avatar
.cci-blog-author-profile-name
.cci-blog-author-profile-bio
.cci-blog-author-profile-stats
.cci-blog-author-social
```

Admin shell classes:

```text
.cci-blog-admin-bootstrap
.cci-blog-admin-root
.cci-blog-admin-loader
```

## CSS Rules

- Keep storefront and admin changes under `.cci-blog-*` or component-local
  Tailwind utilities.
- Do not target raw BlockNote classes globally unless the selector is scoped to
  the blog editor wrapper.
- Do not style CCI Nice Menu classes from the Blog module. Nice Menu renders
  blog blocks using its own menu components.
- Do not promise AI-related UI or classes until those features ship.
- Use visible focus and hover states for links and buttons.

## Frontend Assets

`hookActionFrontControllerSetMedia()` loads:

- `views/css/cci_blog_front.css`,
- `views/js/front.js`,
- optional Owl Carousel CSS/JS when `CCB_OWL_ASSETS_SOURCE=module`.

If the theme already loads Owl, set Owl asset source to `theme` in the module
settings to avoid loading duplicate libraries.

## Template Override Checklist

After a template override:

1. Clear PrestaShop cache.
2. Open `/blog`, a post, a category, a tag, an author page and search results.
3. Check schema/meta output still appears in the page source.
4. Check the configured comment provider when comments are enabled.
5. Check the table of contents expanded on desktop and collapsed on mobile.
6. Check the home widget if `CCB_SHOW_FEATURED_WIDGET` is enabled.
7. Check mobile card layout and images.

## CCI Blog Content Inside Nice Menu

CCI Blog supplies post data, but CCI Nice Menu Pro owns the HTML and Owl
runtime for `blog_post`, `blog_list`, `blog_carousel` and `blog_popular` menu
nodes. Therefore `.cci-nice-menu-blog-*` overrides belong to the active theme or a
Nice Menu extension, not to `cci_blog/views/css/cci_blog_front.css`.

See CCI Nice Menu
`docs/TEMPLATES_AND_STYLING.md#cci-blog-carousel-inside-nice-menu` for the
public class list, ready/fallback states and a complete override example.
