<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require '/var/www/html/config/config.inc.php';
$context = Context::getContext();
if ($context->shop->domain !== 'localhost:4090') {
    throw new RuntimeException('This repair is restricted to the local PrestaShop demo.');
}
Module::getInstanceByName('cci_blog');
$shopId = (int) $context->shop->id;
$db = Db::getInstance();
if (($argv[1] ?? '') === '--plan') {
    $rows = $db->executeS('SELECT pl.*, p.date_upd FROM `' . _DB_PREFIX_ . 'cci_blog_post_lang` pl JOIN `' . _DB_PREFIX_ . 'cci_blog_post` p ON p.id_post=pl.id_post WHERE pl.id_shop=' . $shopId . ' AND pl.slug LIKE "cci-demo-%"');
    $changes = [];
    foreach ($rows as $row) {
        $blocks = json_decode((string) $row['content_blocks'], true);
        if (!is_array($blocks)) {
            continue;
        }
        foreach ($blocks as $index => $block) {
            if (($block['type'] ?? '') !== 'heading' || !in_array($block['text'] ?? '', ['Related resources', 'Powiazane materialy', 'Powiązane materiały'], true)) {
                continue;
            }
            $image = $blocks[$index + 1] ?? [];
            if (($image['type'] ?? '') !== 'image' || !in_array($image['alt'] ?? '', ['Example product used as an article image', 'Przykladowy produkt uzyty jako obraz w artykule'], true)) {
                continue;
            }
            $products = $db->executeS('SELECT pp.id_product FROM `' . _DB_PREFIX_ . 'cci_blog_post_product` pp JOIN `' . _DB_PREFIX_ . 'product_shop` ps ON ps.id_product=pp.id_product AND ps.id_shop=' . $shopId . ' AND ps.active=1 AND ps.visibility!="none" WHERE pp.id_post=' . (int) $row['id_post'] . ' ORDER BY pp.position');
            if (!$products) {
                continue;
            }
            $blocks[$index + 1] = [
                'type' => 'product_carousel', 'presentation' => 'card', 'layout' => 'grid',
                'title' => $block['text'] === 'Related resources' ? 'Products from this guide' : 'Produkty z poradnika',
                'ids' => implode(', ', array_column($products, 'id_product')),
                'options' => ['items_desktop' => 3, 'items_tablet' => 2, 'items_mobile' => 1, 'margin' => 16],
            ];
            $changes[] = ['before' => $row, 'blocks' => $blocks];
            break;
        }
    }
    echo json_encode(['shopId' => $shopId, 'changes' => $changes], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    exit;
}
if (($argv[1] ?? '') !== '--apply') {
    throw new InvalidArgumentException('Use --plan, then --apply with the saved plan path.');
}
$plan = json_decode((string) file_get_contents($argv[2] ?? ''), true, 512, JSON_THROW_ON_ERROR);
if (($plan['shopId'] ?? null) !== $shopId) {
    throw new RuntimeException('Unexpected repair shop.');
}
$pro = Module::isEnabled('cci_blog_pro') ? Module::getInstanceByName('cci_blog_pro') : null;
$licensed = false;
foreach ($pro ? $pro->hookDisplayCciBlogFeatures(['shop_id' => $shopId]) : [] as $feature) {
    if ($feature['key'] === 'product_carousel' && !empty($feature['enabled'])) {
        $licensed = true;
    }
}
if (!$licensed) {
    throw new RuntimeException('An active Pro product carousel entitlement is required.');
}
require_once _PS_ROOT_DIR_ . '/app/AppKernel.php';
require_once _PS_ROOT_DIR_ . '/app/FrontKernel.php';
$kernel = new FrontKernel('prod', false);
$kernel->boot();
$context->container = $kernel->getContainer();
$context->cart = new Cart();
$context->cart->id = 0;
$context->customer = new Customer();
$context->customer->id = 0;
$context->country = new Country((int) Configuration::get('PS_COUNTRY_DEFAULT'));
$context->currency = new Currency((int) Configuration::get('PS_CURRENCY_DEFAULT'));
$db->execute('START TRANSACTION');

try {
    foreach ($plan['changes'] as $change) {
        $before = $change['before'];
        $where = 'id_post=' . (int) $before['id_post'] . ' AND id_lang=' . (int) $before['id_lang'] . ' AND id_shop=' . $shopId;
        $locked = $db->executeS('SELECT content_blocks, content, slug FROM `' . _DB_PREFIX_ . 'cci_blog_post_lang` WHERE ' . $where . ' LIMIT 1 FOR UPDATE', true, false);
        $current = $locked[0] ?? null;
        if (!$current || !str_starts_with($current['slug'], 'cci-demo-') || $current['content_blocks'] !== $before['content_blocks'] || $current['content'] !== $before['content']) {
            throw new RuntimeException('The demo content changed after the repair plan was saved.');
        }
        $context->language = new Language((int) $before['id_lang']);
        $context->currentLocale = Tools::getContextLocale($context);
        $html = CciBlogPost::renderBlocksToHtml($change['blocks'], $context);
        if (!str_contains($html, 'cci-blog-product-collection')) {
            throw new RuntimeException('The product grid did not render through the normal Pro gate.');
        }
        if (!$db->update('cci_blog_post_lang', ['content_blocks' => pSQL(json_encode($change['blocks'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), true), 'content' => pSQL($html, true)], $where)
            || !$db->update('cci_blog_post', ['date_upd' => date('Y-m-d H:i:s')], 'id_post=' . (int) $before['id_post'])) {
            throw new RuntimeException('The demo repair could not be saved.');
        }
    }
    $db->execute('COMMIT');
} catch (Throwable $error) {
    $db->execute('ROLLBACK');
    throw $error;
}
Tools::clearSmartyCache();
echo json_encode(['updatedTranslations' => count($plan['changes']), 'updatedPosts' => count(array_unique(array_column(array_column($plan['changes'], 'before'), 'id_post')))]) . "\n";
