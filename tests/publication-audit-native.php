<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require '/var/www/html/config/config.inc.php';
Module::getInstanceByName('cci_blog');
$context = Context::getContext();
$service = new CciBlogPublicationAuditService($context);
$lang = (int) $context->language->id;
$base = ['title' => 'Publication audit native fixture ' . bin2hex(random_bytes(5)), 'intro' => 'A dedicated description for this read-only audit fixture.', 'slug' => 'native-audit-fixture', 'blocks' => [['type' => 'paragraph', 'html' => '<h2 id="details">Details</h2><p>Useful content for readers.</p>']]];
$assert = static function (bool $condition, string $message): void { if (!$condition) { throw new RuntimeException($message); } };
$check = static function (array $audit, string $id): array { return array_values(array_filter($audit['checks'], static fn (array $rule): bool => $rule['id'] === $id))[0]; };
$audit = $service->audit($base, $lang);
$assert($audit['score'] === 100, 'A short useful fixture without mandatory keywords should pass the native adapter.');
$draft = $base;
$draft['blocks'][] = ['type' => 'link', 'href' => '#details', 'label' => 'Section details'];
$draft['blocks'][] = ['type' => 'product_comparison', 'ids' => [1, 2], 'fields' => ['price']];
$audit = $service->audit($draft, $lang);
$assert($check($audit, 'anchor_targets')['status'] === 'good' && $check($audit, 'product_targets')['status'] === 'good', 'Existing anchors and catalogue products must be verified through native data.');
$draft['blocks'][] = ['type' => 'link', 'href' => '#missing-section', 'label' => 'Missing section'];
$audit = $service->audit($draft, $lang);
$assert($check($audit, 'anchor_targets')['status'] === 'bad', 'A missing static anchor must be reported.');
$draft = $base;
$draft['blocks'][] = ['type' => 'product', 'id_product' => 2147483000];
$assert($check($service->audit($draft, $lang), 'product_targets')['status'] === 'bad', 'Missing product references must not pass.');
$draft = $base;
$draft['blocks'][] = ['type' => 'link', 'href' => $context->link->getProductLink(1, null, null, null, $lang, (int) $context->shop->id), 'label' => 'View the product'];
$assert($check($service->audit($draft, $lang), 'internal_targets')['status'] === 'good', 'A canonical native product URL should be locally recognized.');
$draft = $base;
$draft['blocks'][] = ['type' => 'link', 'href' => '/a-custom-route-that-is-not-verified', 'label' => 'Custom route'];
$assert($service->audit($draft, $lang)['score'] === null, 'Unknown custom routes must not imply a complete verified score.');
$draft = $base;
$draft['blocks'][] = ['type' => 'link', 'href' => 'https://example.invalid/not-fetched', 'label' => 'External documentation'];
$external = $service->audit($draft, $lang);
$assert($external['score'] === 100 && $external['metrics']['externalLinks'] === 1, 'External URLs should be counted without HTTP verification.');
$method = new ReflectionMethod($service, 'absoluteUrl');
$method->setAccessible(true);
$resolved = $method->invoke($service, '../other/guide?mode=1#section', ['siteUrl' => 'https://shop.example/sub/', 'postUrl' => 'https://shop.example/sub/blog/current']);
$assert($resolved === 'https://shop.example/sub/other/guide?mode=1#section', 'Relative links must resolve against the article directory.');
$invalid = false;

try {
    $service->audit($base, 2147483000);
} catch (InvalidArgumentException $error) {
    $invalid = true;
}
$assert($invalid, 'A language outside this shop must be rejected.');
echo "OK: native audit metadata, products, anchors, unknown routes, relative URLs and language scope.\n";

$draft = $base;
$draft['blocks'] = [['type' => 'product_comparison', 'ids' => [1, 2], 'fields' => ['feature:2147483000']]];
$assert($check($service->audit($draft, $lang), 'comparison_configuration')['status'] === 'warning', 'Deleted comparison fields must not be reported as successfully configured.');
$draft = $base;
$draft['blocks'][] = ['type' => 'image', 'src' => '/example.jpg', 'caption' => 'Read the guide', 'caption_link' => ['href' => '#missing-caption-target']];
$assert($check($service->audit($draft, $lang), 'anchor_targets')['status'] === 'bad', 'Missing destinations in image captions must be checked.');
$draft = $base;
$draft['blocks'][] = ['type' => 'link', 'href' => 'https://example.invalid', 'label' => 'Details', 'id' => 'product-details'];
$draft['blocks'][] = ['type' => 'link', 'href' => '#product-details', 'label' => 'Jump to details'];
$assert($check($service->audit($draft, $lang), 'anchor_targets')['status'] === 'good', 'Real advanced-link IDs must not be falsely reported as missing.');
