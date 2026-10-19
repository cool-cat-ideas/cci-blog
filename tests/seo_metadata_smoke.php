<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/config.inc.php';
require_once __DIR__ . '/../classes/CciBlogSeo.php';

$html = CciBlogSeo::ogTags([
    'title' => 'Article title',
    'meta_title' => 'Search & social title',
    'intro' => 'Article intro',
    'meta_description' => 'Description with "quotes"',
    'og_image' => 'https://example.com/image.jpg?size=large&crop=1',
], 'https://example.com/blog/article');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }

    fwrite(STDOUT, "PASS: {$message}\n");
};

$assert(!str_contains($html, 'property="og:title"'), 'theme-owned OG title is not duplicated');
$assert(!str_contains($html, 'property="og:description"'), 'theme-owned OG description is not duplicated');
$assert(!str_contains($html, 'property="og:url"'), 'theme-owned OG URL is not duplicated');
$assert(str_contains($html, 'property="og:image"'), 'article image metadata is rendered');
$assert(str_contains($html, 'twitter:title'), 'Twitter title metadata is rendered');
$assert(str_contains($html, 'Search &amp; social title'), 'social title is escaped');
$assert(str_contains($html, '&quot;quotes&quot;'), 'social description is escaped');
$assert(str_contains($html, 'size=large&amp;crop=1'), 'social image URL is escaped');

fwrite(STDOUT, "CCI Blog SEO metadata smoke test passed.\n");
