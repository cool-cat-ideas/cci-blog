# CCI Blog Pro Local Image Library

The local image library lets an authenticated back-office employee upload an
optimized blog image or select an image already stored by PrestaShop. It is
available next to post cover, Open Graph, category image and linked-image block
URL fields. Selecting an item writes its public, same-origin path into the
normal URL field, so existing rendering and export formats remain portable.

## Use it

1. Install CCI Blog and CCI Blog Pro 1.0.0.
2. Activate a license for the current shop. The license must grant
   `local_media_library`.
3. Edit a post or category and select **Choose local image**.
4. Keep **CCI Blog images** selected and use **Upload image**, or switch to the
   read-only **Store images** source to browse existing files.
5. Search by file name. Images are loaded asynchronously in pages; select
   **Load more** instead of loading the entire store library at once.
6. Select a preview and confirm **Use image**, then save the post or category.

The URL field remains editable for legitimate external HTTP(S) assets.

## Upload and storage contract

The managed blog library accepts JPEG, PNG and WebP files up to 12 MB. It checks
the upload status, MIME with `fileinfo`, raster metadata and a 40 megapixel
limit. Every accepted file is decoded and re-encoded by PrestaShop's image
manager into randomized files in `img/cci_blog`: thumbnail (320 px), medium
(768 px), large (1600 px) and full (2400 px maximum). The original client file
is not copied verbatim. Metadata, employee ID, shop ID and variant filenames
are stored in `cci_blog_media`.

The general Store images source stays read-only. It scans only allowlisted
PrestaShop image roots and accepts validated GIF, JPEG, PNG and WebP raster
files for selection. SVG is excluded because it can contain executable or
remote content. The managed library also excludes SVG and does not expose a
generic filesystem upload target.

Requests require an authenticated back-office context and a valid Pro feature
grant. The server resolves real paths, rejects symlinks and paths outside the
allowlist, validates MIME/type with `getimagesize()`, caps file size and limits
search scans. Normal browsing reads only the selected directory and returns a
bounded page. The browser accepts only same-origin catalog URLs.
The endpoints expose no filesystem path. `media:upload` accepts only the
multipart field `image`; it does not accept a directory, filename destination
or client-provided filesystem root. Managed variants are used automatically as
`srcset` for article covers, cards and image content blocks when Pro is active.
There is no rename, replace or delete action in version 1.0.0.

The module does not grant rights to an image. Merchants remain responsible for
copyright, licenses, model releases and other usage rights for selected media.

## Public integration contract

The Free admin shell sends `media:list` or `media:upload` to the active Pro module. Pro handles listing
through `Cci_Blog_Pro::handleCciLocalMediaLibraryAction(int $shopId, array
$payload): array`; `$payload` accepts `query`, `source`, `directory`, `page` and
`perPage`. The server clamps paging and never accepts a filesystem root.
The method returns a JSON-ready catalog containing `items`, `sources` and
`limits`, or an error when the feature is not licensed. Item HTML is rendered
by the owned admin runtime; the endpoint returns metadata and public URLs, not
HTML.

Uploads are delegated to
`Cci_Blog_Pro::handleCciLocalMediaUploadAction(int $shopId, int $employeeId,
array $file): array`. The Pro manager returns the selected public large variant,
thumbnail URL, original/full URL, `srcset` and structured variant metadata.
