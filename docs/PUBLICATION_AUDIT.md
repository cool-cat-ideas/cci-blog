# Publication checks and custom rules

CCI Blog has one publication-check engine: `classes/CciBlogPublicationAudit.php`.
`CciBlogPublicationAuditService` supplies native PrestaShop data for the current
shop and content language. The live editor, category editor, saves and explicit result refreshes call that
engine. Article/category lists only read saved compact results; they never run
audit evaluators. JavaScript transports drafts
and displays the response; it does not calculate a second score.

These are publication checks, not a prediction of Google rankings, a semantic
quality assessment or a guarantee of traffic. A short article can pass. A focus
phrase is optional. Default rules do not reward repetition, word count, an image
quota or a link quota. No external service receives the draft. No HTTP link
checker runs, including for internal URLs.

## Inputs and scope

`evaluate(array $post, array $context = [], ?array $facts = null): array` is the
pure engine API. Use `CciBlogPublicationAuditService::audit($post, $langId)` in a
running shop to populate availability checks. Calling the pure engine without
the necessary availability data intentionally produces an unavailable score.

Post inputs include `id_post`, `id_category`, `id_shop`, `id_lang`, `title`,
`slug`, `intro`, `meta_title`, `meta_description`, `focus_keyword`,
`seo_content_type`, `allow_comments`, `blocks` and legacy `content`.
`seo_content_type=category` selects category identity. The adapter forces the
current shop and validates the selected language against its language
associations. Employee permissions, native admin tokens and shop access apply to
the draft endpoint. Draft audits are POST requests, limited to 1 MB.

Effective title uses `meta_title`, then article title. Effective description uses
`meta_description`, then introduction; category descriptions use their plain
text fallback. Facts use editorial text, nested columns, headings, links, image
descriptions, video captions, summary points and referenced product IDs. Static
blocks use the same `CciBlogEditorialBlocks` renderer as the storefront, without
executing hooks or product presenters. Image-caption links and advanced-link IDs
therefore participate in destination checks too. Valid image/video content is
not treated as an empty article merely because it has no paragraph text. Paragraph HTML takes
precedence over its plain text representation. Script/style/template contents
are excluded. Unicode letters and numbers, including single-letter Polish
words, count as words. Generated product names, prices and comparison values do
not inflate editorial word count. Focus phrase occurrences are a literal,
case-insensitive diagnostic, not a linguistic relevance measurement.

Dynamic hook/module output is never executed during analysis. Preview it on the
storefront. This avoids side effects and invented facts about an unseen result.

The adapter checks published Blog posts and categories in the same shop and
language, publication dates, known Blog redirects, locally recognized native
routes, fragment IDs and referenced products. Duplicate comparison uses the
effective title/description of other published Blog posts and categories,
excluding the current entity. It does not compare the whole shop or the web.
Product checks require the product to belong to the shop, be active, visible and
have its selected-language name. Stock quantity is not a validity requirement.
An unrecognized route is **unavailable**, not proof of a broken link. External
URLs are counted but not requested. Fragment checks cover analyzed Blog content;
they cannot verify arbitrary dynamic extension output.

## Default rule policy

| Rule ID                    | Weight | Meaning                                                               |
| -------------------------- | -----: | --------------------------------------------------------------------- |
| `title_present`            |     20 | An effective title exists                                             |
| `description_present`      |     10 | A useful description fallback is available                            |
| `body_present`             |     20 | Editorial text, media or product content exists                       |
| `single_h1`                |     10 | Article title plus body has one main heading                          |
| `heading_hierarchy`        |     10 | Section headings do not skip levels                                   |
| `internal_targets`         |     15 | Locally verified internal destinations are available                  |
| `anchor_targets`           |     10 | Checked article section anchors exist                                 |
| `product_targets`          |     15 | Referenced products are available in this shop/language               |
| `duplicate_title`          |     10 | No matching effective title on another published Blog page            |
| `duplicate_description`    |      5 | No matching effective description on another published Blog page      |
| `image_descriptions`       |      0 | Review informative images; explicit decorative HTML `alt=""` is valid |
| `descriptive_links`        |      0 | Review generic link labels in context                                 |
| `title_length`             |      0 | Display guidance; over 70 characters prompts review                   |
| `description_length`       |      0 | Display guidance; over 180 characters prompts review                  |
| `focus_phrase`             |      0 | Optional editorial aid                                                |
| `content_length`           |      0 | Word count, without a minimum                                         |
| `dynamic_content`          |      0 | Reminder to review dynamic storefront output                          |
| `comparison_configuration` |      0 | Supported product and row counts                                      |
| `analysis_limits`          |      0 | Whether the complete draft could be analyzed                          |

