# CCI Blog Demo Seed

The demo seed fills a local shop with content that shows the current CCI Blog
and CCI Nice Menu integration without promising unavailable features.

The seed is a repository/demo tool. Production install ZIPs should not ship
`sql/seed_demo_content.php` or reset scripts.

Run from the PrestaShop container:

```bash
php /var/www/html/modules/cci_blog/sql/seed_demo_content.php
```

## What It Creates

- Blog settings for the local demo shop.
- A demo author based on the first active PrestaShop employee.
- A nested category tree for strategy, production guides and product stories.
- Published demo posts with translated content where the shop languages exist.
- Tags, comments, product relations and view counters for realistic lists.
- CCI Nice Menu demo blocks for sticky article, latest posts, popular posts,
  article carousel and the free Blog categories block.

## Demo Copy Guidelines

Seed descriptions should explain what a block demonstrates:

- "Single selected article rendered as a sticky blog card."
- "Newest active blog posts from CCI Blog."
- "Popular articles ordered by blog view counter."
- "Carousel block sourced from published blog posts."
- "Automatic category listing from CCI Blog."

## Nice Menu Blog Categories Block

The free `blog_categories` menu block renders CCI Blog category links inside a
Nice Menu dropdown. It supports:

- automatic listing of all blog categories or a selected category ID list
- hiding categories without published posts
- sorting by position, title or post count
- optional post count badges
- generated category URLs from the current shop language

Do not use seed copy that claims AI writing, AI optimization, AI support,
automated content generation or any assisted workflow that is not implemented
in the shipped module.

## Data Safety

The seed is intended for local demo data. It uses generated example comments
and no personal employee e-mail. Before updating an existing menu record, keep a
backup of the current `tree_json` if you need to preserve manual demo edits.
