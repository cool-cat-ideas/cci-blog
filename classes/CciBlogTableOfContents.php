<?php
/**
 * Builds a semantic table of contents from article heading elements.
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

final class CciBlogTableOfContents
{
    private const WRAPPER_ID = 'cci-blog-table-of-contents-source';

    /**
     * Adds stable unique IDs to H2-H6 headings and returns their hierarchy.
     *
     * @return array{content: string, items: array<int, array{id: string, title: string, level: int, children: array}>}
     */
    public static function build(string $html, int $minimumHeadings = 2): array
    {
        $fallback = ['content' => $html, 'items' => []];
        $minimumHeadings = max(1, min(20, $minimumHeadings));

        if (trim($html) === '' || !class_exists('DOMDocument') || !class_exists('DOMXPath')) {
            return $fallback;
        }

        $previousUseInternalErrors = libxml_use_internal_errors(true);

        try {
            $document = new DOMDocument('1.0', 'UTF-8');
            $document->preserveWhiteSpace = true;
            $loaded = $document->loadHTML(
                '<?xml encoding="UTF-8"><!DOCTYPE html><html><body><div id="'
                . self::WRAPPER_ID
                . '">'
                . $html
                . '</div></body></html>',
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
            );

            if (!$loaded) {
                return $fallback;
            }

            $xpath = new DOMXPath($document);
            $wrapper = $xpath->query('//*[@id="' . self::WRAPPER_ID . '"]')->item(0);
            if (!$wrapper instanceof DOMElement) {
                return $fallback;
            }

            $headingNodes = $xpath->query('.//*[self::h2 or self::h3 or self::h4 or self::h5 or self::h6]', $wrapper);
            if (!$headingNodes instanceof DOMNodeList) {
                return $fallback;
            }

            $headings = [];
            foreach ($headingNodes as $headingNode) {
                if (!$headingNode instanceof DOMElement) {
                    continue;
                }

                $title = preg_replace('/\s+/u', ' ', trim((string) $headingNode->textContent)) ?: '';
                if ($title === '') {
                    continue;
                }

                $headings[] = [
                    'element' => $headingNode,
                    'level' => (int) substr(strtolower($headingNode->tagName), 1),
                    'title' => $title,
                ];
            }

            if (count($headings) < $minimumHeadings) {
                return $fallback;
            }

            $usedIds = self::collectReservedIds($xpath, $wrapper, $headings);
            $flatItems = [];

            foreach ($headings as $heading) {
                /** @var DOMElement $element */
                $element = $heading['element'];
                $existingId = trim($element->getAttribute('id'));
                $baseId = self::isValidId($existingId)
                    ? $existingId
                    : self::slugify((string) $heading['title']);
                $id = self::makeUniqueId($baseId, $usedIds);
                $element->setAttribute('id', $id);

                $flatItems[] = [
                    'id' => $id,
                    'title' => (string) $heading['title'],
                    'level' => (int) $heading['level'],
                    'children' => [],
                ];
            }

            return [
                'content' => self::innerHtml($document, $wrapper),
                'items' => self::buildHierarchy($flatItems),
            ];
        } catch (Throwable $exception) {
            return $fallback;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseInternalErrors);
        }
    }

    /**
     * @param array<int, array{element: DOMElement, level: int, title: string}> $headings
     * @return array<string, bool>
     */
    private static function collectReservedIds(DOMXPath $xpath, DOMElement $wrapper, array $headings): array
    {
        $headingObjectIds = [];
        foreach ($headings as $heading) {
            $headingObjectIds[spl_object_id($heading['element'])] = true;
        }

        $reserved = [];
        $nodesWithIds = $xpath->query('.//*[@id]', $wrapper);
        if (!$nodesWithIds instanceof DOMNodeList) {
            return $reserved;
        }

        foreach ($nodesWithIds as $node) {
            if (!$node instanceof DOMElement || isset($headingObjectIds[spl_object_id($node)])) {
                continue;
            }

            $id = trim($node->getAttribute('id'));
            if ($id !== '') {
                $reserved[$id] = true;
            }
        }

        return $reserved;
    }

    private static function slugify(string $title): string
    {
        $slug = class_exists('Tools') ? (string) Tools::str2url($title) : '';
        if ($slug === '') {
            $normalized = function_exists('iconv')
                ? (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title)
                : $title;
            $slug = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $normalized), '-'));
        }

        return $slug !== '' ? 'section-' . $slug : 'section';
    }

    private static function isValidId(string $id): bool
    {
        return $id !== '' && (bool) preg_match('/^[A-Za-z][A-Za-z0-9_:.-]*$/', $id);
    }

    /** @param array<string, bool> $usedIds */
    private static function makeUniqueId(string $baseId, array &$usedIds): string
    {
        $candidate = $baseId;
        $suffix = 2;

        while (isset($usedIds[$candidate])) {
            $candidate = $baseId . '-' . $suffix;
            $suffix++;
        }

        $usedIds[$candidate] = true;

        return $candidate;
    }

    /**
     * @param array<int, array{id: string, title: string, level: int, children: array}> $flatItems
     * @return array<int, array{id: string, title: string, level: int, children: array}>
     */
    private static function buildHierarchy(array $flatItems): array
    {
        $tree = [];
        $stack = [];

        foreach ($flatItems as $item) {
            while ($stack !== [] && $stack[array_key_last($stack)]['level'] >= $item['level']) {
                array_pop($stack);
            }

            if ($stack === []) {
                $tree[] = $item;
                $path = [array_key_last($tree)];
            } else {
                $parentPath = $stack[array_key_last($stack)]['path'];
                $children =& $tree;

                foreach ($parentPath as $index) {
                    $children =& $children[$index]['children'];
                }

                $children[] = $item;
                $path = [...$parentPath, array_key_last($children)];
                unset($children);
            }

            $stack[] = [
                'level' => $item['level'],
                'path' => $path,
            ];
        }

        return $tree;
    }

    private static function innerHtml(DOMDocument $document, DOMElement $wrapper): string
    {
        $html = '';
        foreach ($wrapper->childNodes as $childNode) {
            $html .= (string) $document->saveHTML($childNode);
        }

        return $html;
    }
}
