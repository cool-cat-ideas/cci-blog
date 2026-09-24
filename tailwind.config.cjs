const { createTailwindConfig } = require('@cci/admin-theme');

/** @type {import('tailwindcss').Config} */
module.exports = createTailwindConfig({
    namespace: 'cci-blog',
    safelistMode: 'shared-ui',
    content: [
        './src/admin-v2/**/*.{js,jsx}',
    ],
    safelist: ['tw-hidden'],
});
