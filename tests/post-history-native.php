<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require '/var/www/html/config/config.inc.php';
require_once _PS_MODULE_DIR_ . 'cci_blog/controllers/admin/AdminCciBlogConfigurationController.php';
$context = Context::getContext();
$originalEmployee = $context->employee;
$module = Module::getInstanceByName('cci_blog');
$type = new ReflectionClass(AdminCciBlogConfigurationController::class);
$controller = $type->newInstanceWithoutConstructor();
$type->getProperty('context')->setValue($controller, $context);
$type->getProperty('module')->setValue($controller, $module);
foreach (['ensureBlockSchema', 'ensurePostCategorySchema', 'ensureCategoryAuthorSchema'] as $method) {
    $type->getMethod($method)->invoke($controller);
}
CciBlogPostHistory::ensureSchema();
CciBlogPublicationAuditStorage::ensureSchema();
$db = Db::getInstance();
$lang = (int) $type->getMethod('getDefaultShopLanguageId')->invoke($controller);
$shop = (int) $context->shop->id;
$assert = static function (bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); };
$invoke = static fn (string $method, ...$args): array => $type->getMethod($method)->invoke($controller, ...$args);
$employee = static function (string $lastname) use ($lang): Employee {
    $employee = new Employee();
    $employee->firstname = 'History';
    $employee->lastname = $lastname;
    $employee->email = 'cci-history-' . bin2hex(random_bytes(6)) . '@example.invalid';
    $employee->passwd = password_hash(bin2hex(random_bytes(20)), PASSWORD_BCRYPT);
    $employee->id_profile = 1;
    $employee->id_lang = $lang;
    $employee->active = 1;
    if (!$employee->add()) throw new RuntimeException('Could not create a transactional history fixture.');
    return $employee;
};
$dates = static fn (): array => $db->executeS('SELECT id_post,date_add,date_upd,date_published FROM `' . _DB_PREFIX_ . 'cci_blog_post` ORDER BY id_post');
$beforeDates = $dates();
$assert(CciBlogPostHistory::snapshot(['category_ids' => [8, 3, 5]])['category_ids'] === [8, 3, 5], 'A full snapshot must preserve editorial category ordering.');
$db->execute('START TRANSACTION');
try {
    $editor = $employee('Editor');
    $author = $employee('Author');
    $context->employee = $editor;
    $payload = ['id_post' => 0, 'id_lang' => $lang, 'id_author' => (int) $author->id, 'title' => 'History native fixture ' . bin2hex(random_bytes(6)), 'active' => 0, 'blocks' => [['type' => 'paragraph', 'content' => 'Original article text.']]];
    $created = $invoke('savePost', $payload);
    $assert($created['success'] === true && $db->getLink()->inTransaction(), 'A nested save must preserve the surrounding transaction.');
    $postId = (int) $created['post']['id_post'];
    $rows = CciBlogPostHistory::page($postId, $shop, $lang)['revisions'];
    $assert(count($rows) === 1 && (int) $rows[0]['id_employee'] === (int) $editor->id && $rows[0]['employee_name'] === 'History Editor', 'Record the authenticated editor separately from the selected author.');
    $firstId = (int) $rows[0]['id_revision'];
    $first = CciBlogPostHistory::revision($firstId, $postId, $shop, $lang);
    $assert((int) $first['snapshot']['id_author'] === (int) $author->id && !isset($first['snapshot']['views'], $first['snapshot']['publication_audit']), 'Snapshots retain editorial data without dynamic views or audit results.');
    $context->employee = $author;
    $updatedPayload = $created['post'];
    $updatedPayload['title'] .= ' edited';
    $updatedPayload['blocks'][0]['content'] = 'Revised article text.';
    $updatedPayload['id_employee'] = (int) $editor->id;
    $updatedPayload['employee_name'] = 'Forged editor';
    $updated = $invoke('savePost', $updatedPayload);
    $rows = CciBlogPostHistory::page($postId, $shop, $lang)['revisions'];
    $assert(count($rows) === 2 && $rows[0]['employee_name'] === 'History Author' && in_array('title', $rows[0]['changed_fields'], true) && in_array('blocks', $rows[0]['changed_fields'], true), 'Later saves record real changed fields and ignore forged editor metadata.');
    $assert(CciBlogPostHistory::revision($firstId, $postId, $shop, $lang)['snapshot'] === $first['snapshot'], 'Previous versions are immutable.');
    $assert(!array_key_exists('snapshot', $rows[0]), 'Version lists must not download full article snapshots.');
    $invalid = $invoke('savePost', ['id_post' => $postId, 'id_lang' => $lang, 'title' => '']);
    $assert($invalid['success'] === false && count(CciBlogPostHistory::page($postId, $shop, $lang)['revisions']) === 2, 'Rejected saves must not create a version.');
    $assert(CciBlogPostHistory::revision($firstId, $postId, $shop + 999999, $lang) === null && CciBlogPostHistory::revision($firstId, $postId, $shop, $lang + 999999) === null && CciBlogPostHistory::revision($firstId, $postId + 999999, $shop, $lang) === null, 'A version cannot be read through another article, shop or language.');
    $stats = $invoke('getStats');
    $recent = array_values(array_filter($stats['recentPosts'], static fn (array $row): bool => (int) $row['id_post'] === $postId));
    $assert(count($recent) === 1 && $recent[0]['modified_by_name'] === 'History Author', 'The Dashboard reads the last tracked editor from saved versions.');
    // Model an article that predates tracking, without changing any real article.
    $legacy = $created['post'];
    $db->delete('cci_blog_post_revision', 'id_post = ' . $postId);
    $legacy['title'] = 'Legacy article with unknown editor';
    $savedLegacy = $invoke('savePost', $legacy);
    $rows = CciBlogPostHistory::page($postId, $shop, $lang)['revisions'];
    $assert(count($rows) === 2 && $rows[1]['event_type'] === 'baseline' && (int) $rows[1]['id_employee'] === 0, 'The first tracked edit preserves a baseline without inventing its editor.');
    $baseline = CciBlogPostHistory::revision((int) $rows[1]['id_revision'], $postId, $shop, $lang);
    $assert($baseline['snapshot']['title'] === $updatedPayload['title'], 'The baseline contains the actual previous article, not the incoming draft.');
    for ($i = 0; $i < 22; ++$i) CciBlogPostHistory::record($savedLegacy['post'], $savedLegacy['post'], $shop, $lang, $author);
    $page = CciBlogPostHistory::page($postId, $shop, $lang);
    $older = CciBlogPostHistory::page($postId, $shop, $lang, $page['next_before_id']);
    $assert(count($page['revisions']) === 20 && count($older['revisions']) === 4 && !array_intersect(array_column($page['revisions'], 'id_revision'), array_column($older['revisions'], 'id_revision')), 'Cursor pagination must bound responses without skipping or repeating versions.');
    $deleted = $invoke('deletePost', ['id_post' => $postId]);
    $assert($deleted['success'] === true && CciBlogPostHistory::page($postId, $shop, $lang)['revisions'] === [], 'Deleting an article removes its scoped versions too.');
} finally {
    $db->execute('ROLLBACK');
    $context->employee = $originalEmployee;
}
$assert($dates() === $beforeDates, 'History verification must preserve installed articles and dates.');
echo "OK: authenticated editors, immutable versions, baselines, failed saves, scope isolation, pagination, deletion and transaction rollback.\n";
