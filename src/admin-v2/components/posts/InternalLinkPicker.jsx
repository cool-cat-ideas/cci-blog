import React from 'react';
import { Search } from 'lucide-react';
import { apiFetch } from '../../api/api';
import { __ } from '../../i18n/i18n';
import { Button } from '../ui/button';
import { Field } from '../ui/field';
import { Input } from '../ui/input';
import { Select } from '../ui/select';

export default function InternalLinkPicker({ languageId, onSelect }) {
  const [open, setOpen] = React.useState(false);
  const [query, setQuery] = React.useState('');
  const [type, setType] = React.useState('all');
  const [items, setItems] = React.useState([]);
  const [loading, setLoading] = React.useState(false);
  const [error, setError] = React.useState('');
  const [attempt, setAttempt] = React.useState(0);
  const id = React.useId();
  const toggleRef = React.useRef(null);

  React.useEffect(() => {
    if (!open) return undefined;
    const controller = new AbortController();
    setLoading(true);
    setItems([]);
    setError('');
    const timer = window.setTimeout(() => {
      apiFetch('/catalog/links', {
        body: JSON.stringify({ query, type, id_lang: languageId }),
        signal: controller.signal,
      })
        .then((response) => {
          if (!controller.signal.aborted) setItems(response.items || []);
        })
        .catch((failure) => {
          if (!controller.signal.aborted)
            setError(failure.message || __('Could not load blog links.', 'cci-blog'));
        })
        .finally(() => {
          if (!controller.signal.aborted) setLoading(false);
        });
    }, 250);
    return () => {
      window.clearTimeout(timer);
      controller.abort();
    };
  }, [open, query, type, languageId, attempt]);

  return (
    <div className="tw-grid tw-min-w-0 tw-gap-3">
      <Button
        ref={toggleRef}
        type="button"
        variant="secondary"
        className="tw-justify-self-start"
        aria-expanded={open}
        aria-controls={id}
        onClick={() => setOpen(!open)}
      >
        <Search aria-hidden="true" />
        {__('Choose article or category', 'cci-blog')}
      </Button>
      {open && (
        <div id={id} className="tw-grid tw-min-w-0 tw-gap-3">
          <div className="tw-grid tw-gap-3 sm:tw-grid-cols-2">
            <Field label={__('Search blog content', 'cci-blog')}>
              <Input
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                placeholder={__('Title or slug', 'cci-blog')}
                autoFocus
              />
            </Field>
            <Field label={__('Content type', 'cci-blog')}>
              <Select
                value={type}
                onValueChange={setType}
                options={[
                  { value: 'all', label: __('Articles and categories', 'cci-blog') },
                  { value: 'post', label: __('Articles', 'cci-blog') },
                  { value: 'category', label: __('Categories', 'cci-blog') },
                ]}
              />
            </Field>
          </div>
          <p className="tw-m-0 tw-text-xs tw-leading-5 tw-text-cci-blog-muted">
            {__(
              'Published content in the selected language and shop. Up to 20 results.',
              'cci-blog',
            )}
          </p>
          {loading ? (
            <p role="status" className="tw-m-0 tw-text-sm">
              {__('Loading blog links...', 'cci-blog')}
            </p>
          ) : error ? (
            <div role="alert" className="tw-grid tw-gap-2">
              <p className="tw-m-0 tw-text-sm">{error}</p>
              <Button type="button" variant="secondary" onClick={() => setAttempt(attempt + 1)}>
                {__('Try again', 'cci-blog')}
              </Button>
            </div>
          ) : items.length ? (
            <ul
              className="tw-m-0 tw-grid tw-max-h-64 tw-list-none tw-gap-1 tw-overflow-y-auto tw-p-0"
              aria-label={__('Blog link results', 'cci-blog')}
            >
              {items.map((item) => (
                <li key={`${item.type}-${item.id}`} className="tw-min-w-0">
                  <Button
                    type="button"
                    variant="ghost"
                    className="!tw-grid !tw-h-auto tw-w-full !tw-justify-start !tw-justify-items-start tw-gap-1 tw-whitespace-normal tw-text-left"
                    onClick={() => {
                      onSelect(item);
                      setOpen(false);
                      toggleRef.current?.focus();
                    }}
                  >
                    <span className="tw-break-words">{item.title}</span>
                    <span className="tw-text-xs tw-font-normal tw-text-cci-blog-muted">
                      {item.type === 'post'
                        ? __('Article', 'cci-blog')
                        : __('Category', 'cci-blog')}
                    </span>
                    <span className="tw-break-all tw-text-xs tw-font-normal tw-text-cci-blog-muted">
                      {item.url}
                    </span>
                  </Button>
                </li>
              ))}
            </ul>
          ) : (
            <p role="status" className="tw-m-0 tw-text-sm">
              {__('No published content matches your search.', 'cci-blog')}
            </p>
          )}
        </div>
      )}
    </div>
  );
}
