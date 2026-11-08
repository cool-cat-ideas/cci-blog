<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require '/var/www/html/config/config.inc.php';
$module = Module::getInstanceByName('cci_blog');
$context = Context::getContext();
$shopId = (int) $context->shop->id;
$langId = (int) $context->language->id;
$db = Db::getInstance();
$assert = static function (bool $valid, string $message): void { if (!$valid) { throw new RuntimeException($message); } };
$dates = static fn (): array => Db::getInstance()->executeS('SELECT id_post,date_add,date_upd,date_published FROM ' . _DB_PREFIX_ . 'cci_blog_post ORDER BY id_post');
$beforeDates = $dates();
CciBlogPublicationAuditStorage::ensureSchema();
$assert($dates() === $beforeDates, 'Snapshot schema migration must not change article dates.');
$row = $db->getRow('SELECT p.id_category,p.id_author,p.allow_comments,pl.* FROM ' . _DB_PREFIX_ . 'cci_blog_post_lang pl INNER JOIN ' . _DB_PREFIX_ . 'cci_blog_post p ON p.id_post=pl.id_post WHERE pl.id_shop=' . $shopId . ' AND pl.id_lang=' . $langId . ' ORDER BY pl.id_post');
if (!$row) {
    throw new RuntimeException('The native fixture needs an existing article.');
}
$id = (int) $row['id_post'];
$counter = 0;
$engine = new CciBlogPublicationAudit([['module' => 'snapshot_probe', 'rules' => [['id' => 'snapshot_probe.evaluations', 'weight' => 0, 'evaluate' => static function () use (&$counter): array {
    ++$counter;

    return ['status' => 'info', 'message' => 'Probe'];
}]]]]);
$service = new CciBlogPublicationAuditService($context);
(new ReflectionProperty($service, 'engines'))->setValue($service, [$langId => $engine]);
$row['blocks'] = json_decode((string) $row['content_blocks'], true) ?: [];
$report = $service->audit($row, $langId);
$assert($counter === 1, 'An explicit check must run the canonical evaluator once.');
$db->execute('START TRANSACTION');

try {
    $beforeOtherRows = $db->executeS('SELECT * FROM ' . _DB_PREFIX_ . 'cci_blog_post_lang WHERE NOT(id_post=' . $id . ' AND id_shop=' . $shopId . ' AND id_lang=' . $langId . ') ORDER BY id_post,id_shop,id_lang');
    $summary = CciBlogPublicationAuditStorage::store('post', $id, $shopId, $langId, $report);
    $saved = $db->getRow('SELECT seo_score,publication_audit_summary FROM ' . _DB_PREFIX_ . 'cci_blog_post_lang WHERE id_post=' . $id . ' AND id_shop=' . $shopId . ' AND id_lang=' . $langId);
    $read = $service->storedResult($saved, $langId);
    $assert(!$read['stale'] && $read['score'] === $report['score'], 'Read the same saved value used for SQL sorting.');
    require_once _PS_MODULE_DIR_ . 'cci_blog/controllers/admin/AdminCciBlogConfigurationController.php';
    $controllerClass = new ReflectionClass(AdminCciBlogConfigurationController::class);
    $controller = $controllerClass->newInstanceWithoutConstructor();
    $controllerClass->getProperty('context')->setValue($controller, $context);
    $controllerClass->getProperty('module')->setValue($controller, $module);
    $controllerClass->getProperty('publicationAuditService')->setValue($controller, $service);
    $list = [['id_post' => $id, 'id_lang' => $langId] + $saved];
    $controllerClass->getMethod('hydrateAdminPostTableRows')->invokeArgs($controller, [&$list]);
    $assert($counter === 1 && $list[0]['publication_audit']['score'] === $report['score'], 'A real admin list hydration must not invoke any audit evaluator.');
    $controllerClass->getMethod('getAdminCategories')->invoke($controller, $langId);
    $assert($counter === 1, 'A real category list must not invoke any audit evaluator.');
    $module->hookActionObjectUpdateAfter(['object' => new Product()]);
    $stale = $service->storedResult($saved, $langId);
    $assert($stale['stale'] && $stale['score'] === $report['score'] && $counter === 1, 'Catalogue invalidation must retain the saved result and must not run a check.');
    $report['score'] = null;
    $report['overallStatus'] = 'info';
    $report['complete'] = false;
    CciBlogPublicationAuditStorage::store('post', $id, $shopId, $langId, $report);
    $unavailable = $db->getRow('SELECT seo_score FROM ' . _DB_PREFIX_ . 'cci_blog_post_lang WHERE id_post=' . $id . ' AND id_shop=' . $shopId . ' AND id_lang=' . $langId);
    $assert($unavailable['seo_score'] === null, 'Unavailable must be SQL NULL, not a misleading zero.');
    $afterOtherRows = $db->executeS('SELECT * FROM ' . _DB_PREFIX_ . 'cci_blog_post_lang WHERE NOT(id_post=' . $id . ' AND id_shop=' . $shopId . ' AND id_lang=' . $langId . ') ORDER BY id_post,id_shop,id_lang');
    $assert($beforeOtherRows === $afterOtherRows, 'Saving a check must not modify other shop/language rows.');
    $assert($dates() === $beforeDates, 'An explicit check must not change article dates.');
} finally {
    $db->execute('ROLLBACK');
}
echo "OK: native saved results, zero list evaluations, catalogue invalidation, nullable scores, shop/language scope and unchanged dates.\n";
