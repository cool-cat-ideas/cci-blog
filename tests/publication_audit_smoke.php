<?php

declare(strict_types=1);
define('_PS_VERSION_', '9.2.0');
require dirname(__DIR__) . '/classes/CciBlogTableOfContents.php';
require dirname(__DIR__) . '/classes/CciBlogPublicationAudit.php';

function auditCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
$engine = new CciBlogPublicationAudit();
$context = ['duplicateTitle' => [], 'duplicateDescription' => [], 'linkResults' => [], 'anchorResults' => [], 'productResults' => [], 'languageIso' => 'pl'];
$post = ['title' => 'Zażółć gęślą jaźń', 'intro' => 'Useful page description', 'blocks' => [['type' => 'paragraph', 'html' => '<p>Zażółć gęślą jaźń.</p><p>A i w z.</p>']]];
$plain = $engine->evaluate($post, $context);
auditCheck($plain['score'] === 100, 'A useful short article must not need a focus phrase, images or links.');
auditCheck($plain['metrics']['wordCount'] === 7, 'Unicode words and one-letter Polish words must be counted correctly.');
$withKeyword = $engine->evaluate($post + ['focus_keyword' => 'inna fraza'], $context);
auditCheck($withKeyword['score'] === $plain['score'], 'A focus phrase must not change the default score.');
$news = $engine->evaluate($post + ['seo_content_type' => 'news'], $context);
$guide = $engine->evaluate($post + ['seo_content_type' => 'guide'], $context);
auditCheck($news['score'] === $guide['score'], 'Content type must not invent a Google word-count requirement.');
$broken = $engine->evaluate($post, array_replace($context, ['linkResults' => [['status' => 'bad', 'href' => '/blog/missing']]]));
auditCheck($broken['score'] < $plain['score'], 'A confirmed broken internal target must affect the applicable checks.');
$unknown = $engine->evaluate($post, array_replace($context, ['linkResults' => [['status' => 'unavailable', 'href' => '/custom-route']]]));
auditCheck($unknown['score'] === null && !$unknown['complete'], 'An unverified route must not pretend to be either a broken link or a verified success.');
$duplicate = $engine->evaluate($post, array_replace($context, ['duplicateTitle' => [['title' => $post['title']]]]));
auditCheck($duplicate['score'] < 100, 'A known duplicate must be reported.');
$custom = new CciBlogPublicationAudit([
    ['module' => 'module_z', 'priority' => 10, 'rules' => [['id' => 'title_present', 'weight' => 90, 'revision' => '2']]],
    ['module' => 'module_a', 'priority' => 20, 'rules' => [
        ['id' => 'title_present', 'weight' => 0],
        ['id' => 'module_a.review', 'label' => 'Manual requirement', 'group' => 'custom', 'weight' => 10, 'revision' => '1', 'evaluate' => static fn (array $draft, array $facts, array $scope): array => ['status' => 'bad', 'message' => 'A real store-specific requirement is missing.']],
    ]],
]);
$customResult = $custom->evaluate($post, $context);
auditCheck($customResult['customized'] && $customResult['score'] < 100, 'Contributed rules must be evaluated by the same scorer.');
$titleCheck = array_values(array_filter($customResult['checks'], static fn (array $check): bool => $check['id'] === 'title_present'))[0];
auditCheck($titleCheck['source'] === 'module_a' && $titleCheck['weight'] === 0, 'Higher priority overrides must win independently of registration order.');
auditCheck($custom->fingerprint() !== $engine->fingerprint(), 'Rule policy changes must invalidate the rules fingerprint.');
$throwing = new CciBlogPublicationAudit([['module' => 'test', 'rules' => [['id' => 'test.failure', 'weight' => 5, 'evaluate' => static function (): array { throw new RuntimeException('private stack details'); }]]]]);
$failed = $throwing->evaluate($post, $context);
auditCheck($failed['score'] === null && !$failed['complete'], 'Failed scored rules must never produce a fictitious complete result.');
auditCheck(!str_contains(json_encode($failed), 'private stack details'), 'Exception internals must not be exposed.');
$invalid = new CciBlogPublicationAudit([['module' => 'invalid', 'rules' => [['id' => 'not_namespaced', 'evaluate' => static fn (): array => ['status' => 'good']]]]]);
auditCheck($invalid->evaluate($post, $context)['score'] === null, 'Invalid configured rules must be visible as an incomplete audit.');
$rich = $engine->evaluate(['title' => 'Links', 'intro' => 'Description', 'blocks' => [['type' => 'paragraph', 'html' => '<h2 id="details">Details</h2><p><a href="#details">Section details</a></p><img alt="" src="/decorative.png"><script>hidden words</script>']]], $context);
auditCheck($rich['metrics']['wordCount'] === 3 && $rich['metrics']['images'] === 0, 'Analyze the same sanitized rich text as the storefront; removed image/script markup is not visible content.');
$imageCheck = array_values(array_filter($rich['checks'], static fn (array $check): bool => $check['id'] === 'image_descriptions'))[0];
auditCheck($imageCheck['status'] === 'not_applicable', 'An image removed by the storefront sanitizer must not become an audited image.');
$deep = ['type' => 'paragraph', 'content' => 'Text'];
for ($depth = 0; $depth < 15; ++$depth) {
    $deep = ['type' => 'columns', 'columns' => [['blocks' => [$deep]]]];
}
auditCheck($engine->evaluate($post + ['unused' => true], $context)['complete'], 'Normal inputs should complete.');
auditCheck($engine->evaluate(array_replace($post, ['blocks' => [$deep]]), $context)['score'] === null, 'Exceeding analysis limits must not silently score a partial draft.');
echo "OK: canonical publication scoring, Polish text, optional guidance, overrides, failures and analysis limits.\n";

