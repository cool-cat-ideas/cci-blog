<?php
/**
 * CCI Blog – Post model
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

class CciBlogPost extends ObjectModel
{
    private static $externalContentHookOptions = null;
    private static $storefrontContentHookOptions = null;
    private static $allowedContentHookNames = null;

    /** @var int */
    public $id_category;
    /** @var int */
    public $id_author;
    /** @var bool */
    public $active;
    /** @var bool */
    public $featured;
    /** @var bool */
    public $allow_comments;
    /** @var int */
    public $views;
    /** @var string */
    public $cover_image;
    /** @var string */
    public $og_image;
    /** @var string */
    public $date_published;
    /** @var string */
    public $date_add;
    /** @var string */
    public $date_upd;

    // Multilang fields
    /** @var string */
    public $title;
    /** @var string */
    public $slug;
    /** @var string */
    public $intro;
    /** @var string */
    public $content;
    /** @var string */
    public $content_blocks;
    /** @var string */
    public $meta_title;
    /** @var string */
    public $meta_description;
    /** @var string */
    public $meta_keywords;
    /** @var string */
    public $focus_keyword;
    /** @var string */
    public $seo_content_type;
    /** @var string */
    public $og_title;
    /** @var string */
    public $og_description;

    public static $definition = [
        'table'     => 'cci_blog_post',
        'primary'   => 'id_post',
        'multilang' => true,
        'multilang_shop' => true,
        'fields'    => [
            'id_category'    => ['type' => self::TYPE_INT,  'validate' => 'isUnsignedInt'],
            'id_author'      => ['type' => self::TYPE_INT,  'validate' => 'isUnsignedInt'],
            'active'         => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'featured'       => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'allow_comments' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'views'          => ['type' => self::TYPE_INT,  'validate' => 'isUnsignedInt'],
            'cover_image'    => ['type' => self::TYPE_STRING, 'validate' => 'isCleanHtml', 'size' => 255],
            'og_image'       => ['type' => self::TYPE_STRING, 'validate' => 'isCleanHtml', 'size' => 255],
            'date_published' => ['type' => self::TYPE_DATE, 'validate' => 'isDateFormat'],
            'date_add'       => ['type' => self::TYPE_DATE, 'validate' => 'isDateFormat'],
            'date_upd'       => ['type' => self::TYPE_DATE, 'validate' => 'isDateFormat'],
            // Lang fields
            'title'            => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isCleanHtml', 'size' => 512, 'required' => true],
            'slug'             => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isLinkRewrite', 'size' => 512, 'required' => true],
            'intro'            => ['type' => self::TYPE_HTML,   'lang' => true, 'validate' => 'isCleanHtml'],
            'content'          => ['type' => self::TYPE_HTML,   'lang' => true, 'validate' => 'isCleanHtml'],
            'content_blocks'   => ['type' => self::TYPE_HTML,   'lang' => true, 'validate' => 'isCleanHtml'],
            'meta_title'       => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isCleanHtml', 'size' => 512],
            'meta_description' => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isCleanHtml', 'size' => 512],
            'meta_keywords'    => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isCleanHtml', 'size' => 255],
            'focus_keyword'    => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isCleanHtml', 'size' => 255],
            'seo_content_type' => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isCleanHtml', 'size' => 32],
            'og_title'         => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isCleanHtml', 'size' => 255],
            'og_description'   => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isCleanHtml', 'size' => 512],
        ],
    ];

    // -------------------------------------------------------------------------
    // Static queries
    // -------------------------------------------------------------------------

    public static function renderBlocksToHtml(array $blocks, Context $context): string
    {
        $html = [];
        $blocks = self::normalizeLegacyInlineLinkBlocks($blocks);

        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }

            $type = (string) ($block['type'] ?? '');
            $html[] = match ($type) {
                'heading' => self::renderHeadingBlock($block),
                'paragraph' => self::renderParagraphBlock($block),
                'link' => self::renderLinkBlock($block),
                'image' => self::renderImageBlock($block, $context),
                'video' => self::renderVideoBlock($block),
                'columns', 'product', 'product_carousel', 'hook', 'module_block' => self::renderExternalContentBlock($block, $context),
                default => '',
            };
        }

        return implode("\n", array_filter($html));
    }

    private static function renderExternalContentBlock(array $block, Context $context): string
    {
        $type = preg_replace('/[^a-z0-9_\\-]+/', '', strtolower((string) ($block['type'] ?? '')));
        if ($type === '') {
            return '';
        }

        if ($type === 'hook') {
            $hookName = self::sanitizeContentHookName((string) ($block['hook'] ?? ''));
            if ($hookName === '' || !in_array($hookName, self::getAllowedContentHookNames(), true)) {
                return '';
            }

            $block['hook'] = $hookName;
        }

        return (string) Hook::exec('displayCciBlogRenderContentBlock', [
            'type' => $type,
            'block' => $block,
            'context' => $context,
        ]) ?: self::renderExternalContentBlockViaProModule($type, $block, $context);
    }

    private static function renderExternalContentBlockViaProModule(string $type, array $block, Context $context): string
    {
        if (!class_exists('Module')) {
            return '';
        }

        $module = Module::getInstanceByName('cci_blog_pro');
        if (!is_object($module) || !method_exists($module, 'hookDisplayCciBlogRenderContentBlock')) {
            return '';
        }

        return (string) $module->hookDisplayCciBlogRenderContentBlock([
            'type' => $type,
            'block' => $block,
            'context' => $context,
        ]);
    }

    private static function renderHeadingBlock(array $block): string
    {
        $level = max(2, min(4, (int) ($block['level'] ?? 2)));
        $text = trim((string) ($block['text'] ?? ''));
        if ($text === '') {
            return '';
        }

        return '<h' . $level . ' class="cci-blog-content-heading">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</h' . $level . '>';
    }

    private static function renderParagraphBlock(array $block): string
    {
        $richHtml = trim((string) ($block['html'] ?? ''));
        if ($richHtml !== '') {
            return '<div class="cci-blog-content-rich-text">' . self::sanitizeRichHtml($richHtml) . '</div>';
        }

        $content = trim((string) ($block['content'] ?? ''));
        if ($content === '') {
            return '';
        }

        return '<p class="cci-blog-content-paragraph">' . nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8')) . '</p>';
    }

    private static function renderLinkBlock(array $block): string
    {
        $anchor = self::renderLinkAnchor($block);
        if ($anchor === '') {
            return '';
        }

        return '<div class="cci-blog-content-link-wrap">' . $anchor . '</div>';
    }

    private static function renderLinkAnchor(array $block): string
    {
        $attributes = self::buildAnchorAttributes($block, 'cci-blog-content-link');
        if ($attributes === '') {
            return '';
        }

        $label = trim((string) ($block['label'] ?? ''));
        if ($label === '') {
            $label = trim((string) ($block['href'] ?? ''));
        }

        return '<a ' . $attributes . '>'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
            . '</a>';
    }

    /**
     * Moves legacy top-level text links into the surrounding paragraph.
     * Button links remain standalone CTA blocks.
     */
    private static function normalizeLegacyInlineLinkBlocks(array $blocks): array
    {
        $normalized = [];
        $count = count($blocks);

        for ($index = 0; $index < $count; ++$index) {
            $block = $blocks[$index];
            if (!is_array($block) || (string) ($block['type'] ?? '') !== 'link' || self::isButtonLinkBlock($block)) {
                $normalized[] = $block;
                continue;
            }

            $anchor = self::renderLinkAnchor($block);
            if ($anchor === '') {
                continue;
            }
            $anchor = preg_replace('/^<a\s/i', '<a data-cci-blog-advanced-link="true" ', $anchor) ?: $anchor;

            $targetIndex = count($normalized) - 1;
            $mergedWithPrevious = $targetIndex >= 0
                && is_array($normalized[$targetIndex])
                && self::appendInlineHtmlToParagraphBlock($normalized[$targetIndex], $anchor);

            if (!$mergedWithPrevious) {
                $normalized[] = [
                    'type' => 'paragraph',
                    'html' => '<p>' . $anchor . '</p>',
                    'content' => trim(strip_tags($anchor)),
                ];
                $targetIndex = count($normalized) - 1;
            }

            $nextBlock = $blocks[$index + 1] ?? null;
            if (is_array($nextBlock) && (string) ($nextBlock['type'] ?? '') === 'paragraph') {
                $nextInnerHtml = self::getSimpleParagraphInnerHtml($nextBlock);
                if ($nextInnerHtml !== null && self::appendInlineHtmlToParagraphBlock($normalized[$targetIndex], $nextInnerHtml)) {
                    ++$index;
                }
            }
        }

        return $normalized;
    }

    private static function isButtonLinkBlock(array $block): bool
    {
        $variant = self::sanitizeLinkVariant((string) ($block['variant'] ?? $block['link_variant'] ?? ''));
        if ($variant === '') {
            $variant = self::inferLinkVariantFromClass((string) ($block['class'] ?? ''));
        }

        return $variant === 'button';
    }

    private static function appendInlineHtmlToParagraphBlock(array &$block, string $fragment): bool
    {
        if ((string) ($block['type'] ?? '') !== 'paragraph') {
            return false;
        }

        $innerHtml = self::getSimpleParagraphInnerHtml($block);
        if ($innerHtml === null) {
            return false;
        }

        $block['html'] = '<p>' . self::joinInlineHtml($innerHtml, $fragment) . '</p>';
        $block['content'] = trim(strip_tags($block['html']));

        return true;
    }

    private static function getSimpleParagraphInnerHtml(array $block): ?string
    {
        $richHtml = trim((string) ($block['html'] ?? ''));
        if ($richHtml !== '') {
            $richHtml = self::sanitizeRichHtml($richHtml);
            if (preg_match('/^<p(?:\s[^>]*)?>(.*)<\/p>$/is', $richHtml, $matches) === 1) {
                return (string) $matches[1];
            }

            return null;
        }

        $content = trim((string) ($block['content'] ?? ''));

        return $content === '' ? '' : nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8'));
    }

    private static function joinInlineHtml(string $left, string $right): string
    {
        $leftText = html_entity_decode(trim(strip_tags($left)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $rightText = html_entity_decode(trim(strip_tags($right)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $separator = '';

        if ($leftText !== '' && $rightText !== '') {
            $last = substr($leftText, -1);
            $first = substr($rightText, 0, 1);
            if (!preg_match('/[\s(\[{\-\/]/u', $last) && !preg_match('/[\s.,;:!?)}\]\-\/]/u', $first)) {
                $separator = ' ';
            }
        }

        return $left . $separator . $right;
    }

    private static function renderImageBlock(array $block, Context $context): string
    {
        $src = trim((string) ($block['src'] ?? ''));
        if ($src === '') {
            return '';
        }

        $alt = htmlspecialchars((string) ($block['alt'] ?? ''), ENT_QUOTES, 'UTF-8');
        $caption = trim((string) ($block['caption'] ?? ''));
        $width = max(0, min(2400, (int) ($block['width'] ?? 0)));
        $alignment = self::sanitizeImageAlignment((string) ($block['alignment'] ?? ''));
        $figureClasses = ['cci-blog-content-image'];
        if ($alignment !== '') {
            $figureClasses[] = 'cci-blog-content-image-' . $alignment;
        }
        if (!empty($block['framed'])) {
            $figureClasses[] = 'cci-blog-content-image-framed';
        }
        $figureStyle = $width > 0
            ? ' style="--cci-blog-content-image-max-width: ' . $width . 'px"'
            : '';
        $managed = self::getManagedImageAttributes($src, $context);
        $imageAttributes = '';
        if ((string) ($managed['srcset'] ?? '') !== '') {
            $imageAttributes .= ' srcset="' . htmlspecialchars((string) $managed['srcset'], ENT_QUOTES, 'UTF-8') . '"';
            $imageAttributes .= ' sizes="' . htmlspecialchars((string) ($managed['sizes'] ?? '100vw'), ENT_QUOTES, 'UTF-8') . '"';
        }
        if ((int) ($managed['width'] ?? 0) > 0 && (int) ($managed['height'] ?? 0) > 0) {
            $imageAttributes .= ' width="' . (int) $managed['width'] . '" height="' . (int) $managed['height'] . '"';
        }
        $image = '<img src="' . htmlspecialchars((string) ($managed['src'] ?? $src), ENT_QUOTES, 'UTF-8') . '"'
            . $imageAttributes . ' alt="' . $alt . '" loading="lazy">';
        $link = is_array($block['link'] ?? null) ? $block['link'] : [];
        $linkAttributes = self::buildAnchorAttributes($link, 'cci-blog-content-image-link');

        if ($linkAttributes !== '') {
            $image = '<a ' . $linkAttributes . '>' . $image . '</a>';
        }

        $captionHtml = '';
        if ($caption !== '') {
            $captionContent = htmlspecialchars($caption, ENT_QUOTES, 'UTF-8');
            $captionLink = is_array($block['caption_link'] ?? null) ? $block['caption_link'] : [];
            $captionLinkAttributes = self::buildAnchorAttributes($captionLink, 'cci-blog-content-image-caption-link');
            if ($captionLinkAttributes !== '') {
                $captionContent = '<a ' . $captionLinkAttributes . '>' . $captionContent . '</a>';
            }
            $captionHtml = '<figcaption>' . $captionContent . '</figcaption>';
        }

        return '<figure class="' . implode(' ', $figureClasses) . '"' . $figureStyle . '>'
            . $image
            . $captionHtml
            . '</figure>';
    }

    private static function sanitizeImageAlignment(string $alignment): string
    {
        $alignment = strtolower(trim($alignment));

        return in_array($alignment, ['left', 'center', 'right'], true) ? $alignment : '';
    }

    public static function getManagedImageAttributes(string $url, ?Context $context = null): array
    {
        static $cache = [];

        $url = trim($url);
        if ($url === '' || !class_exists('Module')) {
            return [];
        }
        $context = $context ?? Context::getContext();
        $shopId = (int) ($context->shop->id ?? 0);
        $cacheKey = $shopId . ':' . $url;
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        $module = Module::getInstanceByName('cci_blog_pro');
        if (!$module instanceof Module || !method_exists($module, 'getCciManagedImageAttributes')) {
            return $cache[$cacheKey] = [];
        }

        $attributes = $module->getCciManagedImageAttributes($url, $shopId);

        return $cache[$cacheKey] = is_array($attributes) ? $attributes : [];
    }

    private static function hydrateCoverImage(array &$post): void
    {
        $attributes = self::getManagedImageAttributes((string) ($post['cover_image'] ?? ''));
        if ($attributes === []) {
            return;
        }

        $post['cover_image'] = (string) ($attributes['src'] ?? $post['cover_image']);
        $post['cover_image_srcset'] = (string) ($attributes['srcset'] ?? '');
        $post['cover_image_sizes'] = (string) ($attributes['sizes'] ?? '');
        $post['cover_image_width'] = (int) ($attributes['width'] ?? 0);
        $post['cover_image_height'] = (int) ($attributes['height'] ?? 0);
    }

    private static function renderVideoBlock(array $block): string
    {
        $src = self::sanitizeUrl((string) ($block['src'] ?? ''));
        if ($src === '') {
            return '';
        }

        $caption = trim((string) ($block['caption'] ?? ''));
        $title = trim((string) ($block['title'] ?? ''));

        return '<figure class="cci-blog-content-video">'
            . '<video src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" controls preload="metadata"'
            . ($title !== '' ? ' title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '"' : '')
            . '></video>'
            . ($caption !== '' ? '<figcaption>' . htmlspecialchars($caption, ENT_QUOTES, 'UTF-8') . '</figcaption>' : '')
            . '</figure>';
    }

    public static function getDefaultContentHookNames(): array
    {
        return [
            'displayCciBlogPostTop',
            'displayCciBlogPostMiddle',
            'displayCciBlogPostBottom',
        ];
    }

    public static function getAllowedContentHookNames(): array
    {
        if (is_array(self::$allowedContentHookNames)) {
            return self::$allowedContentHookNames;
        }

        $hooks = self::getDefaultContentHookNames();
        foreach (self::getExternalContentHookOptions() as $option) {
            $name = (string) ($option['name'] ?? '');
            if ($name !== '') {
                $hooks[] = $name;
            }
        }
        foreach (self::getStorefrontContentHookOptions() as $option) {
            $name = (string) ($option['name'] ?? '');
            if ($name !== '') {
                $hooks[] = $name;
            }
        }

        self::$allowedContentHookNames = array_values(array_unique($hooks));

        return self::$allowedContentHookNames;
    }

    public static function getExternalContentHookOptions(): array
    {
        if (is_array(self::$externalContentHookOptions)) {
            return self::$externalContentHookOptions;
        }

        $rawOptions = Hook::exec('displayCciBlogContentHookOptions', [], null, true);
        if (!is_array($rawOptions)) {
            self::$externalContentHookOptions = [];

            return self::$externalContentHookOptions;
        }

        $options = [];
        foreach ($rawOptions as $moduleName => $moduleOptions) {
            self::collectContentHookOptions($moduleOptions, is_string($moduleName) ? $moduleName : '', $options);
        }

        self::$externalContentHookOptions = array_values($options);

        return self::$externalContentHookOptions;
    }

    public static function getStorefrontContentHookOptions(): array
    {
        if (is_array(self::$storefrontContentHookOptions)) {
            return self::$storefrontContentHookOptions;
        }

        $rows = Db::getInstance()->executeS(
            'SELECT h.`name`, h.`title`, h.`description`,
                    GROUP_CONCAT(DISTINCT m.`name` ORDER BY hm.`position` ASC, m.`name` ASC SEPARATOR ", ") AS modules,
                    COUNT(DISTINCT m.`id_module`) AS module_count
             FROM `' . _DB_PREFIX_ . 'hook` h
             INNER JOIN `' . _DB_PREFIX_ . 'hook_module` hm ON hm.`id_hook` = h.`id_hook`
             INNER JOIN `' . _DB_PREFIX_ . 'module` m ON m.`id_module` = hm.`id_module`
             WHERE h.`name` LIKE "display%"
             AND m.`active` = 1
             GROUP BY h.`id_hook`, h.`name`, h.`title`, h.`description`
             ORDER BY h.`name` ASC'
        ) ?: [];

        $options = [];
        foreach ($rows as $row) {
            $hookName = self::sanitizeContentHookName((string) ($row['name'] ?? ''));
            if ($hookName === '' || !self::isSafeStorefrontContentHook($hookName)) {
                continue;
            }

            $moduleNames = trim((string) ($row['modules'] ?? ''));
            $title = trim((string) ($row['title'] ?? ''));
            $description = trim((string) ($row['description'] ?? ''));
            if ($description === '') {
                $description = self::translate('Storefront hook with installed module output. It may render empty when a module expects a specific page context.');
            }

            $options[$hookName] = [
                'name' => $hookName,
                'label' => $title !== '' ? $title : $hookName,
                'description' => $description,
                'moduleName' => $moduleNames,
                'moduleCount' => (int) ($row['module_count'] ?? 0),
                'sourceType' => 'storefront',
            ];
        }

        self::$storefrontContentHookOptions = array_values($options);

        return self::$storefrontContentHookOptions;
    }

    private static function collectContentHookOptions($rawOptions, string $moduleName, array &$options): void
    {
        if (is_string($rawOptions)) {
            $decoded = json_decode($rawOptions, true);
            $rawOptions = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }

        if (isset($rawOptions['name']) || isset($rawOptions['hook'])) {
            $rawOptions = [$rawOptions];
        }

        if (!is_array($rawOptions)) {
            return;
        }

        foreach ($rawOptions as $option) {
            if (is_string($option)) {
                $option = ['name' => $option];
            }
            if (!is_array($option)) {
                continue;
            }

            $hookName = self::sanitizeContentHookName((string) ($option['name'] ?? $option['hook'] ?? ''));
            if ($hookName === '') {
                continue;
            }

            $options[$hookName] = [
                'name' => $hookName,
                'label' => trim((string) ($option['label'] ?? $option['title'] ?? $hookName)),
                'description' => trim((string) ($option['description'] ?? '')),
                'moduleName' => trim((string) ($option['moduleName'] ?? $option['module'] ?? $moduleName)),
                'moduleCount' => (int) ($option['moduleCount'] ?? 0),
                'sourceType' => 'extension',
            ];
        }
    }

    private static function sanitizeContentHookName(string $hookName): string
    {
        $hookName = trim($hookName);

        return preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $hookName) ? $hookName : '';
    }

    private static function isSafeStorefrontContentHook(string $hookName): bool
    {
        if (strpos($hookName, 'display') !== 0) {
            return false;
        }

        $blockedExact = [
            'displayAdminAfterHeader',
            'displayAdminBeforeHeader',
            'displayBackOfficeHeader',
            'displayBeforeBodyClosingTag',
            'displayFooter',
            'displayHeader',
            'displayOverrideTemplate',
        ];
        if (in_array($hookName, $blockedExact, true)) {
            return false;
        }

        $lowerHookName = strtolower($hookName);
        foreach (['displayadmin', 'displaybackoffice', 'displaydashboard'] as $blockedPrefix) {
            if (strpos($lowerHookName, $blockedPrefix) === 0) {
                return false;
            }
        }

        return true;
    }

    private static function translate(string $message): string
    {
        try {
            $context = Context::getContext();
            if ($context && method_exists($context, 'getTranslator')) {
                return (string) $context->getTranslator()->trans($message, [], 'Modules.Cciblog.Admin');
            }
        } catch (Throwable $exception) {
            return $message;
        }

        return $message;
    }

    private static function buildAnchorAttributes(array $data, string $baseClass = ''): string
    {
        $href = self::sanitizeUrl((string) ($data['href'] ?? ''));
        if ($href === '') {
            return '';
        }

        $target = self::sanitizeTarget((string) ($data['target'] ?? ''));
        $rel = self::sanitizeRel((string) ($data['rel'] ?? ''));
        if ($target === '_blank') {
            $rel = trim($rel . ' noopener noreferrer');
        }

        $classNames = [];
        if ($baseClass !== '') {
            $classNames[] = $baseClass;
            $variant = self::sanitizeLinkVariant((string) ($data['variant'] ?? $data['link_variant'] ?? ''));
            if ($variant === '') {
                $variant = self::inferLinkVariantFromClass((string) ($data['class'] ?? ''));
            }
            if ($variant !== '') {
                $classNames[] = $baseClass . '--' . $variant;
            }
        }

        $attributes = [
            'href' => $href,
            'class' => implode(' ', $classNames),
        ];

        foreach (['id' => 'id', 'title' => 'title', 'aria_label' => 'aria-label'] as $source => $attribute) {
            $value = trim((string) ($data[$source] ?? ''));
            if ($source === 'id') {
                $value = self::sanitizeHtmlId($value);
            }
            if ($value !== '') {
                $attributes[$attribute] = $value;
            }
        }

        if ($target !== '') {
            $attributes['target'] = $target;
        }
        if ($rel !== '') {
            $attributes['rel'] = implode(' ', array_values(array_unique(explode(' ', $rel))));
        }

        return self::buildHtmlAttributes($attributes);
    }

    private static function buildHtmlAttributes(array $attributes): string
    {
        $html = [];
        foreach ($attributes as $name => $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }

            $html[] = $name . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
        }

        return implode(' ', $html);
    }

    private static function sanitizeRichHtml(string $html): string
    {
        $html = strip_tags($html, '<p><br><strong><b><em><i><u><s><strike><code><pre><hr><ul><ol><li><blockquote><a><h2><h3><h4><table><thead><tbody><tfoot><tr><th><td>');
        $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
        $html = preg_replace('/\s+style\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);

        return (string) preg_replace_callback('/<a\b([^>]*)>/i', static function (array $matches): string {
            $attributes = self::parseHtmlAttributes((string) ($matches[1] ?? ''));
            $classNames = preg_split('/\s+/', trim((string) ($attributes['class'] ?? ''))) ?: [];
            $isAdvancedLink = array_key_exists('data-cci-blog-advanced-link', $attributes)
                || in_array('cci-blog-content-link', $classNames, true);
            $anchorAttributes = self::buildAnchorAttributes($attributes, $isAdvancedLink ? 'cci-blog-content-link' : '');

            return $anchorAttributes === '' ? '<a>' : '<a ' . $anchorAttributes . '>';
        }, $html);
    }

    private static function parseHtmlAttributes(string $raw): array
    {
        $attributes = [];
        preg_match_all('/([a-zA-Z0-9_:-]+)\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))/', $raw, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $name = strtolower((string) $match[1]);
            $value = html_entity_decode((string) ($match[3] ?? $match[4] ?? $match[5] ?? ''), ENT_QUOTES, 'UTF-8');
            if ($name === 'aria-label') {
                $name = 'aria_label';
            }
            $attributes[$name] = $value;
        }

        return $attributes;
    }

    private static function sanitizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (preg_match('/^(#|\/|\?|\.\/|\.\.\/)/', $url)) {
            return $url;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (in_array($scheme, ['http', 'https', 'mailto', 'tel'], true)) {
            return $url;
        }

        return '';
    }

    private static function sanitizeTarget(string $target): string
    {
        $target = trim($target);
        if ($target === '') {
            return '';
        }

        return preg_match('/^(_self|_blank|_parent|_top|[a-zA-Z][a-zA-Z0-9_-]{0,31})$/', $target) ? $target : '';
    }

    private static function sanitizeRel(string $rel): string
    {
        $tokens = preg_split('/\s+/', strtolower(trim($rel))) ?: [];
        $tokens = array_filter($tokens, static fn(string $token): bool => (bool) preg_match('/^[a-z0-9_-]{1,32}$/', $token));

        return implode(' ', array_values(array_unique($tokens)));
    }

    private static function sanitizeLinkVariant(string $variant): string
    {
        $variant = strtolower(trim($variant));
        $variant = preg_replace('/[^a-z0-9_-]+/', '_', $variant);

        return in_array($variant, ['button', 'muted'], true) ? $variant : '';
    }

    private static function inferLinkVariantFromClass(string $class): string
    {
        $tokens = preg_split('/\s+/', strtolower(trim($class))) ?: [];
        foreach ($tokens as $token) {
            if (str_contains($token, 'button') || str_contains($token, 'btn')) {
                return 'button';
            }
            if (str_contains($token, 'muted') || str_contains($token, 'subtle')) {
                return 'muted';
            }
        }

        return '';
    }

    private static function sanitizeHtmlId(string $id): string
    {
        $id = trim($id);

        return preg_match('/^[a-zA-Z][a-zA-Z0-9_:.:-]{0,63}$/', $id) ? $id : '';
    }

    /**
     * Returns paginated list of active posts for the front office.
     */
    public static function getList(
        int $langId,
        int $shopId,
        int $page = 1,
        int $perPage = 9,
        ?int $categoryId = null,
        ?int $tagId = null,
        ?int $authorId = null,
        string $search = ''
    ): array {
        $offset = ($page - 1) * $perPage;
        $where  = 'p.active = 1 AND pl.id_lang = ' . $langId . ' AND ps.id_shop = ' . $shopId
            . " AND (p.date_published IS NULL OR p.date_published <= '" . pSQL(date('Y-m-d H:i:s')) . "')";

        $categoryJoin = '';
        if ($categoryId !== null) {
            if (self::postCategoryTableExists()) {
                $categoryJoin = 'INNER JOIN `' . _DB_PREFIX_ . 'cci_blog_post_category` pc_filter
                    ON pc_filter.id_post = p.id_post';
                $where .= ' AND pc_filter.id_category = ' . $categoryId;
            } else {
                $where .= ' AND p.id_category = ' . $categoryId;
            }
        }
        if ($tagId !== null) {
            $where .= ' AND pt.id_tag = ' . $tagId;
        }
        if ($authorId !== null) {
            $where .= ' AND p.id_author = ' . $authorId;
        }
        if ($search !== '') {
            $escaped = pSQL($search);
            $where  .= " AND MATCH(pl.title, pl.intro, pl.content) AGAINST('$escaped' IN BOOLEAN MODE)";
        }

        $tagJoin = $tagId !== null
            ? 'INNER JOIN `' . _DB_PREFIX_ . 'cci_blog_post_tag` pt ON pt.id_post = p.id_post'
            : '';

        $sql = "
            SELECT p.*, pl.title, pl.slug, pl.intro, pl.content, pl.meta_title, pl.meta_description,
                   COALESCE(NULLIF(a.display_name, ''), CONCAT(e.firstname, ' ', e.lastname)) AS author_name,
                   a.avatar AS author_avatar,
                   cl.name AS category_name, cl.slug AS category_slug
            FROM `" . _DB_PREFIX_ . "cci_blog_post` p
            INNER JOIN `" . _DB_PREFIX_ . "cci_blog_post_lang` pl
                ON pl.id_post = p.id_post AND pl.id_lang = $langId AND pl.id_shop = $shopId
            INNER JOIN `" . _DB_PREFIX_ . "cci_blog_post_shop` ps
                ON ps.id_post = p.id_post AND ps.id_shop = $shopId
            $categoryJoin
            LEFT JOIN `" . _DB_PREFIX_ . "employee` e ON e.id_employee = p.id_author
            LEFT JOIN `" . _DB_PREFIX_ . "cci_blog_author` a ON a.id_employee = p.id_author AND a.active = 1
            LEFT JOIN `" . _DB_PREFIX_ . "cci_blog_category_lang` cl
                ON cl.id_category = p.id_category AND cl.id_lang = $langId AND cl.id_shop = $shopId
            $tagJoin
            WHERE $where
            ORDER BY p.date_published DESC
            LIMIT $offset, $perPage
        ";

        $posts = Db::getInstance()->executeS($sql) ?: [];
        foreach ($posts as &$post) {
            $post['reading_time'] = self::readingTime((string) ($post['content'] ?? $post['intro'] ?? ''));
            $post['author_slug'] = self::buildAuthorSlug((string) ($post['author_name'] ?? ''));
            self::hydrateCoverImage($post);
        }
        unset($post);

        return $posts;
    }

    /**
     * Total count for pagination.
     */
    public static function getTotal(
        int $langId,
        int $shopId,
        ?int $categoryId = null,
        ?int $tagId = null,
        ?int $authorId = null,
        string $search = ''
    ): int {
        $where = 'p.active = 1 AND pl.id_lang = ' . $langId . ' AND ps.id_shop = ' . $shopId
            . " AND (p.date_published IS NULL OR p.date_published <= '" . pSQL(date('Y-m-d H:i:s')) . "')";
        $categoryJoin = '';
        if ($categoryId !== null) {
            if (self::postCategoryTableExists()) {
                $categoryJoin = 'INNER JOIN `' . _DB_PREFIX_ . 'cci_blog_post_category` pc_filter
                    ON pc_filter.id_post = p.id_post';
                $where .= ' AND pc_filter.id_category = ' . $categoryId;
            } else {
                $where .= ' AND p.id_category = ' . $categoryId;
            }
        }
        if ($tagId !== null) {
            $where .= ' AND pt.id_tag = ' . $tagId;
        }
        if ($authorId !== null) {
            $where .= ' AND p.id_author = ' . $authorId;
        }
        if ($search !== '') {
            $escaped = pSQL($search);
            $where  .= " AND MATCH(pl.title, pl.intro, pl.content) AGAINST('$escaped' IN BOOLEAN MODE)";
        }

        $tagJoin = $tagId !== null
            ? 'INNER JOIN `' . _DB_PREFIX_ . 'cci_blog_post_tag` pt ON pt.id_post = p.id_post'
            : '';

        $sql = "
            SELECT COUNT(DISTINCT p.id_post)
            FROM `" . _DB_PREFIX_ . "cci_blog_post` p
            INNER JOIN `" . _DB_PREFIX_ . "cci_blog_post_lang` pl
                ON pl.id_post = p.id_post AND pl.id_lang = $langId AND pl.id_shop = $shopId
            INNER JOIN `" . _DB_PREFIX_ . "cci_blog_post_shop` ps
                ON ps.id_post = p.id_post AND ps.id_shop = $shopId
            $categoryJoin
            $tagJoin
            WHERE $where
        ";

        return (int) Db::getInstance()->getValue($sql);
    }

    /**
     * Find a single post by slug.
     */
    public static function getBySlug(string $slug, int $langId, int $shopId): array
    {
        $sql = "
            SELECT p.*, pl.*,
                   COALESCE(NULLIF(a.display_name, ''), CONCAT(e.firstname, ' ', e.lastname)) AS author_name,
                   a.avatar AS author_avatar, a.twitter, a.linkedin,
                   al.bio AS author_bio,
                   cl.name AS category_name, cl.slug AS category_slug
            FROM `" . _DB_PREFIX_ . "cci_blog_post` p
            INNER JOIN `" . _DB_PREFIX_ . "cci_blog_post_lang` pl
                ON pl.id_post = p.id_post AND pl.id_lang = $langId AND pl.id_shop = $shopId
            INNER JOIN `" . _DB_PREFIX_ . "cci_blog_post_shop` ps
                ON ps.id_post = p.id_post AND ps.id_shop = $shopId
            LEFT JOIN `" . _DB_PREFIX_ . "employee` e ON e.id_employee = p.id_author
            LEFT JOIN `" . _DB_PREFIX_ . "cci_blog_author` a ON a.id_employee = p.id_author AND a.active = 1
            LEFT JOIN `" . _DB_PREFIX_ . "cci_blog_author_lang` al
                ON al.id_author = a.id_author AND al.id_lang = $langId
            LEFT JOIN `" . _DB_PREFIX_ . "cci_blog_category_lang` cl
                ON cl.id_category = p.id_category AND cl.id_lang = $langId AND cl.id_shop = $shopId
            WHERE pl.slug = '" . pSQL($slug) . "' AND p.active = 1
                AND (p.date_published IS NULL OR p.date_published <= '" . pSQL(date('Y-m-d H:i:s')) . "')
        ";

        $post = Db::getInstance()->getRow($sql) ?: [];
        if ($post) {
            self::hydrateRenderedContent($post);
            $post['author_slug'] = self::buildAuthorSlug((string) ($post['author_name'] ?? ''));
            self::hydrateCoverImage($post);
        }

        return $post;
    }

    /**
     * Return the chronologically adjacent published posts for the same shop and language.
     *
     * "previous" points to the nearest older post and "next" to the nearest newer post.
     * The primary key makes the ordering deterministic when publication dates are equal.
     *
     * @return array{previous: array, next: array}
     */
    public static function getAdjacentPosts(
        int $postId,
        string $publishedAt,
        int $langId,
        int $shopId
    ): array {
        $publishedAt = trim($publishedAt);
        if ($postId <= 0 || $publishedAt === '' || !Validate::isDateFormat($publishedAt)) {
            return ['previous' => [], 'next' => []];
        }

        $sortDate = 'COALESCE(p.date_published, p.date_add)';
        $escapedPublishedAt = pSQL($publishedAt);
        $now = pSQL(date('Y-m-d H:i:s'));
        $select = "
            SELECT p.id_post, p.cover_image, p.date_published,
                   pl.title, pl.slug, pl.intro
            FROM `" . _DB_PREFIX_ . "cci_blog_post` p
            INNER JOIN `" . _DB_PREFIX_ . "cci_blog_post_lang` pl
                ON pl.id_post = p.id_post AND pl.id_lang = $langId AND pl.id_shop = $shopId
            INNER JOIN `" . _DB_PREFIX_ . "cci_blog_post_shop` ps
                ON ps.id_post = p.id_post AND ps.id_shop = $shopId
            WHERE p.active = 1
              AND p.id_post != $postId
              AND (p.date_published IS NULL OR p.date_published <= '$now')
        ";

        $previous = Db::getInstance()->getRow(
            $select . "
              AND ($sortDate < '$escapedPublishedAt'
                   OR ($sortDate = '$escapedPublishedAt' AND p.id_post < $postId))
            ORDER BY $sortDate DESC, p.id_post DESC"
        ) ?: [];
        $next = Db::getInstance()->getRow(
            $select . "
              AND ($sortDate > '$escapedPublishedAt'
                   OR ($sortDate = '$escapedPublishedAt' AND p.id_post > $postId))
            ORDER BY $sortDate ASC, p.id_post ASC"
        ) ?: [];

        if ($previous) {
            self::hydrateCoverImage($previous);
        }
        if ($next) {
            self::hydrateCoverImage($next);
        }

        return ['previous' => $previous, 'next' => $next];
    }

    /**
     * Rebuilds storefront HTML from the structured block document.
     *
     * The JSON document is the editable source of truth. Rendering it here
     * prevents previously saved HTML from keeping obsolete wrappers after a
     * renderer update, while legacy posts without blocks still use `content`.
     */
    private static function hydrateRenderedContent(array &$post): void
    {
        $encodedBlocks = trim((string) ($post['content_blocks'] ?? ''));
        if ($encodedBlocks === '') {
            return;
        }

        $blocks = json_decode($encodedBlocks, true);
        if (!is_array($blocks)) {
            return;
        }

        $post['content'] = self::renderBlocksToHtml($blocks, Context::getContext());
    }

    /**
     * Get latest N posts (for home widget).
     */
    public static function getLatest(int $count, int $langId, int $shopId): array
    {
        return self::getList($langId, $shopId, 1, $count);
    }

    /**
     * Get related posts (same category, excluding current).
     */
    public static function getRelated(int $postId, int $categoryId, int $langId, int $shopId, int $limit = 3): array
    {
        $categoryJoin = '';
        $categoryWhere = 'p.id_category = ' . $categoryId;
        if (self::postCategoryTableExists()) {
            $categoryJoin = 'INNER JOIN `' . _DB_PREFIX_ . 'cci_blog_post_category` pc_filter
                ON pc_filter.id_post = p.id_post';
            $categoryWhere = 'pc_filter.id_category = ' . $categoryId;
        }

        $sql = "
            SELECT DISTINCT p.id_post, p.id_author, p.cover_image, p.date_published, p.views,
                   pl.title, pl.slug, pl.intro, pl.content,
                   COALESCE(NULLIF(a.display_name, ''), CONCAT(e.firstname, ' ', e.lastname)) AS author_name,
                   a.avatar AS author_avatar,
                   cl.name AS category_name, cl.slug AS category_slug
            FROM `" . _DB_PREFIX_ . "cci_blog_post` p
            INNER JOIN `" . _DB_PREFIX_ . "cci_blog_post_lang` pl
                ON pl.id_post = p.id_post AND pl.id_lang = $langId AND pl.id_shop = $shopId
            INNER JOIN `" . _DB_PREFIX_ . "cci_blog_post_shop` ps
                ON ps.id_post = p.id_post AND ps.id_shop = $shopId
            $categoryJoin
            LEFT JOIN `" . _DB_PREFIX_ . "employee` e ON e.id_employee = p.id_author
            LEFT JOIN `" . _DB_PREFIX_ . "cci_blog_author` a ON a.id_employee = p.id_author AND a.active = 1
            LEFT JOIN `" . _DB_PREFIX_ . "cci_blog_category_lang` cl
                ON cl.id_category = p.id_category AND cl.id_lang = $langId AND cl.id_shop = $shopId
            WHERE p.active = 1 AND $categoryWhere AND p.id_post != $postId
                AND (p.date_published IS NULL OR p.date_published <= '" . pSQL(date('Y-m-d H:i:s')) . "')
            ORDER BY p.date_published DESC
            LIMIT $limit
        ";

        $posts = Db::getInstance()->executeS($sql) ?: [];
        foreach ($posts as &$post) {
            $post['reading_time'] = self::readingTime((string) ($post['content'] ?? $post['intro'] ?? ''));
            $post['author_slug'] = self::buildAuthorSlug((string) ($post['author_name'] ?? ''));
            self::hydrateCoverImage($post);
        }
        unset($post);

        return $posts;
    }

    private static function buildAuthorSlug(string $name): string
    {
        $slug = Tools::str2url($name);

        return $slug !== '' ? $slug : 'author';
    }

    private static function postCategoryTableExists(): bool
    {
        return (bool) Db::getInstance()->getValue(
            'SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = "' . _DB_PREFIX_ . 'cci_blog_post_category"'
        );
    }

    /**
     * Increment view counter.
     */
    public static function incrementViews(int $postId): void
    {
        Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . 'cci_blog_post` SET views = views + 1 WHERE id_post = ' . $postId
        );
    }

    /**
     * Estimated reading time in minutes.
     */
    public static function readingTime(string $content): int
    {
        $wordCount = str_word_count(strip_tags($content));
        return max(1, (int) ceil($wordCount / 200));
    }

    /**
     * Returns tags for a post.
     */
    public static function getTags(int $postId, int $langId): array
    {
        $sql = "
            SELECT t.id_tag, t.name, t.slug
            FROM `" . _DB_PREFIX_ . "cci_blog_tag` t
            INNER JOIN `" . _DB_PREFIX_ . "cci_blog_post_tag` pt ON pt.id_tag = t.id_tag
            WHERE pt.id_post = $postId AND t.id_lang = $langId
            ORDER BY t.name ASC
        ";

        return Db::getInstance()->executeS($sql) ?: [];
    }

    /**
     * Returns manually linked products for a post.
     */
    public static function getLinkedProducts(int $postId, int $langId): array
    {
        $sql = "
            SELECT pp.id_product, pp.position,
                   pl.name, pl.link_rewrite, p.price, p.reference,
                   im.id_image
            FROM `" . _DB_PREFIX_ . "cci_blog_post_product` pp
            INNER JOIN `" . _DB_PREFIX_ . "product` p ON p.id_product = pp.id_product
            INNER JOIN `" . _DB_PREFIX_ . "product_lang` pl
                ON pl.id_product = p.id_product AND pl.id_lang = $langId
            LEFT JOIN `" . _DB_PREFIX_ . "image` im
                ON im.id_product = p.id_product AND im.cover = 1
            WHERE pp.id_post = $postId AND p.active = 1
            ORDER BY pp.position ASC
        ";

        return Db::getInstance()->executeS($sql) ?: [];
    }

    /**
     * Build Schema.org JSON-LD for a post.
     *
     * @param array<int,array{title:string,url:string}> $breadcrumbLinks
     */
    public static function getSchemaOrg(
        array $post,
        string $url,
        string $shopName,
        string $logoUrl,
        array $breadcrumbLinks = [],
        string $languageIso = ''
    ): string {
        $title = trim((string) ($post['title'] ?? ''));
        $description = trim(strip_tags((string) ($post['meta_description'] ?: ($post['intro'] ?? ''))));
        $publishedAt = self::schemaDate((string) ($post['date_published'] ?: ($post['date_add'] ?? '')));
        $modifiedAt = self::schemaDate((string) ($post['date_upd'] ?: $publishedAt));
        $readingTime = max(1, (int) ($post['reading_time'] ?? self::readingTime((string) ($post['content'] ?? ''))));
        $wordCount = self::schemaWordCount((string) ($post['content'] ?? ''));
        $keywords = self::schemaKeywords($post);
        $author = self::schemaAuthor($post, $shopName);

        $article = self::filterSchemaValue([
            '@type' => 'BlogPosting',
            '@id' => $url . '#blogposting',
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $url,
            ],
            'headline' => $title,
            'description' => $description,
            'url' => $url,
            'inLanguage' => $languageIso,
            'datePublished' => $publishedAt,
            'dateModified' => $modifiedAt,
            'image' => !empty($post['cover_image']) ? [
                '@type' => 'ImageObject',
                'url' => (string) $post['cover_image'],
                'width' => isset($post['cover_image_width']) ? (int) $post['cover_image_width'] : null,
                'height' => isset($post['cover_image_height']) ? (int) $post['cover_image_height'] : null,
            ] : null,
            'author' => $author,
            'publisher' => [
                '@type' => 'Organization',
                'name' => $shopName,
                'logo' => $logoUrl !== '' ? [
                    '@type' => 'ImageObject',
                    'url' => $logoUrl,
                ] : null,
            ],
            'articleSection' => (string) ($post['category_name'] ?? ''),
            'keywords' => $keywords,
            'wordCount' => $wordCount > 0 ? $wordCount : null,
            'timeRequired' => 'PT' . $readingTime . 'M',
        ]);

        $graph = [$article];
        $breadcrumb = self::schemaBreadcrumbList($breadcrumbLinks);
        if ($breadcrumb) {
            $graph[] = $breadcrumb;
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ];

        return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
    }

    private static function schemaAuthor(array $post, string $shopName): array
    {
        $sameAs = [];
        $twitter = ltrim(trim((string) ($post['twitter'] ?? '')), '@');
        if ($twitter !== '') {
            $sameAs[] = 'https://twitter.com/' . rawurlencode($twitter);
        }
        $linkedin = trim((string) ($post['linkedin'] ?? ''));
        if ($linkedin !== '' && preg_match('#^https?://#i', $linkedin)) {
            $sameAs[] = $linkedin;
        }

        return self::filterSchemaValue([
            '@type' => 'Person',
            'name' => (string) ($post['author_name'] ?: $shopName),
            'description' => trim(strip_tags((string) ($post['author_bio'] ?? ''))),
            'image' => (string) ($post['author_avatar'] ?? ''),
            'sameAs' => $sameAs,
        ]);
    }

    private static function schemaDate(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            return '';
        }

        $timestamp = strtotime($date);
        if (!$timestamp) {
            return $date;
        }

        return date(DATE_ATOM, $timestamp);
    }

    private static function schemaKeywords(array $post): string
    {
        $keywords = [];
        foreach (explode(',', (string) ($post['meta_keywords'] ?? '')) as $keyword) {
            $keyword = trim($keyword);
            if ($keyword !== '') {
                $keywords[] = $keyword;
            }
        }
        foreach (($post['tags'] ?? []) as $tag) {
            $name = trim((string) ($tag['name'] ?? ''));
            if ($name !== '') {
                $keywords[] = $name;
            }
        }

        return implode(', ', array_values(array_unique($keywords)));
    }

    private static function schemaWordCount(string $content): int
    {
        $content = html_entity_decode(strip_tags($content), ENT_QUOTES, 'UTF-8');
        if ($content === '') {
            return 0;
        }

        return preg_match_all('/[\p{L}\p{N}]+(?:[’\'-][\p{L}\p{N}]+)*/u', $content) ?: 0;
    }

    /**
     * @param array<int,array{title:string,url:string}> $links
     */
    private static function schemaBreadcrumbList(array $links): array
    {
        $items = [];
        $position = 1;
        foreach ($links as $link) {
            $title = trim((string) ($link['title'] ?? ''));
            $url = trim((string) ($link['url'] ?? ''));
            if ($title === '' || $url === '') {
                continue;
            }
            $items[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $title,
                'item' => $url,
            ];
        }

        if (!$items) {
            return [];
        }

        return [
            '@type' => 'BreadcrumbList',
            '@id' => end($items)['item'] . '#breadcrumb',
            'itemListElement' => $items,
        ];
    }

    private static function filterSchemaValue($value)
    {
        if (is_array($value)) {
            $filtered = [];
            foreach ($value as $key => $item) {
                $item = self::filterSchemaValue($item);
                if ($item === null || $item === '' || $item === []) {
                    continue;
                }
                $filtered[$key] = $item;
            }

            return $filtered;
        }

        return $value;
    }
}
