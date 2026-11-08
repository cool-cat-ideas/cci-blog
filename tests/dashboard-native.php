<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require '/var/www/html/config/config.inc.php';
require_once _PS_MODULE_DIR_ . 'cci_blog/controllers/admin/AdminCciBlogConfigurationController.php';
$context = Context::getContext();
$module = Module::getInstanceByName('cci_blog');
$type = new ReflectionClass(AdminCciBlogConfigurationController::class);
$controller = $type->newInstanceWithoutConstructor();
$type->getProperty('context')->setValue($controller, $context);
$type->getProperty('module')->setValue($controller, $module);
$stats = static fn (): array => $type->getMethod('getStats')->invoke($controller);
$lang = $type->getMethod('getDefaultShopLanguageId')->invoke($controller);
$shop = (int) $context->shop->id;
$db = Db::getInstance();
$before = $stats();
$assert = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
};
$now = new DateTimeImmutable('now', new DateTimeZone((string) (Configuration::get('PS_TIMEZONE') ?: date_default_timezone_get())));
$create = static function (int $shopId, int $langId, bool $active) use ($db, $now): int {
    $db->insert('cci_blog_post', ['id_category' => 1, 'active' => (int) $active, 'date_add' => $now->format('Y-m-d H:i:s'), 'date_upd' => $now->modify('+1 minute')->format('Y-m-d H:i:s'), 'date_published' => $now->modify('+1 day')->format('Y-m-d H:i:s')]);
    $id = (int) $db->Insert_ID();
    $db->insert('cci_blog_post_shop', ['id_post' => $id, 'id_shop' => $shopId]);
    $db->insert('cci_blog_post_lang', ['id_post' => $id, 'id_shop' => $shopId, 'id_lang' => $langId, 'title' => 'Dashboard scope fixture', 'slug' => 'dashboard-scope-' . bin2hex(random_bytes(6))]);
    return $id;
};
$db->execute('START TRANSACTION');
try {
    $scheduled = $create($shop, $lang, true);
    $draft = $create($shop, $lang, false);
    $foreignShop = $create(999999, $lang, true);
    $foreignLanguage = $create($shop, 999999, true);
    $after = $stats();
    $assert($after['posts'] === $before['posts'] + 2, 'Dashboard totals must remain scoped to the selected shop and default language.');
    $assert($after['scheduledPosts'] === $before['scheduledPosts'] + 1, 'Count scheduled active posts using the shop clock.');
    $assert($after['draftPosts'] === $before['draftPosts'] + 1, 'Count inactive posts as drafts.');
    $recent = array_map('intval', array_column($after['recentPosts'], 'id_post'));
    $upcoming = array_map('intval', array_column($after['upcomingPosts'], 'id_post'));
    $assert(in_array($scheduled, $recent, true) && in_array($draft, $recent, true), 'Recent work must include real scheduled posts and drafts.');
    $assert(!in_array($foreignShop, $recent, true) && !in_array($foreignLanguage, $recent, true), 'Recent work must not expose another shop or language.');
    $assert(in_array($scheduled, $upcoming, true) && !in_array($draft, $upcoming, true), 'The publication plan must include only scheduled active posts.');
    $assert(count($recent) <= 5 && count($upcoming) <= 5, 'Overview lists must be bounded.');
} finally {
    $db->execute('ROLLBACK');
}
echo "OK: native dashboard totals, recent work and publication plan preserve shop/language scope.\n";