Weights are editorial policy, not search-engine ranking factors. The heading
checks also help reading and accessibility. Length suggestions are not hard
search-engine limits. Missing links/images are not failures merely because a
short article does not need them.

Each enabled rule returns one status:

| Status           | Score multiplier | Behavior                                                                               |
| ---------------- | ---------------: | -------------------------------------------------------------------------------------- |
| `good`           |                1 | Applicable scored check passes                                                         |
| `warning`        |              0.5 | Applicable check needs review                                                          |
| `bad`            |                0 | Confirmed problem                                                                      |
| `info`           |         excluded | Information, including optional guidance                                               |
| `not_applicable` |         excluded | Nothing applicable to check                                                            |
| `unavailable`    |         excluded | Could not verify the check; a positive-weight rule makes the overall score unavailable |

`score = round(100 * earnedWeight / applicableWeight)`. Only positive-weight
rules returning `good`, `warning` or `bad` enter that denominator. A disabled rule
does not run. Weight zero still displays its result but never affects the score.
For example, a passing weight-20 rule and a warning weight-10 rule earn 25/30,
or 83/100. Overall status is `good` at 80+, `warning` at 55+, otherwise `bad`;
an unavailable score has neutral `info` status. Those thresholds exist only in PHP.

A failed scored callback, invalid rule contribution, incomplete local index or
truncated analysis must not become a fabricated complete result. The response
uses `score: null` and `complete: false`; the interface shows unavailable state.
Zero applicable score weight also produces `score: null`. No results means no
score, rather than 100. Optional unavailable checks remain visible.

## Add or override rules in a separate module

Do not edit CCI Blog's core files. A normal PrestaShop module can register
`displayCciBlogPublicationAuditRules`. It returns an **array**, not HTML. A complete
small example is [cciblogauditexample.php](examples/cciblogauditexample/cciblogauditexample.php).
Copy its directory to `modules/cciblogauditexample`, adapt it and install it
through the native Module Manager. Remove it to restore the default policy.
This capability belongs to the public engine and does not require Pro.

Hook parameters are `auditVersion`, `id_shop`, `id_lang`, `languageIso` and
`defaultRules` (the default descriptor list, without callback closures). The hook
runs once per content language per service/request. Return:

```php
return [
    'priority' => 100,
    'rules' => [
        ['id' => 'description_present', 'weight' => 0, 'revision' => '2'],
        ['id' => 'cciblogauditexample.editor_note', 'label' => 'Reader next step',
         'group' => 'custom', 'weight' => 5, 'enabled' => true, 'revision' => '1',
         'evaluate' => [$this, 'checkReaderNextStep']],
    ],
];
```

Existing IDs override their named rule. Omitted fields retain their previous
value, including the core evaluator. New IDs must contain a namespace separator
`.` and provide a callable. IDs match `[a-z][a-z0-9_.-]{1,95}`. Descriptor fields:
`id`, string `label`, string `group`, integer `weight` (clamped to 0..100), boolean
`enabled`, string `revision`, callable `evaluate`. Additional fields are ignored.
`source` is assigned from the contributing module, not accepted from the payload.
Use `weight => 0` for advice and `enabled => false` to remove a rule from execution.

