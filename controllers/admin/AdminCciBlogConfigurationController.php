<?php
declare(strict_types=1);
if (!defined('_PS_VERSION_')) { exit; }

require_once dirname(__DIR__, 2) . '/classes/CciSharedLicenseEnvironment.php';
require_once dirname(__DIR__, 2) . '/classes/CciBlogFeatureRegistry.php';

class AdminCciBlogConfigurationController extends ModuleAdminController
{
    private const PRODUCT_HOME_URL = 'https://coolcatideas.com/products/cci-blog';
    private const MARKETPLACE_API_BASE = 'https://coolcatideas.com';
    private const MARKETPLACE_API_BASE_OVERRIDE = 'CCI_MARKETPLACE_API_BASE';
    private const MARKETPLACE_PRODUCT_SLUG = 'cci-blog';
    private const SETTINGS_FIELDS = [
        'CCB_POSTS_PER_PAGE'       => 'int',
        'CCB_SHOW_AUTHOR'          => 'bool',
        'CCB_SHOW_DATE'            => 'bool',
        'CCB_SHOW_VIEWS'           => 'bool',
        'CCB_SHOW_READ_TIME'       => 'bool',
        'CCB_COMMENTS_ENABLED'     => 'bool',
        'CCB_COMMENTS_PROVIDER'    => 'comments_provider',
        'CCB_COMMENTS_MODERATION'  => 'bool',
        'CCB_TABLE_OF_CONTENTS_ENABLED' => 'bool',
        'CCB_TABLE_OF_CONTENTS_MIN_HEADINGS' => 'int',
        'CCB_RELATED_POSTS'        => 'int',
        'CCB_RELATED_PRODUCTS'     => 'bool',
        'CCB_BREADCRUMB'           => 'bool',
        'CCB_SOCIAL_SHARE'         => 'bool',
        'CCB_SCHEMA_ORG'           => 'bool',
        'CCB_OG_TAGS'              => 'bool',
        'CCB_DISQUS_SHORTNAME'     => 'disqus_shortname',
        'CCB_BASE_SLUG'            => 'slug',
        'CCB_URL_SUFFIX_HTML'      => 'bool',
        'CCB_SIDEBAR_POSITION'     => 'string',
        'CCB_LAYOUT'               => 'string',
        'CCB_SHOW_FEATURED_WIDGET' => 'bool',
        'CCB_FEED_ENABLED'         => 'bool',
        'CCB_FEED_ITEMS'           => 'int',
        'CCB_HIGHLIGHT_SYNTAX'     => 'bool',
        'CCB_OWL_ASSETS_SOURCE'    => 'owl_assets_source',
        'CCB_LOAD_OWL_LIBRARY'     => 'bool',
        'CCB_LOAD_OWL_STYLES'      => 'bool',
    ];

    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
        $this->meta_title = $this->module->l('CCI Blog');
    }

    public function setMedia($isNewTheme = false): void
    {
        parent::setMedia($isNewTheme);

        $cssPath = _PS_MODULE_DIR_ . $this->module->name . '/views/css/cci_blog_admin.css';
        $version = (string) (@filemtime($cssPath) ?: $this->module->version);

        if (method_exists($this, 'registerStylesheet')) {
            $this->registerStylesheet(
                'cci-blog-admin',
                'modules/' . $this->module->name . '/views/css/cci_blog_admin.css?v=' . rawurlencode($version),
                ['media' => 'all', 'priority' => 230]
            );
            return;
        }

        $this->addCSS($this->module->getPathUri() . 'views/css/cci_blog_admin.css?v=' . rawurlencode($version), 'all');
    }

    public function initContent(): void
    {
        if ($this->controller_name === 'AdminCciBlogConfiguration' && !Tools::isSubmit('ajax')) {
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminCciBlog'));
        }

        if (Tools::isSubmit('ajax')) {
            $this->handleAjaxRequest();
        }

        $this->ensureBlockSchema();
        $this->ensurePostCategorySchema();
        $this->ensureCategoryAuthorSchema();
        if (method_exists($this->module, 'ensureIntegrationHooks')) {
            $this->module->ensureIntegrationHooks();
        }

        $jsonFlags = JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_HEX_TAG
            | JSON_HEX_APOS
            | JSON_HEX_AMP
            | JSON_HEX_QUOT;
        $assetVersion = (string) max(
            @filemtime($this->module->getLocalPath() . 'views/js/cci-blog-admin.js') ?: 0,
            @filemtime($this->module->getLocalPath() . 'views/css/cci_blog_admin.css') ?: 0
        );
        $baseAdminUrl = Tools::getHttpHost(true) . (string) ($_SERVER['REQUEST_URI'] ?? '');

        $this->context->smarty->assign([
            'modulePath' => $this->module->getPathUri(),
            'adminAssetVersion' => $assetVersion,
            'adminAjaxEndpoint' => json_encode($baseAdminUrl, $jsonFlags),
            'moduleVersion' => json_encode($this->module->version, $jsonFlags),
            'initialPayload' => json_encode($this->buildInitialPayload(), $jsonFlags),
            'adminExtensionScripts' => $this->getAdminExtensionScripts(),
            'adminLoadingLabel' => $this->getAdminLoadingLabel(),
        ]);

        $this->content = $this->context->smarty->fetch(
            $this->module->getLocalPath() . 'views/templates/admin/configuration.tpl'
        );

        parent::initContent();
    }

    private function handleAjaxRequest(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!Validate::isLoadedObject($this->context->employee)) {
            die(json_encode(['success' => false, 'error' => $this->module->l('Unauthorized request.')], JSON_UNESCAPED_UNICODE));
        }

        $this->ensureBlockSchema();
        $this->ensurePostCategorySchema();
        $this->ensureCategoryAuthorSchema();
        if (method_exists($this->module, 'ensureIntegrationHooks')) {
            $this->module->ensureIntegrationHooks();
        }

        $action = (string) Tools::getValue('ajaxAction', '');
        $payload = $this->getJsonPayload();

        try {
            $response = match ($action) {
                'dashboard:get' => ['success' => true, 'payload' => $this->buildInitialPayload()],
                'diagnostics:get' => ['success' => true, 'diagnostics' => $this->getDiagnosticsPayload()],
                'settings:save' => $this->saveSettings(is_array($payload) ? $payload : []),
                'posts:list' => $this->getAdminPostsPayload(is_array($payload) ? $payload : []),
                'redirects:list' => ['success' => true, 'redirects' => $this->getAdminSlugRedirects()],
                'redirect:update' => $this->updateSlugRedirect(is_array($payload) ? $payload : []),
                'redirect:delete' => $this->deleteSlugRedirect(is_array($payload) ? $payload : []),
                'post:get' => $this->getPostPayload(
                    (int) Tools::getValue('id_post', 0),
                    (int) Tools::getValue('id_lang', 0)
                ),
                'post:save' => $this->savePost(is_array($payload) ? $payload : []),
                'post:delete' => $this->deletePost(is_array($payload) ? $payload : []),
                'categories:list' => ['success' => true, 'categories' => $this->getAdminCategories()],
                'category:get' => $this->getCategoryPayload(
                    (int) Tools::getValue('id_category', 0),
                    (int) Tools::getValue('id_lang', 0)
                ),
                'category:save' => $this->saveCategory(is_array($payload) ? $payload : []),
                'category:delete' => $this->deleteCategory(is_array($payload) ? $payload : []),
                'multistore:context' => ['success' => true, 'multistore' => $this->getMultistoreContext()],
                'multistore:post:assign-shops' => $this->handleMultistoreAction('post:assign-shops', is_array($payload) ? $payload : []),
                'multistore:category:assign-shops' => $this->handleMultistoreAction('category:assign-shops', is_array($payload) ? $payload : []),
                'comments:list' => ['success' => true, 'comments' => $this->getAdminComments()],
                'comment:update-status' => $this->updateCommentStatus(is_array($payload) ? $payload : []),
                'catalog:products' => $this->getCatalogProductsPayload(is_array($payload) ? $payload : []),
                'media:list' => $this->getLocalMediaLibraryPayload(is_array($payload) ? $payload : []),
                'media:upload' => $this->uploadLocalMedia(),
                'license:get' => ['success' => true, 'license' => $this->getLicensePayload()],
                'license:activate' => $this->activateLicense(is_array($payload) ? $payload : []),
                'license:check' => $this->checkLicense(),
                'license:deactivate' => $this->deactivateLicense(),
                'review-feedback:save' => $this->saveReviewFeedback(is_array($payload) ? $payload : []),
                default => ['success' => false, 'error' => $this->module->l('Unknown admin action.')],
            };
        } catch (Throwable $exception) {
            $response = [
                'success' => false,
                'error' => $this->module->l('Request failed.'),
                'details' => _PS_MODE_DEV_ ? $exception->getMessage() : '',
            ];
        }

        die(json_encode($response, JSON_UNESCAPED_UNICODE));
    }

    private function buildInitialPayload(): array
    {
        $license = $this->getLicensePayload();
        $features = is_array($license['availableFeatures'] ?? null) ? $license['availableFeatures'] : [];
        $featureMap = $this->featureMap($features);
        $marketplaceFeedEndpoint = $this->getMarketplaceFeedEndpoint();
        $postsPayload = $this->getAdminPostsPayload();

        return [
            'stats' => $this->getStats(),
            'posts' => $postsPayload['posts'],
            'postsPagination' => $postsPayload['postsPagination'],
            'slugRedirects' => $this->getAdminSlugRedirects(),
            'authors' => $this->getAdminAuthors(),
            'currentEmployeeId' => isset($this->context->employee) ? (int) $this->context->employee->id : 0,
            'categories' => $this->getAdminCategories(),
            'comments' => $this->getAdminComments(),
            'settings' => $this->getSettings(),
            'license' => $license,
            'features' => $features,
            'enabledFeatures' => $license['enabledFeatures'] ?? [],
            'multistore' => $this->getMultistoreContext(),
            'extensions' => $this->getExtensions($featureMap),
            'marketplaceFeedEndpoint' => $marketplaceFeedEndpoint,
            'extensionStoreEndpoint' => $marketplaceFeedEndpoint,
            'blockTypes' => $this->getBlockTypes($featureMap),
            'blockExtensions' => $this->getBlockExtensions($featureMap),
            'hookOptions' => $this->getContentHookOptions(),
            'language' => $this->getAdminContentLanguagePayload(),
            'languages' => $this->getAdminContentLanguages(),
            'adminDate' => $this->getAdminDateConfig(),
            'adminLinks' => $this->getAdminLinks(),
            'diagnostics' => $this->getDiagnosticsPayload(),
            'i18n' => $this->getI18n(),
        ];
    }

    private function getAdminContentLanguages(): array
    {
        $shopId = (int) ($this->context->shop->id ?? 0);
        $languages = Language::getLanguages(false, $shopId ?: false);
        if (!is_array($languages) || $languages === []) {
            $languages = Language::getLanguages(false);
        }

        return array_values(array_map(static fn(array $language): array => [
            'id' => (int) ($language['id_lang'] ?? 0),
            'name' => (string) ($language['name'] ?? $language['iso_code'] ?? ''),
            'isoCode' => (string) ($language['iso_code'] ?? ''),
            'locale' => (string) ($language['locale'] ?? $language['language_code'] ?? $language['iso_code'] ?? ''),
            'active' => (int) ($language['active'] ?? 1) === 1,
        ], array_filter($languages, static fn(array $language): bool => (int) ($language['id_lang'] ?? 0) > 0)));
    }

    private function getDefaultShopLanguageId(): int
    {
        $shopId = (int) ($this->context->shop->id ?? 0);
        $defaultLangId = (int) Configuration::get('PS_LANG_DEFAULT', null, null, $shopId ?: null);
        if ($defaultLangId <= 0) {
            $defaultLangId = (int) Configuration::get('PS_LANG_DEFAULT');
        }

        $availableIds = array_map(static fn(array $language): int => (int) ($language['id'] ?? 0), $this->getAdminContentLanguages());
        if ($defaultLangId > 0 && in_array($defaultLangId, $availableIds, true)) {
            return $defaultLangId;
        }

        return (int) ($availableIds[0] ?? (int) ($this->context->language->id ?? 0));
    }

    private function getAdminContentLanguagePayload(): array
    {
        $defaultLangId = $this->getDefaultShopLanguageId();
        foreach ($this->getAdminContentLanguages() as $language) {
            if ((int) ($language['id'] ?? 0) === $defaultLangId) {
                return array_merge($language, [
                    'id' => $defaultLangId,
                    'isDefault' => true,
                ]);
            }
        }

        return [
            'id' => $defaultLangId,
            'name' => '',
            'isoCode' => '',
            'locale' => '',
            'active' => true,
            'isDefault' => true,
        ];
    }

    private function canEditTranslations(): bool
    {
        $license = $this->getLicensePayload();
        $features = is_array($license['availableFeatures'] ?? null) ? $this->featureMap($license['availableFeatures']) : [];

        return !empty($features['translations']['enabled']);
    }

    private function resolveContentLanguageId(int $requestedLangId = 0): int
    {
        $defaultLangId = $this->getDefaultShopLanguageId();
        if (!$this->canEditTranslations()) {
            return $defaultLangId;
        }

        $availableIds = array_map(static fn(array $language): int => (int) ($language['id'] ?? 0), $this->getAdminContentLanguages());

        return $requestedLangId > 0 && in_array($requestedLangId, $availableIds, true)
            ? $requestedLangId
            : $defaultLangId;
    }

    private function getMultistoreContext(): array
    {
        $shopId = (int) ($this->context->shop->id ?? 0);
        $proModule = Module::getInstanceByName('cci_blog_pro');
        if ($proModule instanceof Module && method_exists($proModule, 'getCciMultistoreContext')) {
            return (array) $proModule->getCciMultistoreContext($shopId);
        }

        return [
            'enabled' => false,
            'featureActive' => class_exists('Shop') && Shop::isFeatureActive(),
            'currentShopId' => $shopId,
            'shops' => [],
        ];
    }

    private function handleMultistoreAction(string $action, array $payload): array
    {
        $proModule = Module::getInstanceByName('cci_blog_pro');
        if (!$proModule instanceof Module || !method_exists($proModule, 'handleCciMultistoreAction')) {
            return ['success' => false, 'error' => $this->module->l('CCI Blog Pro with Multistore is required.')];
        }

        return (array) $proModule->handleCciMultistoreAction(
            $action,
            (int) ($this->context->shop->id ?? 0),
            $payload
        );
    }

    private function getLocalMediaLibraryPayload(array $payload): array
    {
        $proModule = Module::getInstanceByName('cci_blog_pro');
        if (!$proModule instanceof Module || !method_exists($proModule, 'handleCciLocalMediaLibraryAction')) {
            return ['success' => false, 'error' => $this->module->l('CCI Blog Pro is required to browse local images.')];
        }

        return (array) $proModule->handleCciLocalMediaLibraryAction(
            (int) ($this->context->shop->id ?? 0),
            $payload
        );
    }

    private function uploadLocalMedia(): array
    {
        $proModule = Module::getInstanceByName('cci_blog_pro');
        if (!$proModule instanceof Module || !method_exists($proModule, 'handleCciLocalMediaUploadAction')) {
            return ['success' => false, 'error' => $this->module->l('CCI Blog Pro is required to upload blog images.')];
        }

        $file = is_array($_FILES['image'] ?? null) ? $_FILES['image'] : [];

        return (array) $proModule->handleCciLocalMediaUploadAction(
            (int) ($this->context->shop->id ?? 0),
            (int) ($this->context->employee->id ?? 0),
            $file
        );
    }

    private function getAdminDateConfig(): array
    {
        $language = $this->context->language;
        $dateFormat = (string) ($language->date_format_lite ?? 'Y-m-d');
        $dateTimeFormat = (string) ($language->date_format_full ?? '');

        return [
            'locale' => (string) ($language->locale ?? $language->language_code ?? $language->iso_code ?? 'en-US'),
            'dateFormat' => $dateFormat,
            'timeFormat' => 'H:i',
            'dateTimeFormat' => $dateTimeFormat !== '' ? $dateTimeFormat : trim($dateFormat . ' H:i'),
            'timeZone' => (string) (Configuration::get('PS_TIMEZONE') ?: date_default_timezone_get()),
        ];
    }

    private function getMarketplaceFeedEndpoint(): string
    {
        $base = $this->getMarketplaceApiBase();

        $locale = strtolower(substr((string) ($this->context->language->iso_code ?? 'en'), 0, 2));
        if (!in_array($locale, ['en', 'pl'], true)) {
            $locale = 'en';
        }

        return $base
            . '/api/products/' . rawurlencode(self::MARKETPLACE_PRODUCT_SLUG) . '/plugin-feed/?'
            . http_build_query(['locale' => $locale, 'limit' => 20]);
    }

    private function getMarketplaceApiBase(): string
    {
        $base = trim((string) getenv(self::MARKETPLACE_API_BASE_OVERRIDE));
        if ($base === '') {
            $base = self::MARKETPLACE_API_BASE;
        }

        $parts = parse_url($base);
        if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host'])) {
            $base = self::MARKETPLACE_API_BASE;
        }

        return rtrim($base, '/');
    }

    private function getAdminExtensionScripts(): array
    {
        $raw = Hook::exec('displayCciBlogAdminAssets', [
            'shop_id' => (int) $this->context->shop->id,
        ], null, true);

        $scripts = [];
        foreach ((array) $raw as $moduleAssets) {
            $assets = is_array($moduleAssets) ? $moduleAssets : [];
            foreach ((array) ($assets['scripts'] ?? []) as $script) {
                if (!is_array($script) || empty($script['src'])) {
                    continue;
                }

                $scripts[] = [
                    'src' => (string) $script['src'],
                    'version' => (string) ($script['version'] ?? $this->module->version),
                ];
            }
        }

        return $scripts;
    }

    private function getStats(): array
    {
        $db = Db::getInstance();
        $langId = $this->getDefaultShopLanguageId();
        $shopId = (int) $this->context->shop->id;

        $postScope = '
             FROM `' . _DB_PREFIX_ . 'cci_blog_post` p
             INNER JOIN `' . _DB_PREFIX_ . 'cci_blog_post_lang` pl
                ON pl.id_post = p.id_post AND pl.id_lang = ' . $langId . ' AND pl.id_shop = ' . $shopId . '
             INNER JOIN `' . _DB_PREFIX_ . 'cci_blog_post_shop` ps
                ON ps.id_post = p.id_post AND ps.id_shop = ' . $shopId;

        $categoryScope = '
             FROM `' . _DB_PREFIX_ . 'cci_blog_category` c
             INNER JOIN `' . _DB_PREFIX_ . 'cci_blog_category_lang` cl
                ON cl.id_category = c.id_category AND cl.id_lang = ' . $langId . ' AND cl.id_shop = ' . $shopId . '
             INNER JOIN `' . _DB_PREFIX_ . 'cci_blog_category_shop` cs
                ON cs.id_category = c.id_category AND cs.id_shop = ' . $shopId;

        return [
            'posts' => (int) $db->getValue('SELECT COUNT(DISTINCT p.id_post)' . $postScope),
            'activePosts' => (int) $db->getValue('SELECT COUNT(DISTINCT p.id_post)' . $postScope . ' WHERE p.active = 1'),
            'categories' => (int) $db->getValue('SELECT COUNT(DISTINCT c.id_category)' . $categoryScope),
            'pendingComments' => (int) $db->getValue(
                'SELECT COUNT(*)
                 FROM `' . _DB_PREFIX_ . 'cci_blog_comment` c
                 INNER JOIN `' . _DB_PREFIX_ . 'cci_blog_post_shop` ps
                    ON ps.id_post = c.id_post AND ps.id_shop = ' . $shopId . '
                 WHERE c.status = "pending"'
            ),
            'commentsEnabled' => (int) Configuration::get('CCB_COMMENTS_ENABLED') === 1,
            'blockReady' => $this->hasContentBlocksColumn(),
        ];
    }

    private function getAdminPosts(): array
    {
        return $this->getAdminPostsPayload()['posts'];
    }

    private function getAdminPostsPayload(array $params = []): array
    {
        $langId = $this->getDefaultShopLanguageId();
        $shopId = (int) $this->context->shop->id;
        $db = Db::getInstance();
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = max(5, min(100, (int) ($params['limit'] ?? 10)));
        $offset = ($page - 1) * $limit;
        $query = trim((string) ($params['query'] ?? ''));
        $sort = $this->normalizePostTableSort((string) ($params['sort'] ?? 'updated'));
        $direction = strtolower((string) ($params['direction'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $advancedSearch = $this->isProPlanActive();
        $authorExpression = 'COALESCE(
            (
                SELECT NULLIF(a.display_name, "")
                FROM `' . _DB_PREFIX_ . 'cci_blog_author` a
                WHERE a.id_employee = p.id_author AND a.active = 1
                ORDER BY a.id_author DESC
                LIMIT 1
            ),
            NULLIF(TRIM(CONCAT(e.firstname, " ", e.lastname)), ""),
            "-"
        )';
        $categoryNamesExpression = '(
            SELECT GROUP_CONCAT(DISTINCT pcl.name ORDER BY pc.is_primary DESC, pc.position ASC, pcl.name ASC SEPARATOR ", ")
            FROM `' . _DB_PREFIX_ . 'cci_blog_post_category` pc
            INNER JOIN `' . _DB_PREFIX_ . 'cci_blog_category_lang` pcl
                ON pcl.id_category = pc.id_category AND pcl.id_lang = ' . $langId . ' AND pcl.id_shop = ' . $shopId . '
            WHERE pc.id_post = p.id_post
        )';
        $fromSql = ' FROM `' . _DB_PREFIX_ . 'cci_blog_post` p
             INNER JOIN `' . _DB_PREFIX_ . 'cci_blog_post_lang` pl
                ON pl.id_post = p.id_post AND pl.id_lang = ' . $langId . ' AND pl.id_shop = ' . $shopId . '
             INNER JOIN `' . _DB_PREFIX_ . 'cci_blog_post_shop` ps
                ON ps.id_post = p.id_post AND ps.id_shop = ' . $shopId . '
             LEFT JOIN `' . _DB_PREFIX_ . 'cci_blog_category_lang` cl
                ON cl.id_category = p.id_category AND cl.id_lang = ' . $langId . ' AND cl.id_shop = ' . $shopId . '
             LEFT JOIN `' . _DB_PREFIX_ . 'employee` e
                ON e.id_employee = p.id_author';
        $where = [];

        if ($query !== '') {
            $like = $this->sqlUtf8mb4LikePattern($query);
            if ($advancedSearch) {
                $where[] = '(' . $this->sqlUtf8mb4SearchExpression('pl.title') . ' LIKE ' . $like . '
                OR ' . $this->sqlUtf8mb4SearchExpression('pl.slug') . ' LIKE ' . $like . '
                OR ' . $this->sqlUtf8mb4SearchExpression('pl.intro') . ' LIKE ' . $like . '
                OR ' . $this->sqlUtf8mb4SearchExpression('pl.meta_title') . ' LIKE ' . $like . '
                OR ' . $this->sqlUtf8mb4SearchExpression('pl.meta_description') . ' LIKE ' . $like . '
                OR ' . $this->sqlUtf8mb4SearchExpression('pl.focus_keyword') . ' LIKE ' . $like . '
                OR ' . $this->sqlUtf8mb4SearchExpression('cl.name') . ' LIKE ' . $like . '
                OR ' . $this->sqlUtf8mb4SearchExpression($authorExpression) . ' LIKE ' . $like . '
                OR EXISTS (
                    SELECT 1
                    FROM `' . _DB_PREFIX_ . 'cci_blog_post_category` pc_search
                    INNER JOIN `' . _DB_PREFIX_ . 'cci_blog_category_lang` pcl_search
                        ON pcl_search.id_category = pc_search.id_category AND pcl_search.id_lang = ' . $langId . ' AND pcl_search.id_shop = ' . $shopId . '
                    WHERE pc_search.id_post = p.id_post
                    AND ' . $this->sqlUtf8mb4SearchExpression('pcl_search.name') . ' LIKE ' . $like . '
                ))';
            } else {
                $where[] = $this->sqlUtf8mb4SearchExpression('pl.title') . ' LIKE ' . $like;
            }
        }

        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $orderSql = $this->postTableOrderBySql($sort, $direction, $authorExpression, $categoryNamesExpression);
        $total = (int) $db->getValue('SELECT COUNT(DISTINCT p.id_post)' . $fromSql . $whereSql);
        $totalPages = max(1, (int) ceil($total / $limit));
        if ($page > $totalPages) {
            $page = $totalPages;
            $offset = ($page - 1) * $limit;
        }

        $selectSql = 'SELECT p.id_post, p.id_category, p.id_author, p.active, p.featured, p.allow_comments, p.views, p.date_published, p.date_add, p.date_upd,
                    pl.title, pl.slug, pl.intro, pl.content_blocks,
                    pl.meta_title, pl.meta_description, pl.focus_keyword, pl.seo_content_type, pl.seo_score,
                    cl.name AS category_name,
                    ' . $authorExpression . ' AS author_name,
                    ' . $categoryNamesExpression . ' AS category_names,
                    CASE WHEN COALESCE(pl.content_blocks, "") = "" THEN 0 ELSE 1 END AS has_blocks';

        $posts = $db->executeS(
            $selectSql
            . $fromSql
            . $whereSql
            . ' ORDER BY ' . $orderSql . ', p.id_post DESC'
            . ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset
        ) ?: [];
        $this->hydrateAdminPostTableRows($posts);

        return [
            'success' => true,
            'posts' => $posts,
            'postsPagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'totalPages' => $totalPages,
                'sort' => $sort,
                'direction' => strtolower($direction),
                'query' => $query,
            ],
        ];
    }

    private function normalizePostTableSort(string $sort): string
    {
        return in_array($sort, ['title', 'author', 'category', 'status', 'seo', 'updated'], true) ? $sort : 'updated';
    }

    private function sqlUtf8mb4LikePattern(string $query): string
    {
        return '\'%'
            . pSQL(str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query), true)
            . '%\' COLLATE utf8mb4_general_ci ESCAPE \'\\\\\'';
    }

    private function sqlUtf8mb4SearchExpression(string $expression): string
    {
        return 'CONVERT((' . $expression . ') USING utf8mb4) COLLATE utf8mb4_general_ci';
    }

    private function postTableOrderBySql(string $sort, string $direction, string $authorExpression, string $categoryNamesExpression): string
    {
        return match ($sort) {
            'title' => 'pl.title ' . $direction,
            'author' => $authorExpression . ' ' . $direction,
            'category' => 'COALESCE(NULLIF(' . $categoryNamesExpression . ', ""), NULLIF(cl.name, ""), "") ' . $direction,
            'status' => 'p.active ' . $direction,
            'seo' => 'pl.seo_score ' . $direction,
            default => 'COALESCE(p.date_upd, p.date_published, p.date_add) ' . $direction,
        };
    }

    private function hydrateAdminPostTableRows(array &$posts): void
    {
        foreach ($posts as &$post) {
            $post['blocks'] = $this->decodeBlocks((string) ($post['content_blocks'] ?? ''));
            unset($post['content_blocks']);
            $post['seo_score'] = max(0, min(100, (int) ($post['seo_score'] ?? 0)));
        }
        unset($post);
    }

    private function calculateAdminPostSeoScore(array $post): int
    {
        $keyword = trim((string) ($post['focus_keyword'] ?? ''));
        $title = trim((string) (($post['meta_title'] ?? '') ?: ($post['title'] ?? '')));
        $meta = trim((string) ($post['meta_description'] ?? ''));
        $slug = trim((string) (($post['slug'] ?? '') ?: ($post['title'] ?? '')));
        $content = $this->extractAdminPostSeoContent($post);
        $words = $this->splitSeoWords($content['text']);
        $keywordWords = $this->splitSeoWords($keyword);
        $slugWords = $this->splitSeoWords(str_replace('-', ' ', $slug));
        $titleLength = Tools::strlen($title);
        $metaLength = Tools::strlen($meta);
        $h1Count = ((string) ($post['title'] ?? '') !== '' ? 1 : 0) + (int) ($content['h1_count'] ?? 0);
        $earned = 0.0;
        $total = 0;

        $add = static function (string $status, int $weight) use (&$earned, &$total): void {
            $total += $weight;
            $earned += $weight * match ($status) {
                'good' => 1.0,
                'warning' => 0.5,
                'info' => 0.35,
                default => 0.0,
            };
        };

        $keywordInTitle = $this->containsSeoKeyword($title, $keywordWords);
        $keywordInMeta = $this->containsSeoKeyword($meta, $keywordWords);
        $keywordInSlug = $this->containsSeoKeyword(str_replace('-', ' ', $slug), $keywordWords);
        $keywordInFirstParagraph = $this->containsSeoKeyword((string) ($content['first_paragraph'] ?? ''), $keywordWords);
        $keywordInH2 = false;
        foreach (($content['h2'] ?? []) as $heading) {
            if ($this->containsSeoKeyword((string) $heading, $keywordWords)) {
                $keywordInH2 = true;
                break;
            }
        }

        $add($keyword !== '' ? 'good' : 'bad', 8);
        $add($keyword !== '' && $keywordInTitle ? 'good' : 'bad', 12);
        $add($keyword !== '' ? ($keywordInMeta ? 'good' : 'warning') : 'bad', 8);
        $add($keyword !== '' ? ($keywordInSlug ? 'good' : 'warning') : 'bad', 8);
        $add($keyword !== '' ? ($keywordInFirstParagraph ? 'good' : 'warning') : 'bad', 8);
        $add($keyword !== '' ? ($keywordInH2 ? 'good' : 'warning') : 'bad', 8);
        $add($titleLength >= 45 && $titleLength <= 65 ? 'good' : ($titleLength >= 30 && $titleLength <= 75 ? 'warning' : 'bad'), 12);
        $add($keyword !== '' && $keywordInTitle && $this->keywordStartsEarly($title, $keywordWords) ? 'good' : ($keywordInTitle ? 'warning' : 'bad'), 6);
        $add($metaLength >= 120 && $metaLength <= 158 ? 'good' : ($metaLength >= 90 && $metaLength <= 175 ? 'warning' : 'bad'), 10);
        $add($meta !== '' && preg_match('/[.!?]$/u', $meta) ? 'good' : ($meta !== '' ? 'warning' : 'bad'), 4);
        $add($slug !== '' && Tools::strlen($slug) <= 75 && count($slugWords) <= 8 ? 'good' : ($slug !== '' ? 'warning' : 'bad'), 6);
        $add('good', 2);
        $add($this->contentLengthStatus(count($words)), 8);
        $add($h1Count === 1 ? 'good' : 'bad', 8);
        $add($this->hasValidSeoHeadingHierarchy($content['headings'] ?? []) ? 'good' : 'warning', 8);
        $add($keyword !== '' ? ($this->keywordDensityStatus($words, $keywordWords) ?: 'bad') : 'bad', 4);
        $add(((int) ($content['internal_links'] ?? 0)) > 0 ? 'good' : 'warning', 6);
        $add(((int) ($content['external_links'] ?? 0)) > 0 ? 'good' : 'info', 4);
        $add(((int) ($content['images'] ?? 0)) === 0 ? 'info' : (((int) ($content['missing_alt'] ?? 0)) === 0 ? 'good' : 'bad'), 6);
        $add(((int) ($content['images'] ?? 0)) === 0 ? 'info' : 'good', 4);
        $add('info', 5);

        return $total > 0 ? max(0, min(100, (int) round(($earned / $total) * 100))) : 0;
    }

    private function extractAdminPostSeoContent(array $post): array
    {
        $blocks = is_array($post['blocks'] ?? null) ? $post['blocks'] : [];
        $parts = [];
        $paragraphs = [];
        $headings = [];
        $h2 = [];
        $images = 0;
        $missingAlt = 0;
        $internalLinks = 0;
        $externalLinks = 0;

        $walk = function ($block) use (&$walk, &$parts, &$paragraphs, &$headings, &$h2, &$images, &$missingAlt, &$internalLinks, &$externalLinks): void {
            if (!is_array($block)) {
                return;
            }

            $type = $this->adminSeoScalarString($block['type'] ?? $block['kind'] ?? '');
            $text = trim(strip_tags($this->firstAdminSeoScalarString($block, ['text', 'content', 'caption'])));
            if ($text !== '') {
                $parts[] = $text;
                if ($type === 'paragraph' || $type === '') {
                    $paragraphs[] = $text;
                }
            }

            if ($type === 'heading') {
                $level = (int) ($block['level'] ?? 2);
                $headings[] = ['level' => $level, 'text' => $text];
                if ($level === 2) {
                    $h2[] = $text;
                }
            }

            $src = $this->firstAdminSeoScalarString($block, ['src', 'image', 'image_url']);
            if ($src === '' && $type === 'image') {
                $src = $this->adminSeoHrefString($block['url'] ?? '');
            }
            if ($type === 'image' || $src !== '') {
                $images++;
                if (trim($this->adminSeoScalarString($block['alt'] ?? '')) === '') {
                    $missingAlt++;
                }
            }

            foreach (['href', 'url', 'link', 'button_url'] as $linkKey) {
                $href = $this->adminSeoHrefString($block[$linkKey] ?? '');
                if ($href === '') {
                    continue;
                }
                if (preg_match('#^https?://#i', $href)) {
                    $host = parse_url($href, PHP_URL_HOST);
                    if ($host && isset($this->context->shop) && stripos($host, (string) $this->context->shop->domain) === false) {
                        $externalLinks++;
                    } else {
                        $internalLinks++;
                    }
                } else {
                    $internalLinks++;
                }
            }

            foreach (['items', 'children', 'blocks', 'columns'] as $childrenKey) {
                if (!empty($block[$childrenKey]) && is_array($block[$childrenKey])) {
                    foreach ($block[$childrenKey] as $child) {
                        $walk($child);
                    }
                }
            }
        };

        foreach ($blocks as $block) {
            $walk($block);
        }

        if ($parts === []) {
            $html = (string) ($post['content'] ?? '');
            $plainText = trim(strip_tags($html));
            if ($plainText !== '') {
                $parts[] = $plainText;

                if (preg_match('/<p\b[^>]*>(.*?)<\/p>/is', $html, $match)) {
                    $firstParagraph = trim(strip_tags((string) ($match[1] ?? '')));
                    $paragraphs[] = $firstParagraph !== '' ? $firstParagraph : $plainText;
                } else {
                    $paragraphs[] = $plainText;
                }

                if (preg_match_all('/<h([1-6])\b[^>]*>(.*?)<\/h\1>/is', $html, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $level = (int) ($match[1] ?? 2);
                        $text = trim(strip_tags((string) ($match[2] ?? '')));
                        $headings[] = ['level' => $level, 'text' => $text];
                        if ($level === 2) {
                            $h2[] = $text;
                        }
                    }
                }

                if (preg_match_all('/<img\b[^>]*>/i', $html, $matches)) {
                    $images = count($matches[0]);
                    foreach ($matches[0] as $imageTag) {
                        if (!preg_match('/\salt\s*=\s*["\'][^"\']+["\']/i', (string) $imageTag)) {
                            $missingAlt++;
                        }
                    }
                }

                if (preg_match_all('/<a\b[^>]*\shref\s*=\s*["\']([^"\']+)["\']/i', $html, $matches)) {
                    foreach ($matches[1] as $href) {
                        if (preg_match('#^https?://#i', (string) $href)) {
                            $host = parse_url((string) $href, PHP_URL_HOST);
                            if ($host && isset($this->context->shop) && stripos($host, (string) $this->context->shop->domain) === false) {
                                $externalLinks++;
                            } else {
                                $internalLinks++;
                            }
                        } else {
                            $internalLinks++;
                        }
                    }
                }
            }
        }

        return [
            'text' => implode(' ', $parts),
            'first_paragraph' => (string) ($paragraphs[0] ?? ''),
            'headings' => $headings,
            'h1_count' => count(array_filter($headings, static fn (array $heading): bool => (int) ($heading['level'] ?? 0) === 1)),
            'h2' => $h2,
            'images' => $images,
            'missing_alt' => $missingAlt,
            'internal_links' => $internalLinks,
            'external_links' => $externalLinks,
        ];
    }

    private function firstAdminSeoScalarString(array $source, array $keys): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $source)) {
                $value = $this->adminSeoScalarString($source[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }

    private function adminSeoHrefString($value): string
    {
        $scalarValue = $this->adminSeoScalarString($value);
        if ($scalarValue !== '') {
            return $scalarValue;
        }

        if (!is_array($value)) {
            return '';
        }

        foreach (['href', 'url', 'link', 'button_url'] as $key) {
            if (array_key_exists($key, $value)) {
                $href = $this->adminSeoHrefString($value[$key]);
                if ($href !== '') {
                    return $href;
                }
            }
        }

        return '';
    }

    private function adminSeoScalarString($value): string
    {
        if (is_scalar($value)) {
            return trim((string) $value);
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return trim((string) $value);
        }

        return '';
    }

    private function splitSeoWords(string $text): array
    {
        $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
        preg_match_all('/[\p{L}\p{N}]+/u', $text, $matches);

        return array_values(array_filter($matches[0] ?? [], static fn (string $word): bool => Tools::strlen($word) > 1));
    }

    private function containsSeoKeyword(string $text, array $keywordWords): bool
    {
        if (!$keywordWords) {
            return false;
        }

        $haystack = ' ' . implode(' ', $this->splitSeoWords($text)) . ' ';
        $needle = ' ' . implode(' ', $keywordWords) . ' ';

        return strpos($haystack, $needle) !== false;
    }

    private function keywordStartsEarly(string $text, array $keywordWords): bool
    {
        if (!$keywordWords) {
            return false;
        }

        $words = $this->splitSeoWords($text);
        $needle = implode(' ', $keywordWords);

        return strpos(' ' . implode(' ', array_slice($words, 0, 6)) . ' ', ' ' . $needle . ' ') !== false;
    }

    private function contentLengthStatus(int $wordCount): string
    {
        if ($wordCount >= 900) {
            return 'good';
        }

        if ($wordCount >= 600) {
            return 'warning';
        }

        return 'bad';
    }

    private function keywordDensityStatus(array $words, array $keywordWords): string
    {
        if (!$words || !$keywordWords) {
            return 'bad';
        }

        $needle = implode(' ', $keywordWords);
        $occurrences = 0;
        $windowSize = count($keywordWords);
        $max = count($words) - $windowSize;
        for ($index = 0; $index <= $max; $index++) {
            if (implode(' ', array_slice($words, $index, $windowSize)) === $needle) {
                $occurrences++;
            }
        }

        $density = ($occurrences / count($words)) * 100;

        if ($density >= 0.3 && $density <= 2.5) {
            return 'good';
        }

        return $density > 0 ? 'warning' : 'bad';
    }

    private function hasValidSeoHeadingHierarchy(array $headings): bool
    {
        $previous = 1;
        foreach ($headings as $heading) {
            $level = (int) ($heading['level'] ?? 0);
            if ($level <= 0) {
                continue;
            }
            if ($level > $previous + 1) {
                return false;
            }
            $previous = $level;
        }

        return true;
    }

    private function getAdminSlugRedirects(): array
    {
        $langId = $this->getDefaultShopLanguageId();
        $shopId = (int) $this->context->shop->id;

        return Db::getInstance()->executeS(
            'SELECT r.id_redirect, r.entity_type, r.old_slug, r.id_post, r.id_category, r.date_add,
                    COALESCE(pl.slug, cl.slug) AS current_slug,
                    COALESCE(pl.title, cl.name) AS title,
                    CASE WHEN r.entity_type = "category" THEN r.id_category ELSE r.id_post END AS target_id
             FROM `' . _DB_PREFIX_ . 'cci_blog_slug_redirect` r
             LEFT JOIN `' . _DB_PREFIX_ . 'cci_blog_post_shop` ps
                ON r.entity_type = "post" AND ps.id_post = r.id_post AND ps.id_shop = ' . $shopId . '
             LEFT JOIN `' . _DB_PREFIX_ . 'cci_blog_post_lang` pl
                ON r.entity_type = "post" AND pl.id_post = r.id_post AND pl.id_lang = ' . $langId . ' AND pl.id_shop = ' . $shopId . '
             LEFT JOIN `' . _DB_PREFIX_ . 'cci_blog_category_shop` cs
                ON r.entity_type = "category" AND cs.id_category = r.id_category AND cs.id_shop = ' . $shopId . '
             LEFT JOIN `' . _DB_PREFIX_ . 'cci_blog_category_lang` cl
                ON r.entity_type = "category" AND cl.id_category = r.id_category AND cl.id_lang = ' . $langId . ' AND cl.id_shop = ' . $shopId . '
             WHERE r.id_lang = ' . $langId . '
             AND ((r.entity_type = "post" AND ps.id_post IS NOT NULL)
                  OR (r.entity_type = "category" AND cs.id_category IS NOT NULL))
             ORDER BY r.date_add DESC, r.id_redirect DESC
             LIMIT 250'
        ) ?: [];
    }

    private function updateSlugRedirect(array $payload): array
    {
        $redirectId = (int) ($payload['id_redirect'] ?? 0);
        $oldSlug = preg_replace('/\.html$/i', '', trim((string) ($payload['old_slug'] ?? ''))) ?: '';
        $oldSlug = trim(Tools::str2url($oldSlug), '-');

        if ($redirectId <= 0 || $oldSlug === '') {
            return ['success' => false, 'error' => $this->module->l('A valid old slug is required.')];
        }

        $redirect = $this->getScopedSlugRedirect($redirectId);
        if (!$redirect) {
            return ['success' => false, 'error' => $this->module->l('Slug redirect was not found.')];
        }

        if ($oldSlug === (string) ($redirect['current_slug'] ?? '')) {
            return ['success' => false, 'error' => $this->module->l('The old slug cannot match the current canonical slug.')];
        }

        $duplicateId = (int) Db::getInstance()->getValue(
            'SELECT id_redirect FROM `' . _DB_PREFIX_ . 'cci_blog_slug_redirect`'
            . ' WHERE old_slug = "' . pSQL($oldSlug) . '"'
            . ' AND id_lang = ' . (int) $redirect['id_lang']
            . ' AND entity_type = "' . pSQL((string) $redirect['entity_type']) . '"'
            . ' AND id_redirect != ' . $redirectId
            . ' ORDER BY id_redirect ASC'
        );
        if ($duplicateId > 0) {
            return ['success' => false, 'error' => $this->module->l('This old slug is already registered.')];
        }

        if (!Db::getInstance()->update(
            'cci_blog_slug_redirect',
            ['old_slug' => pSQL($oldSlug)],
            'id_redirect = ' . $redirectId
        )) {
            throw new RuntimeException($this->module->l('Could not update the slug redirect.'));
        }

        return [
            'success' => true,
            'redirects' => $this->getAdminSlugRedirects(),
            'message' => $this->module->l('Slug redirect was updated.'),
        ];
    }

    private function deleteSlugRedirect(array $payload): array
    {
        $redirectId = (int) ($payload['id_redirect'] ?? 0);
        if ($redirectId <= 0 || !$this->getScopedSlugRedirect($redirectId)) {
            return ['success' => false, 'error' => $this->module->l('Slug redirect was not found.')];
        }

        if (!Db::getInstance()->delete('cci_blog_slug_redirect', 'id_redirect = ' . $redirectId)) {
            throw new RuntimeException($this->module->l('Could not delete the slug redirect.'));
        }

        return [
            'success' => true,
            'redirects' => $this->getAdminSlugRedirects(),
            'message' => $this->module->l('Slug redirect was deleted.'),
        ];
    }

    private function getScopedSlugRedirect(int $redirectId): array
    {
        $langId = $this->resolveContentLanguageId((int) ($payload['id_lang'] ?? 0));
        $shopId = (int) $this->context->shop->id;

        return Db::getInstance()->getRow(
            'SELECT r.id_redirect, r.entity_type, r.id_post, r.id_category, r.id_lang, r.old_slug,
                    COALESCE(pl.slug, cl.slug) AS current_slug
             FROM `' . _DB_PREFIX_ . 'cci_blog_slug_redirect` r
             LEFT JOIN `' . _DB_PREFIX_ . 'cci_blog_post_shop` ps
                ON r.entity_type = "post" AND ps.id_post = r.id_post AND ps.id_shop = ' . $shopId . '
             LEFT JOIN `' . _DB_PREFIX_ . 'cci_blog_post_lang` pl
                ON r.entity_type = "post" AND pl.id_post = r.id_post AND pl.id_lang = r.id_lang AND pl.id_shop = ' . $shopId . '
             LEFT JOIN `' . _DB_PREFIX_ . 'cci_blog_category_shop` cs
                ON r.entity_type = "category" AND cs.id_category = r.id_category AND cs.id_shop = ' . $shopId . '
             LEFT JOIN `' . _DB_PREFIX_ . 'cci_blog_category_lang` cl
                ON r.entity_type = "category" AND cl.id_category = r.id_category AND cl.id_lang = r.id_lang AND cl.id_shop = ' . $shopId . '
             WHERE r.id_redirect = ' . $redirectId . ' AND r.id_lang = ' . $langId . '
             AND ((r.entity_type = "post" AND ps.id_post IS NOT NULL)
                  OR (r.entity_type = "category" AND cs.id_category IS NOT NULL))'
        ) ?: [];
    }

    private function getAdminAuthors(): array
    {
        $rows = Db::getInstance()->executeS(
            'SELECT e.id_employee, e.firstname, e.lastname, e.active,
                    COALESCE(
                        MAX(NULLIF(a.display_name, "")),
                        NULLIF(TRIM(CONCAT(e.firstname, " ", e.lastname)), ""),
                        CONCAT("#", e.id_employee)
                    ) AS name
             FROM `' . _DB_PREFIX_ . 'employee` e
             LEFT JOIN `' . _DB_PREFIX_ . 'cci_blog_author` a
                ON a.id_employee = e.id_employee AND a.active = 1
             GROUP BY e.id_employee, e.firstname, e.lastname, e.active
             ORDER BY e.active DESC, name ASC'
        ) ?: [];

        return array_map(static fn(array $row): array => [
            'id' => (int) ($row['id_employee'] ?? 0),
            'name' => trim((string) ($row['name'] ?? '')),
            'active' => (int) ($row['active'] ?? 0) === 1,
        ], $rows);
    }

    private function getPostPayload(int $postId, int $requestedLangId = 0): array
    {
        $langId = $this->resolveContentLanguageId($requestedLangId);
        if ($postId <= 0) {
            $post = $this->getEmptyPost();
            $post['id_lang'] = $langId;

            return ['success' => true, 'post' => $post];
        }

        $shopId = (int) $this->context->shop->id;
        $defaultLangId = $this->getDefaultShopLanguageId();
        $post = Db::getInstance()->getRow(
            'SELECT p.*,
                    COALESCE(pl.title, pl_default.title, "") AS title,
                    COALESCE(pl.slug, pl_default.slug, "") AS slug,
                    COALESCE(pl.intro, pl_default.intro, "") AS intro,
                    COALESCE(pl.content, pl_default.content, "") AS content,
                    COALESCE(pl.content_blocks, pl_default.content_blocks, "") AS content_blocks,
                    COALESCE(pl.meta_title, pl_default.meta_title, "") AS meta_title,
                    COALESCE(pl.meta_description, pl_default.meta_description, "") AS meta_description,
                    COALESCE(pl.meta_keywords, pl_default.meta_keywords, "") AS meta_keywords,
                    COALESCE(pl.focus_keyword, pl_default.focus_keyword, "") AS focus_keyword,
                    COALESCE(pl.seo_content_type, pl_default.seo_content_type, "article") AS seo_content_type,
                    COALESCE(pl.seo_score, pl_default.seo_score, 0) AS seo_score,
                    COALESCE(pl.og_title, pl_default.og_title, "") AS og_title,
                    COALESCE(pl.og_description, pl_default.og_description, "") AS og_description,
                    COALESCE(
                        (
                            SELECT NULLIF(a.display_name, "")
                            FROM `' . _DB_PREFIX_ . 'cci_blog_author` a
                            WHERE a.id_employee = p.id_author AND a.active = 1
                            ORDER BY a.id_author DESC
                            LIMIT 1
                        ),
                        NULLIF(TRIM(CONCAT(e.firstname, " ", e.lastname)), ""),
                        "-"
                    ) AS author_name
             FROM `' . _DB_PREFIX_ . 'cci_blog_post` p
             LEFT JOIN `' . _DB_PREFIX_ . 'cci_blog_post_lang` pl
                ON pl.id_post = p.id_post AND pl.id_lang = ' . $langId . ' AND pl.id_shop = ' . $shopId . '
             LEFT JOIN `' . _DB_PREFIX_ . 'cci_blog_post_lang` pl_default
                ON pl_default.id_post = p.id_post AND pl_default.id_lang = ' . $defaultLangId . ' AND pl_default.id_shop = ' . $shopId . '
             LEFT JOIN `' . _DB_PREFIX_ . 'employee` e
                ON e.id_employee = p.id_author
             WHERE p.id_post = ' . $postId
        );

        if (!$post) {
            return ['success' => false, 'error' => $this->module->l('Post not found.')];
        }

        $post['blocks'] = $this->decodeBlocks((string) ($post['content_blocks'] ?? ''));
        $tags = CciBlogPost::getTags($postId, $langId);
        if ($tags === [] && $langId !== $defaultLangId) {
            $tags = CciBlogPost::getTags($postId, $defaultLangId);
        }
        $post['tags'] = implode(', ', array_map(static fn(array $tag): string => (string) $tag['name'], $tags));
        $post['category_ids'] = $this->getPostCategoryIds($postId, (int) ($post['id_category'] ?? 0));
        $post['id_lang'] = $langId;

        return ['success' => true, 'post' => $post];
    }

    private function getEmptyPost(): array
    {
        return [
            'id_post' => 0,
            'id_category' => 0,
            'id_lang' => $this->getDefaultShopLanguageId(),
            'id_author' => isset($this->context->employee) ? (int) $this->context->employee->id : 0,
            'author_name' => isset($this->context->employee)
                ? trim((string) $this->context->employee->firstname . ' ' . (string) $this->context->employee->lastname)
                : '',
            'category_ids' => [],
            'title' => '',
            'slug' => '',
            'intro' => '',
            'active' => 1,
            'featured' => 0,
            'allow_comments' => 1,
            'cover_image' => '',
            'og_image' => '',
            'date_published' => date('Y-m-d H:i:s'),
            'date_add' => '',
            'meta_title' => '',
            'meta_description' => '',
            'focus_keyword' => '',
            'seo_content_type' => 'article',
            'tags' => '',
            'blocks' => [
                ['type' => 'heading', 'level' => 2, 'text' => $this->module->l('Section heading')],
                ['type' => 'paragraph', 'content' => $this->module->l('Start writing your article here.')],
            ],
        ];
    }

    private function savePost(array $payload): array
    {
        $translationGuard = $this->guardTranslationWrite($payload);
        if ($translationGuard !== null) {
            return $translationGuard;
        }
        $langId = $this->resolveContentLanguageId((int) ($payload['id_lang'] ?? 0));
        $shopId = (int) $this->context->shop->id;
        $postId = (int) ($payload['id_post'] ?? 0);
        $title = trim((string) ($payload['title'] ?? ''));

        if ($title === '') {
            return ['success' => false, 'error' => $this->module->l('Post title is required.')];
        }

        $slug = trim(Tools::str2url((string) ($payload['slug'] ?? '')), '-');
        if ($slug === '') {
            $slug = trim(Tools::str2url($title), '-');
        }

        $primaryCategoryId = max(0, (int) ($payload['id_category'] ?? 0));
        $categoryIds = $this->normalizePostCategoryIds($payload['category_ids'] ?? [], $primaryCategoryId);
        if (!$this->isProPlanActive()) {
            $categoryIds = $primaryCategoryId > 0 ? [$primaryCategoryId] : [];
        }
        $missingCategoryIds = $this->getMissingCategoryIds($categoryIds);

        if ($primaryCategoryId <= 0 && $categoryIds !== []) {
            return ['success' => false, 'error' => $this->module->l('Select the primary category before assigning additional categories.')];
        }

        if ($primaryCategoryId > 0 && in_array($primaryCategoryId, $missingCategoryIds, true)) {
            return ['success' => false, 'error' => $this->module->l('Selected primary category does not exist.')];
        }

        if ($missingCategoryIds !== []) {
            return ['success' => false, 'error' => $this->module->l('One or more selected categories no longer exist.')];
        }

        $blocks = is_array($payload['blocks'] ?? null) ? $payload['blocks'] : [];
        $featureGuard = $this->guardPostFeatureAccess(array_merge($payload, ['blocks' => $blocks]));
        if ($featureGuard !== null) {
            return $featureGuard;
        }

        $slug = $this->makeUniquePostSlug($slug, $postId, $langId, $shopId);

        $previousSlugs = [];
        if ($postId > 0) {
            foreach (Db::getInstance()->executeS(
                'SELECT id_lang, slug FROM `' . _DB_PREFIX_ . 'cci_blog_post_lang`'
                . ' WHERE id_post = ' . $postId . ' AND id_shop = ' . $shopId
            ) ?: [] as $previousSlugRow) {
                $previousSlugs[(int) $previousSlugRow['id_lang']] = (string) $previousSlugRow['slug'];
            }
        }

        $blocksJson = json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $contentHtml = CciBlogPost::renderBlocksToHtml($blocks, $this->context);
        $seoScore = $this->calculateAdminPostSeoScore([
            'title' => $title,
            'slug' => $slug,
            'intro' => (string) ($payload['intro'] ?? ''),
            'blocks' => $blocks,
            'meta_title' => (string) ($payload['meta_title'] ?? ''),
            'meta_description' => (string) ($payload['meta_description'] ?? ''),
            'focus_keyword' => (string) ($payload['focus_keyword'] ?? ''),
            'seo_content_type' => $this->normalizeSeoContentType((string) ($payload['seo_content_type'] ?? 'article')),
        ]);
        $now = date('Y-m-d H:i:s');
        $existingAuthorId = $postId > 0
            ? (int) Db::getInstance()->getValue(
                'SELECT id_author FROM `' . _DB_PREFIX_ . 'cci_blog_post` WHERE id_post = ' . $postId
            )
            : 0;
        $defaultAuthorId = $existingAuthorId > 0
            ? $existingAuthorId
            : (isset($this->context->employee) ? (int) $this->context->employee->id : 0);
        $authorId = array_key_exists('id_author', $payload) ? (int) $payload['id_author'] : $defaultAuthorId;
        $authorIsActive = $authorId > 0 && (bool) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'employee` WHERE id_employee = ' . $authorId . ' AND active = 1'
        );

        if (!$authorIsActive && !($postId > 0 && $authorId === $existingAuthorId)) {
            return ['success' => false, 'error' => $this->module->l('Selected author is not available.')];
        }

        $datePublished = trim((string) ($payload['date_published'] ?? $now));
        if ($datePublished === '') {
            $datePublished = $now;
        }
        if (!Validate::isDateFormat($datePublished)) {
            return ['success' => false, 'error' => $this->module->l('Publication date is invalid.')];
        }

        $postData = [
            'id_category' => $primaryCategoryId,
            'id_author' => $authorId,
            'active' => !empty($payload['active']) ? 1 : 0,
            'featured' => !empty($payload['featured']) ? 1 : 0,
            'allow_comments' => !empty($payload['allow_comments']) ? 1 : 0,
            'views' => max(0, (int) ($payload['views'] ?? 0)),
            'cover_image' => pSQL($this->sanitizeImageUrl((string) ($payload['cover_image'] ?? ''))),
            'og_image' => pSQL($this->sanitizeImageUrl((string) ($payload['og_image'] ?? ''))),
            'date_published' => pSQL($datePublished),
            'date_upd' => $now,
        ];

        if ($postId > 0) {
            Db::getInstance()->update('cci_blog_post', $postData, 'id_post = ' . $postId);
        } else {
            $postData['date_add'] = $now;
            Db::getInstance()->insert('cci_blog_post', $postData);
            $postId = (int) Db::getInstance()->Insert_ID();
            Db::getInstance()->insert('cci_blog_post_shop', [
                'id_post' => $postId,
                'id_shop' => $shopId,
            ], false, true, Db::REPLACE);
        }

        Db::getInstance()->insert('cci_blog_post_lang', [
                'id_post' => $postId,
                'id_lang' => $langId,
                'id_shop' => $shopId,
                'title' => pSQL($title),
                'slug' => pSQL($slug),
                'intro' => pSQL((string) ($payload['intro'] ?? ''), true),
                'content' => pSQL($contentHtml, true),
                'content_blocks' => pSQL((string) $blocksJson, true),
                'meta_title' => pSQL((string) ($payload['meta_title'] ?? '')),
                'meta_description' => pSQL((string) ($payload['meta_description'] ?? '')),
                'meta_keywords' => pSQL((string) ($payload['meta_keywords'] ?? '')),
                'focus_keyword' => pSQL((string) ($payload['focus_keyword'] ?? '')),
                'seo_content_type' => pSQL($this->normalizeSeoContentType((string) ($payload['seo_content_type'] ?? 'article'))),
                'seo_score' => $seoScore,
                'og_title' => pSQL((string) ($payload['og_title'] ?? '')),
                'og_description' => pSQL((string) ($payload['og_description'] ?? '')),
        ], false, true, Db::REPLACE);

        Db::getInstance()->delete(
            'cci_blog_slug_redirect',
            'old_slug = "' . pSQL($slug) . '" AND id_lang = ' . $langId
            . ' AND entity_type = "post" AND id_post = ' . $postId
        );

        $previousSlug = $previousSlugs[$langId] ?? '';
        if ($previousSlug !== '' && $previousSlug !== $slug) {
            CciBlogSeo::saveSlugRedirect($previousSlug, $postId, $langId);
        }

        $this->savePostCategories($postId, $primaryCategoryId, $categoryIds);

        $tags = array_filter(array_map('trim', explode(',', (string) ($payload['tags'] ?? ''))));
        CciBlogTag::setPostTags($postId, $tags, $langId);

        return [
            'success' => true,
            'post' => $this->getPostPayload($postId, $langId)['post'] ?? null,
            'posts' => $this->getAdminPosts(),
            'stats' => $this->getStats(),
        ];
    }

    private function deletePost(array $payload): array
    {
        $postId = (int) ($payload['id_post'] ?? 0);
        if ($postId <= 0) {
            return ['success' => false, 'error' => $this->module->l('Invalid post selected.')];
        }

        $exists = (bool) Db::getInstance()->getValue(
            'SELECT COUNT(*)
             FROM `' . _DB_PREFIX_ . 'cci_blog_post`
             WHERE id_post = ' . $postId
        );
        if (!$exists) {
            return ['success' => false, 'error' => $this->module->l('Post not found.')];
        }

        $this->deleteFromTableIfExists('cci_blog_comment', 'id_post = ' . $postId);
        $this->deleteFromTableIfExists('cci_blog_post_image', 'id_post = ' . $postId);
        $this->deleteFromTableIfExists('cci_blog_post_product', 'id_post = ' . $postId);
        $this->deleteFromTableIfExists('cci_blog_post_tag', 'id_post = ' . $postId);
        $this->deleteFromTableIfExists('cci_blog_slug_redirect', 'id_post = ' . $postId);
        $this->deleteFromTableIfExists('cci_blog_post_category', 'id_post = ' . $postId);
        $this->deleteFromTableIfExists('cci_blog_post_shop', 'id_post = ' . $postId);
        $this->deleteFromTableIfExists('cci_blog_post_lang', 'id_post = ' . $postId);
        $this->deleteFromTableIfExists('cci_blog_post', 'id_post = ' . $postId);

        return [
            'success' => true,
            'posts' => $this->getAdminPosts(),
            'categories' => $this->getAdminCategories(),
            'comments' => $this->getAdminComments(),
            'stats' => $this->getStats(),
        ];
    }

    private function normalizePostCategoryIds(mixed $rawCategoryIds, int $primaryCategoryId): array
    {
        if (is_string($rawCategoryIds)) {
            $rawCategoryIds = array_filter(array_map('trim', explode(',', $rawCategoryIds)));
        }

        if (!is_array($rawCategoryIds)) {
            $rawCategoryIds = [];
        }

        $ids = array_values(array_filter(array_map('intval', $rawCategoryIds), static fn(int $id): bool => $id > 0));

        if ($primaryCategoryId > 0) {
            array_unshift($ids, $primaryCategoryId);
        }

        return array_values(array_unique($ids));
    }

    private function getMissingCategoryIds(array $categoryIds): array
    {
        $categoryIds = array_values(array_unique(array_filter(array_map('intval', $categoryIds), static fn(int $id): bool => $id > 0)));
        if ($categoryIds === []) {
            return [];
        }

        $rows = Db::getInstance()->executeS(
            'SELECT id_category
             FROM `' . _DB_PREFIX_ . 'cci_blog_category`
             WHERE id_category IN (' . implode(',', $categoryIds) . ')'
        ) ?: [];
        $existing = array_map('intval', array_column($rows, 'id_category'));

        return array_values(array_diff($categoryIds, $existing));
    }

    private function getPostCategoryIds(int $postId, int $fallbackCategoryId = 0): array
    {
        if (!$this->hasPostCategoryTable()) {
            return $fallbackCategoryId > 0 ? [$fallbackCategoryId] : [];
        }

        $rows = Db::getInstance()->executeS(
            'SELECT id_category
             FROM `' . _DB_PREFIX_ . 'cci_blog_post_category`
             WHERE id_post = ' . $postId . '
             ORDER BY is_primary DESC, position ASC, id_category ASC'
        ) ?: [];
        $ids = array_values(array_unique(array_map('intval', array_column($rows, 'id_category'))));

        if ($ids === [] && $fallbackCategoryId > 0) {
            return [$fallbackCategoryId];
        }

        return $ids;
    }

    private function savePostCategories(int $postId, int $primaryCategoryId, array $categoryIds): void
    {
        if ($postId <= 0 || !$this->hasPostCategoryTable()) {
            return;
        }

        Db::getInstance()->delete('cci_blog_post_category', 'id_post = ' . $postId);

        if ($primaryCategoryId <= 0) {
            return;
        }

        $categoryIds = $this->normalizePostCategoryIds($categoryIds, $primaryCategoryId);
        foreach ($categoryIds as $position => $categoryId) {
            Db::getInstance()->insert('cci_blog_post_category', [
                'id_post' => $postId,
                'id_category' => $categoryId,
                'is_primary' => $categoryId === $primaryCategoryId ? 1 : 0,
                'position' => $position,
            ], false, true, Db::REPLACE);
        }
    }

    private function getAdminCategories(): array
    {
        $langId = $this->getDefaultShopLanguageId();
        $shopId = (int) $this->context->shop->id;

        $categoryImageSelect = $this->categoryImageColumnAvailable()
            ? 'c.image_url'
            : '"" AS image_url';

        return Db::getInstance()->executeS(
            'SELECT c.id_category, c.id_parent, c.id_author, c.active, c.position, c.date_add, c.date_upd, ' . $categoryImageSelect . ', cl.name, cl.slug, cl.description,
                    cl.meta_title, cl.meta_description, cl.meta_keywords,
                    parent_lang.name AS parent_name,
                    COALESCE(
                        (
                            SELECT NULLIF(a.display_name, "")
                            FROM `' . _DB_PREFIX_ . 'cci_blog_author` a
                            WHERE a.id_employee = c.id_author AND a.active = 1
                            ORDER BY a.id_author DESC
                            LIMIT 1
                        ),
                        NULLIF(TRIM(CONCAT(e.firstname, " ", e.lastname)), ""),
                        "-"
                    ) AS author_name,
                    COUNT(DISTINCT p.id_post) AS post_count
             FROM `' . _DB_PREFIX_ . 'cci_blog_category` c
             INNER JOIN `' . _DB_PREFIX_ . 'cci_blog_category_lang` cl
                ON cl.id_category = c.id_category AND cl.id_lang = ' . $langId . ' AND cl.id_shop = ' . $shopId . '
             LEFT JOIN `' . _DB_PREFIX_ . 'cci_blog_category_lang` parent_lang
                ON parent_lang.id_category = c.id_parent AND parent_lang.id_lang = ' . $langId . ' AND parent_lang.id_shop = ' . $shopId . '
             LEFT JOIN `' . _DB_PREFIX_ . 'employee` e ON e.id_employee = c.id_author
             LEFT JOIN `' . _DB_PREFIX_ . 'cci_blog_post_category` pc ON pc.id_category = c.id_category
             LEFT JOIN `' . _DB_PREFIX_ . 'cci_blog_post` p ON p.id_post = pc.id_post
             GROUP BY c.id_category
             ORDER BY c.position ASC, cl.name ASC'
        ) ?: [];
    }

    private function saveCategory(array $payload): array
    {
        $translationGuard = $this->guardTranslationWrite($payload);
        if ($translationGuard !== null) {
            return $translationGuard;
        }
        $langId = $this->resolveContentLanguageId((int) ($payload['id_lang'] ?? 0));
        $shopId = (int) $this->context->shop->id;
        $categoryId = (int) ($payload['id_category'] ?? 0);
        $name = trim((string) ($payload['name'] ?? ''));
        $previousSlugs = [];
        if ($categoryId > 0) {
            foreach (Db::getInstance()->executeS(
                'SELECT id_lang, slug FROM `' . _DB_PREFIX_ . 'cci_blog_category_lang`'
                . ' WHERE id_category = ' . $categoryId . ' AND id_shop = ' . $shopId
            ) ?: [] as $previousSlugRow) {
                $previousSlugs[(int) $previousSlugRow['id_lang']] = (string) $previousSlugRow['slug'];
            }
        }

        if ($name === '') {
            return ['success' => false, 'error' => $this->module->l('Category name is required.')];
        }

        $slug = Tools::str2url((string) ($payload['slug'] ?? ''));
        if ($slug === '') {
            $slug = Tools::str2url($name);
        }

        $parentId = max(0, (int) ($payload['id_parent'] ?? 0));
        if ($categoryId > 0 && $parentId === $categoryId) {
            return ['success' => false, 'error' => $this->module->l('A category cannot be its own parent.')];
        }
        if ($parentId > 0 && $this->getMissingCategoryIds([$parentId]) !== []) {
            return ['success' => false, 'error' => $this->module->l('Selected parent category does not exist.')];
        }
        if ($categoryId > 0 && $this->categoryParentWouldCreateCycle($categoryId, $parentId)) {
            return ['success' => false, 'error' => $this->module->l('Selected parent would create an invalid category tree.')];
        }

        $now = date('Y-m-d H:i:s');
        $existingAuthorId = $categoryId > 0
            ? (int) Db::getInstance()->getValue(
                'SELECT id_author FROM `' . _DB_PREFIX_ . 'cci_blog_category` WHERE id_category = ' . $categoryId
            )
            : 0;
        $defaultAuthorId = $existingAuthorId > 0
            ? $existingAuthorId
            : (isset($this->context->employee) ? (int) $this->context->employee->id : 0);
        $authorId = array_key_exists('id_author', $payload) ? (int) $payload['id_author'] : $defaultAuthorId;
        $authorIsActive = $authorId > 0 && (bool) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'employee` WHERE id_employee = ' . $authorId . ' AND active = 1'
        );

        if (!$authorIsActive && !($categoryId > 0 && $authorId === $existingAuthorId)) {
            return ['success' => false, 'error' => $this->module->l('Selected author is not available.')];
        }

        $categoryData = [
            'id_parent' => $parentId,
            'id_author' => $authorId,
            'active' => !empty($payload['active']) ? 1 : 0,
            'position' => max(0, (int) ($payload['position'] ?? 0)),
            'date_upd' => $now,
        ];
        if ($this->categoryImageColumnAvailable()) {
            $categoryData['image_url'] = pSQL($this->sanitizeImageUrl((string) ($payload['image_url'] ?? '')));
        }

        if ($categoryId > 0) {
            Db::getInstance()->update('cci_blog_category', $categoryData, 'id_category = ' . $categoryId);
        } else {
            $categoryData['date_add'] = $now;
            Db::getInstance()->insert('cci_blog_category', $categoryData);
            $categoryId = (int) Db::getInstance()->Insert_ID();
            Db::getInstance()->insert('cci_blog_category_shop', [
                'id_category' => $categoryId,
                'id_shop' => $shopId,
            ], false, true, Db::REPLACE);
        }

        Db::getInstance()->insert('cci_blog_category_lang', [
                'id_category' => $categoryId,
                'id_lang' => $langId,
                'id_shop' => $shopId,
                'name' => pSQL($name),
                'slug' => pSQL($slug),
                'description' => pSQL((string) ($payload['description'] ?? ''), true),
                'meta_title' => pSQL((string) ($payload['meta_title'] ?? '')),
                'meta_description' => pSQL((string) ($payload['meta_description'] ?? '')),
                'meta_keywords' => pSQL((string) ($payload['meta_keywords'] ?? '')),
        ], false, true, Db::REPLACE);

        Db::getInstance()->delete(
            'cci_blog_slug_redirect',
            'entity_type = "category" AND old_slug = "' . pSQL($slug) . '"'
            . ' AND id_lang = ' . $langId . ' AND id_category = ' . $categoryId
        );
        $previousSlug = $previousSlugs[$langId] ?? '';
        if ($previousSlug !== '' && $previousSlug !== $slug) {
            CciBlogSeo::saveCategorySlugRedirect($previousSlug, $categoryId, $langId);
        }

        return [
            'success' => true,
            'category' => $this->getAdminCategory($categoryId, $langId),
            'categories' => $this->getAdminCategories(),
            'stats' => $this->getStats(),
        ];
    }

    private function deleteCategory(array $payload): array
    {
        $categoryId = (int) ($payload['id_category'] ?? 0);
        if ($categoryId <= 0) {
            return ['success' => false, 'error' => $this->module->l('Invalid category selected.')];
        }

        $exists = (bool) Db::getInstance()->getValue(
            'SELECT COUNT(*)
             FROM `' . _DB_PREFIX_ . 'cci_blog_category`
             WHERE id_category = ' . $categoryId
        );
        if (!$exists) {
            return ['success' => false, 'error' => $this->module->l('Category not found.')];
        }

        $childCount = (int) Db::getInstance()->getValue(
            'SELECT COUNT(*)
             FROM `' . _DB_PREFIX_ . 'cci_blog_category`
             WHERE id_parent = ' . $categoryId
        );
        if ($childCount > 0) {
            return ['success' => false, 'error' => $this->module->l('Move or delete child categories before deleting this category.')];
        }

        $postCountSql = 'SELECT COUNT(DISTINCT p.id_post)
            FROM `' . _DB_PREFIX_ . 'cci_blog_post` p';
        if ($this->hasPostCategoryTable()) {
            $postCountSql .= ' LEFT JOIN `' . _DB_PREFIX_ . 'cci_blog_post_category` pc ON pc.id_post = p.id_post
                WHERE p.id_category = ' . $categoryId . ' OR pc.id_category = ' . $categoryId;
        } else {
            $postCountSql .= ' WHERE p.id_category = ' . $categoryId;
        }

        if ((int) Db::getInstance()->getValue($postCountSql) > 0) {
            return ['success' => false, 'error' => $this->module->l('Move posts out of this category before deleting it.')];
        }

        $this->deleteFromTableIfExists('cci_blog_category_lang', 'id_category = ' . $categoryId);
        $this->deleteFromTableIfExists('cci_blog_category_shop', 'id_category = ' . $categoryId);
        $this->deleteFromTableIfExists('cci_blog_slug_redirect', 'entity_type = "category" AND id_category = ' . $categoryId);
        $this->deleteFromTableIfExists('cci_blog_category', 'id_category = ' . $categoryId);

        return [
            'success' => true,
            'categories' => $this->getAdminCategories(),
            'stats' => $this->getStats(),
        ];
    }

    private function getCategoryPayload(int $categoryId, int $requestedLangId = 0): array
    {
        if ($categoryId <= 0) {
            return ['success' => true, 'category' => $this->getEmptyCategory($requestedLangId)];
        }

        $category = $this->getAdminCategory($categoryId, $requestedLangId);
        if (!$category) {
            return ['success' => false, 'error' => $this->module->l('Category not found.')];
        }

        return ['success' => true, 'category' => $category];
    }

    private function getEmptyCategory(int $requestedLangId = 0): array
    {
        return [
            'id_category' => 0,
            'id_lang' => $this->resolveContentLanguageId($requestedLangId),
            'id_parent' => 0,
            'id_author' => isset($this->context->employee) ? (int) $this->context->employee->id : 0,
            'author_name' => isset($this->context->employee)
                ? trim((string) $this->context->employee->firstname . ' ' . (string) $this->context->employee->lastname)
                : '',
            'name' => '',
            'slug' => '',
            'description' => '',
            'image_url' => '',
            'active' => 1,
            'position' => 0,
            'meta_title' => '',
            'meta_description' => '',
            'meta_keywords' => '',
            'focus_keyword' => '',
        ];
    }

    private function getAdminCategory(int $categoryId, int $requestedLangId = 0): array
    {
        if ($categoryId <= 0) {
            return [];
        }

        $langId = $this->resolveContentLanguageId($requestedLangId);
        $shopId = (int) $this->context->shop->id;

        $categoryImageSelect = $this->categoryImageColumnAvailable()
            ? 'c.image_url'
            : '"" AS image_url';

        $defaultLangId = $this->getDefaultShopLanguageId();
        $category = Db::getInstance()->getRow(
            'SELECT c.id_category, c.id_parent, c.id_author, c.active, c.position, c.date_add, c.date_upd, ' . $categoryImageSelect . ',
                    COALESCE(cl.name, cl_default.name, "") AS name,
                    COALESCE(cl.slug, cl_default.slug, "") AS slug,
                    COALESCE(cl.description, cl_default.description, "") AS description,
                    COALESCE(cl.meta_title, cl_default.meta_title, "") AS meta_title,
                    COALESCE(cl.meta_description, cl_default.meta_description, "") AS meta_description,
                    COALESCE(cl.meta_keywords, cl_default.meta_keywords, "") AS meta_keywords,
                    COALESCE(
                        (
                            SELECT NULLIF(a.display_name, "")
                            FROM `' . _DB_PREFIX_ . 'cci_blog_author` a
                            WHERE a.id_employee = c.id_author AND a.active = 1
                            ORDER BY a.id_author DESC
                            LIMIT 1
                        ),
                        NULLIF(TRIM(CONCAT(e.firstname, " ", e.lastname)), ""),
                        "-"
                    ) AS author_name
             FROM `' . _DB_PREFIX_ . 'cci_blog_category` c
             LEFT JOIN `' . _DB_PREFIX_ . 'cci_blog_category_lang` cl
                ON cl.id_category = c.id_category AND cl.id_lang = ' . $langId . ' AND cl.id_shop = ' . $shopId . '
             LEFT JOIN `' . _DB_PREFIX_ . 'cci_blog_category_lang` cl_default
                ON cl_default.id_category = c.id_category AND cl_default.id_lang = ' . $defaultLangId . ' AND cl_default.id_shop = ' . $shopId . '
             LEFT JOIN `' . _DB_PREFIX_ . 'employee` e ON e.id_employee = c.id_author
             WHERE c.id_category = ' . $categoryId
        ) ?: [];
        if ($category) {
            $category['id_lang'] = $langId;
            $category['focus_keyword'] = (string) ($category['meta_keywords'] ?? '');
        }

        return $category;
    }

    private function categoryParentWouldCreateCycle(int $categoryId, int $parentId): bool
    {
        while ($parentId > 0) {
            if ($parentId === $categoryId) {
                return true;
            }

            $parentId = (int) Db::getInstance()->getValue(
                'SELECT id_parent
                 FROM `' . _DB_PREFIX_ . 'cci_blog_category`
                 WHERE id_category = ' . $parentId
            );
        }

        return false;
    }

    private function getAdminComments(): array
    {
        $langId = $this->getDefaultShopLanguageId();
        $shopId = (int) $this->context->shop->id;

        return Db::getInstance()->executeS(
            'SELECT c.id_comment, c.id_post, c.author_name, c.author_email, c.content, c.status, c.date_add,
                    pl.title AS post_title
             FROM `' . _DB_PREFIX_ . 'cci_blog_comment` c
             LEFT JOIN `' . _DB_PREFIX_ . 'cci_blog_post_lang` pl
                ON pl.id_post = c.id_post AND pl.id_lang = ' . $langId . ' AND pl.id_shop = ' . $shopId . '
             WHERE c.status != "deleted"
             ORDER BY c.date_add DESC
             LIMIT 100'
        ) ?: [];
    }

    private function updateCommentStatus(array $payload): array
    {
        $commentId = (int) ($payload['id_comment'] ?? 0);
        $status = (string) ($payload['status'] ?? '');
        if ($commentId <= 0 || !in_array($status, ['pending', 'approved', 'spam', 'deleted'], true)) {
            return ['success' => false, 'error' => $this->module->l('Invalid comment status.')];
        }

        Db::getInstance()->update('cci_blog_comment', ['status' => pSQL($status)], 'id_comment = ' . $commentId);

        return [
            'success' => true,
            'comments' => $this->getAdminComments(),
            'stats' => $this->getStats(),
        ];
    }

    private function getCatalogProductsPayload(array $payload): array
    {
        $query = trim((string) ($payload['query'] ?? $payload['q'] ?? Tools::getValue('query', Tools::getValue('q', ''))));
        $ids = $this->normalizeProductIds($payload['ids'] ?? Tools::getValue('ids', ''));
        $limit = max(1, min(50, (int) ($payload['limit'] ?? Tools::getValue('limit', 30))));
        $items = $this->searchCatalogProducts($query, $ids, $limit);

        return [
            'success' => true,
            'items' => $items,
            'products' => $items,
        ];
    }

    private function searchCatalogProducts(string $query, array $ids = [], int $limit = 30): array
    {
        $shopId = $this->resolveCatalogShopId();
        $langId = max(1, (int) ($this->context->language->id ?: Configuration::get('PS_LANG_DEFAULT')));
        $where = [
            'ps.id_shop = ' . $shopId,
            'pl.id_shop = ' . $shopId,
            'pl.id_lang = ' . $langId,
        ];

        if ($ids !== []) {
            $where[] = 'p.id_product IN (' . implode(',', array_map('intval', $ids)) . ')';
            $orderBy = 'FIELD(p.id_product, ' . implode(',', array_map('intval', $ids)) . ')';
        } else {
            $where[] = 'ps.active = 1';
            if ($query !== '') {
                $where[] = '(pl.name LIKE "%' . pSQL($query) . '%" OR p.reference LIKE "%' . pSQL($query) . '%" OR p.id_product = ' . (int) $query . ')';
                $orderBy = 'pl.name ASC, p.id_product DESC';
            } else {
                $orderBy = 'p.date_add DESC, p.id_product DESC';
            }
        }

        $rows = Db::getInstance()->executeS(
            'SELECT p.id_product, p.reference, ps.id_category_default, ps.active, pl.name, pl.link_rewrite,
                    COALESCE(image_shop.id_image, image.id_image) AS id_image
             FROM `' . _DB_PREFIX_ . 'product` p
             INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps
                ON ps.id_product = p.id_product
             INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                ON pl.id_product = p.id_product
             LEFT JOIN `' . _DB_PREFIX_ . 'image` image
                ON image.id_product = p.id_product AND image.cover = 1
             LEFT JOIN `' . _DB_PREFIX_ . 'image_shop` image_shop
                ON image_shop.id_image = image.id_image AND image_shop.id_shop = ' . $shopId . ' AND image_shop.cover = 1
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY p.id_product, p.reference, ps.id_category_default, ps.active, pl.name, pl.link_rewrite, image_shop.id_image, image.id_image
             ORDER BY ' . $orderBy . '
             LIMIT ' . (int) $limit
        );

        return array_map(function (array $row) use ($shopId, $langId): array {
            return $this->formatCatalogProduct($row, $shopId, $langId);
        }, is_array($rows) ? $rows : []);
    }

    private function resolveCatalogShopId(): int
    {
        $shopId = (int) ($this->context->shop->id ?? 0);

        if ($shopId <= 0 && class_exists('Shop')) {
            $shopId = (int) Shop::getContextShopID(true);
        }

        if ($shopId <= 0) {
            $shopId = (int) Configuration::get('PS_SHOP_DEFAULT');
        }

        return max(1, $shopId);
    }

    private function formatCatalogProduct(array $row, int $shopId, int $langId): array
    {
        $productId = (int) ($row['id_product'] ?? 0);
        $imageId = (int) ($row['id_image'] ?? 0);
        $linkRewrite = (string) ($row['link_rewrite'] ?? '');

        return [
            'id' => $productId,
            'id_product' => $productId,
            'type' => 'product',
            'name' => (string) ($row['name'] ?? ''),
            'label' => (string) ($row['name'] ?? ''),
            'reference' => (string) ($row['reference'] ?? ''),
            'price' => $productId > 0 ? (string) Tools::getContextLocale($this->context)->formatPrice(Product::getPriceStatic($productId, true), $this->context->currency->iso_code) : '',
            'image_url' => $imageId > 0 ? (string) $this->context->link->getImageLink($linkRewrite, $productId . '-' . $imageId, 'home_default') : '',
            'url' => $productId > 0 ? (string) $this->context->link->getProductLink(
                $productId,
                $linkRewrite,
                null,
                null,
                $langId,
                $shopId,
                0,
                false,
                false,
                true
            ) : '',
            'category_id' => (int) ($row['id_category_default'] ?? 0),
            'active' => (bool) ($row['active'] ?? false),
        ];
    }

    private function normalizeProductIds($raw): array
    {
        $values = is_array($raw) ? $raw : preg_split('/[\s,]+/', (string) $raw);
        $ids = [];

        foreach ($values ?: [] as $value) {
            $id = (int) $value;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_slice(array_values($ids), 0, 100);
    }

    private function getSettings(): array
    {
        $settings = [];
        $shopId = (int) ($this->context->shop->id ?? 0);
        foreach (self::SETTINGS_FIELDS as $key => $type) {
            $value = Configuration::get($key, null, null, $shopId);
            $settings[$key] = $type === 'bool' ? (bool) $value : ($type === 'int' ? (int) $value : (string) $value);
        }
        $settings['CCB_OWL_ASSETS_SOURCE'] = $this->normalizeOwlAssetsSource(
            $settings['CCB_OWL_ASSETS_SOURCE'] ?? '',
            $settings
        );
        $settings['CCB_LOAD_OWL_LIBRARY'] = $settings['CCB_OWL_ASSETS_SOURCE'] === 'module';
        $settings['CCB_LOAD_OWL_STYLES'] = $settings['CCB_OWL_ASSETS_SOURCE'] === 'module';
        $settings['CCB_COMMENTS_PROVIDER'] = $this->normalizeCommentsProvider(
            $settings['CCB_COMMENTS_PROVIDER'] ?? ''
        );

        return $settings;
    }

    private function saveSettings(array $payload): array
    {
        $shopId = (int) ($this->context->shop->id ?? 0);
        foreach (self::SETTINGS_FIELDS as $key => $type) {
            if (in_array($key, ['CCB_LOAD_OWL_LIBRARY', 'CCB_LOAD_OWL_STYLES'], true) && array_key_exists('CCB_OWL_ASSETS_SOURCE', $payload)) {
                continue;
            }

            $raw = $payload[$key] ?? null;
            $value = match ($type) {
                'bool' => !empty($raw) ? 1 : 0,
                'int' => $this->normalizeIntegerConfig($key, (int) $raw),
                'slug' => Tools::str2url((string) $raw) ?: 'blog',
                'owl_assets_source' => $this->normalizeOwlAssetsSource($raw, $payload),
                'comments_provider' => $this->normalizeCommentsProvider($raw),
                'disqus_shortname' => $this->normalizeDisqusShortname($raw),
                default => pSQL((string) $raw),
            };
            Configuration::updateValue($key, $value, false, null, $shopId);

            if ($key === 'CCB_OWL_ASSETS_SOURCE') {
                Configuration::updateValue('CCB_LOAD_OWL_LIBRARY', $value === 'module' ? 1 : 0);
                Configuration::updateValue('CCB_LOAD_OWL_STYLES', $value === 'module' ? 1 : 0);
            }
        }

        Tools::generateHtaccess();

        return [
            'success' => true,
            'settings' => $this->getSettings(),
            'stats' => $this->getStats(),
            'diagnostics' => $this->getDiagnosticsPayload(),
        ];
    }

    private function getDiagnosticsPayload(): array
    {
        $db = Db::getInstance();
        $settings = $this->getSettings();
        $stats = $this->getStats();
        $license = $this->getLicensePayload();
        $tables = $this->getDatabaseTableDiagnostics([
            'cci_blog_post',
            'cci_blog_post_lang',
            'cci_blog_post_shop',
            'cci_blog_post_category',
            'cci_blog_category',
            'cci_blog_category_lang',
            'cci_blog_category_shop',
            'cci_blog_comment',
            'cci_blog_tag',
            'cci_blog_post_tag',
        ]);
        $assets = $this->getAssetDiagnostics();
        $hooks = $this->getHookDiagnostics();
        $logs = [];

        foreach ($tables as $table) {
            if (empty($table['exists'])) {
                $logs[] = $this->makeDiagnosticLog(
                    'database',
                    sprintf($this->module->l('Database table is missing: %s'), $table['name'])
                );
            }
        }

        if (!$this->hasContentBlocksColumn()) {
            $logs[] = $this->makeDiagnosticLog(
                'database',
                $this->module->l('Block editor column content_blocks is missing in cci_blog_post_lang.')
            );
        }

        if ((int) $stats['posts'] === 0) {
            $logs[] = $this->makeDiagnosticLog('content', $this->module->l('No blog posts exist yet.'));
        }

        if ((int) $stats['activePosts'] === 0) {
            $logs[] = $this->makeDiagnosticLog('content', $this->module->l('No active blog posts are visible on the storefront.'));
        }

        if ((int) $stats['categories'] === 0) {
            $logs[] = $this->makeDiagnosticLog('content', $this->module->l('No blog categories exist yet.'));
        }

        if (empty($settings['CCB_COMMENTS_ENABLED'])) {
            $logs[] = $this->makeDiagnosticLog('settings', $this->module->l('Comments are disabled globally.'));
        }

        if (
            !empty($settings['CCB_COMMENTS_ENABLED'])
            && $this->normalizeCommentsProvider($settings['CCB_COMMENTS_PROVIDER'] ?? '') === 'disqus'
            && $this->normalizeDisqusShortname($settings['CCB_DISQUS_SHORTNAME'] ?? '') === ''
        ) {
            $logs[] = $this->makeDiagnosticLog(
                'settings',
                $this->module->l('Disqus comments are selected, but the Disqus shortname is empty or invalid.')
            );
        }

        foreach ($assets as $asset) {
            if (empty($asset['exists'])) {
                $logs[] = $this->makeDiagnosticLog(
                    'assets',
                    sprintf($this->module->l('Asset file is missing: %s'), $asset['relativePath'])
                );
            }
        }

        foreach ($hooks as $hook) {
            if (!empty($hook['required']) && empty($hook['registered'])) {
                $logs[] = $this->makeDiagnosticLog(
                    'hooks',
                    sprintf($this->module->l('Required hook is not registered for this module: %s'), $hook['name'])
                );
            }

            if (!empty($hook['usedInPosts']) && empty($hook['availableInEditor'])) {
                $logs[] = $this->makeDiagnosticLog(
                    'hooks',
                    sprintf($this->module->l('Saved posts use a hook that is no longer available in the editor: %s'), $hook['name'])
                );
            }

            if (!empty($hook['usedInPosts']) && empty($hook['hasListeners'])) {
                $logs[] = $this->makeDiagnosticLog(
                    'hooks',
                    sprintf($this->module->l('Saved posts use a hook with no registered module listeners: %s'), $hook['name'])
                );
            }
        }

        $shop = $this->context->shop;
        $language = $this->context->language;
        $currency = $this->context->currency;

        return [
            'generatedAt' => date('c'),
            'module' => [
                'name' => (string) $this->module->name,
                'displayName' => (string) $this->module->displayName,
                'version' => (string) $this->module->version,
                'enabled' => (bool) $this->module->active,
                'path' => (string) $this->module->getLocalPath(),
                'baseSlug' => (string) ($settings['CCB_BASE_SLUG'] ?? 'blog'),
                'blockSchemaReady' => $this->hasContentBlocksColumn(),
                'extensionBlocks' => count($this->getBlockExtensions()),
            ],
            'license' => [
                'status' => (string) ($license['status'] ?? 'inactive'),
                'label' => (string) ($license['label'] ?? ''),
                'canUse' => !empty($license['canUse']),
                'checkedAt' => (string) ($license['checkedAt'] ?? ''),
                'domain' => (string) ($license['domain'] ?? ''),
                'environment' => (string) ($license['environment'] ?? ''),
                'siteUrl' => (string) ($license['siteUrl'] ?? ''),
            ],
            'prestashop' => [
                'version' => _PS_VERSION_,
                'debugMode' => defined('_PS_MODE_DEV_') && (bool) _PS_MODE_DEV_,
                'shopFeatureActive' => class_exists('Shop') ? (bool) Shop::isFeatureActive() : false,
                'contextShopId' => (int) ($shop->id ?? 0),
                'contextShopName' => (string) ($shop->name ?? ''),
                'language' => [
                    'id' => (int) ($language->id ?? 0),
                    'isoCode' => (string) ($language->iso_code ?? ''),
                ],
                'currency' => [
                    'id' => (int) ($currency->id ?? 0),
                    'isoCode' => (string) ($currency->iso_code ?? ''),
                ],
            ],
            'shop' => [
                'id' => (int) ($shop->id ?? 0),
                'name' => (string) ($shop->name ?? ''),
                'domain' => (string) ($shop->domain ?? ''),
                'domainSsl' => (string) ($shop->domain_ssl ?? ''),
                'baseUri' => is_object($shop) && method_exists($shop, 'getBaseURI') ? (string) $shop->getBaseURI() : '',
            ],
            'server' => [
                'phpVersion' => PHP_VERSION,
                'sapi' => PHP_SAPI,
                'os' => PHP_OS,
                'memoryLimit' => (string) ini_get('memory_limit'),
                'maxExecutionTime' => (string) ini_get('max_execution_time'),
                'uploadMaxFilesize' => (string) ini_get('upload_max_filesize'),
                'postMaxSize' => (string) ini_get('post_max_size'),
                'timezone' => date_default_timezone_get(),
                'extensions' => [
                    'curl' => extension_loaded('curl'),
                    'intl' => extension_loaded('intl'),
                    'json' => extension_loaded('json'),
                    'mbstring' => extension_loaded('mbstring'),
                    'opcache' => extension_loaded('Zend OPcache'),
                ],
            ],
            'database' => [
                'serverVersion' => (string) $db->getValue('SELECT VERSION()'),
                'prefix' => _DB_PREFIX_,
                'tables' => $tables,
            ],
            'content' => [
                'posts' => (int) $stats['posts'],
                'activePosts' => (int) $stats['activePosts'],
                'categories' => (int) $stats['categories'],
                'pendingComments' => (int) $stats['pendingComments'],
                'commentsEnabled' => !empty($stats['commentsEnabled']),
                'blockReady' => !empty($stats['blockReady']),
            ],
            'settings' => [
                'postsPerPage' => (int) ($settings['CCB_POSTS_PER_PAGE'] ?? 9),
                'layout' => (string) ($settings['CCB_LAYOUT'] ?? 'grid'),
                'sidebarPosition' => (string) ($settings['CCB_SIDEBAR_POSITION'] ?? 'right'),
                'commentsEnabled' => !empty($settings['CCB_COMMENTS_ENABLED']),
                'commentsProvider' => $this->normalizeCommentsProvider($settings['CCB_COMMENTS_PROVIDER'] ?? ''),
                'commentsModeration' => !empty($settings['CCB_COMMENTS_MODERATION']),
                'tableOfContentsEnabled' => !empty($settings['CCB_TABLE_OF_CONTENTS_ENABLED']),
                'tableOfContentsMinHeadings' => (int) ($settings['CCB_TABLE_OF_CONTENTS_MIN_HEADINGS'] ?? 2),
                'feedEnabled' => !empty($settings['CCB_FEED_ENABLED']),
                'owlAssetsSource' => $this->normalizeOwlAssetsSource($settings['CCB_OWL_ASSETS_SOURCE'] ?? '', $settings),
                'owlLibraryLoadedByModule' => $this->normalizeOwlAssetsSource($settings['CCB_OWL_ASSETS_SOURCE'] ?? '', $settings) === 'module',
                'owlStylesLoadedByModule' => $this->normalizeOwlAssetsSource($settings['CCB_OWL_ASSETS_SOURCE'] ?? '', $settings) === 'module',
            ],
            'hooks' => $hooks,
            'assets' => $assets,
            'logs' => $logs,
        ];
    }

    private function getDatabaseTableDiagnostics(array $tables): array
    {
        $db = Db::getInstance();
        $diagnostics = [];

        foreach ($tables as $table) {
            $tableName = _DB_PREFIX_ . $table;
            $tableRows = $db->executeS('SHOW TABLES LIKE \'' . pSQL($tableName) . '\'');
            $exists = is_array($tableRows) && !empty($tableRows);

            $diagnostics[$table] = [
                'name' => $tableName,
                'exists' => $exists,
                'rows' => $exists ? (int) $db->getValue('SELECT COUNT(*) FROM `' . bqSQL($tableName) . '`') : 0,
            ];
        }

        return $diagnostics;
    }

    private function getHookDiagnostics(): array
    {
        $requiredHooks = ['displayHome', 'actionFrontControllerSetMedia', 'moduleRoutes'];
        $extensionHooks = [
            'displayCciBlogAdminBlockExtensions',
            'displayCciBlogContentHookOptions',
        ];
        $contentHookOptions = $this->getContentHookOptions();
        $contentHookNames = array_map(static fn(array $option): string => (string) ($option['name'] ?? ''), $contentHookOptions);
        $contentHookNames = array_values(array_filter(array_unique($contentHookNames)));
        $contentHookMeta = [];
        foreach ($contentHookOptions as $option) {
            $name = (string) ($option['name'] ?? '');
            if ($name !== '') {
                $contentHookMeta[$name] = $option;
            }
        }
        $usage = $this->getContentHookUsageCounts();
        $hookNames = array_values(array_unique(array_merge($requiredHooks, $extensionHooks, $contentHookNames, array_keys($usage))));
        $hooks = [];

        foreach ($hookNames as $hookName) {
            $hooks[] = $this->buildHookDiagnostic(
                $hookName,
                in_array($hookName, $requiredHooks, true),
                in_array($hookName, $contentHookNames, true),
                $usage[$hookName] ?? [],
                $contentHookMeta[$hookName] ?? []
            );
        }

        return $hooks;
    }

    private function buildHookDiagnostic(string $hookName, bool $required, bool $availableInEditor = false, array $usage = [], array $meta = []): array
    {
        $hookId = (int) Hook::getIdByName($hookName);
        $registered = false;
        $modules = [];

        if ($hookId > 0 && (int) $this->module->id > 0) {
            $registered = (bool) Db::getInstance()->getValue(
                'SELECT COUNT(*)
                 FROM `' . _DB_PREFIX_ . 'hook_module`
                 WHERE id_hook = ' . $hookId . '
                 AND id_module = ' . (int) $this->module->id
            );

            $rows = Db::getInstance()->executeS(
                'SELECT m.name, m.version, hm.position
                 FROM `' . _DB_PREFIX_ . 'hook_module` hm
                 INNER JOIN `' . _DB_PREFIX_ . 'module` m ON m.id_module = hm.id_module
                 WHERE hm.id_hook = ' . $hookId . '
                 ORDER BY hm.position ASC, m.name ASC'
            ) ?: [];

            foreach ($rows as $row) {
                $modules[] = [
                    'name' => (string) ($row['name'] ?? ''),
                    'version' => (string) ($row['version'] ?? ''),
                    'position' => (int) ($row['position'] ?? 0),
                ];
            }
        }

        return [
            'name' => $hookName,
            'id' => $hookId,
            'exists' => $hookId > 0,
            'required' => $required,
            'registered' => $registered,
            'availableInEditor' => $availableInEditor,
            'label' => (string) ($meta['label'] ?? $hookName),
            'description' => (string) ($meta['description'] ?? ''),
            'sourceModule' => (string) ($meta['moduleName'] ?? ''),
            'sourceType' => (string) ($meta['sourceType'] ?? ''),
            'moduleCount' => count($modules),
            'modules' => $modules,
            'hasListeners' => count($modules) > 0,
            'usedInPosts' => (int) ($usage['posts'] ?? 0),
            'usedBlocks' => (int) ($usage['blocks'] ?? 0),
        ];
    }

    private function getContentHookUsageCounts(): array
    {
        if (!$this->hasContentBlocksColumn()) {
            return [];
        }

        $rows = Db::getInstance()->executeS(
            'SELECT id_post, content_blocks
             FROM `' . _DB_PREFIX_ . 'cci_blog_post_lang`
             WHERE content_blocks IS NOT NULL
             AND content_blocks != ""'
        ) ?: [];
        $usage = [];

        foreach ($rows as $row) {
            $postId = (int) ($row['id_post'] ?? 0);
            $decoded = json_decode((string) ($row['content_blocks'] ?? ''), true);
            if (!is_array($decoded)) {
                continue;
            }

            $this->collectContentHookUsage($decoded, $postId, $usage);
        }

        foreach ($usage as $hookName => $data) {
            $usage[$hookName]['posts'] = count($data['postIds'] ?? []);
            unset($usage[$hookName]['postIds']);
        }

        return $usage;
    }

    private function collectContentHookUsage(array $blocks, int $postId, array &$usage): void
    {
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }

            if (($block['type'] ?? '') === 'hook') {
                $hookName = trim((string) ($block['hook'] ?? ''));
                if ($hookName !== '') {
                    if (!isset($usage[$hookName])) {
                        $usage[$hookName] = ['blocks' => 0, 'postIds' => []];
                    }
                    $usage[$hookName]['blocks']++;
                    if ($postId > 0) {
                        $usage[$hookName]['postIds'][$postId] = true;
                    }
                }
            }

            if (isset($block['blocks']) && is_array($block['blocks'])) {
                $this->collectContentHookUsage($block['blocks'], $postId, $usage);
            }

            if (isset($block['columns']) && is_array($block['columns'])) {
                foreach ($block['columns'] as $column) {
                    if (is_array($column) && isset($column['blocks']) && is_array($column['blocks'])) {
                        $this->collectContentHookUsage($column['blocks'], $postId, $usage);
                    }
                }
            }
        }
    }

    private function getAssetDiagnostics(): array
    {
        $assets = [
            'views/css/cci_blog_admin.css',
            'views/js/cci-blog-admin.js',
            'views/css/cci_blog_front.css',
            'views/js/front.js',
            'views/vendor/owlcarousel/owl.carousel.min.js',
            'views/vendor/owlcarousel/assets/owl.carousel.min.css',
        ];

        return array_map(function (string $relativePath): array {
            $path = $this->module->getLocalPath() . $relativePath;
            $exists = is_file($path);

            return [
                'relativePath' => $relativePath,
                'exists' => $exists,
                'size' => $exists ? (int) filesize($path) : 0,
                'modifiedAt' => $exists ? date('c', (int) filemtime($path)) : '',
            ];
        }, $assets);
    }

    private function makeDiagnosticLog(string $type, string $message): array
    {
        return [
            'type' => $type,
            'message' => $message,
            'date' => date('Y-m-d H:i:s'),
        ];
    }

    private function getLicensePayload(): array
    {
        return $this->featureRegistry()->decorateLicensePayload($this->getRawLicensePayload());
    }

    private function getRawLicensePayload(): array
    {
        $shopId = $this->currentShopId();
        $proModule = $this->getProLicenseModule();
        if ($proModule instanceof Module && method_exists($proModule, 'getCciLicensePayload')) {
            $payload = $proModule->getCciLicensePayload($shopId);
            return is_array($payload) ? $payload : $this->getFreeLicensePayload($this->module->l('CCI Blog Pro returned an invalid license payload.'));
        }

        return $this->getFreeLicensePayload($this->module->l('Install and activate CCI Blog Pro to unlock paid features.'));
    }

    private function handleLicenseAction(string $action, array $payload = []): array
    {
        $shopId = $this->currentShopId();
        $proModule = $this->getProLicenseModule();
        if ($proModule instanceof Module && method_exists($proModule, 'handleCciLicenseAction')) {
            $response = $proModule->handleCciLicenseAction($action, $shopId, $payload);
            return $this->decorateLicenseResponse(is_array($response) ? $response : [
                'success' => false,
                'error' => $this->module->l('CCI Blog Pro returned an invalid license response.'),
                'license' => $this->getRawLicensePayload(),
            ]);
        }

        return $this->decorateLicenseResponse([
            'success' => false,
            'code' => 'cci_pro_module_missing',
            'errorCode' => 'cci_pro_module_missing',
            'warning' => $this->module->l('Install and activate CCI Blog Pro before managing a Pro license.'),
            'license' => $this->getFreeLicensePayload($this->module->l('CCI Blog Pro is not installed or not enabled.')),
        ]);
    }

    private function decorateLicenseResponse(array $response): array
    {
        $license = is_array($response['license'] ?? null) ? (array) $response['license'] : $this->getRawLicensePayload();
        $decorated = $this->featureRegistry()->decorateLicensePayload($license);

        $response['license'] = $decorated;
        $response['features'] = $decorated['availableFeatures'] ?? [];
        $response['enabledFeatures'] = $decorated['enabledFeatures'] ?? [];

        return $response;
    }

    private function getProLicenseModule(): ?Module
    {
        if (!Module::isInstalled('cci_blog_pro')) {
            return null;
        }

        if (method_exists(Module::class, 'isEnabled') && !Module::isEnabled('cci_blog_pro')) {
            return null;
        }

        $module = Module::getInstanceByName('cci_blog_pro');
        return $module instanceof Module && !empty($module->active) ? $module : null;
    }

    private function getFreeLicensePayload(string $message = ''): array
    {
        $siteIdentity = $this->currentLicenseSiteIdentity();

        return [
            'status' => 'inactive',
            'label' => $this->module->l('Free'),
            'canUse' => false,
            'runtimeAllowed' => false,
            'isActive' => false,
            'locked' => false,
            'writeLocked' => false,
            'canManage' => false,
            'proModuleAvailable' => false,
            'product' => 'CCI Blog',
            'productSlug' => 'cci-blog',
            'productUrl' => self::PRODUCT_HOME_URL,
            'siteUrl' => $siteIdentity['site_url'],
            'domain' => $siteIdentity['domain'],
            'environment' => $siteIdentity['environment'],
            'features' => [],
            'enabledFeatures' => [],
            'availableFeatures' => [],
            'checkedAt' => 0,
            'message' => $message ?: $this->module->l('Free features are available. Pro features require CCI Blog Pro.'),
            'source' => $this->module->name,
        ];
    }

    private function currentShopId(): int
    {
        return (int) ($this->context->shop->id ?? 0);
    }

    private function currentLicenseSiteIdentity(): array
    {
        $siteUrl = $this->currentSiteUrl();

        return CciSharedLicenseEnvironment::siteIdentity($siteUrl);
    }

    private function currentSiteUrl(): string
    {
        $shop = $this->context->shop ?? null;
        if (is_object($shop) && method_exists($shop, 'getBaseURL')) {
            $url = (string) ($shop->getBaseURL(true) ?? '');
            if ($url !== '') {
                return $url;
            }
        }

        return 'https://' . (string) Tools::getHttpHost();
    }

    private function isProPlanActive(): bool
    {
        $license = $this->getLicensePayload();

        if (!empty($license['writeLocked']) || !empty($license['locked'])) {
            return false;
        }

        return !empty($license['isPro'])
            || !empty($license['canUse'])
            || !empty($license['runtimeAllowed'])
            || (string) ($license['status'] ?? '') === 'active';
    }

    private function activateLicense(array $payload): array
    {
        $licenseKey = trim((string) ($payload['licenseKey'] ?? ''));
        if ($licenseKey === '') {
            return [
                'success' => false,
                'error' => $this->module->l('Enter license key.'),
                'license' => $this->getLicensePayload(),
            ];
        }

        return $this->handleLicenseAction('license:activate', $payload);
    }

    private function checkLicense(): array
    {
        return $this->handleLicenseAction('license:check');
    }

    private function deactivateLicense(): array
    {
        return $this->handleLicenseAction('license:deactivate');
    }

    private function getExtensions(?array $features = null): array
    {
        $features = $features ?? $this->featureMap($this->getLicensePayload()['availableFeatures'] ?? []);
        $registeredExtensions = $this->featureRegistry()->getAdminExtensions($features);
        if (!empty($registeredExtensions)) {
            return $registeredExtensions;
        }

        $extensions = [];

        foreach ($this->getBlockExtensions($features) as $blockExtension) {
            $module = trim((string) ($blockExtension['moduleName'] ?? ''));
            if ($module === '') {
                continue;
            }

            if (!isset($extensions[$module])) {
                $extensions[$module] = [
                    'id' => Tools::strtolower(str_replace([' ', '_'], '-', $module)),
                    'name' => $module,
                    'description' => $this->module->l('External editor blocks registered by this module.'),
                    'status' => 'installed',
                    'version' => '',
                    'blocks' => [],
                    'pro' => !empty($blockExtension['requiresPro']) || !empty($blockExtension['requiredFeatures']),
                    'locked' => !empty($blockExtension['locked']),
                    'requiredFeatures' => is_array($blockExtension['requiredFeatures'] ?? null) ? $blockExtension['requiredFeatures'] : [],
                ];
            }

            $extensions[$module]['blocks'][] = [
                'name' => (string) ($blockExtension['blockName'] ?? ''),
                'label' => (string) ($blockExtension['label'] ?? $blockExtension['blockName'] ?? ''),
            ];
        }

        return array_values($extensions);
    }

    private function getBlockTypes(?array $features = null): array
    {
        $features = $features ?? $this->featureMap($this->getLicensePayload()['availableFeatures'] ?? []);
        $types = [
            ['type' => 'paragraph', 'label' => $this->module->l('Text'), 'extension' => 'core'],
            ['type' => 'heading', 'label' => $this->module->l('Heading'), 'extension' => 'core'],
            ['type' => 'link', 'label' => $this->module->l('Link'), 'extension' => 'core'],
            ['type' => 'image', 'label' => $this->module->l('Image'), 'extension' => 'core'],
            ['type' => 'video', 'label' => $this->module->l('Video'), 'extension' => 'core'],
            ['type' => 'columns', 'label' => $this->module->l('Columns'), 'extension' => 'core'],
            ['type' => 'product', 'label' => $this->module->l('Product link'), 'extension' => 'core'],
            ['type' => 'product_carousel', 'label' => $this->module->l('Product carousel'), 'extension' => 'commerce-blocks'],
            ['type' => 'hook', 'label' => $this->module->l('Hook'), 'extension' => 'core'],
            ['type' => 'module_block', 'label' => $this->module->l('Extension block'), 'extension' => 'external'],
        ];

        return array_map(function (array $type) use ($features): array {
            $feature = $this->featureRegistry()->featureForBlockType((string) ($type['type'] ?? ''));
            if ($feature !== '') {
                $type['feature'] = $feature;
                $type['requiresPro'] = true;
                $type['locked'] = empty($features[$feature]['enabled']);
            } else {
                $type['requiresPro'] = false;
                $type['locked'] = false;
            }

            return $type;
        }, $types);
    }

    private function getBlockExtensions(?array $features = null): array
    {
        $features = $features ?? $this->featureMap($this->getLicensePayload()['availableFeatures'] ?? []);
        $rawExtensions = Hook::exec('displayCciBlogAdminBlockExtensions', [], null, true);
        if (!is_array($rawExtensions)) {
            return [];
        }

        $extensions = [];
        foreach ($rawExtensions as $extensionGroup) {
            if (is_string($extensionGroup)) {
                $decoded = json_decode($extensionGroup, true);
                $extensionGroup = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
            }
            if (isset($extensionGroup['module']) || isset($extensionGroup['moduleName'])) {
                $extensionGroup = [$extensionGroup];
            }
            if (!is_array($extensionGroup)) {
                continue;
            }

            foreach ($extensionGroup as $extension) {
                if (!is_array($extension)) {
                    continue;
                }

                $module = trim((string) ($extension['moduleName'] ?? $extension['module'] ?? ''));
                $block = trim((string) ($extension['blockName'] ?? $extension['block'] ?? ''));
                if ($module === '' || $block === '') {
                    continue;
                }

                $requirements = $this->normalizeFeatureRequirements($extension['requires'] ?? $extension['requiredFeatures'] ?? $extension['feature'] ?? 'extension_blocks');

                $extensions[] = [
                    'moduleName' => $module,
                    'blockName' => $block,
                    'label' => (string) ($extension['label'] ?? $extension['title'] ?? $block),
                    'requiresPro' => !empty($requirements) || !empty($extension['requiresPro']) || !empty($extension['pro']),
                    'requiredFeatures' => $requirements,
                    'locked' => !empty($requirements) && !$this->featureRegistry()->requirementsMet($requirements, $features),
                    'payload' => is_array($extension['payload'] ?? null) ? $extension['payload'] : new stdClass(),
                ];
            }
        }

        return $extensions;
    }

    private function getContentHookOptions(): array
    {
        $defaults = [
            [
                'name' => 'displayCciBlogPostTop',
                'label' => $this->module->l('Top of post'),
                'description' => $this->module->l('Rendered before the main article content.'),
                'moduleName' => 'cci_blog',
                'moduleCount' => 0,
                'sourceType' => 'blog',
            ],
            [
                'name' => 'displayCciBlogPostMiddle',
                'label' => $this->module->l('Middle of post'),
                'description' => $this->module->l('Rendered inside the article flow where the Hook block is placed.'),
                'moduleName' => 'cci_blog',
                'moduleCount' => 0,
                'sourceType' => 'blog',
            ],
            [
                'name' => 'displayCciBlogPostBottom',
                'label' => $this->module->l('Bottom of post'),
                'description' => $this->module->l('Rendered after the main article content.'),
                'moduleName' => 'cci_blog',
                'moduleCount' => 0,
                'sourceType' => 'blog',
            ],
        ];

        $options = [];
        foreach ($defaults as $option) {
            $options[$option['name']] = $option;
        }

        foreach (CciBlogPost::getExternalContentHookOptions() as $option) {
            $name = trim((string) ($option['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $options[$name] = [
                'name' => $name,
                'label' => trim((string) ($option['label'] ?? $name)) ?: $name,
                'description' => trim((string) ($option['description'] ?? '')),
                'moduleName' => trim((string) ($option['moduleName'] ?? '')),
                'moduleCount' => (int) ($option['moduleCount'] ?? 0),
                'sourceType' => 'extension',
            ];
        }

        foreach (CciBlogPost::getStorefrontContentHookOptions() as $option) {
            $name = trim((string) ($option['name'] ?? ''));
            if ($name === '' || isset($options[$name])) {
                continue;
            }

            $options[$name] = [
                'name' => $name,
                'label' => trim((string) ($option['label'] ?? $name)) ?: $name,
                'description' => trim((string) ($option['description'] ?? '')),
                'moduleName' => trim((string) ($option['moduleName'] ?? '')),
                'moduleCount' => (int) ($option['moduleCount'] ?? 0),
                'sourceType' => 'storefront',
            ];
        }

        return array_values($options);
    }

    private function getAdminLinks(): array
    {
        return [
            'dashboard' => $this->context->link->getAdminLink('AdminCciBlog'),
            'documentation' => self::PRODUCT_HOME_URL,
            'store' => self::PRODUCT_HOME_URL,
        ];
    }

    private function getAdminLoadingLabel(): string
    {
        $translations = $this->getAdminTranslations();

        return (string) ($translations['Loading CCI Blog...'] ?? 'Loading CCI Blog...');
    }

    private function getI18n(): array
    {
        return [
            'dashboard' => $this->module->l('Posts'),
            'posts' => $this->module->l('Posts'),
            'categories' => $this->module->l('Categories'),
            'comments' => $this->module->l('Comments'),
            'settings' => $this->module->l('Settings'),
            'extensions' => $this->module->l('Extensions'),
            'license' => $this->module->l('License'),
            'save' => $this->trans('Save', [], 'Admin.Actions'),
            'cancel' => $this->trans('Cancel', [], 'Admin.Actions'),
            'edit' => $this->trans('Edit', [], 'Admin.Actions'),
            'delete' => $this->trans('Delete', [], 'Admin.Actions'),
            'duplicate' => $this->trans('Duplicate', [], 'Admin.Actions'),
            'addNew' => $this->trans('Add new', [], 'Admin.Actions'),
            'title' => $this->trans('Title', [], 'Admin.Global'),
            'status' => $this->trans('Status', [], 'Admin.Global'),
            'date' => $this->trans('Date', [], 'Admin.Global'),
            'translations' => $this->getAdminTranslations(),
            'localMediaLibrary' => [
                'title' => $this->module->l('Choose a local image'),
                'description' => $this->module->l('Upload an optimized blog image or select an existing image from this store.'),
                'search' => $this->module->l('Search images'),
                'source' => $this->module->l('Image location'),
                'root' => $this->module->l('Root'),
                'folders' => $this->module->l('Folders'),
                'folder' => $this->module->l('Folder'),
                'loadMore' => $this->module->l('Load more'),
                'choose' => $this->module->l('Use image'),
                'cancel' => $this->module->l('Cancel'),
                'close' => $this->module->l('Close'),
                'loading' => $this->module->l('Loading images...'),
                'empty' => $this->module->l('No matching images found.'),
                'error' => $this->module->l('Images could not be loaded.'),
                'readOnly' => $this->module->l('Read-only library'),
                'managedLibrary' => $this->module->l('Optimized blog images'),
                'upload' => $this->module->l('Upload image'),
                'uploading' => $this->module->l('Uploading and optimizing image...'),
                'uploadError' => $this->module->l('The image could not be uploaded.'),
                'uploadHint' => $this->module->l('Accepted formats: JPEG, PNG or WebP. Maximum file size: 12 MB.'),
                'limited' => $this->module->l('The search limit was reached. Open a narrower folder or refine the search.'),
            ],
        ];
    }

    private function getAdminTranslations(): array
    {
        $locale = $this->getAdminTranslationLocale();
        $translations = $this->loadAdminTranslationsFromModule($this->module, $locale);

        $proModule = Module::getInstanceByName('cci_blog_pro');
        if ($proModule instanceof Module && $proModule->active) {
            $translations = array_merge($translations, $this->loadAdminTranslationsFromModule($proModule, $locale));
        }

        return $translations;
    }

    private function getAdminTranslationLocale(): string
    {
        $candidates = [
            (string) ($this->context->language->locale ?? ''),
            (string) ($this->context->language->language_code ?? ''),
            (string) ($this->context->language->iso_code ?? ''),
        ];

        foreach ($candidates as $candidate) {
            $locale = strtolower(str_replace('_', '-', trim($candidate)));
            if ($locale !== '' && preg_match('/^[a-z]{2}(?:-[a-z]{2})?$/', $locale)) {
                return $locale;
            }
        }

        return 'en';
    }

    private function loadAdminTranslationsFromModule(Module $module, string $locale): array
    {
        $translationDir = rtrim($module->getLocalPath(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'translations' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR;
        $shortLocale = substr($locale, 0, 2);
        $candidates = array_values(array_unique(array_filter([$locale, $shortLocale, 'en'])));

        foreach ($candidates as $candidate) {
            $file = $translationDir . $candidate . '.php';
            if (!is_file($file)) {
                continue;
            }

            $translations = require $file;
            if (is_array($translations)) {
                return $translations;
            }
        }

        return [];
    }

    private function featureRegistry(): CciBlogFeatureRegistry
    {
        return new CciBlogFeatureRegistry($this->module);
    }

    private function featureMap(array $features): array
    {
        $map = [];
        foreach ($features as $feature) {
            if (!is_array($feature)) {
                continue;
            }

            $key = trim((string) ($feature['key'] ?? ''));
            if ($key !== '') {
                $map[$key] = $feature;
            }
        }

        return $map;
    }

    private function normalizeFeatureRequirements($requirements): array
    {
        if (is_string($requirements)) {
            $requirements = preg_split('/[,|]/', $requirements) ?: [];
        } elseif (!is_array($requirements)) {
            $requirements = [];
        }

        return array_values(array_unique(array_filter(array_map(function ($requirement): string {
            $value = strtolower(trim((string) $requirement));
            $value = preg_replace('/[^a-z0-9_\\-]+/', '_', $value);
            $value = is_string($value) ? trim($value, '_-') : '';

            return str_replace('-', '_', $value);
        }, $requirements))));
    }

    private function guardTranslationWrite(array $payload): ?array
    {
        $requested = (int) ($payload['id_lang'] ?? 0);
        if ($requested > 0 && $requested !== $this->getDefaultShopLanguageId() && !$this->canEditTranslations()) {
            return [
                'success' => false,
                'code' => 'ccb_pro_feature_required',
                'error' => $this->module->l('An active Pro license is required to save translations.'),
            ];
        }

        return null;
    }

    private function guardPostFeatureAccess(array $payload): ?array
    {
        $license = $this->getLicensePayload();
        $features = $this->featureMap(is_array($license['availableFeatures'] ?? null) ? $license['availableFeatures'] : []);
        $requirements = $this->featureRegistry()->getPostPayloadRequiredFeatures($payload);
        $postId = (int) ($payload['id_post'] ?? 0);
        $primaryId = (int) ($payload['id_category'] ?? 0);
        $categories = $this->normalizePostCategoryIds($payload['category_ids'] ?? [], $primaryId);
        if (count($categories) > 1) {
            $requirements[] = 'additional_categories';
        }
        // A stripped request must not silently remove saved Pro settings.
        if ($postId > 0 && !empty($license['writeLocked'])) {
            $rows = Db::getInstance()->executeS('SELECT content_blocks FROM ' . _DB_PREFIX_ . 'cci_blog_post_lang WHERE id_post = ' . $postId . ' AND id_shop = ' . $this->currentShopId()) ?: [];
            foreach ($rows as $row) {
                $blocks = json_decode((string) $row['content_blocks'], true);
                $requirements = array_merge($requirements, $this->featureRegistry()->getPostPayloadRequiredFeatures(['blocks' => is_array($blocks) ? $blocks : []]));
            }
            if (count($this->getPostCategoryIds($postId, $primaryId)) > 1) {
                $requirements[] = 'additional_categories';
            }
        }
        $requirements = array_values(array_unique($requirements));

        if ($this->featureRegistry()->requirementsMet($requirements, $features)) {
            return null;
        }

        $missingLabels = $this->featureRegistry()->missingRequirementLabels($requirements, $features);
        return [
            'success' => false,
            'error' => $this->module->l('This post uses Pro editor blocks.'),
            'details' => $this->module->l('Install or activate CCI Blog Pro to save:') . ' ' . implode(', ', $missingLabels),
            'code' => 'ccb_pro_feature_required',
            'errorCode' => 'ccb_pro_feature_required',
            'requiredFeatures' => $requirements,
            'missingFeatures' => $missingLabels,
            'license' => $license,
            'features' => $license['availableFeatures'] ?? [],
            'enabledFeatures' => $license['enabledFeatures'] ?? [],
        ];
    }

    private function getJsonPayload(): ?array
    {
        $raw = trim((string) file_get_contents('php://input'));
        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : null;
    }

    private function decodeBlocks(string $json): array
    {
        if ($json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : [];
    }

    private function normalizeIntegerConfig(string $key, int $value): int
    {
        return match ($key) {
            'CCB_POSTS_PER_PAGE' => max(1, min(50, $value)),
            'CCB_RELATED_POSTS' => max(0, min(12, $value)),
            'CCB_FEED_ITEMS' => max(5, min(100, $value)),
            'CCB_TABLE_OF_CONTENTS_MIN_HEADINGS' => max(1, min(20, $value)),
            default => $value,
        };
    }

    private function normalizeOwlAssetsSource($value, array $settings): string
    {
        $value = trim((string) $value);
        if (in_array($value, ['module', 'theme'], true)) {
            return $value;
        }

        return (!empty($settings['CCB_LOAD_OWL_LIBRARY']) || !empty($settings['CCB_LOAD_OWL_STYLES']))
            ? 'module'
            : 'theme';
    }

    private function normalizeCommentsProvider($value): string
    {
        return (string) $value === 'native' ? 'native' : 'disqus';
    }

    private function normalizeDisqusShortname($value): string
    {
        $shortname = strtolower(trim((string) $value));

        return preg_match('/^[a-z0-9_-]+$/', $shortname) ? $shortname : '';
    }

    private function makeUniquePostSlug(string $slug, int $postId, int $langId, int $shopId): string
    {
        $base = $slug !== '' ? $slug : 'post';
        $candidate = $base;
        $counter = 2;

        while ($this->postSlugExists($candidate, $postId, $langId, $shopId)) {
            $candidate = $base . '-' . $counter;
            $counter++;
        }

        return $candidate;
    }

    private function normalizeSeoContentType(string $value): string
    {
        $value = trim($value);
        $allowed = ['article', 'guide', 'news', 'landing'];

        return in_array($value, $allowed, true) ? $value : 'article';
    }

    private function sanitizeImageUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return preg_match('/^[^\x00-\x1F\x7F]+$/', $url) ? $url : '';
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }

        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true) ? $url : '';
    }

    private function postSlugExists(string $slug, int $postId, int $langId, int $shopId): bool
    {
        return (bool) Db::getInstance()->getValue(
            'SELECT id_post FROM `' . _DB_PREFIX_ . 'cci_blog_post_lang`
             WHERE slug = "' . pSQL($slug) . '"
             AND id_lang = ' . $langId . '
             AND id_shop = ' . $shopId . '
             AND id_post != ' . $postId
        );
    }

    private function ensureBlockSchema(): void
    {
        $seoScoreColumnCreated = false;

        if (!$this->hasPostLangColumn('content_blocks')) {
            Db::getInstance()->execute(
                'ALTER TABLE `' . _DB_PREFIX_ . 'cci_blog_post_lang`
                 ADD COLUMN `content_blocks` LONGTEXT NULL AFTER `content`'
            );
        }

        if (!$this->hasPostLangColumn('focus_keyword')) {
            Db::getInstance()->execute(
                'ALTER TABLE `' . _DB_PREFIX_ . 'cci_blog_post_lang`
                 ADD COLUMN `focus_keyword` VARCHAR(255) NULL AFTER `meta_keywords`'
            );
        }

        if (!$this->hasPostLangColumn('seo_content_type')) {
            Db::getInstance()->execute(
                'ALTER TABLE `' . _DB_PREFIX_ . 'cci_blog_post_lang`
                 ADD COLUMN `seo_content_type` VARCHAR(32) NULL DEFAULT \'article\' AFTER `focus_keyword`'
            );
        }

        if (!$this->hasPostLangColumn('seo_score')) {
            Db::getInstance()->execute(
                'ALTER TABLE `' . _DB_PREFIX_ . 'cci_blog_post_lang`
                 ADD COLUMN `seo_score` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `seo_content_type`,
                 ADD KEY `idx_cci_blog_post_lang_seo_score` (`seo_score`)'
            );
            $seoScoreColumnCreated = true;
        }

        if ($seoScoreColumnCreated || (int) Configuration::get('CCB_SEO_SCORE_SCHEMA_VERSION') < 2) {
            $this->refreshStoredPostSeoScores();
            Configuration::updateValue('CCB_SEO_SCORE_SCHEMA_VERSION', 2);
        }
    }

    private function refreshStoredPostSeoScores(): void
    {
        if (!$this->hasPostLangColumn('seo_score')) {
            return;
        }

        $rows = Db::getInstance()->executeS(
            'SELECT p.id_post, p.id_category, p.id_author, pl.id_lang, pl.id_shop,
                    pl.title, pl.slug, pl.intro, pl.content, pl.content_blocks,
                    pl.meta_title, pl.meta_description, pl.focus_keyword, pl.seo_content_type
             FROM `' . _DB_PREFIX_ . 'cci_blog_post` p
             INNER JOIN `' . _DB_PREFIX_ . 'cci_blog_post_lang` pl ON pl.id_post = p.id_post'
        ) ?: [];

        foreach ($rows as $row) {
            $row['blocks'] = $this->decodeBlocks((string) ($row['content_blocks'] ?? ''));
            unset($row['content_blocks']);
            $score = $this->calculateAdminPostSeoScore($row);

            Db::getInstance()->update(
                'cci_blog_post_lang',
                ['seo_score' => $score],
                'id_post = ' . (int) ($row['id_post'] ?? 0)
                . ' AND id_lang = ' . (int) ($row['id_lang'] ?? 0)
                . ' AND id_shop = ' . (int) ($row['id_shop'] ?? 0)
            );
        }
    }

    private function ensurePostCategorySchema(): void
    {
        Db::getInstance()->execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'cci_blog_post_category` (
                `id_post` INT UNSIGNED NOT NULL,
                `id_category` INT UNSIGNED NOT NULL,
                `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
                `position` INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (`id_post`, `id_category`),
                KEY `idx_category` (`id_category`),
                KEY `idx_primary` (`is_primary`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        Db::getInstance()->execute(
            'INSERT IGNORE INTO `' . _DB_PREFIX_ . 'cci_blog_post_category` (`id_post`, `id_category`, `is_primary`, `position`)
             SELECT id_post, id_category, 1, 0
             FROM `' . _DB_PREFIX_ . 'cci_blog_post`
             WHERE id_category > 0'
        );
    }

    private function ensureCategoryAuthorSchema(): void
    {
        if (!$this->categoryColumnAvailable('id_author')) {
            Db::getInstance()->execute(
                'ALTER TABLE `' . _DB_PREFIX_ . 'cci_blog_category`
                 ADD COLUMN `id_author` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `id_parent`,
                 ADD KEY `idx_author` (`id_author`)'
            );
        }

        $defaultAuthorId = (int) Db::getInstance()->getValue(
            'SELECT id_employee
             FROM `' . _DB_PREFIX_ . 'employee`
             WHERE active = 1
             ORDER BY id_employee ASC'
        );
        if ($defaultAuthorId > 0) {
            Db::getInstance()->update(
                'cci_blog_category',
                ['id_author' => $defaultAuthorId],
                'id_author = 0'
            );
        }
    }

    private function hasContentBlocksColumn(): bool
    {
        return $this->hasPostLangColumn('content_blocks');
    }

    private function saveReviewFeedback(array $payload): array
    {
        $rating = (int) ($payload['rating'] ?? 0);
        if ($rating < 1 || $rating > 5) {
            return [
                'success' => false,
                'error' => $this->module->l('Please select a rating before saving feedback.'),
            ];
        }

        $reviewConsent = is_array($payload['reviewConsent'] ?? null) ? $payload['reviewConsent'] : [];
        $consentContent = trim(strip_tags((string) ($reviewConsent['content'] ?? '')));
        $consentContent = function_exists('mb_substr')
            ? mb_substr($consentContent, 0, 2000)
            : substr($consentContent, 0, 2000);
        if (($reviewConsent['accepted'] ?? false) !== true || $consentContent === '') {
            return [
                'success' => false,
                'error' => $this->module->l('Please accept the required consent before sending your review.'),
            ];
        }

        $comment = trim(strip_tags((string) ($payload['comment'] ?? '')));
        $comment = function_exists('mb_substr')
            ? mb_substr($comment, 0, 1200)
            : substr($comment, 0, 1200);
        $section = preg_replace('/[^a-z0-9_-]+/i', '', (string) ($payload['section'] ?? 'dashboard')) ?: 'dashboard';
        $siteUrl = $this->context->shop instanceof Shop
            ? $this->context->shop->getBaseURL(true)
            : Tools::getShopDomainSsl(true);
        $entry = [
            'productSlug' => self::MARKETPLACE_PRODUCT_SLUG,
            'rating' => $rating,
            'body' => $comment,
            'comment' => $comment,
            'section' => $section,
            'platform' => 'prestashop',
            'submissionContext' => 'module-admin',
            'moduleVersion' => (string) $this->module->version,
            'siteUrl' => $siteUrl,
            'locale' => (string) ($this->context->language->iso_code ?? 'en'),
            'environment' => $this->isDevelopmentSite($siteUrl) ? 'staging' : 'production',
            'reviewConsent' => [
                'accepted' => true,
                'codeName' => preg_replace('/[^a-zA-Z0-9_-]+/', '', (string) ($reviewConsent['codeName'] ?? '')),
                'content' => $consentContent,
                'locale' => preg_replace('/[^a-zA-Z_-]+/', '', (string) ($reviewConsent['locale'] ?? 'en')),
                'version' => (int) ($reviewConsent['version'] ?? 0),
            ],
        ];
        $entry['idempotencyKey'] = hash(
            'sha256',
            implode('|', [
                self::MARKETPLACE_PRODUCT_SLUG,
                $siteUrl,
                (string) $rating,
                $comment,
                $section,
                (string) floor(time() / 600),
            ])
        );
        $remote = $this->postProductFeedback($entry);

        if (!($remote['ok'] ?? false)) {
            return [
                'success' => false,
                'error' => $this->module->l('Feedback could not be sent. Please try again.'),
                'details' => (string) ($remote['error'] ?? ''),
            ];
        }

        return [
            'success' => true,
            'message' => $this->module->l('Thank you for your feedback.'),
            'feedback' => [
                'id' => $remote['id'] ?? null,
                'status' => $remote['status'] ?? 'pending',
            ],
        ];
    }

    private function postProductFeedback(array $payload): array
    {
        $endpoint = $this->getMarketplaceApiBase() . '/api/product-reviews/submit';
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            return ['ok' => false, 'error' => 'Feedback payload could not be encoded.'];
        }

        $statusCode = 0;
        $responseBody = false;

        if (function_exists('curl_init')) {
            $handle = curl_init($endpoint);
            curl_setopt_array($handle, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json'],
                CURLOPT_POSTFIELDS => $body,
            ]);
            $responseBody = curl_exec($handle);
            $statusCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
            $transportError = curl_error($handle);
            curl_close($handle);

            if ($responseBody === false) {
                return ['ok' => false, 'error' => $transportError ?: 'Feedback service is unavailable.'];
            }
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Accept: application/json\r\nContent-Type: application/json\r\n",
                    'content' => $body,
                    'ignore_errors' => true,
                    'timeout' => 10,
                ],
            ]);
            $responseBody = @file_get_contents($endpoint, false, $context);
            foreach ((array) ($http_response_header ?? []) as $header) {
                if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches)) {
                    $statusCode = (int) $matches[1];
                    break;
                }
            }
        }

        $decoded = is_string($responseBody) ? json_decode($responseBody, true) : null;
        if ($statusCode < 200 || $statusCode >= 300 || !is_array($decoded) || !($decoded['ok'] ?? false)) {
            return [
                'ok' => false,
                'error' => is_array($decoded) && isset($decoded['error'])
                    ? (string) $decoded['error']
                    : 'Feedback service returned an invalid response.',
            ];
        }

        return $decoded;
    }

    private function isDevelopmentSite(string $siteUrl): bool
    {
        $host = strtolower((string) parse_url($siteUrl, PHP_URL_HOST));

        return $host === 'localhost'
            || $host === '127.0.0.1'
            || $host === '::1'
            || (bool) preg_match('/\.(?:test|local)$/', $host);
    }

    private function hasPostCategoryTable(): bool
    {
        return $this->tableExists('cci_blog_post_category');
    }

    private function categoryImageColumnAvailable(): bool
    {
        return $this->categoryColumnAvailable('image_url');
    }

    private function categoryColumnAvailable(string $columnName): bool
    {
        return (bool) Db::getInstance()->getValue(
            'SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = "' . _DB_PREFIX_ . 'cci_blog_category"
             AND COLUMN_NAME = "' . pSQL($columnName) . '"'
        );
    }

    private function deleteFromTableIfExists(string $tableName, string $where): void
    {
        if (!$this->tableExists($tableName)) {
            return;
        }

        Db::getInstance()->delete($tableName, $where);
    }

    private function tableExists(string $tableName): bool
    {
        return (bool) Db::getInstance()->getValue(
            'SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = "' . _DB_PREFIX_ . pSQL($tableName) . '"'
        );
    }

    private function hasPostLangColumn(string $columnName): bool
    {
        return (bool) Db::getInstance()->getValue(
            'SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = "' . _DB_PREFIX_ . 'cci_blog_post_lang"
             AND COLUMN_NAME = "' . pSQL($columnName) . '"'
        );
    }
}
