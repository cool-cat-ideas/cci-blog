<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/** Native shop/language data for the pure publication-audit engine. No HTTP requests. */
require_once __DIR__ . '/CciBlogPublicationAuditStorage.php';

final class CciBlogPublicationAuditService
{
    private Context $context;
    private array $engines = [];
    private array $pageIndexes = [];
    private array $products = [];
    private array $targetFactsCache = [];
    private ?string $loadedDataRevision = null;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    public function engine(int $langId): CciBlogPublicationAudit
    {
        if (!isset($this->engines[$langId])) {
            $base = $this->baseContext($langId);
            $raw = Hook::exec('displayCciBlogPublicationAuditRules', [
                'auditVersion' => CciBlogPublicationAudit::VERSION,
                'id_shop' => $base['shopId'], 'id_lang' => $langId,
                'languageIso' => $base['languageIso'],
                'defaultRules' => array_values(CciBlogPublicationAudit::defaultRules()),
            ], null, true);
            $contributions = [];
            foreach (is_array($raw) ? $raw : [] as $module => $pack) {
                if ($pack !== [] && $pack !== null && $pack !== '') {
                    if (!is_array($pack)) {
                        $pack = ['rules' => [null]];
                    }
                    $contributions[] = ['module' => (string) $module, 'priority' => max(-1000, min(1000, (int) ($pack['priority'] ?? 0))), 'rules' => $pack['rules'] ?? [null]];
                }
            }
            $this->engines[$langId] = new CciBlogPublicationAudit($contributions);
        }

        return $this->engines[$langId];
    }

