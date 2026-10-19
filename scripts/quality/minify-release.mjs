import { createRequire } from 'node:module';
import { readFileSync, readdirSync, statSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

const require = createRequire(import.meta.url);
const { transformSync } = require(process.env.CCI_ESBUILD_MODULE || 'esbuild');

export function minifyReleaseStyles(directory) {
  for (const entry of readdirSync(directory)) {
    const file = join(directory, entry);
    if (statSync(file).isDirectory()) {
      minifyReleaseStyles(file);
      continue;
    }
    if (!file.endsWith('.css')) continue;

    const source = readFileSync(file, 'utf8');
    const result = transformSync(source, {
      loader: 'css',
      minify: true,
      legalComments: 'external',
      charset: 'utf8',
    });
    writeFileSync(file, result.code);
    if (result.legalComments) {
      writeFileSync(`${file}.LEGAL.txt`, result.legalComments);
    }
  }
}
