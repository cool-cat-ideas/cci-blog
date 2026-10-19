<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$visibleController = (string) file_get_contents($root . '/controllers/admin/AdminCciBlogController.php');
$module = (string) file_get_contents($root . '/cci_blog.php');
$configurationController = (string) file_get_contents($root . '/controllers/admin/AdminCciBlogConfigurationController.php');

assertContains('extends AdminCciBlogConfigurationController', $visibleController, 'visible controller renders the Blog admin shell');
assertNotContains('redirectAdmin(', $visibleController, 'visible controller does not leave the CCI menu branch');
assertContains("getAdminLink('AdminCciBlog')", $module, 'module configuration opens the visible controller');
assertContains("getAdminLink('AdminCciBlog')", $configurationController, 'dashboard link keeps the visible controller active');
assertContains("controller_name === 'AdminCciBlogConfiguration'", $configurationController, 'legacy hidden route redirects to the visible controller');

echo "CCI Blog admin menu context smoke test passed.\n";

function assertContains(string $needle, string $haystack, string $label): void
{
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
}

function assertNotContains(string $needle, string $haystack, string $label): void
{
    if (strpos($haystack, $needle) !== false) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
}
