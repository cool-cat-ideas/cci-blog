<?php
declare(strict_types=1);
if (!defined('_PS_VERSION_')) { exit; }

class Cci_BlogAuthorModuleFrontController extends CciBlogFrontController
{
    private array $blogMeta = [];

    public function init(): void
    {
        parent::init();

        $authorId = (int) Tools::getValue('id', 0);
        $authorSlug = $this->getRouteSlug();
        $langId = (int) $this->context->language->id;
        $author = $authorId > 0
            ? $this->getAuthorById($authorId, $langId)
            : $this->getAuthorBySlug($authorSlug, $langId);
        if (!$author && $authorSlug !== '' && ctype_digit($authorSlug)) {
            $author = $this->getAuthorById((int) $authorSlug, $langId);
        }
        if ($author) {
            $this->blogMeta = [
                'title' => $author['display_name'] . ' – ' . Configuration::get('PS_SHOP_NAME'),
                'description' => substr(strip_tags($author['bio'] ?? ''), 0, 160),
            ];
            $baseUrl = $this->buildAuthorUrl($this->buildAuthorSlug((string) $author['display_name']));
            $this->blogCanonicalUrl = $this->getCanonicalPaginationUrl(
                $baseUrl,
                max(1, (int) Tools::getValue('page', 1))
            );
        }
    }

    public function initContent(): void
    {
        parent::initContent();

        $authorId = (int) Tools::getValue('id', 0);
        $requestedAuthorId = $authorId;
        $authorSlug = $this->getRouteSlug();
        $langId   = (int) $this->context->language->id;
        $shopId   = (int) $this->context->shop->id;
        $page     = max(1, (int) Tools::getValue('page', 1));
        $perPage  = (int) Configuration::get('CCB_POSTS_PER_PAGE') ?: 9;

        $author = $authorId > 0
            ? $this->getAuthorById($authorId, $langId)
            : $this->getAuthorBySlug($authorSlug, $langId);

        if (!$author && $authorSlug !== '' && ctype_digit($authorSlug)) {
            $author = $this->getAuthorById((int) $authorSlug, $langId);
        }

        if (!$author) {
            $this->renderNotFound();
            return;
        }

        $authorId = (int) $author['id_author'];
        $canonicalSlug = $this->buildAuthorSlug((string) $author['display_name']);
        $baseUrl = $this->buildAuthorUrl($canonicalSlug);
        $pageUrlPattern = $this->getPaginationUrlPattern($baseUrl);
        $author['slug'] = $canonicalSlug;
        $author['initials'] = $this->buildInitials((string) $author['display_name']);
        $author['url'] = $page > 1
            ? str_replace('{page}', (string) $page, $pageUrlPattern)
            : $baseUrl;
        $authorStats = $this->getAuthorStats($authorId, $langId, $shopId);

        if (($authorSlug !== '' && $authorSlug !== $canonicalSlug) || ($authorSlug === '' && $requestedAuthorId > 0)) {
            Tools::redirect($author['url'], '', null, 'HTTP/1.1 301 Moved Permanently');
        }

        $this->redirectPaginationToCanonical($baseUrl, $page);

        $posts = CciBlogPost::getList($langId, $shopId, $page, $perPage, null, null, $authorId);
        $total = CciBlogPost::getTotal($langId, $shopId, null, null, $authorId);
        $pages = (int) ceil($total / $perPage);
        $pagination = CciBlogPagination::build(
            $page,
            $perPage,
            $total,
            $baseUrl,
            [],
            $pageUrlPattern
        );

        $allCategories = CciBlogCategory::getSidebarCategories($langId, $shopId);
        $tagCloud      = CciBlogTag::getCloud($langId, $shopId);

        $this->context->smarty->assign([
            'ccb_author'      => $author,
            'ccb_author_stats' => $authorStats,
            'ccb_posts'       => $posts,
            'ccb_page'        => $page,
            'ccb_pages'       => $pages,
            'ccb_total'       => $total,
            'ccb_pagination'  => $pagination,
            'listing'         => ['pagination' => $pagination],
            'ccb_categories'  => $allCategories,
            'ccb_tag_cloud'   => $tagCloud,
            'ccb_base'        => Configuration::get('CCB_BASE_SLUG'),
            'ccb_layout'      => Configuration::get('CCB_LAYOUT'),
            'ccb_sidebar'     => Configuration::get('CCB_SIDEBAR_POSITION'),
            'ccb_show_author'    => (bool) Configuration::get('CCB_SHOW_AUTHOR'),
            'ccb_show_date'      => (bool) Configuration::get('CCB_SHOW_DATE'),
            'ccb_show_views'     => (bool) Configuration::get('CCB_SHOW_VIEWS'),
            'ccb_show_read_time' => (bool) Configuration::get('CCB_SHOW_READ_TIME'),
            'ccb_breadcrumb'  => (bool) Configuration::get('CCB_BREADCRUMB'),
        ]);

        $this->page_name = 'module-cci_blog-author';
        $this->blogMeta = [
            'title'       => $author['display_name'] . ' – ' . Configuration::get('PS_SHOP_NAME'),
            'description' => substr(strip_tags($author['bio'] ?? ''), 0, 160),
        ];

        $this->setTemplate('module:cci_blog/views/templates/front/author.tpl');
    }