$summaryPost = array_replace($post, ['blocks' => [['type' => 'summary', 'title' => 'W skrócie', 'items' => ['<b>Jeden wniosek</b>', 'Drugi wniosek']]]]);
$summaryFacts = CciBlogPublicationAudit::collectFacts($summaryPost);
auditCheck($summaryFacts['wordCount'] === 6 && !str_contains($summaryFacts['text'], '<b>'), 'Summary normalization and audit facts must share plain text.');
$headingFacts = CciBlogPublicationAudit::collectFacts(array_replace($post, ['blocks' => [['type' => 'heading', 'level' => 1, 'text' => 'Section', 'html' => '<h2>Section</h2>']]]));
auditCheck(count($headingFacts['headings']) === 1 && $headingFacts['headings'][0]['level'] === 2, 'Heading normalization must match the storefront and must not nest heading wrappers.');
$comparison = $engine->evaluate(array_replace($post, ['blocks' => [['type' => 'product_comparison', 'ids' => [1], 'fields' => []]]]), $context);
$comparisonCheck = array_values(array_filter($comparison['checks'], static fn (array $check): bool => $check['id'] === 'comparison_configuration'))[0];
auditCheck($comparisonCheck['status'] === 'warning' && !$comparisonCheck['scored'], 'Unsupported comparison sizes must be explicit advice.');
$badMetadata = new CciBlogPublicationAudit([['module' => 'invalid', 'rules' => [['id' => 'title_present', 'label' => []]]]]);
auditCheck($badMetadata->evaluate($post, $context)['score'] === null, 'Invalid descriptor types must not silently pass.');
$badResult = new CciBlogPublicationAudit([['module' => 'test', 'rules' => [['id' => 'test.shape', 'weight' => 5, 'evaluate' => static fn (): array => ['status' => 'good', 'message' => 'Test', 'details' => [['nested' => new stdClass()]]]]]]]);
auditCheck($badResult->evaluate($post, $context)['score'] === null, 'Non-serializable custom details must become an unavailable result.');
$rules = array_map(static fn (array $rule): array => ['id' => $rule['id'], 'enabled' => false], array_values(CciBlogPublicationAudit::defaultRules()));
$disabled = new CciBlogPublicationAudit([['module' => 'test', 'rules' => $rules]]);
auditCheck($disabled->evaluate($post, $context)['score'] === null, 'No applicable checks cannot imply a perfect score.');
$weights = new CciBlogPublicationAudit([['module' => 'test', 'rules' => array_merge($rules, [
    ['id' => 'test.pass', 'weight' => 20, 'evaluate' => static fn (): array => ['status' => 'good', 'message' => 'Pass']],
    ['id' => 'test.review', 'weight' => 10, 'evaluate' => static fn (): array => ['status' => 'warning', 'message' => 'Review']],
])]]);
auditCheck($weights->evaluate($post, $context)['score'] === 83, 'The documented weighted calculation must remain canonical.');

