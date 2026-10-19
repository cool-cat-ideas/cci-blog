<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_0_1(Module $module): bool
{
    $db = Db::getInstance();
    $table = _DB_PREFIX_ . 'cci_blog_slug_redirect';
    $columnExists = static function (string $column) use ($db, $table): bool {
        return (bool) $db->getValue(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS'
            . ' WHERE TABLE_SCHEMA = DATABASE()'
            . ' AND TABLE_NAME = "' . pSQL($table) . '"'
            . ' AND COLUMN_NAME = "' . pSQL($column) . '"'
        );
    };

    if (!$columnExists('entity_type')
        && !$db->execute('ALTER TABLE `' . bqSQL($table) . '` ADD `entity_type` VARCHAR(16) NOT NULL DEFAULT "post" AFTER `id_redirect`')) {
        return false;
    }
    if (!$columnExists('id_category')
        && !$db->execute('ALTER TABLE `' . bqSQL($table) . '` ADD `id_category` INT UNSIGNED NULL AFTER `id_post`')) {
        return false;
    }
    if (!$db->execute('ALTER TABLE `' . bqSQL($table) . '` MODIFY `id_post` INT UNSIGNED NULL')) {
        return false;
    }

    $db->execute('UPDATE `' . bqSQL($table) . '` SET `entity_type` = "post" WHERE `entity_type` = "" OR `entity_type` IS NULL');
    $module->registerHook('registerGDPRConsent');

    return true;
}