    public function audit(array $post, int $langId): array
    {
        $post = array_intersect_key($post, array_flip(['id_post', 'id_category', 'id_author', 'id_lang', 'id_shop', 'title', 'slug', 'intro', 'content', 'blocks', 'meta_title', 'meta_description', 'focus_keyword', 'seo_content_type', 'allow_comments', 'active']));
        foreach (['id_post', 'id_category', 'id_author'] as $field) {
            if (array_key_exists($field, $post)) {
                $post[$field] = max(0, (int) $post[$field]);
            }
        }
        foreach (['active', 'allow_comments'] as $field) {
            if (array_key_exists($field, $post)) {
                $post[$field] = (bool) (int) $post[$field];
            }
        }
        $context = $this->baseContext($langId);
        $dataRevision = CciBlogPublicationAuditStorage::revision((int) $context['shopId']);
        if ($this->loadedDataRevision !== $dataRevision) {
            $this->pageIndexes = $this->products = $this->targetFactsCache = [];
            $this->loadedDataRevision = $dataRevision;
        }

        $category = ($post['seo_content_type'] ?? '') === 'category';
        $context['entityType'] = $category ? 'category' : 'post';
        $context['entityId'] = (int) ($post[$category ? 'id_category' : 'id_post'] ?? 0);
        $context['tocEnabled'] = !$category && $context['tocEnabled'];
        $context['commentsEnabled'] = !$category && $context['commentsEnabled'];
        $post['id_lang'] = $langId;
        $post['id_shop'] = $context['shopId'];
        $slug = Tools::str2url((string) (($post['slug'] ?? '') ?: ($post['title'] ?? '')));
        $context['postUrl'] = $this->moduleUrl($context['entityType'], ['slug' => $slug], $langId);
        $facts = CciBlogPublicationAudit::collectFacts($post, $context);
        $pages = $this->pages($langId);
        if ($pages['complete']) {
            $context['duplicateTitle'] = [];
            $context['duplicateDescription'] = [];
            foreach (['title', 'description'] as $field) {
                $key = 'duplicate' . ucfirst($field);
                foreach ($pages['metadata'][$field][CciBlogPublicationAudit::fold($facts[$field])] ?? [] as $page) {
                    if ($page['entityType'] !== $context['entityType'] || $page['id'] !== $context['entityId']) {
                        $context[$key][] = ['title' => $page['title'], 'href' => $page['url']];
                    }
                }
            }
        }
        $context['linkResults'] = [];
        $context['anchorResults'] = [];
        $context['internalLinks'] = 0;
        $context['externalLinks'] = 0;
        foreach (array_slice($facts['links'], 0, 100) as $link) {
            $href = $link['href'];
            if ($href === '' || preg_match('#^(?:mailto|tel|sms):#i', $href)) {
                continue;
            }
            $url = $this->absoluteUrl($href, $context);
            if ($url === null) {
                $context['linkResults'][] = ['status' => 'bad', 'href' => $href, 'reason' => 'Invalid or unsupported URL.'];
                continue;
            }
            if (!$this->sameShopOrigin($url, $context['siteUrl'])) {
                ++$context['externalLinks'];
                continue;
            }
            ++$context['internalLinks'];
            $key = self::urlKey($url);
            $self = $key === self::urlKey($context['postUrl']);
            $target = $pages['targets'][$key] ?? null;
            if ($target === null) {
                $target = $this->nativeTarget($url, $langId);
            }
            $fragment = rawurldecode((string) (parse_url($url, PHP_URL_FRAGMENT) ?: ''));
            if ($fragment !== '' && ($self || ($target && $target['public']))) {
                $targetFacts = $self ? $facts : $this->targetFacts($target, $context);
                if ($targetFacts !== null) {
                    $exists = in_array($fragment, $targetFacts['ids'], true);
                    $unknown = !$exists && (!empty($targetFacts['dynamicBlocks']) || !empty($targetFacts['truncated']));
                    $context['anchorResults'][] = ['status' => $exists ? 'good' : ($unknown ? 'unavailable' : 'bad'), 'href' => $href, 'reason' => $exists ? 'Article section exists.' : ($unknown ? 'This section may be supplied by dynamic content and was not verified.' : 'Article section does not exist in the analyzed content.')];
                }
            }
            if ($href[0] === '#' || $self) {
                continue;
            }
            if ($target) {
                $context['linkResults'][] = ['status' => $target['public'] ? 'good' : 'bad', 'href' => $href, 'reason' => $target['public'] ? 'Published page in this shop and language.' : 'Missing or unpublished page in this shop and language.'];
            } elseif (isset($pages['redirects'][$key])) {
                $context['linkResults'][] = ['status' => $pages['redirects'][$key] ? 'warning' : 'bad', 'href' => $href, 'reason' => $pages['redirects'][$key] ? 'Old address redirects to a published page.' : 'Redirect target is not published.'];
            } else {
                $blogPath = rtrim((string) parse_url($this->moduleUrl('list', [], $langId), PHP_URL_PATH), '/');
                $path = (string) parse_url($url, PHP_URL_PATH);
                $isPostRoute = str_starts_with($path, $blogPath . '/') && !preg_match('#/(?:tag|author)/#', $path);
                $context['linkResults'][] = ['status' => $isPostRoute && $pages['complete'] ? 'bad' : 'unavailable', 'href' => $href, 'reason' => $isPostRoute && $pages['complete'] ? 'No matching blog page in this shop and language.' : 'This URL was not verified locally.'];
            }
        }
        $context['productResults'] = $this->productResults($facts['productIds'], $langId);
        $context['comparisonResults'] = [];
        $availableProductIds = array_column(array_filter($context['productResults'], static fn (array $product): bool => $product['status'] === 'good'), 'id_product');
        foreach ($facts['comparisons'] ?? [] as $comparison) {
            $fields = array_column(CciBlogComparisonFields::available($context['shopId'], $langId, $comparison['ids']), 'value');
            $context['comparisonResults'][] = [
                'availableProducts' => count(array_intersect($comparison['ids'], $availableProductIds)),
                'rows' => count(array_intersect($comparison['selectedFields'], $fields)),
                'unavailableFields' => array_values(array_diff($comparison['selectedFields'], $fields)),
            ];
        }

        return $this->engine($langId)->evaluate($post, $context, $facts) + [
            'contextFingerprint' => $this->contextFingerprint($langId),
            'dataRevision' => $dataRevision,
            'validUntil' => $pages['nextPublicationAt'],
        ];
    }

