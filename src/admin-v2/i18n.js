import { pluginData } from './api';

function getPayloadTranslations() {
  const translations = pluginData.i18n?.translations;

  return translations && typeof translations === 'object' ? translations : {};
}

export function __(text, domain = 'cci-blog') {
  if (domain !== 'cci-blog') {
    return text;
  }

  const translations = getPayloadTranslations();

  return translations[text] || text;
}

export function coreString(key, fallback) {
  const value = pluginData.i18n?.[key];
  const translatedFallback = __(fallback, 'cci-blog');

  return value && value !== fallback ? value : translatedFallback;
}
