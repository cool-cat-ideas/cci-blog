# Article navigation

The standard table of contents appears above article text. It is expanded on
desktop and collapsible on phones. Existing installations retain this layout.

CCI Blog Pro adds two layouts under Blog settings, Table of contents layout:

- Article sidebar: a static reading navigation column.
- Sticky article sidebar: the same column stays below fixed site headers while
  the article scrolls. Its height is limited to the viewport and a long outline
  scrolls independently.

These are shop-scoped defaults for articles. They use the existing sidebar
position, or the right column when the sidebar is disabled. **Content below the
table of contents** selects the rest of that column: outline only (the default),
blog search/categories/tags, a selected display hook, or both widgets and a hook.
Other blog views keep their widgets.
Only two reading columns are used. Below 992px, the same navigation element moves
above the article text and becomes collapsible. It is not duplicated in the DOM.
Additional sidebar content follows the article on smaller screens. In sticky
mode the whole sidebar rail stays below the header and scrolls within the viewport.

The Pro layouts highlight the current heading. Anchors account for fixed theme
headers and the configured gap. No JavaScript is required to access the outline;
without it, the navigation remains above the article. Empty outlines never create
an extra column.

The protected Pro entrypoint validates `advanced_layout_blocks` before accepting
or rendering a Pro layout for the current shop. Expiry preserves existing runtime
layouts and prevents Pro changes; revoked or invalid tokens fall back to the
standard inline navigation.

Configuration keys:

- `CCB_TABLE_OF_CONTENTS_LAYOUT`: `inline`, `sidebar` or `sticky`.
- `CCB_TABLE_OF_CONTENTS_STICKY_GAP`: 8 to 80px, default 16px.
- `CCB_TABLE_OF_CONTENTS_SIDEBAR_CONTENT`: `none`, `widgets`, `hook` or `widgets_hook`.
- `CCB_TABLE_OF_CONTENTS_SIDEBAR_HOOK`: an editor-allowlisted display hook, default
  `displayCciBlogArticleSidebar`.

The hook selector reuses the editor's safe display hook list. Attach a compatible
module to `displayCciBlogArticleSidebar` through **Design > Positions**, or choose
an available storefront/extension hook. Its output should fit a sidebar. An empty
hook adds no empty panel. Hook output uses the protected Pro Hook-block renderer
and requires `hook_blocks`; editing these sidebar settings requires active Pro
layout access. Existing published output remains available after ordinary expiry.
The hook receives `block.placement` (`article_sidebar`), `block.id_post`,
`block.id_shop` and `block.id_lang`. An unavailable hook produces no output until
its module is available again; the saved choice is preserved.

Theme overrides can copy `_article_sidebar_content.tpl`, `_table_of_contents.tpl` and `_table_of_contents_items.tpl`
to `themes/YOUR_THEME/modules/cci_blog/views/templates/front/`. Preserve the data
attributes and normal anchor links so keyboard use, breakpoint movement and
active-heading updates continue to work.
