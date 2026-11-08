<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/CciBlogEditorialBlocks.php';
require_once __DIR__ . '/CciBlogComparisonFields.php';

/** Pure, server-owned publication checks. See docs/PUBLICATION_AUDIT.md. */
final class CciBlogPublicationAudit
{
    public const VERSION = '1';
    public const POLICY_REVISION = '2';
    private const STATUSES = ['good', 'warning', 'bad', 'info', 'not_applicable', 'unavailable'];
    private array $rules;
    private array $configurationWarnings = [];

    public function __construct(array $contributions = [])
    {
        $this->rules = self::defaultRules();
        usort($contributions, static fn (array $a, array $b): int => [(int) ($a['priority'] ?? 0), (string) ($a['module'] ?? '')] <=> [(int) ($b['priority'] ?? 0), (string) ($b['module'] ?? '')]);
        foreach ($contributions as $contribution) {
            $module = (string) ($contribution['module'] ?? '');
            if (count((array) ($contribution['rules'] ?? [])) > 64) {
                $this->configurationWarnings[] = 'Too many contributed audit rules: ' . $module;
            }
            foreach (array_slice((array) ($contribution['rules'] ?? []), 0, 64) as $override) {
                $id = is_array($override) && is_string($override['id'] ?? null) ? $override['id'] : '';
                if (!preg_match('/^[a-z][a-z0-9_.-]{1,95}$/D', $id)
                    || (!isset($this->rules[$id]) && (!str_contains($id, '.') || !is_callable($override['evaluate'] ?? null)))
                    || (array_key_exists('evaluate', $override) && !is_callable($override['evaluate']))) {
                    $this->configurationWarnings[] = 'An audit rule contribution is invalid: ' . $module;
                    continue;
                }
                foreach (['label', 'group', 'revision'] as $field) {
                    if (array_key_exists($field, $override) && (!is_string($override[$field]) || preg_match('//u', $override[$field]) !== 1)) {
                        $this->configurationWarnings[] = 'Invalid audit rule metadata: ' . $module . '.' . $id;
                        continue 2;
                    }
                }
                if ((array_key_exists('weight', $override) && !is_int($override['weight'])) || (array_key_exists('enabled', $override) && !is_bool($override['enabled']))) {
                    $this->configurationWarnings[] = 'Invalid audit rule weight or enabled flag: ' . $module . '.' . $id;
                    continue;
                }
                if (count($this->rules) >= 96 && !isset($this->rules[$id])) {
                    $this->configurationWarnings[] = 'Too many audit rules: ' . $module;
                    continue;
                }
                $this->rules[$id] = array_replace($this->rules[$id] ?? ['id' => $id, 'label' => $id, 'group' => 'custom', 'weight' => 0, 'enabled' => true, 'revision' => '1'], array_intersect_key($override, array_flip(['label', 'group', 'weight', 'enabled', 'revision', 'evaluate'])), ['source' => $module]);
                $this->rules[$id]['weight'] = max(0, min(100, (int) $this->rules[$id]['weight']));
            }
        }
    }

    public static function defaultRules(): array
    {
        $definitions = [
            ['title_present', 'Title', 'metadata', 20],
            ['description_present', 'Search description', 'metadata', 10],
            ['body_present', 'Article content', 'content', 20],
            ['single_h1', 'Main heading', 'content', 10],
            ['heading_hierarchy', 'Heading hierarchy', 'content', 10],
            ['internal_targets', 'Internal link targets', 'links', 15],
            ['anchor_targets', 'Links to article sections', 'links', 10],
            ['product_targets', 'Referenced products', 'links', 15],
            ['duplicate_title', 'Duplicate title', 'metadata', 10],
            ['duplicate_description', 'Duplicate search description', 'metadata', 5],
            ['image_descriptions', 'Image descriptions', 'media', 0],
            ['descriptive_links', 'Link text', 'links', 0],
            ['title_length', 'Title display length', 'metadata', 0],
            ['description_length', 'Description display length', 'metadata', 0],
            ['focus_phrase', 'Optional focus phrase', 'keyword', 0],
            ['content_length', 'Content length', 'content', 0],
            ['dynamic_content', 'Dynamic content', 'content', 0],
            ['comparison_configuration', 'Product comparison setup', 'content', 0],
            ['analysis_limits', 'Analysis completeness', 'content', 0],
        ];
        $rules = [];
        foreach ($definitions as [$id, $label, $group, $weight]) {
            $rules[$id] = compact('id', 'label', 'group', 'weight') + ['enabled' => true, 'source' => 'core', 'revision' => self::POLICY_REVISION];
        }

        return $rules;
    }

