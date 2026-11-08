<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

final class CciBlogArticleSidebar
{
    public static function normalizeContent(string $value): string
    {
        return in_array($value, ['widgets', 'hook', 'widgets_hook'], true) ? $value : 'none';
    }

    public static function showsWidgets(string $value): bool
    {
        return in_array($value, ['widgets', 'widgets_hook'], true);
    }

    public static function showsHook(string $value): bool
    {
        return in_array($value, ['hook', 'widgets_hook'], true);
    }

    public static function renderHook(string $name, int $postId, Context $context): string
    {
        // Reuse the editor's allowlist and protected Pro renderer. Never execute
        // an arbitrary configured hook directly from the storefront controller.
        return CciBlogPost::renderBlocksToHtml([[
            'type' => 'hook',
            'hook' => $name,
            'placement' => 'article_sidebar',
            'id_post' => $postId,
            'id_shop' => (int) $context->shop->id,
            'id_lang' => (int) $context->language->id,
        ]], $context);
    }
}
