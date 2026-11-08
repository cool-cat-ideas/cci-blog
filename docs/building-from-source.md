# Build from source

Use Node.js 23.7 or later and npm 11.7 or later. From this repository:

```sh
npm ci
npm run build:admin
```

The lockfile pins public CCI Admin UI release archives and integrity hashes.
The source and build tools for those packages are maintained at
[CCI Admin UI on GitHub](https://github.com/cool-cat-ideas/cci-admin-ui). The matching source is tagged [v1.0.1](https://github.com/cool-cat-ideas/cci-admin-ui/tree/v1.0.1).
A private framework checkout and Pro sources are not required to build this product.

For a shared component change, contribute to the UI repository. Build its candidate
release, then use its `scripts/prepare-product.mjs /absolute/path/to/this/product`
command to install the candidate and update the product lockfile. Run this product's
normal build afterwards. Do not edit installed files under `node_modules`.

The UI release archives referenced by the lockfile must be accessible to run `npm ci`.
These commands build assets only; they do not publish or create an installable ZIP.

The admin JavaScript entry is `views/js/cci-blog-admin.js`. Shared code and lazy
BlockNote language data are emitted to `views/js/admin-chunks/` with content hashes.
Deploy the entry and its chunks together; the normal release builder includes both.
The browser loads language data when needed. A successful build removes obsolete
generated chunks without changing theme assets or other module scripts.