    public function contextFingerprint(int $langId): string
    {
        return hash('sha256', json_encode($this->baseContext($langId), JSON_THROW_ON_ERROR));
    }

    /** Decode the saved result; does not load page indexes, targets or rule evaluators. */
    public function storedResult(array $row, int $langId): array
    {
        return CciBlogPublicationAuditStorage::read($row['publication_audit_summary'] ?? null, $row['seo_score'] ?? null,
            $this->engine($langId)->fingerprint(), $this->contextFingerprint($langId),
            CciBlogPublicationAuditStorage::revision((int) $this->context->shop->id));
    }

    public function policyFingerprint(): string
    {
        $policies = [];
        foreach (Language::getLanguages(false, (int) $this->context->shop->id) as $language) {
            $policies[(int) $language['id_lang']] = $this->engine((int) $language['id_lang'])->fingerprint();
        }
        ksort($policies);

        return hash('sha256', json_encode($policies));
    }

    /** Explicit maintenance only. Web lists never invoke this batch operation. */
    public function refreshStoredScores(): int
    {
        CciBlogPublicationAuditStorage::ensureSchema();
        $shopId = (int) $this->context->shop->id;
        $updated = 0;
        foreach (['post', 'category'] as $entity) {
            $lastId = $lastLanguage = 0;
            $extra = $entity === 'post' ? ', e.id_category, e.id_author, e.allow_comments' : '';
            do {
                $rows = Db::getInstance()->executeS('SELECT l.*' . $extra . '
                    FROM ' . _DB_PREFIX_ . 'cci_blog_' . $entity . ' e
                    INNER JOIN ' . _DB_PREFIX_ . 'cci_blog_' . $entity . '_shop es ON es.id_' . $entity . '=e.id_' . $entity . ' AND es.id_shop=' . $shopId . '
                    INNER JOIN ' . _DB_PREFIX_ . 'cci_blog_' . $entity . '_lang l ON l.id_' . $entity . '=e.id_' . $entity . ' AND l.id_shop=es.id_shop
                    INNER JOIN ' . _DB_PREFIX_ . 'lang_shop ls ON ls.id_lang=l.id_lang AND ls.id_shop=l.id_shop
                    WHERE e.id_' . $entity . '>' . $lastId . ' OR (e.id_' . $entity . '=' . $lastId . ' AND l.id_lang>' . $lastLanguage . ')
                    ORDER BY e.id_' . $entity . ', l.id_lang LIMIT 100');
                if ($rows === false) {
                    throw new RuntimeException('Stored publication scores could not be loaded.');
                }
                foreach ($rows as $row) {
                    $id = (int) $row['id_' . $entity];
                    $langId = (int) $row['id_lang'];
                    if ($entity === 'post') {
                        $row['blocks'] = json_decode((string) ($row['content_blocks'] ?? ''), true) ?: [];
                    }
                    $audit = $this->audit($entity === 'category' ? self::categoryDraft($row) : $row, $langId);
                    CciBlogPublicationAuditStorage::store($entity, $id, $shopId, $langId, $audit);
                    $lastId = $id;
                    $lastLanguage = $langId;
                    ++$updated;
                }
            } while (count($rows) === 100);
        }

        return $updated;
    }

    public static function categoryDraft(array $category): array
    {
        return [
            'id_category' => (int) ($category['id_category'] ?? 0), 'id_lang' => (int) ($category['id_lang'] ?? 0),
            'title' => (string) ($category['name'] ?? ''), 'slug' => (string) ($category['slug'] ?? ''),
            'meta_title' => (string) ($category['meta_title'] ?? ''),
            'meta_description' => (string) (($category['meta_description'] ?? '') ?: CciBlogPublicationAudit::plainText((string) ($category['description'] ?? ''))),
            'focus_keyword' => (string) ($category['focus_keyword'] ?? ''), 'seo_content_type' => 'category',
            'blocks' => [['type' => 'paragraph', 'html' => (string) ($category['description'] ?? '')]],
        ];
    }

    private function baseContext(int $langId): array
    {
        $shopId = (int) $this->context->shop->id;
        $languages = Language::getLanguages(false, $shopId);
        $language = null;
        foreach ($languages as $candidate) {
            if ((int) $candidate['id_lang'] === $langId) {
                $language = $candidate;
                break;
            }
        }
        if ($language === null) {
            throw new InvalidArgumentException('Select a language assigned to this shop.');
        }

        return [
            'shopId' => $shopId, 'languageId' => $langId, 'languageIso' => (string) $language['iso_code'],
            'siteUrl' => (string) $this->context->shop->getBaseURL(true),
            'timezone' => date_default_timezone_get(),
            'comparisonFeaturesEnabled' => (bool) Feature::isFeatureActive(),
            'tocEnabled' => (bool) Configuration::get('CCB_TABLE_OF_CONTENTS_ENABLED', null, null, $shopId),
            'tocMinHeadings' => (int) (Configuration::get('CCB_TABLE_OF_CONTENTS_MIN_HEADINGS', null, null, $shopId) ?: 2),
            'commentsEnabled' => (bool) Configuration::get('CCB_COMMENTS_ENABLED', null, null, $shopId)
                && ((string) Configuration::get('CCB_COMMENTS_PROVIDER', null, null, $shopId) === 'native'
                    || preg_match('/^[a-z0-9_-]+$/', strtolower(trim((string) Configuration::get('CCB_DISQUS_SHORTNAME', null, null, $shopId))))),
        ];
    }

    private function pages(int $langId): array
    {
        if (isset($this->pageIndexes[$langId])) {
            return $this->pageIndexes[$langId];
        }
        $shopId = (int) $this->context->shop->id;
        $db = Db::getInstance();
        $rows = $db->executeS('SELECT p.id_post AS id, p.active, p.date_published, pl.title, pl.slug, pl.intro, pl.meta_title, pl.meta_description FROM ' . _DB_PREFIX_ . 'cci_blog_post p INNER JOIN ' . _DB_PREFIX_ . 'cci_blog_post_shop ps ON ps.id_post=p.id_post AND ps.id_shop=' . $shopId . ' INNER JOIN ' . _DB_PREFIX_ . 'cci_blog_post_lang pl ON pl.id_post=p.id_post AND pl.id_shop=ps.id_shop AND pl.id_lang=' . $langId . ' ORDER BY p.id_post LIMIT 10001');
        $categories = $db->executeS('SELECT c.id_category AS id, c.active, cl.name AS title, cl.slug, cl.description AS intro, cl.meta_title, cl.meta_description FROM ' . _DB_PREFIX_ . 'cci_blog_category c INNER JOIN ' . _DB_PREFIX_ . 'cci_blog_category_shop cs ON cs.id_category=c.id_category AND cs.id_shop=' . $shopId . ' INNER JOIN ' . _DB_PREFIX_ . 'cci_blog_category_lang cl ON cl.id_category=c.id_category AND cl.id_shop=cs.id_shop AND cl.id_lang=' . $langId . ' ORDER BY c.id_category LIMIT 10001');
        if ($rows === false || $categories === false) {
            throw new RuntimeException('Publication metadata could not be loaded.');
        }
        $index = ['targets' => [], 'entities' => [], 'metadata' => ['title' => [], 'description' => []], 'redirects' => [], 'nextPublicationAt' => null, 'complete' => count($rows) <= 10000 && count($categories) <= 10000];
        foreach (['post' => $rows, 'category' => $categories] as $entity => $pages) {
            foreach ($pages as $row) {
                $public = !empty($row['active']) && (empty($row['date_published']) || $row['date_published'] <= date('Y-m-d H:i:s'));
                if ($entity === 'post' && !empty($row['active']) && !empty($row['date_published']) && !$public) {
                    $time = strtotime((string) $row['date_published']);
                    if ($time !== false && ($index['nextPublicationAt'] === null || $time < $index['nextPublicationAt'])) {
                        $index['nextPublicationAt'] = $time;
                    }
                }
                $target = ['id' => (int) $row['id'], 'entityType' => $entity, 'public' => $public, 'title' => (string) $row['title'], 'url' => $this->moduleUrl($entity, ['slug' => (string) $row['slug']], $langId)];
                $index['targets'][self::urlKey($target['url'])] = $target;
                $index['entities'][$entity . ':' . $target['id']] = $target;
                if ($public) {
                    foreach (['title' => $row['meta_title'] ?: $row['title'], 'description' => $row['meta_description'] ?: $row['intro']] as $field => $value) {
                        $folded = CciBlogPublicationAudit::fold((string) $value);
                        if ($folded !== '') {
                            $index['metadata'][$field][$folded][] = $target;
                        }
                    }
                }
            }
        }
        $index['targets'][self::urlKey($this->moduleUrl('list', [], $langId))] = ['id' => 0, 'entityType' => 'list', 'public' => true];
        $index['targets'][self::urlKey($this->moduleUrl('feed', [], $langId))] = ['id' => 0, 'entityType' => 'feed', 'public' => (bool) Configuration::get('CCB_FEED_ENABLED', null, null, $shopId)];
        foreach (['index', 'contact', 'sitemap', 'search'] as $controller) {
            $url = $this->context->link->getPageLink($controller, null, $langId, null, false, $shopId);
            $index['targets'][self::urlKey($url)] = ['id' => 0, 'entityType' => 'store_page', 'public' => true];
        }
        $redirects = $db->executeS('SELECT r.entity_type, r.old_slug, r.id_post, r.id_category FROM ' . _DB_PREFIX_ . 'cci_blog_slug_redirect r WHERE r.id_lang=' . $langId . ' AND ((r.entity_type="post" AND EXISTS (SELECT 1 FROM ' . _DB_PREFIX_ . 'cci_blog_post_shop ps WHERE ps.id_post=r.id_post AND ps.id_shop=' . $shopId . ')) OR (r.entity_type="category" AND EXISTS (SELECT 1 FROM ' . _DB_PREFIX_ . 'cci_blog_category_shop cs WHERE cs.id_category=r.id_category AND cs.id_shop=' . $shopId . '))) LIMIT 10000') ?: [];
        foreach ($redirects as $redirect) {
            $target = $index['entities'][$redirect['entity_type'] . ':' . (int) $redirect['id_' . $redirect['entity_type']]] ?? null;
            if ($target) {
                $key = self::urlKey($this->moduleUrl($redirect['entity_type'], ['slug' => $redirect['old_slug']], $langId));
                $index['redirects'][$key] = $target['public'];
            }
        }
        $this->pageIndexes[$langId] = $index;

        return $index;
    }

    private function targetFacts(array $target, array $context): ?array
    {
        if (!in_array($target['entityType'], ['post', 'category'], true)) {
            return null;
        }
        $type = $target['entityType'];
        $cacheKey = $type . ':' . (int) $target['id'] . ':' . (int) $context['languageId'];
        if (isset($this->targetFactsCache[$cacheKey])) {
            return $this->targetFactsCache[$cacheKey];
        }
        $field = $type === 'post' ? 'content_blocks, content, (SELECT p.allow_comments FROM ' . _DB_PREFIX_ . 'cci_blog_post p WHERE p.id_post=' . (int) $target['id'] . ') AS allow_comments' : 'description';
        $row = Db::getInstance()->getRow('SELECT ' . $field . ' FROM ' . _DB_PREFIX_ . 'cci_blog_' . $type . '_lang WHERE id_' . $type . '=' . (int) $target['id'] . ' AND id_shop=' . (int) $context['shopId'] . ' AND id_lang=' . (int) $context['languageId']);
        if (!$row) {
            return null;
        }
        $post = $type === 'post' ? ['blocks' => json_decode((string) $row['content_blocks'], true) ?: [], 'content' => $row['content'], 'allow_comments' => $row['allow_comments']] : ['content' => $row['description']];
        $context['tocEnabled'] = $type === 'post' && (bool) Configuration::get('CCB_TABLE_OF_CONTENTS_ENABLED', null, null, (int) $context['shopId']);
        $context['commentsEnabled'] = $type === 'post' && $this->baseContext((int) $context['languageId'])['commentsEnabled'];

        return $this->targetFactsCache[$cacheKey] = CciBlogPublicationAudit::collectFacts($post, $context);
    }

    private function productResults(array $ids, int $langId): array
    {
        $missing = array_values(array_diff($ids, array_keys($this->products[$langId] ?? [])));
        if ($missing) {
            $shopId = (int) $this->context->shop->id;
            foreach ($missing as $id) {
                $this->products[$langId][$id] = ['status' => 'bad', 'id_product' => $id, 'reason' => 'Product is missing, hidden or unavailable in this shop and language.'];
            }
            $rows = Db::getInstance()->executeS('SELECT p.id_product, ps.active, ps.visibility, pl.name FROM ' . _DB_PREFIX_ . 'product p INNER JOIN ' . _DB_PREFIX_ . 'product_shop ps ON ps.id_product=p.id_product AND ps.id_shop=' . $shopId . ' INNER JOIN ' . _DB_PREFIX_ . 'product_lang pl ON pl.id_product=p.id_product AND pl.id_shop=ps.id_shop AND pl.id_lang=' . $langId . ' WHERE p.id_product IN (' . implode(',', array_map('intval', $missing)) . ')');
            if ($rows === false) {
                throw new RuntimeException('Product availability could not be loaded.');
            }
            foreach ($rows as $row) {
                $id = (int) $row['id_product'];
                if ($row['active'] && $row['visibility'] !== 'none' && trim((string) $row['name']) !== '') {
                    $this->products[$langId][$id] = ['status' => 'good', 'id_product' => $id, 'title' => $row['name'], 'reason' => 'Product is visible in this shop and language.'];
                }
            }
        }

        return array_values(array_intersect_key($this->products[$langId] ?? [], array_flip($ids)));
    }

    private function moduleUrl(string $controller, array $params, int $langId): string
    {
        return $this->context->link->getModuleLink('cci_blog', $controller, $params, null, $langId, (int) $this->context->shop->id);
    }

    private function absoluteUrl(string $href, array $context): ?string
    {
        if (preg_match('#^https?://#i', $href)) {
            return filter_var($href, FILTER_VALIDATE_URL) ? $href : null;
        }
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $href)) {
            return null;
        }
        $base = parse_url($context['siteUrl']);
        $origin = ($base['scheme'] ?? 'https') . '://' . ($base['host'] ?? '') . (isset($base['port']) ? ':' . $base['port'] : '');
        if (str_starts_with($href, '//')) {
            return ($base['scheme'] ?? 'https') . ':' . $href;
        }
        if ($href[0] === '#' || $href[0] === '?') {
            return preg_replace('/[?#].*$/', '', $context['postUrl']) . $href;
        }

