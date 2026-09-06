const { createTailwindConfig } = require('../../cci_admin_framework/packages/admin-theme/src/index.cjs');

/** @type {import('tailwindcss').Config} */
module.exports = createTailwindConfig({
    namespace: 'cci-blog',
    content: [
        './src/admin-v2/**/*.{js,jsx}',
        '../../cci_admin_framework/packages/admin-ui/src/**/*.{js,jsx}',
    ],
    safelist: ['tw-hidden'],
});
