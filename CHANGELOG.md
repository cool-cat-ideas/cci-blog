# Changelog

## 1.1.0 - 2026-11-08

- Record scoped article versions with the authenticated editor, timestamp, changed fields and immutable snapshots. Add read-only history, full-version previews and text comparisons; preserve a baseline when tracking starts on an existing article. Show the last recorded editor on Dashboard.
- Separate Dashboard from full-width management lists. Show recent articles with Title, Updated and Modified by columns, draft and scheduled counts, and upcoming publications. Use shared informational callouts for empty lists.
- Use one PHP publication-check engine for editing, saving and explicit checks. Document its rules, scoring, unavailable results and extension hook with a working module example.
- Save compact check results per article or category, shop and language. Lists read and sort these values without recalculating them. Saving refreshes the result automatically; changed dependencies mark the previous value as needing refresh. Remove the manual per-row refresh control and endpoint.
- Add an author-written In brief block with theme colors, compact spacing and visible list markers, plus an internal link picker. Preserve independent image and caption links when reopening articles.
- Add signed, short-lived previews for unsaved drafts. Publish scheduled active articles by the shop clock without cron and refresh status badges every 30 seconds. Improve RSS output, conditional requests and shop context.
- Support theme-native product layouts supplied by CCI Blog Pro: wrapped single products, grids, carousels and comparisons. Add the carousel adapter for existing Owl Carousel or Swiper instances, with a native scroll fallback.
- Support Pro table-of-contents positions and sidebar extension content; add separators to article metadata and preserve responsive reading order.
- Update editor dependencies and split administration JavaScript into shared and lazy-loaded chunks.
- Add an idempotent PrestaShop upgrade for saved check results and article versions. Preserve existing articles and dates, leave older editors unknown, and clear previous scores that cannot prove a saved check.

## 1.0.1

- Enforce employee permissions for administration actions and isolate posts, categories, comments and redirects by shop.

## 1.0.0

- Fill the available window height while letting long administration pages scroll normally, without extra sidebar scrollbars.

- First public release.
