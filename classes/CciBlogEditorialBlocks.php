<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/** Bounded editorial block data shared by rendering and publication checks. */
final class CciBlogEditorialBlocks
{
    public static function summary(array $block): array
    {
        $title = self::text($block['title'] ?? '') ?: 'In brief';
        $items = [];
        foreach (array_slice(is_array($block['items'] ?? null) ? $block['items'] : [], 0, 8) as $item) {
            $text = self::text($item);
            if ($text !== '') {
                $items[] = function_exists('mb_substr') ? mb_substr($text, 0, 500, 'UTF-8') : substr($text, 0, 500);
            }
        }

        return ['title' => $title, 'items' => $items];
    }

    private static function text($value): string
    {
        return is_scalar($value) ? trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '';
    }

    /** Render only static editorial blocks. Never executes hooks or product presenters. */
    public static function render(array $block): ?string
    {
        return match ((string) ($block['type'] ?? '')) {
            'heading' => self::renderHeadingBlock($block),
            'paragraph' => self::renderParagraphBlock($block),
            'link' => self::renderLinkBlock($block),
            'image' => self::renderImageBlock($block),
            'summary' => self::renderSummaryBlock($block),
            'video' => self::renderVideoBlock($block),
            default => null,
        };
    }

    public static function renderHeadingBlock(array $block): string
    {
        $level = max(2, min(4, (int) ($block['level'] ?? 2)));
        $text = trim((string) ($block['text'] ?? ''));
        if ($text === '') {
            return '';
        }

        $inlineHtml = trim((string) ($block['html'] ?? ''));
        $content = $inlineHtml !== ''
            ? self::sanitizeRichHtml(strip_tags($inlineHtml, '<a><strong><b><em><i><u><s><strike><code><br>'))
            : htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        return '<h' . $level . ' class="cci-blog-content-heading">' . $content . '</h' . $level . '>';
    }

    public static function renderParagraphBlock(array $block): string
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

    public static function renderLinkBlock(array $block): string
    {
        $anchor = self::renderLinkAnchor($block);
        if ($anchor === '') {
            return '';
        }

        return '<div class="cci-blog-content-link-wrap">' . $anchor . '</div>';
    }

    public static function renderLinkAnchor(array $block): string
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

    public static function renderSummaryBlock(array $block): string
    {
        $summary = CciBlogEditorialBlocks::summary($block);
        if (!$summary['items']) {
            return '';
        }
        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        return '<section class="cci-blog-summary"><h2 class="cci-blog-summary-title">' . $escape($summary['title']) . '</h2><ul class="cci-blog-summary-list"><li>'
            . implode('</li><li>', array_map($escape, $summary['items'])) . '</li></ul></section>';
    }

    public static function renderImageBlock(array $block, array $managed = []): string
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

    public static function renderVideoBlock(array $block): string
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

    public static function sanitizeImageAlignment(string $alignment): string
    {
        $alignment = strtolower(trim($alignment));

        return in_array($alignment, ['left', 'center', 'right'], true) ? $alignment : '';
    }

    public static function buildAnchorAttributes(array $data, string $baseClass = ''): string
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

    public static function buildHtmlAttributes(array $attributes): string
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

    public static function sanitizeRichHtml(string $html): string
    {
        $html = preg_replace('#<(script|style|template)\b[^>]*>.*?</\1\s*>#is', '', $html) ?? '';
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

    public static function parseHtmlAttributes(string $raw): array
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

    public static function sanitizeUrl(string $url): string
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

    public static function sanitizeTarget(string $target): string
    {
        $target = trim($target);
        if ($target === '') {
            return '';
        }

        return preg_match('/^(_self|_blank|_parent|_top|[a-zA-Z][a-zA-Z0-9_-]{0,31})$/', $target) ? $target : '';
    }

    public static function sanitizeRel(string $rel): string
    {
        $tokens = preg_split('/\s+/', strtolower(trim($rel))) ?: [];
        $tokens = array_filter($tokens, static fn (string $token): bool => (bool) preg_match('/^[a-z0-9_-]{1,32}$/', $token));

        return implode(' ', array_values(array_unique($tokens)));
    }

    public static function sanitizeLinkVariant(string $variant): string
    {
        $variant = strtolower(trim($variant));
        $variant = preg_replace('/[^a-z0-9_-]+/', '_', $variant);

        return in_array($variant, ['button', 'muted'], true) ? $variant : '';
    }

    public static function inferLinkVariantFromClass(string $class): string
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

    public static function sanitizeHtmlId(string $id): string
    {
        $id = trim($id);

        return preg_match('/^[a-zA-Z][a-zA-Z0-9_:.:-]{0,63}$/', $id) ? $id : '';
    }
}