    public function fingerprint(): string
    {
        $metadata = [];
        foreach ($this->rules as $id => $rule) {
            $metadata[$id] = array_intersect_key($rule, array_flip(['weight', 'enabled', 'revision', 'source']));
        }

        return hash('sha256', self::VERSION . ':' . self::POLICY_REVISION . ':' . json_encode(['rules' => $metadata, 'configurationWarnings' => $this->configurationWarnings], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function evaluate(array $post, array $context = [], ?array $facts = null): array
    {
        $facts = $facts ?? self::collectFacts($post, $context);
        $checks = [];
        $earned = 0.0;
        $possible = 0;
        $complete = $this->configurationWarnings === [] && empty($facts['truncated']);
        foreach ($this->rules as $id => $rule) {
            if (empty($rule['enabled'])) {
                continue;
            }

            try {
                $result = isset($rule['evaluate']) ? ($rule['evaluate'])($post, $facts, $context) : $this->evaluateCoreRule($id, $post, $facts, $context);
                if (!is_array($result) || !in_array($result['status'] ?? '', self::STATUSES, true)) {
                    throw new UnexpectedValueException('Invalid audit rule result.');
                }
                if (!is_string($result['message'] ?? null) || (isset($result['help']) && !is_string($result['help'])) || (isset($result['details']) && !is_array($result['details']))) {
                    throw new UnexpectedValueException('Invalid audit rule message or details.');
                }
                if (isset($result['details'])) {
                    foreach (array_slice($result['details'], 0, 10) as $detail) {
                        if (!is_array($detail) || array_filter($detail, static fn ($value): bool => !is_scalar($value) && $value !== null)) {
                            throw new UnexpectedValueException('Audit details must contain scalar values.');
                        }
                    }
                }
                // Validate only the fields returned to the client. NaN/INF and invalid
                // UTF-8 must fail this rule, never the complete JSON response or save.
                json_encode([
                    'message' => $result['message'],
                    'help' => $result['help'] ?? '',
                    'details' => array_slice($result['details'] ?? [], 0, 10),
                ], JSON_THROW_ON_ERROR);
            } catch (Throwable $error) {
                $result = ['status' => 'unavailable', 'message' => 'This rule could not be evaluated. Check the rule configuration.'];
            }
            $status = $result['status'];
            $scored = (int) $rule['weight'] > 0 && in_array($status, ['good', 'warning', 'bad'], true);
            if ($status === 'unavailable' && (int) $rule['weight'] > 0) {
                $complete = false;
            }
            if ($scored) {
                $possible += (int) $rule['weight'];
                $earned += (int) $rule['weight'] * ($status === 'good' ? 1 : ($status === 'warning' ? 0.5 : 0));
            }
            $checks[] = [
                'id' => $id, 'label' => (string) $rule['label'], 'group' => (string) $rule['group'],
                'source' => (string) $rule['source'], 'revision' => (string) $rule['revision'],
                'weight' => (int) $rule['weight'], 'scored' => $scored, 'status' => $status,
                'message' => (string) ($result['message'] ?? ''), 'help' => (string) ($result['help'] ?? ''),
                'details' => array_slice(is_array($result['details'] ?? null) ? $result['details'] : [], 0, 10),
            ];
        }
        $score = $complete && $possible > 0 ? max(0, min(100, (int) round(100 * $earned / $possible))) : null;
        $keyword = self::plainText((string) ($post['focus_keyword'] ?? ''));
        $occurrences = $keyword === '' ? 0 : substr_count(self::fold($facts['text']), self::fold($keyword));

        return [
            'version' => self::VERSION, 'rulesFingerprint' => $this->fingerprint(), 'complete' => $complete,
            'score' => $score, 'overallStatus' => $score === null ? 'info' : ($score >= 80 ? 'good' : ($score >= 55 ? 'warning' : 'bad')),
            'scoreKind' => 'publication_checks', 'earnedWeight' => $earned, 'applicableWeight' => $possible,
            'checks' => $checks, 'configurationWarnings' => $this->configurationWarnings,
            'customized' => count(array_filter($this->rules, static fn (array $rule): bool => $rule['source'] !== 'core')) > 0,
            'metrics' => [
                'wordCount' => $facts['wordCount'], 'titleLength' => self::length($facts['title']),
                'metaLength' => self::length($facts['description']), 'titlePixels' => null,
                'internalLinks' => (int) ($context['internalLinks'] ?? 0), 'externalLinks' => (int) ($context['externalLinks'] ?? 0),
                'images' => count($facts['images']), 'headings' => count($facts['headings']),
                'keywordOccurrences' => $occurrences,
                'keywordDensity' => $facts['wordCount'] ? round(100 * $occurrences / $facts['wordCount'], 2) : 0,
                'contentType' => (string) ($post['seo_content_type'] ?? 'article'), 'languageIso' => (string) ($context['languageIso'] ?? ''),
            ],
        ];
    }

    private function evaluateCoreRule(string $id, array $post, array $facts, array $context): array
    {
        $result = static fn (string $status, string $message, array $details = []): array => compact('status', 'message', 'details');
        switch ($id) {
            case 'title_present':
                return $result($facts['title'] !== '' ? 'good' : 'bad', $facts['title'] !== '' ? 'A title is available, including the article-title fallback.' : 'Write a title that describes this page.');
            case 'description_present':
                return $result($facts['description'] !== '' ? 'good' : 'warning', $facts['description'] !== '' ? 'A search description is available, including the introduction fallback.' : 'Add a useful description or introduction. Search engines can also choose text from the page.');
            case 'body_present':
                return $result($facts['text'] !== '' || $facts['productIds'] || !empty($facts['mediaCount']) ? 'good' : ($facts['dynamicBlocks'] > 0 ? 'unavailable' : 'bad'), $facts['text'] !== '' || $facts['productIds'] || !empty($facts['mediaCount']) ? 'The article contains text, media or product content.' : ($facts['dynamicBlocks'] > 0 ? 'Review the dynamic output on the storefront.' : 'Add the content readers will see.'));
            case 'single_h1':
                $count = ((string) ($post['title'] ?? '') !== '' ? 1 : 0) + count(array_filter($facts['headings'], static fn (array $heading): bool => $heading['level'] === 1));

                return $result($count === 1 ? 'good' : 'warning', $count === 1 ? 'The page has one main heading.' : 'Review the main heading structure. This is an editorial and accessibility check.');
            case 'heading_hierarchy':
                if (!$facts['headings']) {
                    return $result('not_applicable', 'There are no section headings to check. A short article may not need them.');
                }
                $previous = 1;
                foreach ($facts['headings'] as $heading) {
                    if ($heading['level'] > $previous + 1) {
                        return $result('warning', 'Review skipped heading levels so readers can follow the structure.');
                    }
                    $previous = $heading['level'];
                }

                return $result('good', 'Section headings follow a consistent hierarchy.');
            case 'internal_targets':
            case 'anchor_targets':
            case 'product_targets':
                $key = ['internal_targets' => 'linkResults', 'anchor_targets' => 'anchorResults', 'product_targets' => 'productResults'][$id];
                $items = $context[$key] ?? [];
                if (!array_key_exists($key, $context) && ($id === 'product_targets' ? $facts['productIds'] : $facts['links'])) {
                    return $result('unavailable', 'Target availability was not checked.');
                }
                if (!$items) {
                    return $result('not_applicable', 'There are no applicable targets to check.');
                }
                $bad = array_values(array_filter($items, static fn (array $item): bool => ($item['status'] ?? '') === 'bad'));
                $warnings = array_values(array_filter($items, static fn (array $item): bool => ($item['status'] ?? '') === 'warning'));
                $unknown = array_filter($items, static fn (array $item): bool => ($item['status'] ?? '') === 'unavailable');

                return $result($unknown ? 'unavailable' : ($bad ? 'bad' : ($warnings ? 'warning' : 'good')), $unknown ? 'Some shop URLs could not be verified locally. No external requests were made.' : ($bad ? 'Some referenced targets are missing or unavailable in this shop and language.' : ($warnings ? 'Some links use an old address that redirects to a published page.' : 'The locally checked targets are available.')), array_merge($bad, $warnings, array_values($unknown)));
            case 'duplicate_title':
            case 'duplicate_description':
                $field = $id === 'duplicate_title' ? 'title' : 'description';
                if ($facts[$field] === '') {
                    return $result('not_applicable', 'There is no value to compare with other published pages.');
                }
                if (!array_key_exists('duplicate' . ucfirst($field), $context)) {
                    return $result('unavailable', 'Published pages were not available for this comparison.');
                }
                $duplicates = $context['duplicate' . ucfirst($field)];

                return $result($duplicates ? 'warning' : 'good', $duplicates ? 'Another published page in this shop and language uses the same text. Review whether it describes each page clearly.' : 'No matching text was found among other published pages in this shop and language.', $duplicates);
            case 'image_descriptions':
                $missing = array_values(array_filter($facts['images'], static fn (array $image): bool => !$image['decorative'] && $image['alt'] === ''));

                return $result(!$facts['images'] ? 'not_applicable' : ($missing ? 'warning' : 'good'), !$facts['images'] ? 'This article has no editorial images.' : ($missing ? 'Describe informative images. Empty alt text is appropriate for decorative images; keywords are not required.' : 'The editorial images have descriptions or are marked decorative.'), $missing);
            case 'descriptive_links':
                $generic = array_values(array_filter($facts['links'], static fn (array $link): bool => in_array(self::fold($link['text']), ['', 'click here', 'here', 'read more', 'kliknij tutaj', 'tutaj', 'czytaj więcej'], true)));

                return $result(!$facts['links'] ? 'not_applicable' : ($generic ? 'warning' : 'good'), $generic ? 'Check generic link labels. Readers should understand where a link leads from its context.' : 'No generic text links were detected.', $generic);
            case 'title_length':
                return $result(self::length($facts['title']) > 70 ? 'warning' : 'info', 'Title length is guidance only. Search engines can truncate or replace the displayed title.');
            case 'description_length':
                return $result(self::length($facts['description']) > 180 ? 'warning' : 'info', 'Description length is guidance only. The displayed snippet depends on the query and device.');
            case 'focus_phrase':
                return $result(trim((string) ($post['focus_keyword'] ?? '')) === '' ? 'not_applicable' : 'info', 'The focus phrase is an optional editorial aid. It does not affect the default score.');
            case 'content_length':
                return $result('info', 'Word count describes the draft. There is no minimum word count or automatic measure of usefulness.');
            case 'dynamic_content':
                return $result($facts['dynamicBlocks'] > 0 ? 'info' : 'not_applicable', 'Hook and extension output is not executed by this audit. Review it on the storefront.');
            case 'analysis_limits':
                return $result(!empty($facts['truncated']) ? 'unavailable' : 'good', !empty($facts['truncated']) ? 'This draft exceeds the analysis limits. The score is unavailable until the whole draft can be checked.' : 'The draft is within the analysis limits.');
            case 'comparison_configuration':
                if (empty($facts['comparisons'])) {
                    return $result('not_applicable', 'There is no product comparison to configure.');
                }
                foreach ($facts['comparisons'] as $comparison) {
                    if ($comparison['products'] < 2 || $comparison['products'] > 4 || $comparison['fields'] < 1 || $comparison['fields'] > 12) {
                        return $result('warning', 'Choose 2 to 4 products and 1 to 12 comparison rows. Extra products or rows are not displayed.');
                    }
                }

                if (!array_key_exists('comparisonResults', $context)) {
                    return $result('unavailable', 'Catalogue comparison fields were not checked.');
                }
                foreach ($context['comparisonResults'] as $comparison) {
                    if ($comparison['availableProducts'] < 2) {
                        return $result('warning', 'Fewer than two selected products are available. The storefront cannot show a full comparison.');
                    }
                    if ($comparison['unavailableFields']) {
                        return $result('warning', 'Some selected comparison fields were deleted, disabled or are not assigned to these products.', array_map(static fn (string $field): array => ['title' => $field], $comparison['unavailableFields']));
                    }
                    if ($comparison['rows'] === 0) {
                        return $result('warning', 'No comparison rows are available. The storefront shows product cards without a comparison table.');
                    }
                }

                return $result('good', 'Selected comparison fields are available. Missing product values are marked as not provided.');
        }

        return $result('unavailable', 'This rule could not be evaluated. Check the rule configuration.');
    }

    public static function collectFacts(array $post, array $context = []): array
    {
        $facts = ['title' => trim((string) (($post['meta_title'] ?? '') ?: ($post['title'] ?? ''))), 'description' => self::plainText((string) (($post['meta_description'] ?? '') ?: ($post['intro'] ?? ''))), 'text' => '', 'wordCount' => 0, 'headings' => [], 'images' => [], 'links' => [], 'productIds' => [], 'ids' => [], 'dynamicBlocks' => 0, 'truncated' => false];
        $fragments = [];
        $visited = 0;
        $walk = function (array $blocks, int $depth = 0) use (&$walk, &$facts, &$fragments, &$visited): void {
            if ($depth > 12) {
                $facts['truncated'] = true;

                return;
            }
            foreach ($blocks as $block) {
                if (!is_array($block)) {
                    continue;
                }
                if (++$visited > 1500) {
                    $facts['truncated'] = true;
                    break;
                }
                $type = (string) ($block['type'] ?? '');
                $editorialHtml = CciBlogEditorialBlocks::render($block);
                if ($editorialHtml !== null) {
                    if ($type === 'image') {
                        $editorialHtml = str_replace('<img ', '<img data-cci-blog-audit-decorative="' . (!empty($block['decorative']) ? '1' : '0') . '" ', $editorialHtml);
                    }
                    $fragments[] = $editorialHtml;
                } elseif ($type === 'columns') {
                    foreach (array_slice((array) ($block['columns'] ?? []), 0, 12) as $column) {
                        if (is_array($column)) {
                            if (is_array($column['blocks'] ?? null)) {
                                $walk($column['blocks'], $depth + 1);
                            } else {
                                $fragments[] = (string) (($column['html'] ?? '') ?: htmlspecialchars((string) ($column['content'] ?? ''), ENT_QUOTES, 'UTF-8'));
                            }
                        }
                    }
                } elseif (in_array($type, ['product', 'product_carousel', 'product_comparison'], true)) {
                    $rawIds = $block['ids'] ?? [];
                    $ids = $type === 'product' ? [$block['id_product'] ?? 0] : self::productIds($rawIds);
                    $rawItems = is_array($rawIds) ? $rawIds : preg_split('/[\s,]+/', (string) $rawIds);
                    if (count($rawItems ?: []) > 100) {
                        $facts['truncated'] = true;
                    }
                    $facts['productIds'] = array_merge($facts['productIds'], $ids);
                    if ($type === 'product_comparison') {
                        $fields = $block['fields'] ?? ['price', 'reference'];
                        $selection = CciBlogComparisonFields::selection($block);
                        $facts['comparisons'][] = ['products' => $selection['productCount'], 'fields' => $selection['fieldCount'], 'ids' => $selection['ids'], 'selectedFields' => $selection['fields']];
                    }
                } elseif (in_array($type, ['hook', 'module_block'], true)) {
                    ++$facts['dynamicBlocks'];
                }
            }
        };
        $blocks = is_array($post['blocks'] ?? null) ? $post['blocks'] : [];
        $walk($blocks);
        $html = $blocks ? implode("\n", $fragments) : (string) ($post['content'] ?? '');
        if (!empty($context['tocEnabled']) && class_exists('CciBlogTableOfContents')) {
            $toc = CciBlogTableOfContents::build($html, (int) ($context['tocMinHeadings'] ?? 2));
            $html = $toc['content'];
            if ($toc['items']) {
                $facts['ids'][] = 'cci-blog-reading-navigation';
            }
        }
        if (!empty($post['allow_comments']) && !empty($context['commentsEnabled'])) {
            $facts['ids'][] = 'cci-blog-comments';
            $facts['ids'][] = 'cci-blog-comments-title';
        }
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $html = preg_replace('#</(?:p|li|h[1-6]|td|th|div|blockquote)>|<br\s*/?>#i', '$0 ', $html) ?? $html;
        $document->loadHTML('<?xml encoding="utf-8" ?><div>' . $html . '</div>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);
        foreach ($xpath->query('//script|//style|//template') ?: [] as $node) {
            $node->parentNode->removeChild($node);
        }
        $facts['text'] = trim(preg_replace('/\s+/u', ' ', $document->textContent) ?? '');
        $facts['wordCount'] = self::wordCount($facts['text']);
        $facts['mediaCount'] = $xpath->query('//img[@src] | //video[@src]')->length;
        foreach ($xpath->query('//h1|//h2|//h3|//h4|//h5|//h6') ?: [] as $node) {
            $facts['headings'][] = ['level' => (int) substr($node->nodeName, 1), 'text' => trim($node->textContent) ?: trim(implode(' ', array_map(static fn (DOMElement $image): string => $image->getAttribute('alt'), iterator_to_array($node->getElementsByTagName('img')))))];
        }
        foreach ($xpath->query('//*[@id]') ?: [] as $node) {
            $facts['ids'][] = $node->getAttribute('id');
        }
        foreach ($xpath->query('//a[@href]') ?: [] as $node) {
            $facts['links'][] = ['href' => trim($node->getAttribute('href')), 'text' => trim($node->textContent) ?: trim(implode(' ', array_map(static fn (DOMElement $image): string => $image->getAttribute('alt'), iterator_to_array($node->getElementsByTagName('img')))))];
        }
        foreach ($xpath->query('//img') ?: [] as $node) {
            $facts['images'][] = ['src' => $node->getAttribute('src'), 'alt' => trim($node->getAttribute('alt')), 'decorative' => $node->hasAttribute('data-cci-blog-audit-decorative') ? $node->getAttribute('data-cci-blog-audit-decorative') === '1' : ($node->hasAttribute('alt') && $node->getAttribute('alt') === '')];
        }
        $facts['truncated'] = $facts['truncated'] || count($facts['links']) > 100 || count(array_unique($facts['productIds'])) > 100;
        $facts['productIds'] = self::productIds($facts['productIds']);

        return $facts;
    }

    public static function productIds($value): array
    {
        $items = is_array($value) ? $value : preg_split('/[\s,]+/', (string) $value);

        return array_slice(array_values(array_unique(array_filter(array_map('intval', $items ?: []), static fn (int $id): bool => $id > 0))), 0, 100);
    }

    public static function wordCount(string $text): int
    {
        return preg_match_all('/[\p{L}\p{N}]+(?:[\x{2019}\x{27}][\p{L}\p{N}]+)*/u', $text) ?: 0;
    }

    public static function plainText(string $value): string
    {
        $value = preg_replace('#</(?:p|li|h[1-6]|td|th|div|blockquote)>|<br\s*/?>#i', '$0 ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    public static function fold(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower(self::plainText($value), 'UTF-8') : strtolower(self::plainText($value));
    }

    private static function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}
