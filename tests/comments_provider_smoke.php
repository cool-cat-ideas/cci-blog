<?php

declare(strict_types=1);

$moduleRoot = dirname(__DIR__);
$modulePhp = (string) file_get_contents($moduleRoot . '/cci_blog.php');
$controller = (string) file_get_contents($moduleRoot . '/controllers/front/post.php');
$adminController = (string) file_get_contents($moduleRoot . '/controllers/admin/AdminCciBlogConfigurationController.php');
$template = (string) file_get_contents($moduleRoot . '/views/templates/front/post.tpl');
$frontJs = (string) file_get_contents($moduleRoot . '/views/js/front.js');
$settingsPanel = (string) file_get_contents($moduleRoot . '/src/admin-v2/components/BlogSettingsPanel.jsx');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$assert(str_contains($modulePhp, "'CCB_COMMENTS_PROVIDER'     => 'disqus'"), 'New installations must default to Disqus.');
$assert(str_contains($modulePhp, "'cci-blog-front'"), 'The shared storefront runtime must be registered.');
$assert(!str_contains($modulePhp, "views/js/front.js?v="), 'The storefront runtime URL must not embed a query string in the asset path.');
$assert(str_contains($controller, "=== 'native' ? 'native' : 'disqus'"), 'The storefront must normalize the comments provider.');
$assert(str_contains($controller, '$nativeCommentsEnabled'), 'Native comment queries and submissions must have an explicit provider guard.');
$assert(str_contains($template, "ccb_comments_provider == 'disqus'"), 'The template must render the Disqus branch explicitly.');
$assert(str_contains($template, 'data-disqus-shortname'), 'The template must pass the sanitized shortname to the frontend runtime.');
$assert(!str_contains($template, 'disqus.com/embed.js'), 'The Smarty template must not inject an inline Disqus loader.');
$assert(str_contains($frontJs, "script.src = 'https://' + shortname + '.disqus.com/embed.js'"), 'The frontend runtime must load the selected Disqus site.');
$assert(str_contains($adminController, 'normalizeDisqusShortname'), 'The shortname must be sanitized server-side.');
$assert(str_contains($settingsPanel, 'CCB_COMMENTS_PROVIDER'), 'The provider must be configurable in the React admin.');

fwrite(STDOUT, "OK: Disqus is the explicit default provider and native comments remain isolated.\n");
