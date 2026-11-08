<?php

declare(strict_types=1);

define('_PS_VERSION_', '9.1.5');
class ModuleAdminController
{
    public $context;
    public $module;
}
class Module
{
    public static bool $enabled = false;
    public static bool $allowLayout = false;
    public static array $checks = [];

    public static function isEnabled(string $name): bool
    {
        return self::$enabled;
    }

    public static function getInstanceByName(string $name): self
    {
        return new self();
    }

    public function l(string $text): string
    {
        return $text;
    }

    public function getCciTableOfContentsLayout(string $layout, int $shop, bool $forWrite): string
    {
        self::$checks[] = [$shop, $forWrite];

        return self::$allowLayout ? $layout : 'inline';
    }

    public function hookDisplayCciBlogFeatures(array $params): array
    {
        return [['key' => 'hook_blocks', 'enabled' => false]];
    }
}
class Configuration
{
    public static array $values = [];
    public static int $writes = 0;

    public static function get(string $key, ...$scope)
    {
        return self::$values[$key] ?? false;
    }

    public static function updateValue(...$arguments): bool
    {
        ++self::$writes;

        return true;
    }
}
class CciBlogPost
{
    public static function getAllowedContentHookNames(): array
    {
        return ['displayCciBlogArticleSidebar'];
    }
}
require dirname(__DIR__) . '/controllers/admin/AdminCciBlogConfigurationController.php';
$reflection = new ReflectionClass(AdminCciBlogConfigurationController::class);
$controller = $reflection->newInstanceWithoutConstructor();
$controller->context = (object) ['shop' => (object) ['id' => 7]];
$controller->module = new Module();
$save = $reflection->getMethod('saveSettings');
foreach ([
    [false, [], ['CCB_TABLE_OF_CONTENTS_LAYOUT' => 'sticky']],
    [true, ['CCB_TABLE_OF_CONTENTS_LAYOUT' => 'sticky', 'CCB_TABLE_OF_CONTENTS_STICKY_GAP' => 16], ['CCB_TABLE_OF_CONTENTS_STICKY_GAP' => 24]],
    [true, ['CCB_TABLE_OF_CONTENTS_LAYOUT' => 'sticky'], ['CCB_TABLE_OF_CONTENTS_SIDEBAR_CONTENT' => 'widgets']],
    [true, [], ['CCB_TABLE_OF_CONTENTS_SIDEBAR_CONTENT' => 'hook']],
    [false, [], ['CCB_TABLE_OF_CONTENTS_SIDEBAR_HOOK' => 'actionDeleteShop']],
] as [$enabled, $saved, $payload]) {
    Module::$enabled = $enabled;
    Configuration::$values = $saved;
    $response = $save->invoke($controller, $payload);
    if (!empty($response['success']) || Configuration::$writes !== 0) {
        throw new RuntimeException('A Pro TOC change caused an unauthorized or partial settings write.');
    }
}
if (Module::$checks !== [[7, true], [7, true], [7, true]]) {
    throw new RuntimeException('TOC writes must validate the current shop through the Pro write gate.');
}
Module::$enabled = true;
Module::$allowLayout = true;
Configuration::$values = ['CCB_TABLE_OF_CONTENTS_LAYOUT' => 'sticky', 'CCB_TABLE_OF_CONTENTS_SIDEBAR_CONTENT' => 'widgets'];
$response = $save->invoke($controller, ['CCB_TABLE_OF_CONTENTS_SIDEBAR_CONTENT' => 'hook']);
if (!empty($response['success']) || Configuration::$writes !== 0) {
    throw new RuntimeException('Layout access alone must not authorize Hook-block settings.');
}
echo "OK: full and partial TOC/sidebar changes require Pro write access; unsafe hooks cause no writes.\n";