Contributions sort by ascending priority (-1000..1000), then module name. Later
contributions win when they touch the same ID. Within a contribution, later
descriptors win. Hook position does not control precedence. Avoid accidental
conflicts by using your module name as the namespace. Maximum 64 descriptors per
module and 96 resulting rules. Keep contributions and evaluators deterministic.

Evaluator signature:

```php
public function checkReaderNextStep(array $post, array $facts, array $context): array
{
    return ['status' => 'info', 'message' => 'Review the next step for readers.',
            'help' => 'This is an editorial suggestion.', 'details' => []];
}
```

Facts include `title`, `description`, `text`, `wordCount`, `headings` (`level`,
`text`), `images` (`src`, `alt`, `decorative`), `links` (`href`, `text`),
`productIds`, `ids`, `mediaCount`, `dynamicBlocks`, `truncated` and optional `comparisons`.
Context includes `shopId`, `languageId`, `languageIso`, `siteUrl`, `entityType`,
`entityId`, `postUrl`, `tocEnabled`, `tocMinHeadings`, `commentsEnabled`,
`duplicateTitle`, `duplicateDescription`, `linkResults`, `anchorResults`,
`productResults`, `comparisonResults`, `internalLinks` and `externalLinks` when available. Treat absent
availability context as unknown. Do not silently replace it with a successful
check. Some internal context names may be extended in later audit versions;
check `auditVersion` before enabling a module that needs a specific contract.

Return an array with a valid `status` and string `message`; optional `help` is a
string. `details` is a list of flat scalar-valued arrays, such as
`['href' => '/blog/example', 'reason' => 'Your explanation']`. At most ten details
are displayed. Messages are plain text, escaped by React; never return HTML,
secrets, raw exception messages or personal data. Known message keys use Blog's
admin dictionary; custom modules can provide their own translated strings.
Throwing callbacks, invalid result shapes, non-finite numbers (`INF`/`NaN`) and
invalid UTF-8 in returned fields become an `unavailable` result
without exposing the exception. Invalid configuration is separately reported.

Callbacks run with trusted PHP module privileges, not in a sandbox. They must be
fast, read-only and side-effect free. Do not make network requests, change shop
data or depend on the current employee, wall-clock randomness or unsaved external
state. The same rule runs for drafts, saves and explicit refreshes. Lists only read
saved summaries and do not execute the callback. Preserve shop
and language scoping in every custom query. An expensive callback affects editing and explicit checks, so keep it bounded. Check `seo_content_type` if a rule is article-only and return
`not_applicable` for categories.

## Full report response and policy identity

The native admin `post:audit` action returns `success` and `audit`. Audit fields:
`version`, `rulesFingerprint`, `complete`, nullable `score`, `overallStatus`,
`scoreKind=publication_checks`, `earnedWeight`, `applicableWeight`, `checks`,
`configurationWarnings`, `customized` and `metrics`. Checks expose their effective
descriptor, source, status, message, help, details and whether they were scored.
Metrics are descriptive, not a second set of score inputs.

The editor debounces drafts for 400 ms, aborts obsolete requests and ignores late
responses. While pending or after transport failure it does not present the
previous draft's score as current. Retry runs the same endpoint. Audit failures
do not block ordinary article saving or change storefront publication rules.

`rulesFingerprint` hashes engine version and effective descriptor metadata,
including revision, source, enabled state and weight. **Increment `revision`
whenever evaluator logic changes.** Closures and their source code cannot be
hashed reliably. Increase the core audit version when changing its contract.
Display labels and groups are excluded from the scoring fingerprint, so an
admin language change does not trigger a score-cache rebuild. Invalid policy
configuration is included and cannot preserve a stale successful cache policy.

## Saved results and list performance

Each post/category language row stores nullable `seo_score` plus a compact
`publication_audit_summary` JSON value. It contains **only the last result and
freshness metadata**, not checks, messages, draft text or a full report:

```json
{
  "score": 83,
  "overallStatus": "good",
  "complete": true,
  "version": "1",
  "rulesFingerprint": "...",
  "contextFingerprint": "...",
  "dataRevision": "...",
  "validUntil": null,
  "checkedAt": "2026-11-08T12:00:00Z"
}
```

