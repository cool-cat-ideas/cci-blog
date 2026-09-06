<?php

declare(strict_types=1);

define('_PS_VERSION_', '9.0.0');

if (!class_exists('Tools')) {
    final class Tools
    {
        public static function str2url(string $value): string
        {
            $value = strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value));

            return trim((string) preg_replace('/[^a-z0-9]+/', '-', $value), '-');
        }
    }
}

require_once dirname(__DIR__) . '/classes/CciBlogTableOfContents.php';

if (!class_exists('DOMDocument') || !class_exists('DOMXPath')) {
    fwrite(STDOUT, "SKIP: PHP DOM extension is required for the table-of-contents smoke test.\n");
    exit(0);
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$result = CciBlogTableOfContents::build(
    '<p>Intro</p><h2>Plan wdrożenia</h2><h3>Przygotowanie</h3><h4>Backup</h4><h2>Plan wdrożenia</h2>',
    2
);

$assert(count($result['items']) === 2, 'H2 headings must create two root items.');
$assert(count($result['items'][0]['children']) === 1, 'H3 must be nested under the preceding H2.');
$assert(count($result['items'][0]['children'][0]['children']) === 1, 'H4 must be nested under H3.');
$assert(str_contains($result['content'], 'id="section-plan-wdrozenia"'), 'The first heading must receive a stable slug ID.');
$assert(str_contains($result['content'], 'id="section-plan-wdrozenia-2"'), 'Duplicate heading IDs must be unique.');

$single = CciBlogTableOfContents::build('<h2>Only section</h2>', 2);
$assert($single['items'] === [], 'The minimum heading threshold must suppress a short table of contents.');
$assert($single['content'] === '<h2>Only section</h2>', 'Suppressed output must preserve the original HTML.');

$template = (string) file_get_contents(dirname(__DIR__) . '/views/templates/front/post.tpl');
$assert(
    str_contains($template, "{l s='In this article' mod='cci_blog'}"),
    'The storefront table of contents must use the "In this article" label.'
);

$stylesheet = (string) file_get_contents(dirname(__DIR__) . '/views/css/cci_blog_front.css');
$linkRuleMatches = [];
$assert(
    preg_match('/\.cci-blog-table-of-contents-link\s*\{([^}]*)\}/s', $stylesheet, $linkRuleMatches) === 1,
    'The storefront stylesheet must contain the table-of-contents link layout rule.'
);
$linkRule = (string) ($linkRuleMatches[1] ?? '');
$assert(!str_contains($linkRule, 'color:'), 'Table-of-contents links must inherit theme colors.');
$assert(!str_contains($linkRule, 'text-decoration:'), 'Table-of-contents links must inherit theme decoration.');
$assert(!str_contains($linkRule, 'background:'), 'Table-of-contents links must inherit theme backgrounds.');

fwrite(STDOUT, "OK: table of contents follows the H2-H6 hierarchy and generates unique anchors.\n");
