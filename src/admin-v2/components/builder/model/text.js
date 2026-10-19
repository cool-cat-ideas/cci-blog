export function normalizeAdminSlug(value) {
  const replacements = {
    '\u0105': 'a',
    '\u0107': 'c',
    '\u0119': 'e',
    '\u0142': 'l',
    '\u0144': 'n',
    '\u00f3': 'o',
    '\u015b': 's',
    '\u017a': 'z',
    '\u017c': 'z',
    '\u0104': 'a',
    '\u0106': 'c',
    '\u0118': 'e',
    '\u0141': 'l',
    '\u0143': 'n',
    '\u00d3': 'o',
    '\u015a': 's',
    '\u0179': 'z',
    '\u017b': 'z',
  };

  return String(value || '')
    .replace(/[\u0104-\u017c]/g, (character) => replacements[character] || character)
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

export function normalizePlainText(text) {
  return String(text || '')
    .replace(/\r\n?/g, '\n')
    .replace(/\u00a0/g, ' ')
    .replace(/[ \t]+\n/g, '\n')
    .replace(/\n[ \t]+/g, '\n')
    .trim();
}

export function htmlToPlainText(html) {
  if (typeof document === 'undefined') {
    return String(html || '')
      .replace(/<[^>]+>/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
  }

  const template = document.createElement('template');
  template.innerHTML = sanitizeDraftHtml(html || '');
  return normalizePlainText(template.content.textContent || '');
}

export function sanitizeDraftHtml(html) {
  if (typeof document === 'undefined') {
    return String(html || '');
  }

  const template = document.createElement('template');
  template.innerHTML = String(html || '');
  const allowedTags = new Set([
    'P',
    'BR',
    'STRONG',
    'B',
    'EM',
    'I',
    'U',
    'UL',
    'OL',
    'LI',
    'BLOCKQUOTE',
    'A',
    'H2',
    'H3',
    'H4',
    'DIV',
  ]);
  const walker = document.createTreeWalker(template.content, NodeFilter.SHOW_ELEMENT);
  const elements = [];

  while (walker.nextNode()) {
    elements.push(walker.currentNode);
  }

  elements.reverse().forEach((element) => {
    if (!allowedTags.has(element.tagName)) {
      element.replaceWith(document.createTextNode(element.textContent || ''));
      return;
    }

    Array.from(element.attributes).forEach((attribute) => {
      const name = attribute.name.toLowerCase();
      const value = attribute.value || '';

      if (
        element.tagName === 'A' &&
        ['href', 'title', 'target', 'rel', 'class', 'id', 'aria-label'].includes(name)
      ) {
        if (name === 'href' && !isSafeUrl(value)) {
          element.removeAttribute(name);
        }
        return;
      }

      element.removeAttribute(attribute.name);
    });
  });

  return template.innerHTML;
}

export function isSafeUrl(value) {
  const url = String(value || '')
    .trim()
    .toLowerCase();
  return (
    url === '' ||
    url.startsWith('#') ||
    url.startsWith('/') ||
    url.startsWith('http://') ||
    url.startsWith('https://') ||
    url.startsWith('mailto:') ||
    url.startsWith('tel:')
  );
}