`CciBlogPublicationAuditStorage` owns persistence and decoding. Lists read this
summary and sort by the same persisted `seo_score` column, including SQL `NULL`
for unavailable/unchecked results. They do not parse article blocks, load the
Blog availability index or execute audit evaluators. The service only resolves
rule descriptor fingerprints and the small shop configuration/revision values
needed to label freshness. Opening, filtering, sorting or paginating a list
must never initiate a full-blog recalculation.

The result is refreshed automatically when the relevant language version is
saved. The list only displays that saved result and has no recalculation action.
The explicit CLI command below is available for maintenance after an upgrade.
Saving checks the final persisted title/slug/content and excludes the saved
entity itself from duplicate detection. Native IDs and boolean flags are
normalized before custom evaluators see drafts or database rows. Category
results follow the same contract. Unsaved live-editor checks do not overwrite
the saved result. Full reports exist in the editor's `post:audit` response.

The saved check timestamp is technical metadata, not an editorial review date.

Freshness is separate from the numeric result:

- Missing/corrupt summaries show **Not checked**, never an invented zero or 100.
- A changed rules fingerprint, relevant shop settings, shop/language context,
  or catalogue revision marks the saved result **Needs refresh**. Its previous
  number remains visible, with neutral styling. This does not block publishing.
- `POLICY_REVISION` changes core calculation behavior without changing the hook
  contract `VERSION` or product package version. Custom modules still increment
  their own evaluator `revision` when callback semantics change.
- Product/CMS/feature/feature-value and native Blog object mutations invalidate
  freshness through `actionObjectAddAfter`, `actionObjectUpdateAfter` and
  `actionObjectDeleteAfter`. Controller saves/deletes, redirect edits and
  multistore assignments invalidate explicitly because they use direct SQL.
  Invalidation only updates a small revision marker; it never recalculates rows.
- Invalidation is deliberately conservative across shop/language rows for native
  catalogue object changes. It means data **may** affect a result, not that every
  score actually changed. Saving the relevant language version checks it again.
- Scheduled active posts give checks an expiry (`validUntil`) at the next known
  publication time, so a list cannot keep calling an old result current merely
  because no database write occurred at the scheduled time.
- Direct SQL changes made outside the module bypass native hooks. Integrations
  must call `CciBlogPublicationAuditStorage::invalidate($shopId)` or explicitly
  refresh affected results. No snapshot claims continuous external monitoring.

On upgrade, schema setup adds the compact summary and allows nullable score
columns. Numeric values created by the previous score cache are cleared because
they have no matching verification metadata and used a different policy. Those
rows remain unchecked until saved/rechecked; migration never analyzes the whole
blog or changes article dates. New installs start in the same unchecked state.

For an explicitly requested bulk refresh of posts and categories, use:

```sh
php modules/cci_blog/scripts/rebuild-publication-scores.php \
  --bootstrap=/absolute/shop/config/config.inc.php --shop=1
```

This maintenance script is source-only and excluded from installable ZIPs.
Copy it from this repository into the module's `scripts/` directory first. It
uses the installed PHP service and needs no Node development dependencies. The
command validates the shop and traverses its associated language rows in
keyset batches of 100. It updates saved results only, without changing dates.
It runs only when invoked; no admin page launches it in the background.

## Limits and verification

Analysis is bounded to 12 nested column levels, 1,500 blocks, 100 links and 100
unique product references; exceeding these limits marks the score unavailable.
The local Blog index has a 10,000-page cap per entity kind. An incomplete index
does not confirm the absence of duplicates or destinations. Maximum draft
payload is 1 MB. The storefront still owns actual rendering and theme behavior.

Run `php tests/publication_audit_smoke.php` for deterministic engine contracts;
the workspace smoke runner also exercises them in the native PrestaShop PHP
container. Verify runtime changes with real shop/language fixtures, native
permissions and browser interactions. A rule extension should test success,
failure, non-applicability, missing context and callback exceptions, together
with unchanged results between draft and saved representations.
