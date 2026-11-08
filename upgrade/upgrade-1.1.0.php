<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/** Upgrade saved publication results without rewriting or checking articles. */
function upgrade_module_1_1_0(Module $module): bool
{
    require_once dirname(__DIR__) . '/classes/CciBlogPublicationAuditStorage.php';
    require_once dirname(__DIR__) . '/classes/CciBlogPostHistory.php';

    CciBlogPublicationAuditStorage::ensureSchema();
    CciBlogPostHistory::ensureSchema();
    foreach (['post', 'category'] as $entity) {
        $columns = array_column(Db::getInstance()->executeS(
            'SHOW COLUMNS FROM `' . _DB_PREFIX_ . 'cci_blog_' . $entity . '_lang`'
        ) ?: [], null, 'Field');
        if (!isset($columns['publication_audit_summary'], $columns['seo_score'])
            || $columns['seo_score']['Null'] !== 'YES') {
            return false;
        }
    }

    $module->ensureIntegrationHooks();
    foreach (['actionObjectAddAfter', 'actionObjectUpdateAfter', 'actionObjectDeleteAfter',
        'displayCciBlogArticleSidebar', 'registerGDPRConsent'] as $hook) {
        if (!$module->registerHook($hook)) {
            return false;
        }
    }

    return true;
}
