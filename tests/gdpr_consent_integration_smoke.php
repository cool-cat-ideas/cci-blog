<?php

declare(strict_types=1);

$moduleRoot = dirname(__DIR__);
$modulePhp = (string) file_get_contents($moduleRoot . '/cci_blog.php');
$controller = (string) file_get_contents($moduleRoot . '/controllers/front/post.php');
$template = (string) file_get_contents($moduleRoot . '/views/templates/front/post.tpl');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$assert(str_contains($modulePhp, "'registerGDPRConsent'"), 'The module must register with consent providers.');
$assert(str_contains($controller, "Hook::exec('displayGDPRConsent'"), 'The comment form must use the standard consent hook.');
$assert(str_contains($template, '$ccb_gdpr_consent nofilter'), 'Provider HTML must be rendered by the comment form.');
$assert(!str_contains($template, 'ccb_privacy_consent'), 'The module must not render its own consent checkbox.');
$assert(!str_contains($template, 'cci-blog-consent-checkbox'), 'The module must not ship a private consent component.');
$assert(!str_contains($controller, "Tools::getValue('psgdpr_consent_checkbox'"), 'The controller must not depend on a provider field name.');
$assert(!str_contains($controller, "Tools::getValue('ccb_privacy_consent'"), 'The controller must not validate a private consent field.');

fwrite(STDOUT, "OK: GDPR consent is delegated to displayGDPRConsent providers.\n");
