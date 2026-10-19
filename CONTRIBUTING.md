# Contributing

## Code style and source layout

Install development dependencies with `npm install` and `composer install --working-dir=scripts/quality`. Run `npm run format` before committing and `npm run format:check` to verify the same rules without changing files.

PrestaShop and shared PHP follow the PrestaShop PHP-CS-Fixer preset. WordPress adapter PHP uses WordPress spacing rules. JavaScript, React, CSS and configuration files use Prettier. Use UTF-8, LF, a final newline and separate functions and import groups with blank lines.

Keep the platform entry point and hooks in the platform adapter. Put domain behavior in named classes, optional integrations in their own directory, and views and browser assets beside the matching platform. Shared products use `src/` as their source and generate matching platform runtime files during the build. Edit source files, then rebuild; generated bundles and copied licensing libraries are excluded from formatting.

Development tools, tests, documentation, build outputs and local editor files must not enter installable packages. Keep source CSS readable; build distribution assets with comments removed and minification enabled.
