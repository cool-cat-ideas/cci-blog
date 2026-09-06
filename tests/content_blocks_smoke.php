<?php
declare(strict_types=1);

define('_PS_VERSION_', '9.1.5');

class ObjectModel
{
    public const TYPE_INT = 1;
    public const TYPE_BOOL = 2;
    public const TYPE_STRING = 3;
    public const TYPE_DATE = 4;
    public const TYPE_HTML = 5;
}

class Context
{
}

class Hook
{
    public static function exec($hookName, $params = [], $idModule = null, $arrayReturn = false)
    {
        return '';
    }
}

require_once __DIR__ . '/../classes/CciBlogPost.php';

function assertContentBlock(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$inlineHtml = CciBlogPost::renderBlocksToHtml([
    [
        'type' => 'paragraph',
        'html' => '<p>Among the available materials are</p>',
    ],
    [
        'type' => 'link',
        'label' => 'Plotter inks',
        'href' => 'https://example.com/inks',
    ],
    [
        'type' => 'paragraph',
        'html' => '<p>, which are suitable for outdoor prints.</p>',
    ],
], new Context());

assertContentBlock(
    substr_count($inlineHtml, '<div class="cci-blog-content-rich-text">') === 1,
    'A legacy text link and its adjacent text should render as one rich-text block.'
);
assertContentBlock(
    str_contains(
        $inlineHtml,
        '<p>Among the available materials are <a href="https://example.com/inks" class="cci-blog-content-link">Plotter inks</a>, which are suitable for outdoor prints.</p>'
    ),
    'A text link should remain inline at its original position in the sentence.'
);
assertContentBlock(
    !str_contains($inlineHtml, 'cci-blog-content-link-wrap'),
    'An inline text link must not receive a standalone block wrapper.'
);

$buttonHtml = CciBlogPost::renderBlocksToHtml([
    [
        'type' => 'link',
        'label' => 'View the guide',
        'href' => 'https://example.com/guide',
        'variant' => 'button',
    ],
], new Context());

assertContentBlock(
    str_starts_with($buttonHtml, '<div class="cci-blog-content-link-wrap">'),
    'A button-style link should remain a standalone CTA block.'
);

$imageHtml = CciBlogPost::renderBlocksToHtml([
    [
        'type' => 'image',
        'src' => 'https://example.com/product.jpg',
        'alt' => 'Product image',
        'caption' => 'View the product',
        'width' => 400,
        'alignment' => 'center',
        'framed' => true,
        'link' => ['href' => 'https://example.com/product'],
        'caption_link' => ['href' => 'https://example.com/product'],
    ],
], new Context());

assertContentBlock(
    str_contains($imageHtml, 'class="cci-blog-content-image cci-blog-content-image-center cci-blog-content-image-framed"'),
    'A migrated figure should retain its alignment and frame.'
);
assertContentBlock(
    str_contains($imageHtml, 'style="--cci-blog-content-image-max-width: 400px"'),
    'A migrated figure should retain its safe responsive width.'
);
assertContentBlock(
    str_contains($imageHtml, '<figcaption><a href="https://example.com/product" class="cci-blog-content-image-caption-link">View the product</a></figcaption>'),
    'A linked figure caption should remain inside the figure.'
);

echo "OK: content block rendering smoke test passed.\n";
