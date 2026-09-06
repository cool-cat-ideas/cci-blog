import { ProductNewsProvider } from '@cci/admin-ui/product-panels';
import { __ } from './i18n';
import React from 'react';
import { createRoot } from 'react-dom/client';
import { LoadingState, Select, createLocalMediaLibraryUiBridge } from '@cci/admin-ui/blog';
import App from './App';

window.CCIBlogMediaLibraryUi = createLocalMediaLibraryUiBridge({ createRoot, Select, LoadingState });

const mount = document.getElementById('cci-blog-admin-root');

if (mount) {
  const loaderStartedAt = performance.now();
  const minimumLoaderMs = 180;

  const mountApp = () => {
    if (mount.dataset.cciReactMounted === '1') {
      return;
    }

    mount.dataset.cciReactMounted = '1';
    createRoot(mount).render(<ProductNewsProvider t={__}><App /></ProductNewsProvider>);
  };

  const scheduleMount = () => {
    const delay = Math.max(0, minimumLoaderMs - (performance.now() - loaderStartedAt));

    window.setTimeout(() => {
      requestAnimationFrame(() => {
        requestAnimationFrame(mountApp);
      });
    }, delay);
  };

  if (document.readyState === 'complete') {
    scheduleMount();
  } else {
    window.addEventListener('load', scheduleMount, { once: true });
  }
}
