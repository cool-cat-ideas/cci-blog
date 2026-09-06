<?php
/**
 * CCI Blog admin shell exposed by the visible CCI menu tab.
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/AdminCciBlogConfigurationController.php';

class AdminCciBlogController extends AdminCciBlogConfigurationController
{
}
