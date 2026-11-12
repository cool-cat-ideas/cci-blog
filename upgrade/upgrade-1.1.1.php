<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/** Add reader feedback without rewriting articles or existing comments. */
function upgrade_module_1_1_1(Module $module): bool
{
    require_once dirname(__DIR__) . '/classes/CciBlogReaderFeedback.php';
    if (Configuration::get('CCB_READER_FEEDBACK_ENABLED') === false) {
        Configuration::updateValue('CCB_READER_FEEDBACK_ENABLED', 1);
    }
    $pro = Module::isEnabled('cci_blog_pro') ? Module::getInstanceByName('cci_blog_pro') : null;

    return CciBlogReaderFeedback::install() && (!$pro || !method_exists($pro, 'ensureCciReaderEngagementSchema') || $pro->ensureCciReaderEngagementSchema());
}
