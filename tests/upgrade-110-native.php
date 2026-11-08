<?php

declare(strict_types=1);

// CLI-only MariaDB migration test using isolated tables, never installed blog data.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$parameters = (require '/var/www/html/app/config/parameters.php')['parameters'];
$connection = new PDO(
    'mysql:host=' . $parameters['database_host'] . ';port=' . ($parameters['database_port'] ?: '3306')
    . ';dbname=' . $parameters['database_name'] . ';charset=utf8mb4',
    $parameters['database_user'], $parameters['database_password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
define('_PS_VERSION_', '9.2.0');
define('_DB_PREFIX_', 'cci_upgrade_' . bin2hex(random_bytes(5)) . '_');

class Db
{
    public static PDO $connection;
    public static function getInstance(): self { return new self(); }
    public function execute(string $sql): bool { return self::$connection->exec($sql) !== false; }
    public function executeS(string $sql): array { return self::$connection->query($sql)->fetchAll(PDO::FETCH_ASSOC); }
}
class Module
{
    public array $hooks = [];
    public function ensureIntegrationHooks(): void {}
    public function registerHook(string $hook): bool { $this->hooks[$hook] = true; return true; }
}
Db::$connection = $connection;
require dirname(__DIR__) . '/upgrade/upgrade-1.1.0.php';
$assert = static function (bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } };
$post = _DB_PREFIX_ . 'cci_blog_post_lang';
$category = _DB_PREFIX_ . 'cci_blog_category_lang';
$history = _DB_PREFIX_ . 'cci_blog_post_revision';
$install = file_get_contents(dirname(__DIR__) . '/sql/install.sql');
preg_match_all('/CREATE TABLE IF NOT EXISTS `PREFIX_([^`]+)`/', $install, $matches);
$freshPrefix = _DB_PREFIX_ . 'fresh_';
$freshTables = array_map(static fn (string $name): string => $freshPrefix . $name, $matches[1]);
try {
    foreach (array_filter(array_map('trim', explode(';', str_replace('PREFIX_', $freshPrefix, $install)))) as $query) {
        $connection->exec($query);
    }
    foreach (['post', 'category'] as $entity) {
        $columns = array_column($connection->query('SHOW COLUMNS FROM `' . $freshPrefix . 'cci_blog_' . $entity . '_lang`')->fetchAll(PDO::FETCH_ASSOC), null, 'Field');
        $assert(isset($columns['publication_audit_summary']) && $columns['seo_score']['Null'] === 'YES', 'A fresh install must use the same saved-result schema.');
    }
    $connection->exec('CREATE TABLE `' . $post . '` (id_post INT, id_lang INT, id_shop INT, content LONGTEXT, date_upd DATETIME, seo_score TINYINT UNSIGNED NOT NULL DEFAULT 0)');
    $connection->exec('CREATE TABLE `' . $category . '` (id_category INT, id_lang INT, id_shop INT, description TEXT)');
    $connection->exec("INSERT INTO `$post` VALUES (7,1,1,'Original article','2026-10-29 12:00:00',83),(7,2,2,'Translation','2026-10-30 13:00:00',95)");
    $connection->exec("INSERT INTO `$category` VALUES (3,1,1,'Original category')");
    $before = $connection->query('SELECT id_post,id_lang,id_shop,content,date_upd FROM `' . $post . '`')->fetchAll(PDO::FETCH_ASSOC);
    $module = new Module();
    $assert(upgrade_module_1_1_0($module), 'Upgrade must succeed from the 1.0.1 score contract.');
    $assert((int) $connection->query('SELECT COUNT(*) FROM `' . $history . '`')->fetchColumn() === 0, 'The upgrade must not invent historical versions or editors.');
    $assert((int) $connection->query('SELECT COUNT(*) FROM `' . $post . '` WHERE seo_score IS NULL AND publication_audit_summary IS NULL')->fetchColumn() === 2, 'Old scores must become unchecked for every shop and language.');
    $connection->exec("UPDATE `$post` SET seo_score=91,publication_audit_summary='saved-result' WHERE id_lang=1");
    (new ReflectionProperty(CciBlogPublicationAuditStorage::class, 'schemaReady'))->setValue(null, false);
    (new ReflectionProperty(CciBlogPostHistory::class, 'schemaReady'))->setValue(null, false);
    $assert(upgrade_module_1_1_0($module), 'Repeating the migration must succeed.');
    $assert($connection->query('SELECT publication_audit_summary FROM `' . $post . '` WHERE id_lang=1')->fetchColumn() === 'saved-result', 'A repeated upgrade must preserve saved checks.');
    $after = $connection->query('SELECT id_post,id_lang,id_shop,content,date_upd FROM `' . $post . '`')->fetchAll(PDO::FETCH_ASSOC);
    $assert($before === $after, 'Content, associations and dates must be unchanged.');
    $assert(count($module->hooks) === 5, 'The migration must register native invalidation and sidebar hooks.');
    echo "OK: real MariaDB fresh install and 1.0.1 upgrade, nullable unchecked results, idempotence and preserved content/dates.\n";
} finally {
    $connection->exec('DROP TABLE IF EXISTS `' . $post . '`, `' . $category . '`, `' . $history . '`');
    foreach ($freshTables as $table) {
        $connection->exec('DROP TABLE IF EXISTS `' . $table . '`');
    }
}
