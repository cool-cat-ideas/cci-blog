/** Product collections: reuse a site's Owl/Swiper, or enhance native scroll snap. */
(function () {
  'use strict';

  const instances = new WeakMap();
  const pending = new WeakMap();
  const loads = new Map();
  let registeredSwiper = null;
  // Keep the plugin's jQuery owner when a theme later replaces window.jQuery.
  // This reuses the loaded Owl instance without evaluating its script twice.
  let owlJquery = window.CCIBlogCarouselLibraries?.owlJquery || null;
  const config = () => window.cciBlogCarouselConfig || {};
  const jquery = () => {
    const current = window.jQuery || window.$;
    window.CCIBlogCarouselLibraries?.capture();
    owlJquery = window.CCIBlogCarouselLibraries?.owlJquery || owlJquery;
    if (current?.fn?.owlCarousel) owlJquery = current;
    return owlJquery || current;
  };
  const swiper = () =>
    registeredSwiper ||
    window.CCIBlogCarouselLibraries?.Swiper ||
    (typeof window.Swiper === 'function' ? window.Swiper : window.Swiper?.default);
  const available = (engine) =>
    engine === 'swiper'
      ? typeof swiper() === 'function'
      : typeof jquery()?.fn?.owlCarousel === 'function';
  const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function loadLibrary(engine) {
    if (available(engine)) return Promise.resolve(true);
    if (loads.has(engine)) return loads.get(engine);
    const asset = config().assets?.[engine];
    const existing = Array.from(document.scripts).find((script) =>
      engine === 'owl' ? /owl[.\/-]?carousel/i.test(script.src) : /swiper/i.test(script.src),
    );
    if (!existing && (!config().allowModuleAssets || !asset || (engine === 'owl' && !jquery()?.fn)))
      return Promise.resolve(false);
    const promise = new Promise((resolve) => {
      const script = existing || document.createElement('script');
      let settled = false;
      const finish = () => {
        if (settled) return;
        settled = true;
        clearTimeout(timeout);
        resolve(available(engine));
      };
      const timeout = setTimeout(finish, 5000);
      script.addEventListener('load', finish, { once: true });
      script.addEventListener('error', finish, { once: true });
      if (!existing) {
        const hasStyles = Array.from(document.querySelectorAll('link[rel="stylesheet"]')).some(
          (link) =>
            engine === 'owl' ? /owl[.\/-]?carousel/i.test(link.href) : /swiper/i.test(link.href),
        );
        if (!hasStyles) {
          const style = document.createElement('link');
          style.rel = 'stylesheet';
          style.href = asset.css;
          document.head.appendChild(style);
        }
        script.src = asset.js;
        script.dataset.cciBlogLibrary = engine;
        document.head.appendChild(script);
      }
    });
    loads.set(engine, promise);
    return promise;
  }

  function visibleItems(options) {
    let items = options.items || 3;
    Object.entries(options.responsive || {})
      .sort((a, b) => Number(a[0]) - Number(b[0]))
      .forEach(([width, value]) => {
        if (window.innerWidth >= Number(width)) items = value.items || items;
      });
    return Number(items);
  }

  function nativeAdapter(track, options, update) {
    const cards = Array.from(track.children);
    const index = () => {
      const edge = track.getBoundingClientRect().left;
      return cards.reduce(
        (best, card, current) =>
          Math.abs(card.getBoundingClientRect().left - edge) <
          Math.abs(cards[best].getBoundingClientRect().left - edge)
            ? current
            : best,
        0,
      );
    };
    const go = (target) => {
      const maximum = Math.max(0, cards.length - visibleItems(options));
      const next =
        options.rewind || options.loop
          ? target < 0
            ? maximum
            : target > maximum
              ? 0
              : target
          : Math.max(0, Math.min(maximum, target));
      const item = cards[next];
      track.scrollTo({
        left:
          track.scrollLeft + item.getBoundingClientRect().left - track.getBoundingClientRect().left,
        behavior: reducedMotion() ? 'instant' : 'smooth',
      });
    };
    let timer;
    track.addEventListener('scroll', update, { passive: true });
    const adapter = {
      index,
      go,
      next: () => go(index() + (options.slideBy || 1)),
      prev: () => go(index() - (options.slideBy || 1)),
      pause: () => clearInterval(timer),
      play: () => {
        clearInterval(timer);
        if (options.autoplay) timer = setInterval(adapter.next, options.autoplayTimeout || 5000);
      },
      destroy: () => {
        clearInterval(timer);
        track.removeEventListener('scroll', update);
      },
    };
    adapter.play();
    return adapter;
  }

  function owlAdapter(track, options, update) {
    const $ = jquery();
    track.classList.add('owl-carousel');
    const node = $(track);
    node.on('changed.owl.carousel.cciblog refreshed.owl.carousel.cciblog', update);
    node.owlCarousel({ ...options, nav: false, dots: false });
    const api = node.data('owl.carousel');
    return {
      index: () => api.relative(api.current()) || 0,
      go: (index) => node.trigger('to.owl.carousel', [index, options.smartSpeed, true]),
      next: () => node.trigger('next.owl.carousel'),
      prev: () => node.trigger('prev.owl.carousel'),
      pause: () => node.trigger('stop.owl.autoplay'),
      play: () => {
        if (options.autoplay) node.trigger('play.owl.autoplay', [options.autoplayTimeout]);
      },
      destroy: () => {
        node.off('.cciblog');
        node.trigger('destroy.owl.carousel');
      },
    };
  }

  function swiperAdapter(track, options, update) {
    const wrapper = document.createElement('div');
    wrapper.className = 'swiper-wrapper';
    Array.from(track.children).forEach((card) => {
      card.classList.add('swiper-slide');
      wrapper.appendChild(card);
    });
    track.appendChild(wrapper);
    track.classList.add('swiper');
    const breakpoints = {};
    Object.entries(options.responsive || {}).forEach(([width, value]) => {
      breakpoints[width] = {
        slidesPerView: value.items,
        spaceBetween: value.margin,
        slidesOffsetBefore: value.stagePadding,
        slidesOffsetAfter: value.stagePadding,
      };
    });
    const Constructor = swiper();
    const api = new Constructor(track, {
      slidesPerView: options.items,
      spaceBetween: options.margin,
      speed: options.smartSpeed,
      slidesPerGroup: options.slideBy || 1,
      centeredSlides: options.center,
      allowTouchMove: options.touchDrag,
      autoHeight: options.autoHeight,
      loop: options.loop,
      rewind: options.rewind && !options.loop,
      autoplay: options.autoplay
        ? {
            delay: options.autoplayTimeout,
            pauseOnMouseEnter: options.autoplayHoverPause,
            disableOnInteraction: false,
          }
        : false,
      watchOverflow: true,
      a11y: { enabled: true },
      breakpoints,
      on: { slideChange: update, breakpoint: update, resize: update },
    });
    return {
      index: () => api.realIndex || 0,
      go: (index) => (options.loop ? api.slideToLoop(index) : api.slideTo(index)),
      next: () => api.slideNext(),
      prev: () => api.slidePrev(),
      pause: () => api.autoplay?.stop(),
      play: () => {
        if (options.autoplay) api.autoplay?.start();
      },
      destroy: () => {
        api.destroy(true, true);
        wrapper.replaceWith(...wrapper.children);
        track.classList.remove('swiper');
      },
    };
  }

  function enhance(carousel, track, requested, options) {
    const count = track.children.length;
    if (!count) return;
    const largest = Math.max(
      options.items || 3,
      ...Object.values(options.responsive || {}).map((value) => value.items || 1),
    );
    options = {
      ...options,
      loop: Boolean(options.loop && count >= largest + (options.slideBy || 1)),
      autoplay: Boolean(options.autoplay && !reducedMotion()),
      smartSpeed: reducedMotion() ? 0 : (options.smartSpeed ?? 300),
    };
    const suppliedControls = carousel.querySelector('[data-cci-blog-carousel-controls]');
    const controls = suppliedControls || document.createElement('div');
    if (!suppliedControls) controls.className = 'cci-blog-carousel-controls';
    const navigation = carousel.querySelector('[data-cci-blog-carousel-navigation]');
    const pageTemplate = carousel.querySelector('[data-cci-blog-carousel-page-template]');
    const listeners = [];
    const text = {
      previous: 'Previous products',
      next: 'Next products',
      pause: 'Pause carousel',
      play: 'Play carousel',
      page: 'Product page',
      ...config().strings,
    };
    const button = (label, action, content) => {
      const control = document.createElement('button');
      control.type = 'button';
      control.className = 'btn btn-outline-primary';
      control.setAttribute('aria-label', label);
      control.textContent = content || label;
      control.addEventListener('click', action);
      return control;
    };
    let adapter;
    let manualPause = false;
    let focused = false;
    let hovered = false;
    let destroyed = false;
    const bind = (selector, label, action, content) => {
      const supplied = carousel.querySelector(selector);
      if (!supplied) return button(label, action, content);
      supplied.addEventListener('click', action);
      listeners.push(() => supplied.removeEventListener('click', action));
      return supplied;
    };
    const previous = bind(
      '[data-cci-blog-carousel-prev]',
      text.previous,
      () => adapter.prev(),
      '‹',
    );
    const next = bind('[data-cci-blog-carousel-next]', text.next, () => adapter.next(), '›');
    const pages =
      carousel.querySelector('[data-cci-blog-carousel-pages]') || document.createElement('div');
    if (!pages.hasAttribute('data-cci-blog-carousel-pages'))
      pages.className = 'cci-blog-carousel-pages';
    const pause = bind('[data-cci-blog-carousel-pause]', text.pause, () => {
      manualPause = !manualPause;
      syncAutoplay();
    });
    pause.hidden = !options.autoplay;
    const syncAutoplay = () => {
      if (manualPause || focused || hovered) adapter.pause();
      else adapter.play();
      (pause.querySelector('[data-cci-blog-carousel-pause-label]') || pause).textContent =
        manualPause ? text.play : text.pause;
      pause.setAttribute('aria-label', manualPause ? text.play : text.pause);
      pause.setAttribute('aria-pressed', String(manualPause));
    };
    const update = () => {
      if (!adapter || destroyed) return;
      const visible = visibleItems(options);
      const maximum =
        requested !== 'native' && options.center ? count - 1 : Math.max(0, count - visible);
      const current = adapter.index();
      const canWrap = options.loop || options.rewind;
      previous.disabled = !canWrap && current === 0;
      next.disabled = !canWrap && current >= maximum;
      controls.hidden =
        count <= visible || (suppliedControls && !options.dots && !options.autoplay);
      if (navigation) navigation.hidden = count <= visible || !options.nav;
      const step = Math.max(1, Number(options.slideBy) || 1);
      const last = options.loop ? count - 1 : maximum;
      const positions = [];
      for (let position = 0; position <= last; position += step) positions.push(position);
      if (positions[positions.length - 1] !== last) positions.push(last);
      const total = positions.length;
      if (pages.children.length !== total) {
        pages.replaceChildren(
          ...Array.from({ length: total }, (_, index) => {
            const action = () => adapter.go(positions[index]);
            if (!pageTemplate)
              return button(`${text.page} ${index + 1}`, action, String(index + 1));
            const control = pageTemplate.content.firstElementChild.cloneNode(true);
            const target = control.matches('button') ? control : control.querySelector('button');
            target.setAttribute('aria-label', `${text.page} ${index + 1}`);
            (control.querySelector('[data-cci-blog-carousel-page-label]') || control).textContent =
              String(index + 1);
            target.addEventListener('click', action);
            return control;
          }),
        );
      }
      const activePage = positions.reduce(
        (best, position, index) =>
          Math.abs(position - current) < Math.abs(positions[best] - current) ? index : best,
        0,
      );
      Array.from(pages.children).forEach((page, index) => {
        const active = index === activePage;
        const target = page.matches('button') ? page : page.querySelector('button');
        page.classList.toggle('active', active);
        target.setAttribute('aria-current', active ? 'page' : 'false');
      });
    };
    try {
      adapter =
        requested === 'owl'
          ? owlAdapter(track, options, update)
          : requested === 'swiper'
            ? swiperAdapter(track, options, update)
            : nativeAdapter(track, options, update);
    } catch (error) {
      // A partially initialized site plugin must not make the products unreadable.
      if (track.swiper) track.swiper.destroy(true, true);
      const wrapper = track.querySelector(':scope > .swiper-wrapper');
      if (wrapper) wrapper.replaceWith(...wrapper.children);
      track.classList.remove('swiper', 'owl-carousel');
      requested = 'native';
      adapter = nativeAdapter(track, options, update);
    }
    carousel.dataset.ccbCarouselReady = requested;
    track.dataset.ccbCarouselReady = requested;
    carousel.classList.add('cci-blog-state-' + requested + '-ready');
    if (!suppliedControls) {
      if (options.nav) controls.append(previous, next);
      if (options.dots) controls.appendChild(pages);
      if (options.autoplay) controls.appendChild(pause);
      carousel.appendChild(controls);
    }
    const onFocus = () => {
      focused = true;
      syncAutoplay();
    };
    const onBlur = (event) => {
      if (!carousel.contains(event.relatedTarget)) {
        focused = false;
        syncAutoplay();
      }
    };
    const onEnter = () => {
      hovered = Boolean(options.autoplayHoverPause);
      syncAutoplay();
    };
    const onLeave = () => {
      hovered = false;
      syncAutoplay();
    };
    const onKey = (event) => {
      if (
        !['ArrowLeft', 'ArrowRight'].includes(event.key) ||
        event.target.matches('input,select,textarea,button,a')
      )
        return;
      event.preventDefault();
      event.key === 'ArrowLeft' ? adapter.prev() : adapter.next();
    };
    carousel.tabIndex = 0;
    carousel.addEventListener('focusin', onFocus);
    carousel.addEventListener('focusout', onBlur);
    carousel.addEventListener('mouseenter', onEnter);
    carousel.addEventListener('mouseleave', onLeave);
    carousel.addEventListener('keydown', onKey);
    window.addEventListener('resize', update);
    instances.set(track, {
      engine: requested,
      destroy() {
        destroyed = true;
        adapter.destroy();
        if (suppliedControls) {
          controls.hidden = true;
          pages.replaceChildren();
        } else controls.remove();
        if (navigation) navigation.hidden = true;
        listeners.forEach((remove) => remove());
        carousel.removeEventListener('focusin', onFocus);
        carousel.removeEventListener('focusout', onBlur);
        carousel.removeEventListener('mouseenter', onEnter);
        carousel.removeEventListener('mouseleave', onLeave);
        carousel.removeEventListener('keydown', onKey);
        window.removeEventListener('resize', update);
        carousel.classList.remove('cci-blog-state-' + requested + '-ready');
      },
    });
    update();
  }

  async function initOne(carousel) {
    const track = carousel.querySelector(
      '[data-cci-blog-carousel-track], .cci-blog-content-product-carousel-track',
    );
    if (!track || pending.has(track)) return;
    const mode = carousel.dataset.cciBlogCarousel;
    const current = instances.get(track);
    if (current && current.engine !== 'native') return;
    let options;
    try {
      options = JSON.parse(carousel.dataset.cciBlogCarouselOptions || '{}');
    } catch {
      options = {};
    }
    let engine = mode === 'auto' ? (available('owl') ? 'owl' : 'swiper') : mode;
    if (!['owl', 'swiper', 'native'].includes(engine)) engine = 'native';
    const task = engine === 'native' ? Promise.resolve(false) : loadLibrary(engine);
    pending.set(track, task);
    const loaded = await task;
    pending.delete(track);
    if (!track.isConnected) return;
    engine = loaded ? engine : 'native';
    if (current?.engine === engine) return;
    current?.destroy();
    enhance(carousel, track, engine, options);
  }

  const init = (root) =>
    (root || document).querySelectorAll('[data-cci-blog-carousel]').forEach(initOne);
  window.CCIBlogProductCarousels = {
    init,
    registerSwiper(Constructor) {
      registeredSwiper = Constructor;
      init(document);
    },
  };
  window.addEventListener('load', () => init(document), { once: true });
  window.addEventListener('cci-blog:carousel-library-ready', () => init(document));
})();
