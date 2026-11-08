<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require '/var/www/html/config/config.inc.php';
require_once _PS_ROOT_DIR_ . '/app/AppKernel.php';
require_once _PS_ROOT_DIR_ . '/app/FrontKernel.php';
$kernel = new FrontKernel('prod', false);
$kernel->boot();
$context = Context::getContext();
if ($context->shop->domain !== 'localhost:4090') {
    throw new RuntimeException('This fixture is restricted to the local PrestaShop demo.');
}
$context->container = $kernel->getContainer();
$context->cart = new Cart();
$context->cart->id = 0;
$context->customer = new Customer();
$context->customer->id = 0;
$context->country = new Country((int) Configuration::get('PS_COUNTRY_DEFAULT'));
$context->currency = new Currency((int) Configuration::get('PS_CURRENCY_DEFAULT'));
$context->currentLocale = Tools::getContextLocale($context);
Module::getInstanceByName('cci_blog');
$pro = Module::getInstanceByName('cci_blog_pro');
$license = $pro->getCciLicensePayload((int) $context->shop->id);
if (empty($license['canUse']) || empty($license['runtimeAllowed'])) {
    throw new RuntimeException('The demo needs a valid Blog Pro license.');
}
$fixture = json_decode((string) file_get_contents($argv[1] ?? ''), true, 512, JSON_THROW_ON_ERROR);
$shopId = (int) $context->shop->id;
$slug = (string) $fixture['slug'];
$existingId = (int) Db::getInstance()->getValue('SELECT id_post FROM `' . _DB_PREFIX_ . 'cci_blog_post_lang` WHERE slug="' . pSQL($slug) . '" AND id_shop=' . $shopId);
$template = Db::getInstance()->getRow('SELECT p.* FROM `' . _DB_PREFIX_ . 'cci_blog_post` p JOIN `' . _DB_PREFIX_ . 'cci_blog_post_shop` ps ON ps.id_post=p.id_post WHERE ps.id_shop=' . $shopId . ' ORDER BY p.id_post');
if (!$template) {
    throw new RuntimeException('An existing demo article is needed for its category, author and cover.');
}
$post = $existingId > 0 ? new CciBlogPost($existingId) : new CciBlogPost();
$post->id_shop_list = [$shopId];
$post->id_category = (int) $template['id_category'];
$post->id_author = (int) $template['id_author'];
$post->active = true;
$post->featured = false;
$post->allow_comments = false;
$post->views = $existingId > 0 ? (int) $post->views : 0;
$post->cover_image = $template['cover_image'];
$post->og_image = $template['og_image'];
$post->date_published = $existingId > 0 ? $post->date_published : date('Y-m-d H:i:s');
$blocksJson = json_encode($fixture['blocks'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
foreach (Language::getLanguages(false) as $language) {
    $langId = (int) $language['id_lang'];
    $context->language = new Language($langId);
    $content = CciBlogPost::renderBlocksToHtml($fixture['blocks'], $context);
    if (substr_count($content, 'class="cci-blog-product-inset ') !== 3) {
        throw new RuntimeException('All three product cards must render through the normal Pro hook.');
    }
    foreach (['title', 'slug', 'intro', 'meta_title', 'meta_description'] as $field) {
        $post->{$field}[$langId] = $fixture[$field];
    }
    $post->content[$langId] = $content;
    $post->content_blocks[$langId] = $blocksJson;
    $post->meta_keywords[$langId] = '';
    $post->focus_keyword[$langId] = 'printed products';
    $post->seo_content_type[$langId] = 'article';
    $post->og_title[$langId] = $fixture['meta_title'];
    $post->og_description[$langId] = $fixture['meta_description'];
}
$db = Db::getInstance();
$db->execute('START TRANSACTION');

try {
    if (!($existingId > 0 ? $post->update() : $post->add())) {
        throw new RuntimeException('Could not save the demo article.');
    }
    if (!$db->insert('cci_blog_post_shop', ['id_post' => (int) $post->id, 'id_shop' => $shopId], false, true, Db::INSERT_IGNORE)) {
        throw new RuntimeException('Could not associate the article with its shop.');
    }
    if (!$db->insert('cci_blog_post_category', ['id_post' => (int) $post->id, 'id_category' => (int) $post->id_category], false, true, Db::INSERT_IGNORE)) {
        throw new RuntimeException('Could not associate the article with its category.');
    }
    $db->execute('COMMIT');
} catch (Throwable $error) {
    $db->execute('ROLLBACK');
    throw $error;
}
Tools::clearSmartyCache();
echo json_encode(['id_post' => (int) $post->id, 'url' => $context->link->getModuleLink('cci_blog', 'post', ['slug' => $slug]), 'blocks' => count($fixture['blocks'])], JSON_UNESCAPED_SLASHES) . "\n";