        $path = $href[0] === '/' ? $href : rtrim(dirname((string) parse_url($context['postUrl'], PHP_URL_PATH)), '/') . '/' . $href;
        $parts = preg_split('/(?=[?#])/', $path, 2);
        $segments = [];
        foreach (explode('/', $parts[0]) as $segment) {
            if ($segment === '..') {
                array_pop($segments);
            } elseif ($segment !== '' && $segment !== '.') {
                $segments[] = $segment;
            }
        }

        return $origin . '/' . implode('/', $segments) . ($parts[1] ?? '');
    }

    /** Verify recognized catalogue destinations without executing a front controller. */
    private function nativeTarget(string $url, int $langId): ?array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $candidate = [];
        if (!empty($query['id_product'])) {
            $candidate['product'] = (int) $query['id_product'];
        } elseif (!empty($query['id_cms'])) {
            $candidate['cms'] = (int) $query['id_cms'];
        } elseif (preg_match('#/(\d+)(?:-\d+)?-[^/]+(?:\.html)?$#', (string) parse_url($url, PHP_URL_PATH), $match)) {
            // A number in a custom URL alone is not proof of its route type.
            $candidate = ['product' => (int) $match[1], 'cms' => (int) $match[1]];
        }
        foreach ($candidate as $type => $id) {
            if ($id <= 0) {
                continue;
            }
            $shopId = (int) $this->context->shop->id;
            if ($type === 'product') {
                $row = Db::getInstance()->getRow('SELECT ps.active, ps.visibility, pl.name, pl.link_rewrite FROM ' . _DB_PREFIX_ . 'product_shop ps INNER JOIN ' . _DB_PREFIX_ . 'product_lang pl ON pl.id_product=ps.id_product AND pl.id_shop=ps.id_shop AND pl.id_lang=' . $langId . ' WHERE ps.id_product=' . $id . ' AND ps.id_shop=' . $shopId);
                $canonical = $row ? $this->context->link->getProductLink($id, null, null, null, $langId, $shopId) : '';
                $public = $row && $row['active'] && $row['visibility'] !== 'none' && trim((string) $row['name']) !== '';
            } else {
                $row = Db::getInstance()->getRow('SELECT c.active, cl.link_rewrite FROM ' . _DB_PREFIX_ . 'cms c INNER JOIN ' . _DB_PREFIX_ . 'cms_shop cs ON cs.id_cms=c.id_cms AND cs.id_shop=' . $shopId . ' INNER JOIN ' . _DB_PREFIX_ . 'cms_lang cl ON cl.id_cms=c.id_cms AND cl.id_shop=cs.id_shop AND cl.id_lang=' . $langId . ' WHERE c.id_cms=' . $id);
                $canonical = $row ? $this->context->link->getCMSLink($id, null, null, $langId, $shopId) : '';
                $public = $row && $row['active'];
            }
            $explicit = isset($query['id_' . $type]) && ($query['controller'] ?? $type) === $type;
            if ($explicit || ($canonical !== '' && self::urlKey($canonical) === self::urlKey($url))) {
                return ['id' => $id, 'entityType' => $type, 'public' => (bool) $public];
            }
        }

        return null;
    }

    private function sameShopOrigin(string $a, string $b): bool
    {
        $one = parse_url($a);
        $two = parse_url($b);
        $port = static fn (array $url): int => isset($url['port']) && !in_array((int) $url['port'], [80, 443], true) ? (int) $url['port'] : 0;

        return strtolower((string) ($one['host'] ?? '')) === strtolower((string) ($two['host'] ?? '')) && $port($one) === $port($two);
    }

    private static function urlKey(string $url): string
    {
        $parts = parse_url($url);
        parse_str((string) ($parts['query'] ?? ''), $query);
        $query = array_intersect_key($query, array_flip(['fc', 'module', 'controller', 'slug', 'id_product', 'id_cms']));
        ksort($query);

        return rtrim(rawurldecode((string) ($parts['path'] ?? '/')), '/') . ($query ? '?' . http_build_query($query) : '');
    }
}
