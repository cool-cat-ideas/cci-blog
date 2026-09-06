# CCI Blog architecture direction

## Recommendation

Keep the domain layer in PHP and build the writing experience in React.

PHP should own installation, ObjectModel classes, front controllers, hooks, routes, cache invalidation, SEO output and import/export jobs. React should own the admin writing workflow: editor, block controls, media selection, product embeds, preview and validation.

This keeps the module native to PrestaShop while allowing an admin experience similar to `cci_nice_menu`.

## Admin shell

Use one shared admin layout:

- left navigation: Posts, Categories, Comments, Media, Settings
- top header: current section title, primary action, status
- main area: grid/list/editor
- right sidebar: SEO score, publish state, preview, related products

The current PHP configuration page already uses this direction. The full post editor should be the first React screen.

## Block editor MVP

Store both structured blocks and rendered HTML:

- `content_blocks` as JSON for editing
- `content` as rendered HTML for fast front office output and search

Minimum block set:

- paragraph
- heading
- image
- gallery
- quote
- callout
- product embed
- product grid
- category link
- video
- FAQ
- table of contents
- related posts
- newsletter/signup
- raw HTML for advanced users

## Differentiators

- live front-office preview without leaving the editor
- product search and product embeds inside posts
- SEO panel with title, description, canonical, OG image and schema preview
- automatic 301 redirects when slugs change
- scheduled publishing
- reusable content snippets
- import from SmartBlog and WordPress
- cache tags invalidated on post/category updates
- image alt text checks and lazy-loading defaults
- schema blocks: Article, FAQPage, HowTo, BreadcrumbList

## Storage additions for the React editor

Recommended next columns:

- `content_blocks` LONGTEXT NULL
- `status` ENUM('draft','scheduled','published','archived') NOT NULL DEFAULT 'draft'
- `canonical_url` VARCHAR(512) NULL
- `reading_time` INT UNSIGNED NOT NULL DEFAULT 1
- `is_indexable` TINYINT(1) NOT NULL DEFAULT 1

Recommended new tables:

- `cci_blog_revision`
- `cci_blog_media`
- `cci_blog_snippet`
- `cci_blog_import_job`

## Why not pure PHP admin

Classic `ModuleAdminController` is enough for grids and basic forms, but it is a weak fit for a modern editor. A block editor needs local state, drag/drop, previews, async product search, autosave, validation and undo. Those are simpler and safer in React.
