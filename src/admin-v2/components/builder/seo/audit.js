import { pluginData } from '../../../api/api';
import { __ } from '../../../i18n/i18n';
import { normalizeAdminSlug, normalizePlainText, htmlToPlainText } from '../model/text.js';
import { seoStopWordsByLanguage, seoContentTypeConfig } from './config.js';

export function calculateSeoAudit(post = {}) {
  const keyword = String(post.focus_keyword || '').trim();
  const languageIso = getSeoLanguageIso();
  const stopWords = getSeoStopWords(languageIso);
  const contentType = normalizeSeoContentType(post.seo_content_type);
  const contentTypeConfig = seoContentTypeConfig[contentType] || seoContentTypeConfig.article;
  const isCategoryContent = contentType === 'category';
  const keywordSearch = normalizeSearchText(keyword);
  const keywordSlug = normalizeAdminSlug(keyword);
  const titleTag = String(post.meta_title || post.title || '').trim();
  const metaDescription = String(post.meta_description || '').trim();
  const slug = normalizeAdminSlug(post.slug || post.title || '');
  const content = extractSeoContent(post);
  const bodyText = content.text;
  const bodyWords = wordsFromText(bodyText);
  const firstParagraph = wordsFromText(content.paragraphs[0] || '')
    .slice(0, 100)
    .join(' ');
  const h1Count =
    (post.title ? 1 : 0) + content.headings.filter((heading) => Number(heading.level) === 1).length;
  const h2Headings = content.headings.filter((heading) => Number(heading.level) === 2);
  const keywordOccurrences = countKeywordOccurrences(bodyText, keyword, stopWords);
  const keywordDensity = bodyWords.length
    ? Number(((keywordOccurrences / bodyWords.length) * 100).toFixed(2))
    : 0;
  const sentenceStats = calculateSentenceStats(bodyText);
  const titlePixels = Math.round(measureTextWidth(titleTag));
  const titleLength = titleTag.length;
  const metaLength = metaDescription.length;
  const internalLinks = content.links.filter((link) => isInternalLink(link.href)).length;
  const externalLinks = content.links.filter((link) => isExternalLink(link.href)).length;
  const imageAltMissing = content.images.filter((image) => !String(image.alt || '').trim()).length;
  const randomImageNames = content.images.filter((image) =>
    imageFilenameLooksRandom(image.src),
  ).length;
  const slugWords = slug.split('-').filter(Boolean);
  const slugStopWords = slugWords.filter((word) => stopWords.has(word)).length;
  const checks = [];
  const add = (check) => checks.push(check);
  const keywordNotSetMessage = __('Set a focus keyword to run this check.', 'cci-blog');
  const titleWidthMessage = `${__('Estimated search width:', 'cci-blog')} ${titlePixels}px.`;

  add({
    id: 'focus_keyword_set',
    group: 'keyword',
    label: __('Focus keyword', 'cci-blog'),
    status: keyword ? 'good' : 'bad',
    message: keyword
      ? `${__('Focus keyword:', 'cci-blog')} ${keyword}`
      : isCategoryContent
        ? __('Add the main search phrase for this category.', 'cci-blog')
        : __('Add the main search phrase for this article.', 'cci-blog'),
    weight: 8,
  });

  add({
    id: 'keyword_in_title',
    group: 'keyword',
    label: __('Keyword in title tag', 'cci-blog'),
    status: keyword ? (containsKeyword(titleTag, keyword, stopWords) ? 'good' : 'bad') : 'bad',
    message: keyword
      ? containsKeyword(titleTag, keyword, stopWords)
        ? __('The focus keyword appears in the title tag.', 'cci-blog')
        : __('Add the focus keyword to the title tag.', 'cci-blog')
      : keywordNotSetMessage,
    weight: 12,
  });

  add({
    id: 'keyword_in_meta',
    group: 'keyword',
    label: __('Keyword in meta description', 'cci-blog'),
    status: keyword
      ? containsKeyword(metaDescription, keyword, stopWords)
        ? 'good'
        : 'warning'
      : 'bad',
    message: keyword
      ? containsKeyword(metaDescription, keyword, stopWords)
        ? __('The focus keyword appears in the meta description.', 'cci-blog')
        : __('Use the focus keyword naturally in the meta description.', 'cci-blog')
      : keywordNotSetMessage,
    weight: 8,
  });

  add({
    id: 'keyword_in_slug',
    group: 'keyword',
    label: __('Keyword in URL', 'cci-blog'),
    status: keyword ? (slugContainsKeyword(slug, keyword, stopWords) ? 'good' : 'warning') : 'bad',
    message: keyword
      ? slugContainsKeyword(slug, keyword, stopWords)
        ? __('The URL contains the focus keyword.', 'cci-blog')
        : __('Add the focus keyword to the slug when it reads naturally.', 'cci-blog')
      : keywordNotSetMessage,
    weight: 8,
  });

  add({
    id: 'keyword_in_first_paragraph',
    group: 'keyword',
    label: __('Keyword in first paragraph', 'cci-blog'),
    status: keyword
      ? containsKeyword(firstParagraph, keyword, stopWords)
        ? 'good'
        : 'warning'
      : 'bad',
    message: keyword
      ? containsKeyword(firstParagraph, keyword, stopWords)
        ? __('The focus keyword appears in the first 100 words.', 'cci-blog')
        : __('Use the focus keyword in the opening paragraph.', 'cci-blog')
      : keywordNotSetMessage,
    weight: 8,
  });

  add({
    id: 'keyword_in_h2',
    group: 'keyword',
    label: __('Keyword in H2', 'cci-blog'),
    status: keyword
      ? h2Headings.some((heading) => containsKeyword(heading.text, keyword, stopWords))
        ? 'good'
        : 'warning'
      : 'bad',
    message: keyword
      ? h2Headings.some((heading) => containsKeyword(heading.text, keyword, stopWords))
        ? __('At least one H2 contains the focus keyword or a close variant.', 'cci-blog')
        : __('Add the focus keyword or a close variant to one H2.', 'cci-blog')
      : keywordNotSetMessage,
    weight: 8,
  });

  add({
    id: 'keyword_in_image_alt',
    group: 'keyword',
    label: __('Keyword in image alt', 'cci-blog'),
    status: keyword
      ? content.images.some((image) => containsKeyword(image.alt, keyword, stopWords))
        ? 'good'
        : content.images.length
          ? 'warning'
          : 'info'
      : 'bad',
    message: keyword
      ? content.images.some((image) => containsKeyword(image.alt, keyword, stopWords))
        ? __('At least one image alt contains the focus keyword.', 'cci-blog')
        : content.images.length
          ? __('Use the focus keyword in one image alt when it describes the image.', 'cci-blog')
          : __('No images are available for this check.', 'cci-blog')
      : keywordNotSetMessage,
    weight: 5,
  });

  add({
    id: 'title_length',
    group: 'title',
    label: __('Title tag length', 'cci-blog'),
    status:
      titleLength >= 45 && titleLength <= 65 && titlePixels <= 600
        ? 'good'
        : titleLength >= 30 && titlePixels <= 680
          ? 'warning'
          : 'bad',
    message: `${__('Title tag length:', 'cci-blog')} ${titleLength} ${__('characters', 'cci-blog')}. ${titleWidthMessage}`,
    help: __(
      'Length is counted in characters. Pixel width is an estimate of how much space the title may take in search results.',
      'cci-blog',
    ),
    weight: 12,
  });

  add({
    id: 'keyword_near_title_start',
    group: 'title',
    label: __('Keyword near title start', 'cci-blog'),
    status: keyword
      ? keywordStartsEarly(titleTag, keyword, stopWords)
        ? 'good'
        : containsKeyword(titleTag, keyword, stopWords)
          ? 'warning'
          : 'bad'
      : 'bad',
    message: keyword
      ? keywordStartsEarly(titleTag, keyword, stopWords)
        ? __('The focus keyword appears near the start of the title tag.', 'cci-blog')
        : __('Move the focus keyword closer to the start of the title tag.', 'cci-blog')
      : keywordNotSetMessage,
    weight: 6,
  });

  add({
    id: 'meta_length',
    group: 'meta',
    label: __('Meta description length', 'cci-blog'),
    status:
      metaLength >= 120 && metaLength <= 158
        ? 'good'
        : metaLength >= 90 && metaLength <= 175
          ? 'warning'
          : 'bad',
    message: `${__('Meta description length:', 'cci-blog')} ${metaLength}/120-158 ${__('characters', 'cci-blog')}.`,
    weight: 10,
  });

  add({
    id: 'meta_sentence',
    group: 'meta',
    label: __('Meta description sentence', 'cci-blog'),
    status:
      metaDescription && /[.!?]$/.test(metaDescription)
        ? 'good'
        : metaDescription
          ? 'warning'
          : 'bad',
    message:
      metaDescription && /[.!?]$/.test(metaDescription)
        ? __('The meta description ends with a complete sentence.', 'cci-blog')
        : __('End the meta description with a complete sentence.', 'cci-blog'),
    weight: 4,
  });

  add({
    id: 'slug_short',
    group: 'slug',
    label: __('Short slug', 'cci-blog'),
    status: slug && slug.length <= 75 && slugWords.length <= 8 ? 'good' : slug ? 'warning' : 'bad',
    message: slug
      ? `${__('Slug:', 'cci-blog')} ${slug}`
      : __('Create a readable slug for this post.', 'cci-blog'),
    weight: 6,
  });

  add({
    id: 'slug_stop_words',
    group: 'slug',
    label: __('Slug stop-words', 'cci-blog'),
    status: slugStopWords === 0 ? 'good' : slugStopWords <= 1 ? 'warning' : 'bad',
    message:
      slugStopWords === 0
        ? __('The slug does not contain obvious stop-words.', 'cci-blog')
        : `${__('Stop-words found in slug:', 'cci-blog')} ${slugStopWords}.`,
    weight: 4,
  });

  add({
    id: 'content_type',
    group: 'content',
    label: __('Content type', 'cci-blog'),
    status: 'good',
    message: `${contentTypeConfig.label}: ${contentTypeConfig.description}`,
    weight: 2,
  });

  add({
    id: 'content_length',
    group: 'content',
    label: __('Content length', 'cci-blog'),
    status: contentLengthStatus(bodyWords.length, contentTypeConfig),
    message: `${__('Word count:', 'cci-blog')} ${bodyWords.length}. ${__('Targets:', 'cci-blog')} ${contentTypeConfig.warning}/${contentTypeConfig.ok}/${contentTypeConfig.good}. ${contentLengthMessage(bodyWords.length, contentTypeConfig)}`,
    weight: 8,
  });

  add({
    id: 'single_h1',
    group: 'content',
    label: __('Single H1', 'cci-blog'),
    status: h1Count === 1 ? 'good' : 'bad',
    message:
      h1Count === 1
        ? isCategoryContent
          ? __('The category title is the only H1.', 'cci-blog')
          : __('The article title is the only H1.', 'cci-blog')
        : `${__('H1 count:', 'cci-blog')} ${h1Count}. ${__('Use exactly one H1.', 'cci-blog')}`,
    weight: 8,
  });

  add({
    id: 'heading_hierarchy',
    group: 'content',
    label: __('Heading hierarchy', 'cci-blog'),
    status: hasValidHeadingHierarchy(content.headings) ? 'good' : 'warning',
    message: hasValidHeadingHierarchy(content.headings)
      ? __('Heading levels do not skip hierarchy.', 'cci-blog')
      : __('Avoid jumps such as H2 to H4 or H3 before the first H2.', 'cci-blog'),
    weight: 8,
  });

  add({
    id: 'keyword_density',
    group: 'content',
    label: __('Keyword density', 'cci-blog'),
    status: keyword
      ? keywordDensity >= 0.3 && keywordDensity <= 2.5
        ? 'good'
        : keywordDensity > 0
          ? 'warning'
          : 'bad'
      : 'bad',
    message: keyword
      ? `${__('Keyword density:', 'cci-blog')} ${keywordDensity}%.`
      : keywordNotSetMessage,
    help: __(
      'Keyword density is not a ranking factor. Use it only to avoid unnatural repetition.',
      'cci-blog',
    ),
    weight: 4,
  });

  add({
    id: 'internal_link',
    group: 'links',
    label: __('Internal links', 'cci-blog'),
    status: internalLinks > 0 ? 'good' : 'warning',
    message:
      internalLinks > 0
        ? `${__('Internal links:', 'cci-blog')} ${internalLinks}.`
        : __('Add at least one internal link to another post, category or page.', 'cci-blog'),
    weight: 6,
  });

  add({
    id: 'external_link',
    group: 'links',
    label: __('External links', 'cci-blog'),
    status: externalLinks > 0 ? 'good' : 'info',
    message:
      externalLinks > 0
        ? `${__('External links:', 'cci-blog')} ${externalLinks}.`
        : __('No external authority link found.', 'cci-blog'),
    weight: 4,
  });

  add({
    id: 'image_alt',
    group: 'media',
    label: __('Image alt text', 'cci-blog'),
    status: content.images.length === 0 ? 'info' : imageAltMissing === 0 ? 'good' : 'bad',
    message:
      content.images.length === 0
        ? __('No images in content.', 'cci-blog')
        : imageAltMissing === 0
          ? __('Every image has alt text.', 'cci-blog')
          : `${__('Images missing alt:', 'cci-blog')} ${imageAltMissing}.`,
    weight: 6,
  });

  add({
    id: 'image_filename',
    group: 'media',
    label: __('Image filenames', 'cci-blog'),
    status: content.images.length === 0 ? 'info' : randomImageNames === 0 ? 'good' : 'warning',
    message:
      content.images.length === 0
        ? __('No image filenames to audit.', 'cci-blog')
        : randomImageNames === 0
          ? __('Image filenames look readable.', 'cci-blog')
          : `${__('Possibly random image filenames:', 'cci-blog')} ${randomImageNames}.`,
    weight: 4,
  });

  add({
    id: 'readability_sentences',
    group: 'readability',
    label: __('Sentence length', 'cci-blog'),
    status:
      sentenceStats.count === 0
        ? 'info'
        : sentenceStats.average <= 20 && sentenceStats.longPercent <= 25
          ? 'good'
          : sentenceStats.average <= 26
            ? 'warning'
            : 'bad',
    message:
      sentenceStats.count === 0
        ? __('No complete sentences detected yet.', 'cci-blog')
        : `${__('Average sentence:', 'cci-blog')} ${sentenceStats.average} ${__('words', 'cci-blog')}. ${sentenceStats.longPercent}% ${__('over 20 words', 'cci-blog')}.`,
    weight: 5,
  });

  const totalWeight = checks.reduce((sum, check) => sum + check.weight, 0);
  const earnedWeight = checks.reduce(
    (sum, check) => sum + check.weight * seoStatusFactor(check.status),
    0,
  );
  const score =
    totalWeight > 0
      ? Math.max(0, Math.min(100, Math.round((earnedWeight / totalWeight) * 100)))
      : 0;

  return {
    score,
    overallStatus: score >= 80 ? 'good' : score >= 55 ? 'warning' : 'bad',
    checks,
    metrics: {
      wordCount: bodyWords.length,
      keywordDensity,
      titleLength,
      titlePixels,
      metaLength,
      internalLinks,
      externalLinks,
      images: content.images.length,
      headings: content.headings.length,
      keywordOccurrences,
      keyword: keywordSearch,
      keywordSlug,
      contentType,
      contentTypeLabel: contentTypeConfig.label,
      languageIso,
    },
  };
}