    public function getTemplateVarPage()
    {
        $page = parent::getTemplateVarPage();

        if ($this->blogMeta) {
            $page['meta']['title'] = $this->blogMeta['title'];
            $page['meta']['description'] = $this->blogMeta['description'];
        }

        return $page;
    }

    private function renderNotFound(): void
    {
        header('HTTP/1.1 404 Not Found');
        header('Status: 404 Not Found');
        $this->notFound = true;
        $this->setTemplate('errors/404');
    }

    private function getAuthorById(int $authorId, int $langId): array
    {
        return Db::getInstance()->getRow(
            "SELECT e.id_employee AS id_author,
                    COALESCE(NULLIF(a.display_name, ''), CONCAT(e.firstname, ' ', e.lastname)) AS display_name,
                    a.avatar, a.twitter, a.linkedin, al.bio
             FROM `" . _DB_PREFIX_ . "employee` e
             LEFT JOIN `" . _DB_PREFIX_ . "cci_blog_author` a
                 ON a.id_employee = e.id_employee AND a.active = 1
             LEFT JOIN `" . _DB_PREFIX_ . "cci_blog_author_lang` al
                 ON al.id_author = a.id_author AND al.id_lang = $langId
             WHERE e.id_employee = $authorId AND e.active = 1"
        ) ?: [];
    }

    private function getAuthorBySlug(string $slug, int $langId): array
    {
        if ($slug === '') {
            return [];
        }

        $authors = Db::getInstance()->executeS(
            "SELECT e.id_employee AS id_author,
                    COALESCE(NULLIF(a.display_name, ''), CONCAT(e.firstname, ' ', e.lastname)) AS display_name,
                    a.avatar, a.twitter, a.linkedin, al.bio
             FROM `" . _DB_PREFIX_ . "employee` e
             LEFT JOIN `" . _DB_PREFIX_ . "cci_blog_author` a
                 ON a.id_employee = e.id_employee AND a.active = 1
             LEFT JOIN `" . _DB_PREFIX_ . "cci_blog_author_lang` al
                 ON al.id_author = a.id_author AND al.id_lang = $langId
             WHERE e.active = 1
             ORDER BY e.id_employee ASC"
        ) ?: [];

        foreach ($authors as $author) {
            if ($this->buildAuthorSlug((string) ($author['display_name'] ?? '')) === $slug) {
                return $author;
            }
        }

        return [];
    }

    private function getRouteSlug(): string
    {
        $slug = trim((string) Tools::getValue('slug', ''));
        if ($slug !== '') {
            return preg_replace('/[^_a-zA-Z0-9-]/', '', $slug) ?: '';
        }

        $path = trim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/');
        $base = trim((string) (Configuration::get('CCB_BASE_SLUG') ?: 'blog'), '/');
        if ($path === '' || $base === '') {
            return '';
        }

        $segments = explode('/', $path);
        $baseSegments = explode('/', $base);
        $baseCount = count($baseSegments);

        for ($offset = 0, $limit = count($segments) - $baseCount - 1; $offset <= $limit; $offset++) {
            if (array_slice($segments, $offset, $baseCount) !== $baseSegments) {
                continue;
            }

            if (($segments[$offset + $baseCount] ?? '') === 'author') {
                return preg_replace('/[^_a-zA-Z0-9-]/', '', (string) ($segments[$offset + $baseCount + 1] ?? '')) ?: '';
            }
        }

        return '';
    }

    private function buildAuthorSlug(string $name): string
    {
        $slug = Tools::str2url($name);

        return $slug !== '' ? $slug : 'author';
    }

    private function buildAuthorUrl(string $slug): string
    {
        return $this->context->link->getModuleLink('cci_blog', 'author', ['slug' => $slug]);
    }

    private function getAuthorStats(int $authorId, int $langId, int $shopId): array
    {
        $row = Db::getInstance()->getRow(
            "SELECT COALESCE(SUM(p.views), 0) AS total_views,
                    MAX(p.date_published) AS latest_published
             FROM `" . _DB_PREFIX_ . "cci_blog_post` p
             INNER JOIN `" . _DB_PREFIX_ . "cci_blog_post_lang` pl
                 ON pl.id_post = p.id_post AND pl.id_lang = $langId AND pl.id_shop = $shopId
             INNER JOIN `" . _DB_PREFIX_ . "cci_blog_post_shop` ps
                 ON ps.id_post = p.id_post AND ps.id_shop = $shopId
             WHERE p.id_author = $authorId
                 AND p.active = 1
                 AND (p.date_published IS NULL OR p.date_published <= '" . pSQL(date('Y-m-d H:i:s')) . "')"
        ) ?: [];

        return [
            'total_views' => (int) ($row['total_views'] ?? 0),
            'latest_published' => (string) ($row['latest_published'] ?? ''),
        ];
    }

    private function buildInitials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $initials = '';

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $initials .= Tools::strtoupper(Tools::substr($part, 0, 1));
            if (Tools::strlen($initials) >= 2) {
                break;
            }
        }

        return $initials !== '' ? $initials : '?';
    }
}
