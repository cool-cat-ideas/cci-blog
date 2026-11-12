import React from 'react';
import { Search } from 'lucide-react';
import { InfoCallout } from '@cci/admin-ui/blog';
import LoadingState from '../feedback/LoadingState';
import { apiFetch } from '../../api/api';
import { __ } from '../../i18n/i18n';
import { Button } from '../ui/button';
import { Field } from '../ui/field';
import { Input } from '../ui/input';
import { Select } from '../ui/select';

const searchBlogLinks = async ({ query, type, languageId, signal }) => {
  const response = await apiFetch('/catalog/links', {
    body: JSON.stringify({ query, type, id_lang: languageId }),
    signal,
  });
  return response.items || [];
};

/** One search-and-select view for internal links and article recommendations. */
export function BlogContentPicker({
  languageId,
  onSelect,
  inline = false,
  includeCategories = true,
  loadItems = searchBlogLinks,
  disabled = false,
  onLoadingChange,
  showStatusFilter = false,
  renderMetadata,
}) {
  const [open, setOpen] = React.useState(inline);
  const [query, setQuery] = React.useState('');
  const [type, setType] = React.useState('all');
  const [status, setStatus] = React.useState('published');
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
    onLoadingChange?.(true);
    setItems([]);
    setError('');
    const timer = window.setTimeout(() => {
      loadItems({ query, type, status, languageId, signal: controller.signal })
        .then((results) => {
          if (!controller.signal.aborted) setItems(results);
        })
        .catch((failure) => {
          if (!controller.signal.aborted)
            setError(failure.message || __('Could not load blog links.', 'cci-blog'));
        })
        .finally(() => {
          if (!controller.signal.aborted) {
            setLoading(false);
            onLoadingChange?.(false);
          }
        });
    }, 250);
    return () => {
      window.clearTimeout(timer);
      controller.abort();
    };
  }, [open, query, type, status, languageId, attempt, loadItems, onLoadingChange]);

  return (
    <div className="tw-grid tw-min-w-0 tw-gap-3">
      {!inline && (
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
      )}
      {open && (
        <div id={id} className="tw-grid tw-min-w-0 tw-gap-3">
          <div
            className={
              includeCategories || showStatusFilter
                ? 'tw-grid tw-gap-3 sm:tw-grid-cols-2'
                : 'tw-grid tw-gap-3'
            }
          >
            <Field
              label={
                includeCategories
                  ? __('Search blog content', 'cci-blog')
                  : __('Add a recommendation', 'cci-blog')
              }
            >
              <Input
                type="search"
                disabled={disabled}
                aria-label={
                  includeCategories
                    ? __('Search blog content', 'cci-blog')
                    : __('Search articles', 'cci-blog')
                }
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                placeholder={__('Title or slug', 'cci-blog')}
                autoFocus={!inline}
              />
            </Field>
            {showStatusFilter && (
              <Field label={__('Publication status', 'cci-blog')}>
                <Select
                  ariaLabel={__('Publication status', 'cci-blog')}
                  value={status}
                  onValueChange={setStatus}
                  disabled={disabled}
                  options={[
                    { value: 'published', label: __('Published', 'cci-blog') },
                    { value: 'draft', label: __('Draft', 'cci-blog') },
                    { value: 'scheduled', label: __('Scheduled', 'cci-blog') },
                    { value: 'all', label: __('All statuses', 'cci-blog') },
                  ]}
                />
              </Field>
            )}
            {includeCategories && (
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
            )}
          </div>
          {includeCategories && (
            <p className="tw-m-0 tw-text-xs tw-leading-5 tw-text-cci-blog-muted">
              {__(
                'Published content in the selected language and shop. Up to 20 results.',
                'cci-blog',
              )}
            </p>
          )}
          {loading ? (
            <LoadingState
              label={
                includeCategories
                  ? __('Loading blog links...', 'cci-blog')
                  : __('Loading articles...', 'cci-blog')
              }
            />
          ) : error ? (
            <InfoCallout tone="danger" role="alert">
              <p className="tw-m-0 tw-text-sm">{error}</p>
              <Button type="button" variant="secondary" onClick={() => setAttempt(attempt + 1)}>
                {__('Try again', 'cci-blog')}
              </Button>
            </InfoCallout>
          ) : disabled && !includeCategories ? (
            <InfoCallout tone="info">{__('Six recommendations selected.', 'cci-blog')}</InfoCallout>
          ) : items.length ? (
            <ul
              className="tw-m-0 tw-grid tw-max-h-64 tw-list-none tw-gap-1 tw-overflow-y-auto tw-p-0"
              aria-label={
                includeCategories
                  ? __('Blog link results', 'cci-blog')
                  : __('Article search results', 'cci-blog')
              }
            >
              {items.map((item) => (
                <li key={`${item.type}-${item.id}`} className="tw-min-w-0">
                  <Button
                    type="button"
                    disabled={disabled}
                    variant="ghost"
                    className="!tw-grid !tw-h-auto !tw-min-h-11 tw-w-full !tw-justify-start !tw-justify-items-start tw-gap-1 !tw-py-2 !tw-leading-5 tw-whitespace-normal tw-text-left"
                    onClick={() => {
                      onSelect(item);
                      if (!inline) {
                        setOpen(false);
                        toggleRef.current?.focus();
                      }
                    }}
                  >
                    <span className="tw-break-words">{item.title}</span>
                    {renderMetadata?.(item)}
                    {includeCategories && (
                      <span className="tw-text-xs tw-font-normal tw-text-cci-blog-muted">
                        {item.type === 'post'
                          ? __('Article', 'cci-blog')
                          : __('Category', 'cci-blog')}
                      </span>
                    )}
                    {item.url && (
                      <span className="tw-break-all tw-text-xs tw-font-normal tw-text-cci-blog-muted">
                        {item.url}
                      </span>
                    )}
                  </Button>
                </li>
              ))}
            </ul>
          ) : (
            <p role="status" className="tw-m-0 tw-text-sm">
              {includeCategories
                ? __('No published content matches your search.', 'cci-blog')
                : disabled
                  ? __('Six recommendations selected.', 'cci-blog')
                  : showStatusFilter
                    ? __('No articles match this search and publication status.', 'cci-blog')
                    : __('No articles match your search.', 'cci-blog')}
            </p>
          )}
        </div>
      )}
    </div>
  );
}

export default function InternalLinkPicker(props) {
  return <BlogContentPicker {...props} />;
}