export function extractSeoContent(post = {}) {
  const content = {
    textParts: [],
    paragraphs: [],
    headings: [],
    images: [],
    links: [],
    text: '',
  };

  const intro = normalizePlainText(post.intro || '');
  if (intro) {
    content.paragraphs.push(intro);
    content.textParts.push(intro);
  }

  collectSeoBlocks(Array.isArray(post.blocks) ? post.blocks : [], content);
  content.text = normalizePlainText(content.textParts.join('\n\n'));

  return content;
}

export function collectSeoBlocks(blocks, content) {
  (Array.isArray(blocks) ? blocks : []).forEach((block) => collectSeoBlock(block, content));
}

export function collectSeoBlock(block, content) {
  if (!block || typeof block !== 'object') {
    return;
  }

  if (block.type === 'heading') {
    const text = normalizePlainText(block.text || '');
    if (text) {
      content.headings.push({ level: Number(block.level || 2), text });
      content.textParts.push(text);
    }
    return;
  }

  if (block.type === 'paragraph') {
    const html = String(block.html || '').trim();
    if (html) {
      const details = extractHtmlSeoDetails(html);
      content.paragraphs.push(...details.paragraphs);
      content.headings.push(...details.headings);
      content.images.push(...details.images);
      content.links.push(...details.links);
      if (details.text) {
        content.textParts.push(details.text);
      }
      return;
    }

    const text = normalizePlainText(block.content || '');
    if (text) {
      content.paragraphs.push(text);
      content.textParts.push(text);
    }
    return;
  }

  if (block.type === 'link') {
    const label = normalizePlainText(block.label || block.href || '');
    content.links.push({ href: block.href || '', text: label });
    if (label) {
      content.textParts.push(label);
    }
    return;
  }

  if (block.type === 'image') {
    content.images.push({ src: block.src || '', alt: block.alt || '' });
    const caption = normalizePlainText(block.caption || block.alt || '');
    if (caption) {
      content.paragraphs.push(caption);
      content.textParts.push(caption);
    }
    if (block.link?.href) {
      content.links.push({ href: block.link.href, text: block.link.title || caption });
    }
    return;
  }

  if (block.type === 'columns') {
    (Array.isArray(block.columns) ? block.columns : []).forEach((column) => {
      if (Array.isArray(column?.blocks)) {
        collectSeoBlocks(column.blocks, content);
        return;
      }

      const html = String(column?.html || '').trim();
      if (html) {
        const details = extractHtmlSeoDetails(html);
        content.paragraphs.push(...details.paragraphs);
        content.headings.push(...details.headings);
        content.images.push(...details.images);
        content.links.push(...details.links);
        if (details.text) {
          content.textParts.push(details.text);
        }
        return;
      }

      const text = normalizePlainText(column?.content || '');
      if (text) {
        content.paragraphs.push(text);
        content.textParts.push(text);
      }
    });
    return;
  }

  const text = normalizePlainText(block.label || block.title || block.caption || '');
  if (text) {
    content.textParts.push(text);
  }
}

