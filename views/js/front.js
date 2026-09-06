/**
 * CCI Blog – front.js
 * Progressive enhancements, with optional Owl Carousel support.
 */

(function () {
  'use strict';

  // ------------------------------------------------------------------
  // 1. Reading progress bar
  // ------------------------------------------------------------------
  var progressBar = document.createElement('div');
  progressBar.id = 'cci-blog-progress';
  progressBar.style.cssText = 'position:fixed;top:0;left:0;width:0%;height:3px;background:var(--cci-blog-primary,#2c7be5);z-index:9999;transition:width .1s linear;pointer-events:none;';
  document.body.appendChild(progressBar);

  window.addEventListener('scroll', function () {
    var docEl   = document.documentElement;
    var scrolled = docEl.scrollTop || document.body.scrollTop;
    var total    = docEl.scrollHeight - docEl.clientHeight;
    progressBar.style.width = total > 0 ? (scrolled / total * 100) + '%' : '0%';
  }, { passive: true });

  // ------------------------------------------------------------------
  // 2. Responsive article table of contents
  // ------------------------------------------------------------------
  function initTableOfContents(root) {
    var scope = root || document;
    var tables = scope.querySelectorAll('[data-cci-blog-table-of-contents]');
    var mobileQuery = window.matchMedia ? window.matchMedia('(max-width: 768px)') : null;

    tables.forEach(function (details) {
      if (details.dataset.cciBlogTocReady === '1') return;
      details.dataset.cciBlogTocReady = '1';

      var syncBreakpoint = function () {
        if (!mobileQuery) return;
        details.open = !mobileQuery.matches;
      };

      syncBreakpoint();
      if (mobileQuery) {
        if (typeof mobileQuery.addEventListener === 'function') {
          mobileQuery.addEventListener('change', syncBreakpoint);
        } else if (typeof mobileQuery.addListener === 'function') {
          mobileQuery.addListener(syncBreakpoint);
        }
      }
    });
  }

  // ------------------------------------------------------------------
  // 3. Disqus comments
  // ------------------------------------------------------------------
  function initDisqus(root) {
    var scope = root || document;
    var container = scope.querySelector('[data-cci-blog-disqus]');
    if (!container || container.dataset.cciBlogDisqusReady === '1') return;

    var shortname = (container.dataset.disqusShortname || '').trim().toLowerCase();
    if (!/^[a-z0-9_-]+$/.test(shortname)) return;

    container.dataset.cciBlogDisqusReady = '1';
    window.disqus_config = function () {
      this.page.url = container.dataset.disqusUrl || window.location.href;
      this.page.identifier = container.dataset.disqusIdentifier || window.location.pathname;
    };

    if (document.querySelector('script[data-cci-blog-disqus-script]')) return;

    var script = document.createElement('script');
    script.src = 'https://' + shortname + '.disqus.com/embed.js';
    script.async = true;
    script.setAttribute('data-cci-blog-disqus-script', '1');
    script.setAttribute('data-timestamp', String(Date.now()));
    (document.head || document.body).appendChild(script);
  }

  // ------------------------------------------------------------------
  // 4. Threaded comment reply
  // ------------------------------------------------------------------
  document.addEventListener('click', function (e) {
    if (!e.target.classList.contains('cci-blog-comment-reply-btn')) return;
    e.preventDefault();
    var parentId = e.target.dataset.id || '0';
    var inp      = document.getElementById('cci-blog-reply-parent');
    if (inp) {
      inp.value = parentId;
      var form = document.querySelector('.cci-blog-comment-form');
      if (form) {
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        form.querySelector('input, textarea').focus();
      }
    }
  });

  // ------------------------------------------------------------------
  // 5. Auto-generate slug from title (admin-like convenience in search)
  // ------------------------------------------------------------------
  var titleInput = document.getElementById('cci-blog-post-title');
  var slugInput  = document.getElementById('cci-blog-post-slug');
  if (titleInput && slugInput) {
    titleInput.addEventListener('input', function () {
      if (slugInput.dataset.manual) return;
      slugInput.value = titleInput.value
        .toLowerCase()
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
    });
    slugInput.addEventListener('input', function () {
      slugInput.dataset.manual = '1';
    });
  }

  // ------------------------------------------------------------------
  // 6. Lazy-load inline videos / iframes
  // ------------------------------------------------------------------
  if ('IntersectionObserver' in window) {
    var iframes = document.querySelectorAll('iframe[data-src]');
    var ioVideo = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          var el = entry.target;
          el.src = el.dataset.src;
          ioVideo.unobserve(el);
        }
      });
    });
    iframes.forEach(function (f) { ioVideo.observe(f); });
  }

  // ------------------------------------------------------------------
  // 7. Estimated read-time countdown in the sticky header (progressive)
  // ------------------------------------------------------------------
  var readTimeBadge = document.querySelector('.cci-blog-meta-read-time');
  var postBody      = document.querySelector('.cci-blog-post-body');
  if (readTimeBadge && postBody) {
    window.addEventListener('scroll', function () {
      var rect     = postBody.getBoundingClientRect();
      var visible  = Math.max(0, Math.min(rect.bottom, window.innerHeight) - Math.max(rect.top, 0));
      var fraction = visible / rect.height;
      var remaining = parseInt(readTimeBadge.dataset.minutes || '0', 10);
      if (fraction > 0) {
        var left = Math.max(0, Math.round(remaining * (1 - (rect.top < 0 ? Math.abs(rect.top) / rect.height : 0))));
        readTimeBadge.textContent = left + ' min read';
      }
    }, { passive: true });
    readTimeBadge.dataset.minutes = readTimeBadge.textContent.trim().split(' ')[0];
  }

  // ------------------------------------------------------------------
  // 8. Presta-style pagination buttons
  // ------------------------------------------------------------------
  document.addEventListener('click', function (event) {
    var target = event.target;
    if (!target || !target.closest) {
      return;
    }

    var control = target.closest(
      '.cci-blog-listing .pagination a[href], .cci-blog-listing .js-pager-link[data-ps-data]'
    );
    if (!control
      || control.disabled
      || control.classList.contains('disabled')
      || control.getAttribute('aria-disabled') === 'true'
      || control.closest('li.current')) {
      return;
    }

    var url = control.getAttribute('href') || control.getAttribute('data-ps-data');
    if (url) {
      event.preventDefault();
      event.stopImmediatePropagation();
      window.location.assign(url);
    }
  }, true);

  // ------------------------------------------------------------------
  // 9. Product carousels
  // ------------------------------------------------------------------
  var owlCarouselLoadPromise = null;

  function getOwlCarouselScriptSrc() {
    var script = document.querySelector('script[src*="/owlcarousel/owl.carousel.min.js"]');
    if (script && script.src) {
      return script.src;
    }

    var stylesheet = document.querySelector('link[href*="/owlcarousel/assets/owl.carousel.min.css"]');
    if (stylesheet && stylesheet.href) {
      return stylesheet.href.replace('/assets/owl.carousel.min.css', '/owl.carousel.min.js');
    }

    return '';
  }

  function ensureOwlCarouselPlugin() {
    var $ = window.jQuery || window.$;
    if ($ && $.fn && typeof $.fn.owlCarousel === 'function') {
      return Promise.resolve(true);
    }

    var src = getOwlCarouselScriptSrc();
    if (!$ || !$.fn || !src) {
      return Promise.resolve(false);
    }

    if (owlCarouselLoadPromise) {
      return owlCarouselLoadPromise;
    }

    owlCarouselLoadPromise = new Promise(function (resolve) {
      var script = document.createElement('script');
      script.src = src;
      script.async = false;
      script.onload = function () {
        var currentJquery = window.jQuery || window.$;
        resolve(Boolean(currentJquery && currentJquery.fn && currentJquery.fn.owlCarousel));
      };
      script.onerror = function () {
        resolve(false);
      };
      document.head.appendChild(script);
    });

    return owlCarouselLoadPromise;
  }

  function initProductCarousels(root) {
    var scope = root || document;
    var tracks = scope.querySelectorAll('[data-cci-blog-carousel="owl"] .cci-blog-content-product-carousel-track');
    var $ = window.jQuery || window.$;

    if (!$ || !$.fn || typeof $.fn.owlCarousel !== 'function') {
      tracks.forEach(function (track) {
        if (track.dataset.ccbCarouselReady !== 'owl') {
          var carousel = track.closest('[data-cci-blog-carousel="owl"]');
          track.dataset.ccbCarouselReady = 'pending';
          track.classList.remove('cci-blog-state-carousel-fallback');
          track.classList.add('cci-blog-state-carousel-pending');
          if (carousel) {
            carousel.classList.remove('cci-blog-state-carousel-fallback');
            carousel.classList.add('cci-blog-state-carousel-pending');
          }
        }
      });

      ensureOwlCarouselPlugin().then(function (loaded) {
        if (loaded) {
          window.requestAnimationFrame(function () {
            initProductCarousels(root);
          });
          return;
        }

        tracks.forEach(function (track) {
          if (track.dataset.ccbCarouselReady !== 'owl') {
            var carousel = track.closest('[data-cci-blog-carousel="owl"]');
            track.dataset.ccbCarouselReady = 'fallback';
            track.classList.remove('cci-blog-state-carousel-pending');
            track.classList.add('cci-blog-state-carousel-fallback');
            if (carousel) {
              carousel.classList.remove('cci-blog-state-carousel-pending');
              carousel.classList.add('cci-blog-state-carousel-fallback');
            }
          }
        });
      });

      return;
    }

    tracks.forEach(function (track) {
      if (track.dataset.ccbCarouselReady === 'owl') {
        $(track).trigger('refresh.owl.carousel');
        return;
      }

      var carousel = track.closest('[data-cci-blog-carousel="owl"]');
      var options = {};
      try {
        options = JSON.parse(carousel.getAttribute('data-cci-blog-carousel-options') || '{}');
      } catch (error) {
        options = {};
      }

      track.classList.remove('cci-blog-state-carousel-fallback');
      track.classList.remove('cci-blog-state-carousel-pending');
      track.classList.add('owl-carousel');
      $(track).owlCarousel(options);
      track.dataset.ccbCarouselReady = 'owl';
      carousel.classList.remove('cci-blog-state-carousel-fallback');
      carousel.classList.remove('cci-blog-state-carousel-pending');
      carousel.classList.add('cci-blog-state-owl-ready');
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      initTableOfContents(document);
      initDisqus(document);
      initProductCarousels(document);
    });
  } else {
    initTableOfContents(document);
    initDisqus(document);
    initProductCarousels(document);
  }
  window.cciBlogInitTableOfContents = initTableOfContents;
  window.cciBlogInitDisqus = initDisqus;
  window.cciBlogInitProductCarousels = initProductCarousels;

})();
