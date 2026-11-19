import React from 'react';
import { createRoot } from 'react-dom/client';
import { Captcha } from '@cci/form-captcha';

function mount() {
  for (const section of document.querySelectorAll('[data-cci-blog-captcha]')) {
    const form = section.closest('form');
    const token = section.querySelector('input[name="captchaToken"]');
    const host = section.querySelector('[data-captcha-widget]');
    if (!form || !token || !host) continue;

    const root = createRoot(host);
    const { provider, siteKey, operation, label, error, validation } = section.dataset;
    const config = { provider, siteKey, action: `cci_blog_${operation}` };
    let attempt = 0;
    let invalid = false;
    const render = () => root.render(
      <Captcha
        config={config}
        operation={operation}
        attempt={attempt}
        onToken={(value) => {
          token.value = value;
          if (value && invalid) {
            invalid = false;
            render();
          }
        }}
        validationError={invalid ? validation : ''}
        t={(message) => message === 'Security check' ? label : message === validation ? validation : error}
        className="cci-blog-captcha"
        errorClassName="cci-blog-captcha-error"
      />,
    );
    form.addEventListener('submit', (event) => {
      if (token.value) return;
      event.preventDefault();
      event.stopPropagation();
      invalid = true;
      render();
      host.querySelector('[data-field="captcha"]')?.focus();
    }, true);
    form.addEventListener('cci-blog:captcha-complete', () => {
      root.unmount();
      section.hidden = true;
    }, { once: true });
    form.addEventListener('cci-blog:captcha-reset', (event) => {
      token.value = '';
      invalid = Boolean(event.detail?.invalid);
      attempt += 1;
      render();
    });
    render();
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', mount, { once: true });
} else {
  mount();
}