$labelPolicy = static fn (string $label, string $revision): CciBlogPublicationAudit => new CciBlogPublicationAudit([['module' => 'test', 'rules' => [['id' => 'title_present', 'label' => $label, 'revision' => $revision]]]]);
auditCheck($labelPolicy('Title', '1')->fingerprint() === $labelPolicy('Tytuł', '1')->fingerprint(), 'Display translations must not invalidate the scoring policy.');
auditCheck($labelPolicy('Title', '1')->fingerprint() !== $labelPolicy('Title', '2')->fingerprint(), 'Changing a callback revision must invalidate the scoring policy.');
auditCheck($invalid->fingerprint() !== $engine->fingerprint(), 'An invalid integration must not retain the successful default policy fingerprint.');

$tocFacts = CciBlogPublicationAudit::collectFacts(array_replace($post, ['blocks' => [['type' => 'heading', 'text' => 'Only section']]]), ['tocEnabled' => true, 'tocMinHeadings' => 2]);
auditCheck(!in_array('cci-blog-reading-navigation', $tocFacts['ids'], true), 'A hidden table of contents must not create a fictitious anchor.');

$video = $engine->evaluate(array_replace($post, ['blocks' => [['type' => 'video', 'src' => 'https://example.com/guide.mp4', 'caption' => 'Practical guide demonstration']]]), $context);
auditCheck($video['metrics']['wordCount'] === 3 && $video['score'] === 100, 'Visible video captions must count as editorial content.');
$videoOnly = $engine->evaluate(array_replace($post, ['blocks' => [['type' => 'video', 'src' => 'https://example.com/guide.mp4']]]), $context);
auditCheck($videoOnly['score'] === 100, 'A valid video must not be falsely reported as an empty article.');
$linkedImage = CciBlogPublicationAudit::collectFacts(['blocks' => [['type' => 'image', 'src' => '/photo.jpg', 'alt' => 'Example', 'caption' => 'Read the guide', 'caption_link' => ['href' => '#missing-caption-target']]]]);
auditCheck(count($linkedImage['links']) === 1 && $linkedImage['links'][0]['href'] === '#missing-caption-target', 'Caption destinations must be included in link checks.');
$anchor = CciBlogPublicationAudit::collectFacts(['blocks' => [['type' => 'link', 'href' => 'https://example.com', 'label' => 'Details', 'id' => 'product-details']]]);
auditCheck(in_array('product-details', $anchor['ids'], true), 'Advanced link IDs must be recognized as real section destinations.');
$decorative = $engine->evaluate(array_replace($post, ['blocks' => [['type' => 'image', 'src' => '/decoration.jpg', 'alt' => '', 'decorative' => true]]]), $context);
$imageCheck = array_values(array_filter($decorative['checks'], static fn (array $check): bool => $check['id'] === 'image_descriptions'))[0];
auditCheck($imageCheck['status'] === 'good', 'An explicitly decorative structured image must remain decorative.');
foreach ([INF, NAN, "\xB1\x31"] as $invalidValue) {
    $badJson = new CciBlogPublicationAudit([['module' => 'test', 'rules' => [['id' => 'test.json', 'weight' => 5, 'evaluate' => static fn (): array => ['status' => 'good', 'message' => 'Custom check', 'details' => [['value' => $invalidValue]]]]]]]);
    $result = $badJson->evaluate($post, $context);
    auditCheck($result['score'] === null && !$result['complete'] && json_encode($result, JSON_THROW_ON_ERROR) !== '', 'Invalid JSON values must fail only their own scored rule.');
}
$missingFields = $engine->evaluate(array_replace($post, ['blocks' => [['type' => 'product_comparison', 'ids' => [1, 2], 'fields' => ['feature:999']]]]), $context + ['comparisonResults' => [['availableProducts' => 2, 'rows' => 0, 'unavailableFields' => ['feature:999']]]]);
$comparisonCheck = array_values(array_filter($missingFields['checks'], static fn (array $check): bool => $check['id'] === 'comparison_configuration'))[0];
auditCheck($comparisonCheck['status'] === 'warning', 'A deleted comparison field must not receive a successful configuration check.');