export function extractHtmlSeoDetails(html) {
  if (typeof document === 'undefined') {
    const text = htmlToPlainText(html);
    return { text, paragraphs: text ? [text] : [], headings: [], images: [], links: [] };
  }

  const template = document.createElement('template');
  template.innerHTML = String(html || '');
  const root = template.content;
  const paragraphs = Array.from(root.querySelectorAll('p, li, blockquote, figcaption'))
    .map((node) => normalizePlainText(node.textContent || ''))
    .filter(Boolean);
  const text = normalizePlainText(root.textContent || '');

  return {
    text,
    paragraphs: paragraphs.length ? paragraphs : text ? [text] : [],
    headings: Array.from(root.querySelectorAll('h1, h2, h3, h4, h5, h6'))
      .map((node) => ({
        level: Number(node.tagName.slice(1)),
        text: normalizePlainText(node.textContent || ''),
      }))
      .filter((heading) => heading.text),
    images: Array.from(root.querySelectorAll('img')).map((image) => ({
      src: image.getAttribute('src') || '',
      alt: image.getAttribute('alt') || '',
    })),
    links: Array.from(root.querySelectorAll('a[href]')).map((anchor) => ({
      href: anchor.getAttribute('href') || '',
      text: normalizePlainText(anchor.textContent || ''),
    })),
  };
}

