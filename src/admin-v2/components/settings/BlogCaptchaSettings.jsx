import React from 'react';
import { Plug, Settings } from 'lucide-react';
import { createCatalogComponents } from '@cci/admin-ui';
import { InfoCallout } from '@cci/admin-ui/blog';
import { CaptchaSettingsDialog } from '@cci/form-captcha/settings';
import { apiFetch } from '../../api/api';
import { __ } from '../../i18n/i18n';
import { Button } from '../ui/button';
import { CheckboxField } from '../ui/checkbox';
import { Field } from '../ui/field';
import { Input } from '../ui/input';

const { CatalogGrid, CatalogProductCard } = createCatalogComponents('blog');
const controls = { Button, CheckboxField, Field, Input, InfoCallout };
const t = (text) => __(text, 'cci-blog');
const dialogPortal = () => document.querySelector('.cci-blog-admin-root') || document.body;

export default function BlogCaptchaSettings({ initial, setNotice }) {
  const [data, setData] = React.useState(initial);
  const [selected, setSelected] = React.useState('');
  const [draft, setDraft] = React.useState(null);
  const [busy, setBusy] = React.useState(false);
  const [error, setError] = React.useState('');
  const siteKeyInput = React.useRef(null);
  const configureButton = React.useRef(null);
  const current = data?.items?.find(({ id }) => id === selected);

  const close = () => {
    setSelected('');
    setError('');
  };

  const save = async (event) => {
    event.preventDefault();
    if (!current || !draft || busy) return;
    setBusy(true);
    setError('');
    try {
      const result = await apiFetch('/captcha/save', {
        method: 'POST',
        body: JSON.stringify({ ...draft, id: current.id, revision: data.revision }),
      });
      setData(result.captcha);
      close();
      setNotice?.({ type: 'success', message: t('Integration settings saved.') });
    } catch (failure) {
      setError(t(failure.message));
    } finally {
      setBusy(false);
    }
  };

  return (
    <section className="tw-grid tw-min-w-0 tw-gap-4 tw-border-0 tw-border-t tw-border-solid tw-border-cci-blog-border tw-bg-white tw-p-4" aria-labelledby="cci-blog-captcha-heading">
      <div className="tw-grid tw-gap-1">
        <h3 id="cci-blog-captcha-heading" className="tw-m-0">{t('Form protection')}</h3>
        <p className="tw-m-0 tw-text-sm tw-text-cci-blog-muted">
          {t('Protect native comments and article feedback with a security check. Disqus manages its own forms.')}
        </p>
      </div>
      <CatalogGrid>
        {(data?.items ?? []).map((item) => (
          <CatalogProductCard
            key={item.id}
            item={{ title: item.title, description: t(item.description), installed: true }}
            mode="installed"
            fallbackIcon={Plug}
            installedLabel={t(item.enabled ? 'Enabled' : 'Disabled')}
            actions={
              <Button
                type="button"
                variant="outlineAccent"
                aria-label={`${t('Configure')} ${item.title}`}
                disabled={busy}
                onClick={(event) => {
                  configureButton.current = event.currentTarget;
                  setError('');
                  setSelected(item.id);
                  setDraft({ enabled: item.enabled, siteKey: item.siteKey, secret: '' });
                }}
              >
                <Settings aria-hidden="true" />
                {t('Configure')}
              </Button>
            }
          />
        ))}
      </CatalogGrid>
      {current && draft && (
        <CaptchaSettingsDialog
          controls={controls}
          current={current}
          draft={draft}
          setDraft={setDraft}
          busy={busy}
          error={error}
          onClose={close}
          onSave={save}
          portal={dialogPortal}
          initialFocusRef={siteKeyInput}
          returnFocusRef={configureButton}
          setupUrl={current.setupUrl}
          t={t}
        />
      )}
    </section>
  );
}
