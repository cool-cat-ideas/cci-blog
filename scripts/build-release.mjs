import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { copyFileSync, existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { minifyReleaseStyles } from './quality/minify-release.mjs';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const config = join(root, 'config.xml');
const platform = existsSync(config) ? 'prestashop' : 'wordpress';
const xml = platform === 'prestashop' ? readFileSync(config, 'utf8') : '';

const tag = (name) =>
  xml.match(new RegExp(`<${name}>\\s*(?:<!\\[CDATA\\[)?([^\\]<]+)`))?.[1].trim();

const slug = tag('name') || JSON.parse(readFileSync(join(root, 'package.json'), 'utf8')).name;
const mainFile = `${slug}.php`;
const version =
  tag('version') ||
  readFileSync(join(root, mainFile), 'utf8').match(/^\s*\*?\s*Version:\s*(\S+)/m)?.[1];
if (!version || !existsSync(join(root, mainFile))) throw new Error('Missing product identity.');

const output = process.env.CCI_RELEASE_DIR
  ? resolve(process.env.CCI_RELEASE_DIR)
  : join(root, 'release', 'packages', platform);
const stage = join(output, `.stage-${slug}`);
const runtime = join(stage, slug);
const archive = join(output, `${slug}-${version}.zip`);
const forbidden = [
  /^(?:scripts|docs|tests?|output|release|releases|dist|internal|node_modules|\.wordpress-org)(?:\/|$)/,
  /^(?:src\/admin-v2|src\/front|assets\/scss)(?:\/|$)/,
  /(?:^|\/)(?:\.[^/]+|node_modules|__pycache__|test-results|coverage)(?:\/|$)/,
  /^(?:AGENTS|PROJECT_GUIDE|README|CONTRIBUTING|MARKETPLACE-CHECKLIST)\.(?:md|markdown|rst)$/i,
  /^(?:package(?:-lock)?\.json|composer\.lock|pnpm-lock\.yaml|yarn\.lock)$/,
  /^(?:(?:webpack|tailwind|postcss|eslint|vite|vitest|playwright|jest)\.config\.|tsconfig|tmp-)/,
  /\.(?:map|log|pyc|tsbuildinfo|code-workspace)$/,
  /:Zone\.Identifier$/,
];
const files = execFileSync(
  'git',
  ['ls-files', '-z', '--cached', '--others', '--exclude-standard'],
  {
    cwd: root,
    encoding: 'utf8',
  },
)
  .split('\0')
  .filter(
    (file) =>
      file && existsSync(join(root, file)) && !forbidden.some((pattern) => pattern.test(file)),
  );

if (relative(output, stage) !== `.stage-${slug}`) throw new Error('Invalid staging directory.');
mkdirSync(output, { recursive: true });
rmSync(stage, { recursive: true, force: true });
try {
  for (const file of files) {
    const target = resolve(runtime, file);
    if (relative(runtime, target).startsWith('..'))
      throw new Error(`Invalid release path: ${file}`);
    mkdirSync(dirname(target), { recursive: true });
    copyFileSync(join(root, file), target);
  }
  minifyReleaseStyles(runtime);
  rmSync(archive, { force: true });
  execFileSync('python3', ['-m', 'zipfile', '-c', archive, slug], { cwd: stage });
  const checksum = createHash('sha256').update(readFileSync(archive)).digest('hex');
  writeFileSync(`${archive}.sha256`, `${checksum}  ${slug}-${version}.zip\n`);
  console.log(archive);
} finally {
  rmSync(stage, { recursive: true, force: true });
}
