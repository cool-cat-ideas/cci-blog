<?php

declare(strict_types=1);

$moduleRoot = dirname(__DIR__);
$checks = [
    'free payload disables license management' => [
        $moduleRoot . '/controllers/admin/AdminCciBlogConfigurationController.php',
        "'canManage' => false",
    ],
    'feature registry exposes Pro availability' => [
        $moduleRoot . '/classes/CciBlogFeatureRegistry.php',
        "'proModuleAvailable' => \$proInstalled",
    ],
    'feature registry gates management with Pro availability' => [
        $moduleRoot . '/classes/CciBlogFeatureRegistry.php',
        "'canManage' => \$proInstalled",
    ],
    'initial admin payload preserves backend gate' => [
        $moduleRoot . '/src/admin-v2/api.js',
        'Boolean(rawLicense.canManage && rawLicense.proModuleAvailable)',
    ],
    'license responses preserve backend gate' => [
        $moduleRoot . '/src/admin-v2/api.js',
        'Boolean(response.license.canManage && response.license.proModuleAvailable)',
    ],
    'sidebar requires an available Pro module' => [
        $moduleRoot . '/src/admin-v2/components/DashboardSidebar.jsx',
        'license?.canManage && license?.proModuleAvailable && license?.apiBase',
    ],
    'upgrade modal hides activation path without Pro' => [
        $moduleRoot . '/src/admin-v2/components/ProUpgradeModal.jsx',
        'onActivateLicense={canManageLicense ? onActivateLicense : undefined}',
    ],
];

foreach ($checks as $label => [$path, $needle]) {
    $contents = file_get_contents($path);
    if ($contents === false || strpos($contents, $needle) === false) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
}

echo "CCI Blog license management visibility smoke test passed.\n";
