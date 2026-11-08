<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/** Short-lived snapshots live outside the storefront and never update a post. */
final class CciBlogPostPreview
{
    public const LIFETIME = 900;
    private const MAX_BYTES = 2097152;

    public static function create(array $post, int $shopId, int $langId): string
    {
        $directory = self::directory();
        if ((!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) || is_link($directory)) {
            throw new RuntimeException('The private preview could not be created.');
        }
        foreach (array_slice(glob($directory . '/*.json') ?: [], 0, 200) as $old) {
            if (filemtime($old) < time() - self::LIFETIME) {
                unlink($old);
            }
        }
        $id = bin2hex(random_bytes(24));
        $expires = time() + self::LIFETIME;
        $data = json_encode($post, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        if (strlen($data) > self::MAX_BYTES) {
            throw new RuntimeException('The article is too large for a private preview.');
        }
        $file = $directory . '/' . $id . '.json';
        if (file_put_contents($file, $data, LOCK_EX) === false) {
            throw new RuntimeException('The private preview could not be created.');
        }
        chmod($file, 0600);

        return $id . '.' . $expires . '.' . self::signature($id, $expires, $shopId, $langId);
    }

    public static function read(string $token, int $shopId, int $langId): ?array
    {
        if (!preg_match('/^([a-f0-9]{48})\.([0-9]{10})\.([a-f0-9]{64})$/D', $token, $parts)) {
            return null;
        }
        [, $id, $expires, $signature] = $parts;
        if ((int) $expires <= time() || (int) $expires > time() + self::LIFETIME
            || !hash_equals(self::signature($id, (int) $expires, $shopId, $langId), $signature)) {
            return null;
        }
        $file = self::directory() . '/' . $id . '.json';
        if (!is_file($file) || is_link($file) || filesize($file) > self::MAX_BYTES) {
            return null;
        }
        $post = json_decode((string) file_get_contents($file), true);

        return is_array($post) ? $post : null;
    }

    private static function directory(): string
    {
        return rtrim(sys_get_temp_dir(), '/\\') . '/cci-blog-previews-' . substr(hash('sha256', _COOKIE_KEY_), 0, 24);
    }

    private static function signature(string $id, int $expires, int $shopId, int $langId): string
    {
        return hash_hmac('sha256', implode(':', ['cci-blog-preview', $id, $expires, $shopId, $langId]), _COOKIE_KEY_);
    }
}
