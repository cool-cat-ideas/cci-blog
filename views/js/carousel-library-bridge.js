/** Retain already loaded carousel constructors across theme bundle boundaries. */
(function () {
  'use strict';
  const libraries = window.CCIBlogCarouselLibraries || {};
  const capture = () => {
    const jquery = window.jQuery || window.$;
    if (typeof jquery?.fn?.owlCarousel === 'function') libraries.owlJquery = jquery;
    const Swiper = typeof window.Swiper === 'function' ? window.Swiper : window.Swiper?.default;
    if (typeof Swiper === 'function') libraries.Swiper = Swiper;
  };
  libraries.capture = capture;
  window.CCIBlogCarouselLibraries = libraries;
  capture();
  document.addEventListener(
    'load',
    (event) => {
      if (event.target.tagName === 'SCRIPT') capture();
    },
    true,
  );
})();
