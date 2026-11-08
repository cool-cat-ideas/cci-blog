# Article versions

## Review an earlier version

1. Open **Posts**, then open an article.
2. Choose **Versions**.
3. Select a version by its number. The list includes the save time and employee.
4. Use **Compare with current version** to inspect changes. Deleted text uses a
   removal marking; added text uses an insertion marking.
5. Enable **Show unchanged fields** when you need more context.
6. Use **View full version** to read the saved content and its settings.

The comparison uses the current saved article, not unsaved editor changes.
**Load older versions** retrieves the next page. This view does not change the
article and has no restore action.

## What a version contains

Every successful administration save records a snapshot of the article's
editorial fields: title, slug, introduction, content blocks, generated content,
author, category assignments, tags, publication settings, images and search and
social metadata. The employee who saves the article is separate from its author.
The employee's name is retained with the version so later account changes do
not change the recorded attribution.

View counters and publication-check results are excluded. A save with no
editorial changes still records the save and is labelled accordingly. When
blocks are present, generated storefront HTML does not count as a separate
editorial change; the blocks are the source of truth.

For an existing article, its first tracked edit also preserves the previous
state as a baseline. Its previous editor is unknown. The baseline's time is when
it was captured, not a reconstructed historical edit time. Upgrading does not
create versions of every article, guess editors, or change article dates.

Dashboard reads the latest tracked save in the current shop and default content
language. Articles with no tracked save show **Not recorded** in the **Modified
by** column. It never substitutes the article author for the editor.

## Scope and access

Versions belong to one article, shop and content language. Native employee
permissions, admin tokens, shop access and language checks apply. A revision ID
alone is insufficient to read a version. History is available only in the
administration; there is no public history endpoint.

Snapshots are immutable. Deleting an article removes its versions in every
scope, and uninstalling the module removes the history table. There is no
automatic pruning. Only manual saves create versions; typing, live publication
checks, opening a list and viewing an article do not create versions.

## Storage and integration notes

`classes/CciBlogPostHistory.php` owns the storage contract. The table is
`PREFIX_cci_blog_post_revision`, indexed by article, shop, language and revision
ID. It stores the authenticated employee ID and name, save time, event type,
changed field names and a JSON snapshot. A `baseline` has no known employee;
regular entries use the `save` event type.

The native `post:save` adapter locks the article row and saves content, the
publication result and history in one transaction. A failed save rolls back.
When called inside a PDO transaction it uses a savepoint and leaves the caller's
transaction open. Employee attribution comes from the authenticated context,
never request fields. The upgrade and fresh installer create the same table.
The administration initializes missing history storage when needed.

The internal authenticated administration actions `post:history` and
`post:revision` accept an article ID and content language in the JSON body. The
shop is resolved from the employee context. History pages contain up to 20
metadata rows; `before_id` provides cursor pagination. Selecting an entry loads
its snapshot and the current saved snapshot. These internal actions are not a
public integration API.

Comparisons use stored data. They do not render live product cards or execute
content hooks. The full-version view sanitizes stored HTML and disables links;
block settings remain available as read-only data. Text comparisons use jsdiff
with a computation limit; a large or substantially rewritten field falls back
to showing its full old and new values. The complete snapshots are retained.

Native regression coverage is in `tests/post-history-native.php` and
`tests/upgrade-110-native.php`.
