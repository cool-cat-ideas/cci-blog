<?php

/**
 * Front controller – single blog post
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

class Cci_BlogPostModuleFrontController extends CciBlogFrontController
{
    private array $blogMeta = [];
    private array $displayPost = [];
    private bool $isPreview = false;

    public function init(): void
    {
        parent::init();

        $previewValue = Tools::getValue('cci_blog_preview', '');
        $previewToken = is_string($previewValue) ? $previewValue : '';
        $this->isPreview = Tools::getIsset('cci_blog_preview');
        if ($this->isPreview) {
            header('Cache-Control: private, no-store, max-age=0');
            header('X-Robots-Tag: noindex, nofollow, noarchive');
            header('Referrer-Policy: no-referrer');
        }
        $post = $this->isPreview ? (CciBlogPostPreview::read($previewToken, (int) $this->context->shop->id, (int) $this->context->language->id) ?? []) : CciBlogPost::getBySlug(
            $this->getRouteSlug(),
            (int) $this->context->language->id,
            (int) $this->context->shop->id,
            false
        );
        $this->displayPost = $post;
        if ($post) {
            $this->blogMeta = [
                'title' => $post['meta_title'] ?: $post['title'],
                'description' => $post['meta_description'] ?: $post['intro'],
                'keywords' => $post['meta_keywords'] ?? '',
            ];
            $this->blogCanonicalUrl = $this->context->link->getModuleLink(
                'cci_blog',
                'post',
                ['slug' => $post['slug']]
            );
        }
    }

    public function initContent(): void
    {
        parent::initContent();

        $slug = $this->getRouteSlug();
        $langId = (int) $this->context->language->id;
        $shopId = (int) $this->context->shop->id;

        // --- 301 redirect for old slugs ---
        $redirect = $this->isPreview ? false : Db::getInstance()->getRow(
            'SELECT id_post FROM `' . _DB_PREFIX_ . "cci_blog_slug_redirect`
             WHERE entity_type = 'post' AND old_slug = '" . pSQL($slug) . "' AND id_lang = $langId"
        );
        if ($redirect) {
            $newSlug = Db::getInstance()->getValue(
                'SELECT slug FROM `' . _DB_PREFIX_ . 'cci_blog_post_lang`
                 WHERE id_post = ' . (int) $redirect['id_post'] . " AND id_lang = $langId AND id_shop = $shopId"
            );
            if ($newSlug && $newSlug !== $slug) {
                $url = $this->context->link->getModuleLink('cci_blog', 'post', ['slug' => $newSlug]);
                Tools::redirect($url, '', null, 'HTTP/1.1 301 Moved Permanently');
            }
        }

        $post = $this->displayPost;
        // Native product miniatures need the theme variables assigned by the
        // parent initContent, including URLs, tokens and module hook variables.
        CciBlogPost::hydrateRenderedContent($post);

        if (!$post) {
            $this->renderNotFound();

            return;
        }

        if (!$this->isPreview && $this->isHtmlRequest() !== (bool) Configuration::get('CCB_URL_SUFFIX_HTML')) {
            $url = $this->context->link->getModuleLink('cci_blog', 'post', ['slug' => $post['slug']]);
            Tools::redirect($url, '', null, 'HTTP/1.1 301 Moved Permanently');
        }

        // Increment view counter (simple, cookie-deduped)
        $cookieKey = 'ccb_viewed_' . $post['id_post'];
        if (!$this->isPreview && !isset($this->context->cookie->$cookieKey)) {
            CciBlogPost::incrementViews((int) $post['id_post']);
            $this->context->cookie->$cookieKey = 1;
        }

        $commentsGloballyEnabled = (bool) Configuration::get('CCB_COMMENTS_ENABLED');
        $commentsProvider = $this->getCommentsProvider();
        $disqusShortname = $this->getDisqusShortname();
        $commentsEnabledForPost = !$this->isPreview && $commentsGloballyEnabled && (bool) $post['allow_comments'];
        $nativeCommentsEnabled = $commentsEnabledForPost && $commentsProvider === 'native';
        $disqusCommentsEnabled = $commentsEnabledForPost
            && $commentsProvider === 'disqus'
            && $disqusShortname !== '';

        $tableOfContents = ['content' => (string) ($post['content'] ?? ''), 'items' => []];
        if ((bool) Configuration::get('CCB_TABLE_OF_CONTENTS_ENABLED')) {
            $tableOfContents = CciBlogTableOfContents::build(
                (string) ($post['content'] ?? ''),
                (int) (Configuration::get('CCB_TABLE_OF_CONTENTS_MIN_HEADINGS') ?: 2)
            );
            $post['content'] = $tableOfContents['content'];
        }

        $post['reading_time'] = CciBlogPost::readingTime($post['content'] ?? '');
        $post['tags'] = $this->isPreview ? ($post['tags'] ?? []) : CciBlogPost::getTags((int) $post['id_post'], $langId);
        $post['comments'] = $nativeCommentsEnabled
            ? CciBlogComment::getApprovedForPost((int) $post['id_post'])
            : [];

        $adjacentPosts = CciBlogPost::getAdjacentPosts(
            (int) $post['id_post'],
            (string) ($post['date_published'] ?: $post['date_add']),
            $langId,
            $shopId
        );

        $sidebarPosition = (string) Configuration::get('CCB_SIDEBAR_POSITION');
        if (!in_array($sidebarPosition, ['left', 'right', 'none'], true)) {
            $sidebarPosition = 'right';
        }

        $tocLayout = 'inline';
        if ($tableOfContents['items'] && Module::isEnabled('cci_blog_pro')) {
            $pro = Module::getInstanceByName('cci_blog_pro');
            if ($pro && method_exists($pro, 'getCciTableOfContentsLayout')) {
                $tocLayout = $pro->getCciTableOfContentsLayout((string) Configuration::get('CCB_TABLE_OF_CONTENTS_LAYOUT'), $shopId);
            }
        }
        if ($tocLayout !== 'inline' && $sidebarPosition === 'none') {
            $sidebarPosition = 'right';
        }

        $sidebarCategories = [];
        $sidebarTagCloud = [];
        $sidebarContent = $tocLayout !== 'inline'
            ? CciBlogArticleSidebar::normalizeContent((string) Configuration::get('CCB_TABLE_OF_CONTENTS_SIDEBAR_CONTENT'))
            : 'none';
        $sidebarWidgets = CciBlogArticleSidebar::showsWidgets($sidebarContent);
        $sidebarHook = CciBlogArticleSidebar::showsHook($sidebarContent)
            ? CciBlogArticleSidebar::renderHook((string) (Configuration::get('CCB_TABLE_OF_CONTENTS_SIDEBAR_HOOK') ?: 'displayCciBlogArticleSidebar'), (int) $post['id_post'], $this->context)
            : '';
        if ($sidebarPosition !== 'none' && ($tocLayout === 'inline' || $sidebarWidgets)) {
            $sidebarCategories = CciBlogCategory::getSidebarCategories($langId, $shopId);
            $sidebarTagCloud = CciBlogTag::getCloud($langId, $shopId);
        }

        // Related
        $related = CciBlogPost::getRelated(
            (int) $post['id_post'],
            (int) $post['id_category'],
            $langId,
            $shopId,
            (int) Configuration::get('CCB_RELATED_POSTS')
        );

        // Linked products
        $products = (int) Configuration::get('CCB_RELATED_PRODUCTS')
            ? CciBlogPost::getLinkedProducts((int) $post['id_post'], $langId)
            : [];

        // SEO
        $url = $this->context->link->getModuleLink('cci_blog', 'post', ['slug' => $slug]);
        $logoFile = (string) Configuration::get('PS_LOGO');
        $logoUrl = $logoFile !== ''
            ? $this->context->link->getMediaLink(__PS_BASE_URI__ . 'img/' . $logoFile)
            : '';
        $schemaOrg = !$this->isPreview && (int) Configuration::get('CCB_SCHEMA_ORG')
            ? CciBlogPost::getSchemaOrg(
                $post,
                $url,
                Configuration::get('PS_SHOP_NAME'),
                $logoUrl,
                $this->buildSchemaBreadcrumbLinks($post, $url),
                (string) $this->context->language->iso_code
            )
            : '';
        $ogTags = !$this->isPreview && (int) Configuration::get('CCB_OG_TAGS') ? CciBlogSeo::ogTags($post, $url) : '';

        // Handle comment submission
        $commentError = '';
        $commentSuccess = false;
        if (Tools::isSubmit('submit_comment') && $nativeCommentsEnabled) {
            [$commentSuccess, $commentError] = $this->handleCommentSubmit((int) $post['id_post'], (int) $post['allow_comments']);
        }

        $gdprConsent = $nativeCommentsEnabled
            ? (string) Hook::exec('displayGDPRConsent', ['id_module' => (int) $this->module->id])
            : '';
        $commentFormStartedAt = time();

        $this->context->smarty->assign([
            'ccb_post' => $post,
            'ccb_preview' => $this->isPreview,
            'ccb_social_enabled' => !$this->isPreview && (bool) Configuration::get('CCB_OG_TAGS'),
            'ccb_previous_post' => $adjacentPosts['previous'],
            'ccb_next_post' => $adjacentPosts['next'],
            'ccb_related' => $related,
            'ccb_products' => $this->presentLinkedProducts($products),
            'ccb_schema' => $schemaOrg,
            'ccb_og' => $ogTags,
            'ccb_base' => Configuration::get('CCB_BASE_SLUG'),
            'ccb_comments_on' => $nativeCommentsEnabled || $disqusCommentsEnabled,
            'ccb_comments_provider' => $commentsProvider,
            'ccb_moderation' => (bool) Configuration::get('CCB_COMMENTS_MODERATION'),
            'ccb_social_share' => !$this->isPreview && (bool) Configuration::get('CCB_SOCIAL_SHARE'),
            'ccb_breadcrumb' => (bool) Configuration::get('CCB_BREADCRUMB'),
            'ccb_disqus_shortname' => $disqusShortname,
            'ccb_table_of_contents' => $tableOfContents['items'],
            'ccb_toc_layout' => $tocLayout,
            'ccb_toc_sidebar_widgets' => $sidebarWidgets,
            'ccb_toc_sidebar_hook' => $sidebarHook,
            'ccb_toc_sticky_gap' => max(8, min(80, (int) (Configuration::get('CCB_TABLE_OF_CONTENTS_STICKY_GAP') ?: 16))),
            'ccb_highlight' => (bool) Configuration::get('CCB_HIGHLIGHT_SYNTAX'),
            'ccb_show_author' => (bool) Configuration::get('CCB_SHOW_AUTHOR'),
            'ccb_show_date' => (bool) Configuration::get('CCB_SHOW_DATE'),
            'ccb_show_views' => !$this->isPreview && (bool) Configuration::get('CCB_SHOW_VIEWS'),
            'ccb_show_read_time' => (bool) Configuration::get('CCB_SHOW_READ_TIME'),
            'ccb_sidebar' => $sidebarPosition,
            'ccb_categories' => $sidebarCategories,
            'ccb_tag_cloud' => $sidebarTagCloud,
            'ccb_feed_enabled' => !$this->isPreview && (bool) Configuration::get('CCB_FEED_ENABLED'),
            'ccb_comment_error' => $commentError,
            'ccb_comment_success' => $commentSuccess,
            'ccb_comment_token' => $this->commentFormToken((int) $post['id_post']),
            'ccb_comment_started_at' => $commentFormStartedAt,
            'ccb_comment_form_signature' => $this->commentFormSignature((int) $post['id_post'], $commentFormStartedAt),
            'ccb_gdpr_consent' => $gdprConsent,
            'ccb_post_url' => $url,
            'ccb_date_format' => '%d %b %Y',
        ]);

        $this->page_name = 'module-cci_blog-post';
        $this->blogMeta = [
            'title' => $post['meta_title'] ?: $post['title'],
            'description' => $post['meta_description'] ?: $post['intro'],
            'keywords' => $post['meta_keywords'] ?? '',
        ];
        $this->setTemplate('module:cci_blog/views/templates/front/post.tpl');
    }

    /** Present linked products with the same listing contract as the active theme. */
    private function presentLinkedProducts(array $products): array
    {
        if (!$products) {
            return [];
        }

        $assembler = new ProductAssembler($this->context);
        $factory = new ProductPresenterFactory($this->context);
        $settings = $factory->getPresentationSettings();
        $presenter = new PrestaShop\PrestaShop\Adapter\Presenter\Product\ProductListingPresenter(
            new PrestaShop\PrestaShop\Adapter\Image\ImageRetriever($this->context->link),
            $this->context->link,
            new PrestaShop\PrestaShop\Adapter\Product\PriceFormatter(),
            new PrestaShop\PrestaShop\Adapter\Product\ProductColorsRetriever(),
            $this->context->getTranslator()
        );
        $result = [];
        foreach ($products as $product) {
            // Fetch current shop/language/pricing data rather than reusing the legacy raw price.
            $assembled = $assembler->assembleProduct(['id_product' => (int) $product['id_product']]);
            $result[] = $presenter->present($settings, $assembled, $this->context->language);
        }

        return $result;
    }

    public function getTemplateVarPage()
    {
        $page = parent::getTemplateVarPage();

        if ($this->blogMeta) {
            $page['meta']['title'] = $this->blogMeta['title'];
            $page['meta']['description'] = strip_tags((string) $this->blogMeta['description']);
            if (!empty($this->blogMeta['keywords'])) {
                $page['meta']['keywords'] = $this->blogMeta['keywords'];
            }
        }

        if ($this->isPreview) {
            $page['canonical'] = '';
            $page['meta']['robots'] = 'noindex,nofollow';
        }

        return $page;
    }

    public function getTemplateVarUrls()
    {
        $urls = parent::getTemplateVarUrls();
        $urls['alternative_langs'] = !$this->isPreview && $this->displayPost
            ? CciBlogSeo::hreflangUrls((int) $this->displayPost['id_post'], (int) $this->context->shop->id) : [];

        return $urls;
    }

    protected function getBreadcrumbLinks()
    {
        $breadcrumb = parent::getBreadcrumbLinks();
        $post = $this->displayPost;
        if (!$post) {
            return $breadcrumb;
        }
        if (!empty($post['category_name']) && !empty($post['category_slug'])) {
            $breadcrumb['links'][] = [
                'title' => (string) $post['category_name'],
                'url' => $this->context->link->getModuleLink('cci_blog', 'category', ['slug' => $post['category_slug']]),
            ];
        }
        $breadcrumb['links'][] = [
            'title' => (string) $post['title'],
            'url' => $this->context->link->getModuleLink('cci_blog', 'post', ['slug' => $post['slug']]),
        ];

        return $breadcrumb;
    }

    protected function getRouteSlug(): string
    {
        $slug = trim((string) Tools::getValue('slug', ''));
        if ($slug !== '') {
            return $slug;
        }

        $path = trim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/');
        $base = trim((string) (Configuration::get('CCB_BASE_SLUG') ?: 'blog'), '/');
        if ($path === '' || $base === '') {
            return '';
        }

        $segments = explode('/', $path);
        $baseSegments = explode('/', $base);
        $baseCount = count($baseSegments);
        $slugSegments = [];

        for ($offset = 0, $limit = count($segments) - $baseCount; $offset <= $limit; ++$offset) {
            if (array_slice($segments, $offset, $baseCount) === $baseSegments) {
                $slugSegments = array_slice($segments, $offset + $baseCount);
                break;
            }
        }

        $candidate = trim((string) ($slugSegments[0] ?? ''));
        $candidate = preg_replace('/\.html$/i', '', $candidate) ?: '';
        if ($candidate === '' || in_array($candidate, ['category', 'tag', 'author', 'search', 'feed.xml'], true)) {
            return '';
        }

        return preg_replace('/[^_a-zA-Z0-9-]/', '', $candidate) ?: '';
    }

    private function isHtmlRequest(): bool
    {
        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);

        return (bool) preg_match('/\.html$/i', rtrim($path, '/'));
    }

    /**
     * @return array<int,array{title:string,url:string}>
     */
    private function buildSchemaBreadcrumbLinks(array $post, string $postUrl): array
    {
        $links = [
            [
                'title' => $this->module->l('Home'),
                'url' => $this->context->link->getPageLink('index'),
            ],
            [
                'title' => $this->module->l('Blog'),
                'url' => $this->context->link->getModuleLink('cci_blog', 'list', []),
            ],
        ];

        if (!empty($post['category_name']) && !empty($post['category_slug'])) {
            $links[] = [
                'title' => (string) $post['category_name'],
                'url' => $this->context->link->getModuleLink('cci_blog', 'category', ['slug' => $post['category_slug']]),
            ];
        }

        $links[] = [
            'title' => (string) ($post['title'] ?? ''),
            'url' => $postUrl,
        ];

        return $links;
    }

    private function renderNotFound(): void
    {
        header('HTTP/1.1 404 Not Found');
        header('Status: 404 Not Found');
        $this->notFound = true;
        $this->setTemplate('errors/404');
    }

    private function handleCommentSubmit(int $postId, int $allowComments): array
    {
        if (!$allowComments) {
            return [false, $this->module->l('Comments are disabled for this post.')];
        }

        $submittedToken = trim((string) Tools::getValue('comment_token', ''));
        if ($submittedToken === '' || !hash_equals($this->commentFormToken($postId), $submittedToken)) {
            return [false, $this->module->l('The comment form expired. Refresh the page and try again.')];
        }

        $startedAt = (int) Tools::getValue('comment_started_at', 0);
        $submittedSignature = trim((string) Tools::getValue('comment_form_signature', ''));
        $age = time() - $startedAt;
        if (
            $startedAt <= 0
            || $submittedSignature === ''
            || !hash_equals($this->commentFormSignature($postId, $startedAt), $submittedSignature)
            || $age < 3
            || $age > 7200
        ) {
            return [false, $this->module->l('The comment form expired. Refresh the page and try again.')];
        }

        $honeypot = trim((string) Tools::getValue('website_url', ''));
        $ip = (string) Tools::getRemoteAddr();

        if (CciBlogComment::isSpam($honeypot, $ip)) {
            return [false, $this->module->l('Your comment was flagged as spam.')];
        }

        $name = trim(strip_tags((string) Tools::getValue('author_name', '')));
        $email = trim((string) Tools::getValue('author_email', ''));
        $content = trim(strip_tags((string) Tools::getValue('comment_content', '')));
        $parent = (int) Tools::getValue('id_parent', 0);

        if (
            $name === ''
            || Tools::strlen($name) > 128
            || !Validate::isEmail($email)
            || Tools::strlen($email) > 255
            || $content === ''
            || Tools::strlen($content) > 3000
            || ($parent > 0 && !CciBlogComment::parentBelongsToPost($parent, $postId))
        ) {
            return [false, $this->module->l('Please fill in all required fields with valid data.')];
        }
        if (CciBlogComment::isDuplicate($postId, $email, $content)) {
            return [false, $this->module->l('This comment has already been submitted.')];
        }

        $comment = new CciBlogComment();
        $comment->id_post = $postId;
        $comment->id_parent = $parent;
        $comment->id_customer = (int) $this->context->customer->id;
        $comment->author_name = $name;
        $comment->author_email = $email;
        $comment->content = $content;
        $comment->status = Configuration::get('CCB_COMMENTS_MODERATION') ? 'pending' : 'approved';
        $comment->ip_address = CciBlogComment::fingerprintIp($ip);
        $comment->date_add = date('Y-m-d H:i:s');
        if (!$comment->add()) {
            return [false, $this->module->l('The comment could not be saved. Please try again.')];
        }

        return [true, ''];
    }

    private function commentFormToken(int $postId): string
    {
        return Tools::hash($this->module->name . '|comment|' . $postId . '|' . Tools::getToken(false));
    }

    private function commentFormSignature(int $postId, int $startedAt): string
    {
        return hash_hmac('sha256', $postId . '|' . $startedAt, (string) _COOKIE_KEY_);
    }

    private function getCommentsProvider(): string
    {
        return (string) Configuration::get('CCB_COMMENTS_PROVIDER') === 'native' ? 'native' : 'disqus';
    }

    private function getDisqusShortname(): string
    {
        $shortname = strtolower(trim((string) Configuration::get('CCB_DISQUS_SHORTNAME')));

        return preg_match('/^[a-z0-9_-]+$/', $shortname) ? $shortname : '';
    }
}
