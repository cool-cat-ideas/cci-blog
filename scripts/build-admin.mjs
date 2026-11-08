import { build } from 'esbuild';
import { readdir, unlink } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const chunks = resolve(root, 'views/js/admin-chunks');

// Keep BlockNote's language data lazy instead of inlining every locale into the
// initial admin script. Hashed chunks also retain native browser cache behavior.
const result = await build({
  absWorkingDir: root,
  entryPoints: { 'cci-blog-admin': 'src/admin-v2/index.jsx' },
  outdir: 'views/js',
  chunkNames: 'admin-chunks/[name]-[hash]',
  bundle: true,
  splitting: true,
  format: 'esm',
  target: 'es2020',
  preserveSymlinks: true,
  minify: true,
  // Emit embedded CSS with escaped line breaks instead of whitespace-only lines.
  supported: { 'template-literal': false },
  metafile: true,
  alias: {
    react: resolve(root, 'node_modules/react'),
    'react-dom': resolve(root, 'node_modules/react-dom'),
  },
});

// Remove only obsolete files owned by this build, after the new build succeeds.
const outputs = new Set(Object.keys(result.metafile.outputs).map((name) => resolve(root, name)));
for (const name of await readdir(chunks)) {
  const path = resolve(chunks, name);
  if (/^[\w-]+-[A-Z0-9]{8}\.js$/.test(name) && !outputs.has(path)) {
    await unlink(path);
  }
}
console.log(`Admin JavaScript built with ${outputs.size - 1} shared and lazy chunks.`);
