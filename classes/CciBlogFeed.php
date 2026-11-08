<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

final class CciBlogFeed
{
    public static function render(array $posts, array $channel): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:media="http://search.yahoo.com/mrss/"><channel>'
            . self::element('title', $channel['title']) . self::element('link', $channel['url'])
            . '<atom:link href="' . self::escape($channel['feedUrl']) . '" rel="self" type="application/rss+xml"/>'
            . self::element('description', $channel['description']) . self::element('language', $channel['language']);
        $modified = self::lastModified($posts);
        if ($modified > 0) {
            $xml .= self::element('lastBuildDate', gmdate(DATE_RSS, $modified));
        }
        foreach ($posts as $post) {
            $published = strtotime((string) ($post['date_published'] ?: $post['date_add']));
            $guid = 'urn:cci-blog:' . $channel['identity'] . ':' . (int) $channel['shopId'] . ':' . (int) $channel['langId'] . ':' . (int) $post['id_post'];
            $xml .= '<item>' . self::element('title', $post['title']) . self::element('link', $post['url'])
                . '<guid isPermaLink="false">' . self::escape($guid) . '</guid>'
                . ($published ? self::element('pubDate', gmdate(DATE_RSS, $published)) : '')
                . self::element('description', html_entity_decode(strip_tags((string) ($post['intro'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if (!empty($post['cover_image'])) {
                $xml .= '<media:thumbnail url="' . self::escape($post['cover_image']) . '"/>';
            }
            $xml .= '</item>';
        }

        return $xml . '</channel></rss>';
    }

    public static function lastModified(array $posts): int
    {
        $modified = 0;
        foreach ($posts as $post) {
            foreach (['date_upd', 'date_published', 'date_add'] as $field) {
                $modified = max($modified, (int) strtotime((string) ($post[$field] ?? '')));
            }
        }

        return $modified;
    }

    public static function notModified(string $etag, int $modified, array $server): bool
    {
        if (isset($server['HTTP_IF_NONE_MATCH'])) {
            foreach (explode(',', (string) $server['HTTP_IF_NONE_MATCH']) as $candidate) {
                if (trim($candidate) === '*' || preg_replace('/^W\//', '', trim($candidate)) === $etag) {
                    return true;
                }
            }

            return false;
        }

        return $modified > 0 && isset($server['HTTP_IF_MODIFIED_SINCE'])
            && (int) strtotime((string) $server['HTTP_IF_MODIFIED_SINCE']) >= $modified;
    }

    /** Track the whole representation, including deletions and shop metadata. */
    public static function validators(string $xml, string $scope): array
    {
        $etag = '"' . hash('sha256', $xml) . '"';
        $result = ['etag' => $etag, 'modified' => 0];
        $directory = rtrim(sys_get_temp_dir(), '/\\') . '/cci-blog-feeds-' . substr(hash('sha256', _COOKIE_KEY_), 0, 24);
        if ((!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) || is_link($directory)) {
            return $result;
        }
        $path = $directory . '/' . hash('sha256', $scope) . '.json';
        if (is_link($path) || !($file = @fopen($path, 'c+'))) {
            return $result;
        }

        try {
            if (!flock($file, LOCK_EX)) {
                return $result;
            }
            $previous = json_decode((string) stream_get_contents($file), true);
            $result['modified'] = ($previous['etag'] ?? '') === $etag ? (int) $previous['modified'] : time();
            if (($previous['etag'] ?? '') !== $etag) {
                rewind($file);
                ftruncate($file, 0);
                if (fwrite($file, json_encode($result)) === false) {
                    $result['modified'] = 0;
                }
                chmod($path, 0600);
            }
            flock($file, LOCK_UN);
        } finally {
            fclose($file);
        }

        return $result;
    }

    private static function element(string $name, string $value): string
    {
        return '<' . $name . '>' . self::escape($value) . '</' . $name . '>';
    }

    private static function escape(string $value): string
    {
        $value = mb_scrub($value, 'UTF-8');
        $value = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value) ?? '';

        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    }
}