export function seoStatusFactor(status) {
  if (status === 'good' || status === 'info') return 1;
  if (status === 'warning') return 0.5;
  return 0;
}

export function wordsFromText(text) {
  const normalized = normalizeSearchText(text);
  return normalized.match(/[a-z0-9]+/g) || [];
}

export function getSeoLanguageIso() {
  const fromPayload =
    pluginData.language?.isoCode || pluginData.diagnostics?.prestashop?.language?.isoCode || '';
  const fromDocument = typeof document !== 'undefined' ? document.documentElement.lang || '' : '';

  return (
    normalizeSearchText(fromPayload || fromDocument || 'en')
      .split(' ')[0]
      .slice(0, 2) || 'en'
  );
}

export function getSeoStopWords(languageIso = getSeoLanguageIso()) {
  return seoStopWordsByLanguage[languageIso] || seoStopWordsByLanguage.en;
}

export function normalizeSeoContentType(value) {
  const type = String(value || '').trim();

  return seoContentTypeConfig[type] ? type : 'article';
}

export function contentLengthStatus(wordCount, config) {
  if (wordCount >= config.ok) {
    return 'good';
  }

  if (wordCount >= config.warning) {
    return 'warning';
  }

  return 'bad';
}

export function contentLengthMessage(wordCount, config) {
  if (wordCount >= config.good) {
    return __('Good for long-form depth.', 'cci-blog');
  }

  if (wordCount >= config.ok) {
    return __('Meets the baseline for this content type.', 'cci-blog');
  }

  if (wordCount >= config.warning) {
    return __('Useful start, but consider expanding it.', 'cci-blog');
  }

  return __('Consider expanding the content.', 'cci-blog');
}

