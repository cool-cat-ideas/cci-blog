<?php

declare(strict_types=1);

define('_PS_VERSION_', '9.1.5');
define('_DB_PREFIX_', 'ps_');

class ObjectModel
{
    public const TYPE_INT = 1;
    public const TYPE_BOOL = 2;
    public const TYPE_STRING = 3;
    public const TYPE_DATE = 4;
    public const TYPE_HTML = 5;
}

class Context
{
    public object $shop;
    public object $language;
}

class Db
{
    public static function getInstance(): self
    {
        return new self();
    }

    public function executeS(string $query): array
    {
        return [];
    }
}

class Hook
{
    /** @var string[] */
    public static array $executed = [];

    public static function exec($hookName, $params = [], $idModule = null, $arrayReturn = false)
    {
        if ($hookName === 'displayCciBlogContentHookOptions') {
            return [
                'demo_extension' => [
                    [
                        'name' => 'displayDemoExtensionBlogHook',
                        'label' => 'Demo extension hook',
                        'description' => 'Fixture hook exposed by an extension module.',
                    ],
                ],
            ];
        }

        if ($hookName === 'displayCciBlogRenderContentBlock' && ($params['type'] ?? '') === 'hook') {
            return self::exec((string) ($params['block']['hook'] ?? ''), $params);
        }

        self::$executed[] = (string) $hookName;

        return '<div data-hook="' . htmlspecialchars((string) $hookName, ENT_QUOTES, 'UTF-8') . '"></div>';
    }
}

require_once __DIR__ . '/../classes/CciBlogPost.php';
require_once __DIR__ . '/../classes/CciBlogArticleSidebar.php';

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$context = new Context();
$context->shop = (object) ['id' => 7];
$context->language = (object) ['id' => 2];

Hook::$executed = [];
$unknown = CciBlogPost::renderBlocksToHtml([
    ['type' => 'hook', 'hook' => 'displayUnknownInjectedHook'],
], $context);
assertTrue($unknown === '', 'Unknown hook should render empty output.');
assertTrue(!in_array('displayUnknownInjectedHook', Hook::$executed, true), 'Unknown hook must not be executed.');

Hook::$executed = [];
$core = CciBlogPost::renderBlocksToHtml([
    ['type' => 'hook', 'hook' => 'displayCciBlogPostMiddle'],
], $context);
assertTrue(str_contains($core, 'displayCciBlogPostMiddle'), 'Core allowed hook should render output.');
assertTrue(in_array('displayCciBlogPostMiddle', Hook::$executed, true), 'Core allowed hook should be executed.');

Hook::$executed = [];
$external = CciBlogPost::renderBlocksToHtml([
    ['type' => 'hook', 'hook' => 'displayDemoExtensionBlogHook'],
], $context);
assertTrue(str_contains($external, 'displayDemoExtensionBlogHook'), 'Extension-declared hook should render output.');
assertTrue(in_array('displayDemoExtensionBlogHook', Hook::$executed, true), 'Extension-declared hook should be executed.');

Hook::$executed = [];
$sidebar = CciBlogArticleSidebar::renderHook('displayCciBlogArticleSidebar', 10, $context);
assertTrue(str_contains($sidebar, 'displayCciBlogArticleSidebar'), 'The dedicated sidebar hook must use the allowed block renderer.');
$unknownSidebar = CciBlogArticleSidebar::renderHook('actionDeleteShop', 10, $context);
assertTrue($unknownSidebar === '' && !in_array('actionDeleteShop', Hook::$executed, true), 'Sidebar configuration must never execute an unsafe hook.');
foreach (['none', 'widgets', 'hook', 'widgets_hook', 'unknown'] as $mode) {
    $normalized = CciBlogArticleSidebar::normalizeContent($mode);
    assertTrue(CciBlogArticleSidebar::showsWidgets($normalized) === in_array($mode, ['widgets', 'widgets_hook'], true), 'Sidebar widget visibility is incorrect.');
    assertTrue(CciBlogArticleSidebar::showsHook($normalized) === in_array($mode, ['hook', 'widgets_hook'], true), 'Sidebar hook visibility is incorrect.');
}
echo "OK: article and sidebar hook allowlists and sidebar content modes passed.\n";
