<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$options = getopt('', ['bootstrap:', 'shop:']);
if (empty($options['bootstrap']) || empty($options['shop'])) {
    fwrite(STDERR, "Usage: php rebuild-publication-scores.php --bootstrap=/shop/config/config.inc.php --shop=1\n");
    exit(2);
}
require (string) $options['bootstrap'];
$shopId = (int) $options['shop'];
$shop = new Shop($shopId);
if ($shopId <= 0 || !Validate::isLoadedObject($shop)) {
    throw new InvalidArgumentException('Select an existing shop.');
}
Shop::setContext(Shop::CONTEXT_SHOP, $shopId);
$context = Context::getContext();
$context->shop = $shop;
$context->language = new Language((int) Configuration::get('PS_LANG_DEFAULT', null, null, $shopId));
$module = Module::getInstanceByName('cci_blog');
$module->ensureIntegrationHooks();
$service = new CciBlogPublicationAuditService($context);
$count = $service->refreshStoredScores();
echo json_encode(['shop' => $shopId, 'updatedLanguageRows' => $count], JSON_THROW_ON_ERROR) . "\n";