export function normalizeSearchText(value) {
  return String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

export function keywordTerms(keyword, stopWords = getSeoStopWords()) {
  return wordsFromText(keyword).filter((word) => word.length > 2 && !stopWords.has(word));
}

export function containsKeyword(value, keyword, stopWords = getSeoStopWords()) {
  const haystack = normalizeSearchText(value);
  const needle = normalizeSearchText(keyword);
  if (!haystack || !needle) {
    return false;
  }

  if (haystack.includes(needle)) {
    return true;
  }

  const terms = keywordTerms(keyword, stopWords);
  return terms.length > 1 && terms.every((term) => keywordTermMatches(haystack, term));
}

export function slugContainsKeyword(slug, keyword, stopWords = getSeoStopWords()) {
  const cleanSlug = normalizeAdminSlug(slug);
  const cleanKeyword = normalizeAdminSlug(keyword);
  if (!cleanSlug || !cleanKeyword) {
    return false;
  }

  if (cleanSlug.includes(cleanKeyword)) {
    return true;
  }

  const slugWords = cleanSlug.split('-').filter(Boolean);
  const terms = keywordTerms(keyword, stopWords);
  return (
    terms.length > 1 &&
    terms.every((term) => slugWords.some((word) => keywordTermMatches(word, term)))
  );
}

export function keywordStartsEarly(value, keyword, stopWords = getSeoStopWords()) {
  const haystack = normalizeSearchText(value);
  const needle = normalizeSearchText(keyword);
  if (!haystack || !needle) {
    return false;
  }

  const index = haystack.indexOf(needle);
  if (index >= 0) {
    return index <= Math.max(30, Math.floor(haystack.length * 0.35));
  }

  const firstTerm = keywordTerms(keyword, stopWords)[0] || '';
  const variantIndex = firstTerm ? keywordTermIndex(haystack, firstTerm) : -1;

  return variantIndex >= 0 && variantIndex <= Math.max(30, Math.floor(haystack.length * 0.35));
}

export function countKeywordOccurrences(value, keyword, stopWords = getSeoStopWords()) {
  const haystack = normalizeSearchText(value);
  const needle = normalizeSearchText(keyword);
  if (!haystack || !needle) {
    return 0;
  }

  const escaped = needle.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const exact = (haystack.match(new RegExp(`(^|\\s)${escaped}(?=\\s|$)`, 'g')) || []).length;
  if (exact > 0) {
    return exact;
  }

  const terms = keywordTerms(keyword, stopWords);
  if (terms.length < 2) {
    return 0;
  }

  return Math.min(...terms.map((term) => countKeywordTermOccurrences(haystack, term)));
}

export function keywordTermMatches(haystack, term) {
  return keywordTermIndex(haystack, term) >= 0;
}

export function keywordTermIndex(haystack, term) {
  const variants = keywordTermVariants(term);

  return variants.reduce((best, variant) => {
    const index = haystack.indexOf(variant);
    if (index < 0) {
      return best;
    }

    return best < 0 ? index : Math.min(best, index);
  }, -1);
}

export function countKeywordTermOccurrences(haystack, term) {
  return keywordTermVariants(term).reduce((count, variant) => {
    const escaped = variant.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

    return Math.max(count, (haystack.match(new RegExp(`(^|\\s)${escaped}`, 'g')) || []).length);
  }, 0);
}

export function keywordTermVariants(term) {
  const normalized = normalizeSearchText(term);
  if (normalized.length <= 4) {
    return [normalized];
  }

  const stemLength = normalized.length > 8 ? normalized.length - 3 : normalized.length - 2;
  const stem = normalized.slice(0, Math.max(4, stemLength));

  return Array.from(new Set([normalized, stem]));
}

export function measureTextWidth(text) {
  if (typeof document === 'undefined') {
    return String(text || '').length * 8;
  }

  const canvas =
    measureTextWidth.canvas || (measureTextWidth.canvas = document.createElement('canvas'));
  const context = canvas.getContext('2d');
  if (!context) {
    return String(text || '').length * 8;
  }

  context.font = '20px Arial, sans-serif';
  return context.measureText(String(text || '')).width;
}

export function hasValidHeadingHierarchy(headings) {
  let previousLevel = 1;
  let hasH2 = false;

  return headings.every((heading) => {
    const level = Number(heading.level || 2);
    if (level === 2) {
      hasH2 = true;
    }
    if (level > 2 && !hasH2) {
      return false;
    }
    const valid = level <= previousLevel + 1;
    previousLevel = level;
    return valid;
  });
}

export function isInternalLink(href) {
  const value = String(href || '').trim();
  if (!value) {
    return false;
  }
  if (value.startsWith('/') || value.startsWith('#')) {
    return true;
  }
  if (!/^https?:\/\//i.test(value)) {
    return true;
  }

  try {
    const url = new URL(value, window.location.origin);
    return url.origin === window.location.origin;
  } catch (error) {
    return false;
  }
}

export function isExternalLink(href) {
  const value = String(href || '').trim();
  if (!/^https?:\/\//i.test(value)) {
    return false;
  }

  try {
    const url = new URL(value, window.location.origin);
    return url.origin !== window.location.origin;
  } catch (error) {
    return false;
  }
}

export function imageFilenameLooksRandom(src) {
  const value = String(src || '').trim();
  if (!value) {
    return false;
  }

  let pathname = value;
  try {
    pathname = new URL(value, window.location.origin).pathname;
  } catch (error) {
    pathname = value.split('?')[0];
  }

  const filename = pathname.split('/').pop() || '';
  const base = filename.replace(/\.[a-z0-9]{2,6}$/i, '').replace(/[-_]+/g, '');
  if (base.length < 8) {
    return false;
  }

  if (/^[a-f0-9]{10,}$/i.test(base) || /^[0-9]{6,}$/.test(base)) {
    return true;
  }

  const letters = base.replace(/[^a-z]/gi, '');
  if (letters.length >= 10) {
    const vowels = (letters.match(/[aeiouy]/gi) || []).length;
    return vowels / letters.length < 0.18;
  }

  return false;
}

export function calculateSentenceStats(text) {
  const sentences = String(text || '')
    .split(/[.!?]+/)
    .map((sentence) => wordsFromText(sentence).length)
    .filter((length) => length > 0);

  if (!sentences.length) {
    return { count: 0, average: 0, longPercent: 0 };
  }

  const total = sentences.reduce((sum, length) => sum + length, 0);
  const long = sentences.filter((length) => length > 20).length;

  return {
    count: sentences.length,
    average: Number((total / sentences.length).toFixed(1)),
    longPercent: Math.round((long / sentences.length) * 100),
  };
}
