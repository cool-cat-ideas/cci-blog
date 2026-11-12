<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require '/var/www/html/config/config.inc.php';
require_once _PS_MODULE_DIR_ . 'cci_blog/cci_blog.php';
require_once _PS_MODULE_DIR_ . 'cci_blog_pro/cci_blog_pro.php';

$context = Context::getContext();
$shop = (int) $context->shop->id;
$lang = (int) $context->language->id;
$db = Db::getInstance();
$assert = static function (bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } };
$rejects = static function (callable $action, string $message) use ($assert): void {
    try {
        $action();
    } catch (DomainException|InvalidArgumentException $error) {
        return;
    }
    $assert(false, $message);
};
CciBlogReaderFeedback::install();
CciBlogProReaderEngagement::install();
$db->execute('START TRANSACTION');

try {
    $ids = [];
    for ($i = 0; $i < 4; ++$i) {
        $db->insert('cci_blog_post', ['id_category' => 1, 'id_author' => 0, 'active' => $i === 3 ? 0 : 1,
            'allow_comments' => 0, 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s'),
            'date_published' => date('Y-m-d H:i:s', time() + ($i === 2 ? 86400 : -86400))]);
        $id = (int) $db->Insert_ID();
        $ids[] = $id;
        $db->insert('cci_blog_post_shop', ['id_post' => $id, 'id_shop' => $shop]);
        $db->insert('cci_blog_post_lang', ['id_post' => $id, 'id_shop' => $shop, 'id_lang' => $lang,
            'title' => 'Reader engagement integration fixture ' . $i, 'slug' => 'reader-fixture-' . $id,
            'intro' => 'Integration test', 'content' => '<p>Guide content.</p>']);
    }
    $search = CciBlogProReaderEngagement::options($shop, $lang, 'integration fixture 2', [$ids[1]]);
    $searchIds = array_map('intval', array_column($search['posts'], 'id_post'));
    $assert(in_array($ids[1], $searchIds, true) && in_array($ids[2], $searchIds, true), 'Search must return matching articles as well as selected recommendations.');
    $slugSearch = CciBlogProReaderEngagement::options($shop, $lang, 'reader-fixture-' . $ids[2], [$ids[1]]);
    $assert(in_array($ids[2], array_map('intval', array_column($slugSearch['posts'], 'id_post')), true), 'Article search supports the URL slug as well as the title.');
    $series = CciBlogProReaderEngagement::saveSeries($shop, $lang, ['title' => 'Integration series', 'slug' => 'reader-fixture-' . $ids[0], 'active' => 1]);
    $settings = ['related_ids' => [$ids[2], $ids[1]], 'related_title' => 'Continue learning', 'related_intro' => 'A useful next step.', 'id_series' => (int) $series['id_series'], 'position' => 1];
    CciBlogProReaderEngagement::savePost($ids[0], $shop, $lang, $settings);
    CciBlogProReaderEngagement::savePost($ids[1], $shop, $lang, ['related_ids' => [], 'id_series' => $series['id_series'], 'position' => 2]);
    $front = CciBlogProReaderEngagement::storefront($ids[0], $shop, $lang);
    $assert(array_map('intval', array_column($front['related'], 'id_post')) === [$ids[1]], 'Future recommendations must stay private.');
    $assert((int) $front['series']['next']['id_post'] === $ids[1], 'Next part follows the configured order.');
    $assert($front['series']['previous'] === null, 'First part has no previous link.');
    $order = CciBlogProReaderEngagement::order((int) $series['id_series'], $shop, $lang);
    $changed = CciBlogProReaderEngagement::reorder((int) $series['id_series'], $shop, $lang, [$ids[1], $ids[0]], $order['revision']);
    $assert(array_map('intval', array_column($changed['items'], 'id_post')) === [$ids[1], $ids[0]], 'Dragging saves the entire series order.');
    $rejects(fn () => CciBlogProReaderEngagement::reorder((int) $series['id_series'], $shop, $lang, [$ids[0], $ids[1]], $order['revision']), 'An outdated editor must not overwrite a newer order.');
    $rejects(fn () => CciBlogProReaderEngagement::reorder((int) $series['id_series'], $shop, $lang, [$ids[1], $ids[2]], $changed['revision']), 'Ordering cannot silently replace series membership.');
    $rejects(fn () => CciBlogProReaderEngagement::reorder((int) $series['id_series'], $shop, $lang, [$ids[1], $ids[1]], $changed['revision']), 'Ordering cannot duplicate an article.');
    Module::getInstanceByName('cci_blog_pro')->saveCciReaderEngagementPost($ids[0], $shop, $lang, $settings);
    $assert((int) CciBlogProReaderEngagement::post($ids[0], $shop, $lang)['position'] === 2, 'Saving an older article form must preserve a separately saved series order.');
    $saved = CciBlogProReaderEngagement::post($ids[0], $shop, $lang);
    $saved['related_intro'] = 'Read these articles in order.';
    CciBlogProReaderEngagement::saveArticle($ids[0], $shop, $lang, $saved, ['id_series' => $series['id_series'], 'post_ids' => [$ids[0], $ids[1]], 'revision' => $changed['revision']]);
    $order = CciBlogProReaderEngagement::order((int) $series['id_series'], $shop, $lang);
    $assert(array_map('intval', array_column($order['items'], 'id_post')) === [$ids[0], $ids[1]], 'One article save persists the whole series order.');
    $bad = $saved;
    $bad['related_intro'] = 'This change must roll back.';
    $rejects(fn () => CciBlogProReaderEngagement::saveArticle($ids[0], $shop, $lang, $bad, ['id_series' => $series['id_series'], 'post_ids' => [$ids[0], $ids[0]], 'revision' => $order['revision']]), 'Invalid order must reject the combined save.');
    $assert(CciBlogProReaderEngagement::post($ids[0], $shop, $lang)['related_intro'] === 'Read these articles in order.', 'A failed combined save must roll back recommendations too.');
    CciBlogProReaderEngagement::saveArticle($ids[2], $shop, $lang, CciBlogProReaderEngagement::normalize(['id_series' => $series['id_series']]), ['id_series' => $series['id_series'], 'post_ids' => ['current', $ids[0], $ids[1]], 'revision' => $order['revision']]);
    $order = CciBlogProReaderEngagement::order((int) $series['id_series'], $shop, $lang);
    $assert((int) $order['items'][0]['id_post'] === $ids[2], 'An article can join a series and receive its order in the same save.');
    $assert(CciBlogProReaderEngagement::archive($series['slug'], $shop + 999, $lang, 1) === [], 'Series cannot leak between shops.');
    $assert(CciBlogProReaderEngagement::archive($series['slug'], $shop, $lang + 999, 1) === [], 'Series cannot leak between languages.');
    $assert(!CciBlogReaderFeedback::isPublished($ids[2], $shop, $lang), 'Future articles do not accept public votes.');
    $assert(!CciBlogReaderFeedback::isPublished($ids[3], $shop, $lang), 'Drafts do not accept public votes.');
    $rejects(fn () => CciBlogProReaderEngagement::savePost($ids[0], $shop, $lang, ['related_ids' => [$ids[0]]]), 'Self recommendations must be rejected.');
    $rejects(fn () => CciBlogProReaderEngagement::savePost($ids[0], $shop + 999, $lang, ['related_ids' => [$ids[1]]]), 'Cross-shop recommendations must be rejected.');
    $rejects(fn () => CciBlogProReaderEngagement::savePost($ids[0], $shop, $lang, ['id_series' => $series['id_series'] + 999]), 'Missing series must be rejected.');
    $rejects(fn () => CciBlogProReaderEngagement::normalize(['related_ids' => range(1, 7)]), 'Recommendation count is bounded.');
    $rejects(fn () => CciBlogProReaderEngagement::saveSeries($shop, $lang, ['id_series' => $series['id_series'], 'title' => 'Changed title', 'slug' => 'changed-url', 'active' => 1]), 'Existing series URLs remain stable.');

    $token = CciBlogReaderFeedback::token($ids[0], $context);
    $rejects(fn () => CciBlogReaderFeedback::submit($ids[0], $context, ['token' => 'invalid', 'helpful' => '1'], true), 'Votes require the visitor-bound token.');
    $rejects(fn () => CciBlogReaderFeedback::submit($ids[0], $context, ['token' => $token, 'helpful' => '2'], true), 'Votes accept only yes/no.');
    CciBlogReaderFeedback::submit($ids[0], $context, ['token' => $token, 'helpful' => '0', 'message' => 'Please add a checklist.'], true);
    $count = (int) $db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'cci_blog_reader_feedback` WHERE id_post=' . $ids[0]);
    $assert($count === 1, 'A valid vote creates one record.');
    $rejects(fn () => CciBlogReaderFeedback::submit($ids[0], $context, ['token' => $token, 'helpful' => '1'], true), 'Repeated submissions are throttled.');
    $report = CciBlogReaderFeedback::report($shop, $lang, 1, false);
    $assert($report['messages'] === [], 'The base report does not expose Pro text feedback.');
    $report = CciBlogReaderFeedback::report($shop, $lang, 1, true);
    $assert(count(array_filter($report['messages'], fn (array $row): bool => (int) $row['id_post'] === $ids[0])) === 1, 'Pro reports include private messages.');
    $assert(CciBlogReaderFeedback::report($shop + 999, $lang, 1, true)['rows'] === [], 'Ratings remain shop-scoped.');
    $assert(CciBlogReaderFeedback::report($shop, $lang + 999, 1, true)['rows'] === [], 'Ratings remain language-scoped.');
    $token2 = CciBlogReaderFeedback::token($ids[1], $context);
    CciBlogReaderFeedback::submit($ids[1], $context, ['token' => $token2, 'helpful' => '1', 'message' => 'This must not be retained without Pro.'], false);
    $assert($db->getValue('SELECT message FROM `' . _DB_PREFIX_ . 'cci_blog_reader_feedback` WHERE id_post=' . $ids[1]) === '', 'Text collection stays disabled without Pro.');
    echo "Reader engagement native integration checks passed; all fixtures rolled back.\n";
} finally {
    $db->execute('ROLLBACK');
}
