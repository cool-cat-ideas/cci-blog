# Build from source

Use Node.js 23.7 or later and npm 11.7 or later. From this repository:

```sh
npm ci
npm run build:admin
```

The lockfile pins public CCI Admin UI release archives and integrity hashes.
The source and build tools for those packages are maintained at
[CCI Admin UI on GitHub](https://github.com/cool-cat-ideas/cci-admin-ui). The matching source is tagged [v1.0.0](https://github.com/cool-cat-ideas/cci-admin-ui/tree/v1.0.0).
A private framework checkout and Pro sources are not required to build this product.

For a shared component change, contribute to the UI repository. Build its candidate
release, then use its `scripts/prepare-product.mjs /absolute/path/to/this/product`
command to install the candidate and update the product lockfile. Run this product's
normal build afterwards. Do not edit installed files under `node_modules`.

The initial public UI release must be published before external contributors can
run `npm ci`. Publish the matching UI source tag and archives before this product.
These commands build assets only; they do not publish or create an installable ZIP.
