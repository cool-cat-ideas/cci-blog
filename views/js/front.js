/**
 * CCI Blog – front.js
 * Progressive enhancements, with optional Owl Carousel and Swiper support.
 */

(function () {
  'use strict';

  // ------------------------------------------------------------------
  // 1. Reading progress bar
  // ------------------------------------------------------------------
  var progressBar = document.createElement('div');
  progressBar.id = 'cci-blog-progress';
  progressBar.style.cssText =
    'position:fixed;top:0;left:0;width:0%;height:3px;background:var(--cci-blog-primary,#2c7be5);z-index:9999;transition:width .1s linear;pointer-events:none;';
  document.body.appendChild(progressBar);

  window.addEventListener(
    'scroll',
    function () {
      var docEl = document.documentElement;
      var scrolled = docEl.scrollTop || document.body.scrollTop;
      var total = docEl.scrollHeight - docEl.clientHeight;
      progressBar.style.width = total > 0 ? (scrolled / total) * 100 + '%' : '0%';
    },
    { passive: true },
  );

  // ------------------------------------------------------------------
  // 2. Responsive article table of contents
  // ------------------------------------------------------------------
  function initTableOfContents(root) {
    var scope = root || document;
    var tables = scope.querySelectorAll('[data-cci-blog-table-of-contents]');
    tables.forEach(function (details) {
      if (details.dataset.cciBlogTocReady === '1') return;
      details.dataset.cciBlogTocReady = '1';
      var nav = details.closest('[data-cci-blog-toc-nav]');
      var layout = details.closest('[data-cci-blog-post-layout]');
      var mode = nav ? nav.dataset.cciBlogTocMode : 'inline';
      var sideMode = mode === 'sidebar' || mode === 'sticky';
      var mobileQuery = window.matchMedia
        ? window.matchMedia(sideMode ? '(max-width: 991px)' : '(max-width: 768px)')
        : null;
      var home = layout && layout.querySelector('[data-cci-blog-toc-home]');
      var rail = layout && layout.querySelector('[data-cci-blog-toc-rail]');
      var headers = Array.from(
        document.querySelectorAll('#header, .cci-demo-store-banner, #wpadminbar'),
      );
      var links = nav ? Array.from(nav.querySelectorAll('a[href^="#"]')) : [];
      var sections = links.map(function (link) {
        return document.getElementById(decodeURIComponent(link.hash.slice(1)));
      });
      var queued = false;
      var lastOffset = -1;
      var current = -1;

      var updateOffset = function () {
        var bottom = headers
          .map(function (header) {
            return {
              position: getComputedStyle(header).position,
              rect: header.getBoundingClientRect(),
            };
          })
          .sort(function (a, b) {
            return a.rect.top - b.rect.top;
          })
          .reduce(function (value, header) {
            var rect = header.rect;
            return (header.position === 'fixed' || header.position === 'sticky') &&
              rect.top <= value + 2 &&
              rect.bottom > 0
              ? Math.max(value, rect.bottom)
              : value;
          }, 0);
        var offset = Math.max(8, bottom + Number((nav && nav.dataset.cciBlogTocGap) || 16));
        var nativePadding =
          parseFloat(
            getComputedStyle(document.scrollingElement || document.documentElement)
              .scrollPaddingTop,
          ) || 0;
        if (layout)
          layout.style.setProperty(
            '--cci-blog-anchor-offset',
            Math.max(0, offset - nativePadding) + 'px',
          );
        if (layout && lastOffset !== offset)
          layout.style.setProperty('--cci-blog-sticky-top', offset + 'px');
        lastOffset = offset;
        return offset;
      };
      var updateCurrent = function () {
        queued = false;
        var offset = updateOffset();
        if (!sideMode) return;
        var active = 0;
        sections.forEach(function (section, index) {
          if (section && section.getBoundingClientRect().top <= offset + 16) active = index;
        });
        if (active === current) return;
        current = active;
        links.forEach(function (link, index) {
          if (index === active) link.setAttribute('aria-current', 'location');
          else link.removeAttribute('aria-current');
        });
        if (rail && rail.contains(nav) && details.open && !nav.contains(document.activeElement)) {
          var linkRect = links[active] && links[active].getBoundingClientRect();
          var railRect = rail.getBoundingClientRect();
          if (linkRect && linkRect.top < railRect.top)
            rail.scrollTop -= railRect.top - linkRect.top + 8;
          else if (linkRect && linkRect.bottom > railRect.bottom)
            rail.scrollTop += linkRect.bottom - railRect.bottom + 8;
        }
      };
      var scheduleUpdate = function () {
        if (!queued) {
          queued = true;
          window.requestAnimationFrame(updateCurrent);
        }
      };

      var syncBreakpoint = function () {
        if (!mobileQuery) return;
        if (sideMode && nav && rail && home) {
          var focused = nav.contains(document.activeElement) ? document.activeElement : null;
          (mobileQuery.matches ? home : rail).prepend(nav);
          nav.classList.toggle('cci-blog-toc-in-sidebar', !mobileQuery.matches);
          if (focused) focused.focus({ preventScroll: true });
        }
        details.open = !mobileQuery.matches;
        scheduleUpdate();
      };

      syncBreakpoint();
      if (mobileQuery) {
        if (typeof mobileQuery.addEventListener === 'function') {
          mobileQuery.addEventListener('change', syncBreakpoint);
        } else if (typeof mobileQuery.addListener === 'function') {
          mobileQuery.addListener(syncBreakpoint);
        }
      }
      window.addEventListener('scroll', scheduleUpdate, { passive: true });
      window.addEventListener('resize', scheduleUpdate, { passive: true });
      details.addEventListener('toggle', function () {
        current = -1;
        scheduleUpdate();
      });
      if (typeof ResizeObserver === 'function') {
        var observer = new ResizeObserver(scheduleUpdate);
        headers.forEach(function (header) {
          observer.observe(header);
        });
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
    var inp = document.getElementById('cci-blog-reply-parent');
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
  var slugInput = document.getElementById('cci-blog-post-slug');
  if (titleInput && slugInput) {
    titleInput.addEventListener('input', function () {
      if (slugInput.dataset.manual) return;
      slugInput.value = titleInput.value
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
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
    iframes.forEach(function (f) {
      ioVideo.observe(f);
    });
  }

  // ------------------------------------------------------------------
  // 7. Estimated read-time countdown in the sticky header (progressive)
  // ------------------------------------------------------------------
  var readTimeBadge = document.querySelector('.cci-blog-meta-read-time');
  var postBody = document.querySelector('.cci-blog-post-body');
  if (readTimeBadge && postBody) {
    window.addEventListener(
      'scroll',
      function () {
        var rect = postBody.getBoundingClientRect();
        var visible = Math.max(
          0,
          Math.min(rect.bottom, window.innerHeight) - Math.max(rect.top, 0),
        );
        var fraction = visible / rect.height;
        var remaining = parseInt(readTimeBadge.dataset.minutes || '0', 10);
        if (fraction > 0) {
          var left = Math.max(
            0,
            Math.round(remaining * (1 - (rect.top < 0 ? Math.abs(rect.top) / rect.height : 0))),
          );
          readTimeBadge.textContent = left + ' min read';
        }
      },
      { passive: true },
    );
    readTimeBadge.dataset.minutes = readTimeBadge.textContent.trim().split(' ')[0];
  }

  // ------------------------------------------------------------------
  // 8. Presta-style pagination buttons
  // ------------------------------------------------------------------
  document.addEventListener(
    'click',
    function (event) {
      var target = event.target;
      if (!target || !target.closest) {
        return;
      }

      var control = target.closest(
        '.cci-blog-listing .pagination a[href], .cci-blog-listing .js-pager-link[data-ps-data]',
      );
      if (
        !control ||
        control.disabled ||
        control.classList.contains('disabled') ||
        control.getAttribute('aria-disabled') === 'true' ||
        control.closest('li.current')
      ) {
        return;
      }

      var url = control.getAttribute('href') || control.getAttribute('data-ps-data');
      if (url) {
        event.preventDefault();
        event.stopImmediatePropagation();
        window.location.assign(url);
      }
    },
    true,
  );

  // ------------------------------------------------------------------
  // 9. Product carousels
  // ------------------------------------------------------------------
  function initProductCarousels(root) {
    window.CCIBlogProductCarousels?.init(root);
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
