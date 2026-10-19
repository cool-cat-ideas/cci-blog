import { execFileSync, spawnSync } from 'node:child_process';
import { readFileSync, writeFileSync, existsSync, mkdtempSync, rmSync } from 'node:fs';
import { dirname, join, extname, resolve } from 'node:path';
import { tmpdir } from 'node:os';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const check = process.argv.includes('--check');
const toolsRoot = process.env.CCI_QUALITY_TOOLS_DIR || root;
const requireTools = createRequire(join(toolsRoot, 'package.json'));
const prettier = requireTools('prettier');
const phpTools = process.env.CCI_PHP_QUALITY_TOOLS_DIR || join(root, 'scripts/quality');
const policy = JSON.parse(readFileSync(join(root, 'scripts/quality/files.json'), 'utf8'));
const exclusions = policy.exclude.map((pattern) => new RegExp(pattern));
const listed = execFileSync(
  'git',
  ['ls-files', '-z', '--cached', '--others', '--exclude-standard'],
  { cwd: root, encoding: 'utf8' },
);
const files = [...new Set(listed.split('\0'))].filter(
  (file) =>
    file && existsSync(join(root, file)) && !exclusions.some((pattern) => pattern.test(file)),
);
const wp = policy.wordpress.map((pattern) => new RegExp(pattern));
const php = files.filter((file) => file.endsWith('.php'));
const wpFiles = php.filter((file) => wp.some((pattern) => pattern.test(file)));
const prestaFiles = php.filter((file) => !wpFiles.includes(file));
const temporary = mkdtempSync(join(tmpdir(), 'cci-code-style-'));
let failed = false;

function run(binary, args, env = process.env) {
  const result = spawnSync(binary, args, { cwd: root, env, stdio: 'inherit' });
  if (result.error) throw result.error;
  return result.status;
}

try {
  if (prestaFiles.length) {
    const list = join(temporary, 'php-files.json');
    writeFileSync(list, JSON.stringify(prestaFiles.map((file) => join(root, file))));
    const status = run(
      'php',
      [
        join(phpTools, 'vendor/bin/php-cs-fixer'),
        'fix',
        '--config=' + join(root, 'scripts/quality/php-cs-fixer.php'),
        '--no-interaction',
        ...(check ? ['--dry-run', '--diff'] : []),
      ],
      { ...process.env, CCI_FORMAT_PHP_FILE_LIST: list, CCI_PHP_QUALITY_TOOLS_DIR: phpTools },
    );
    if (status !== 0) failed = true;
  }
  if (wpFiles.length) {
    const list = join(temporary, 'wordpress-files.txt');
    writeFileSync(list, wpFiles.join('\n') + '\n');
    const status = run('php', [
      join(phpTools, check ? 'vendor/bin/phpcs' : 'vendor/bin/phpcbf'),
      '--standard=' + join(root, 'scripts/quality/wordpress.xml'),
      '--file-list=' + list,
      '--extensions=php',
      '--parallel=4',
      '--report=summary',
    ]);
    if (check ? status !== 0 : ![0, 1].includes(status)) failed = true;
  }
  const front = files.filter(
    (file) =>
      /\.(?:[cm]?js|jsx|ts|tsx|css|scss|json|ya?ml)$/.test(file) &&
      !/(?:^|\/)(?:package-lock|composer\.lock|pnpm-lock|yarn\.lock)/.test(file),
  );
  let changed = 0;
  for (const file of front) {
    const filename = join(root, file);
    const source = readFileSync(filename, 'utf8');
    const info = await prettier.getFileInfo(filename, {
      ignorePath: join(root, '.prettierignore'),
    });
    if (info.ignored || !info.inferredParser) continue;
    const options = { ...(await prettier.resolveConfig(filename)), filepath: filename };
    let prepared = source;
    if (/\.(?:[cm]?js|jsx)$/.test(file)) {
      const babel = requireTools('prettier/plugins/babel');
      const ast = await babel.parsers.babel.parse(source, options);
      const body = ast.program?.body || ast.body || [];
      const functionNode = (node) =>
        /(?:Function|Class)Declaration$/.test(node.type) ||
        (node.type === 'ExportDefaultDeclaration' &&
          /(?:Function|Class)/.test(node.declaration?.type || '')) ||
        (node.type === 'VariableDeclaration' &&
          node.declarations.some((item) =>
            /(?:Arrow)?FunctionExpression$/.test(item.init?.type || ''),
          ));
      const insertions = [];
      for (let index = 1; index < body.length; index++) {
        const previous = body[index - 1],
          current = body[index];
        const gap = source.slice(previous.end, current.start);
        const separate =
          functionNode(previous) ||
          functionNode(current) ||
          (previous.type === 'ImportDeclaration' && current.type !== 'ImportDeclaration');
        if (separate && /^\s*$/.test(gap) && !/\n\s*\n/.test(gap)) insertions.push(current.start);
      }
      for (const offset of insertions.reverse())
        prepared = prepared.slice(0, offset) + '\n' + prepared.slice(offset);
    }
    const formatted = await prettier.format(prepared, options);
    if (source !== formatted) {
      changed++;
      if (check) console.error('Style differs: ' + file);
      else writeFileSync(filename, formatted);
    }
  }
  if (check && changed) failed = true;
  console.log(
    `${check ? 'Checked' : 'Formatted'} ${php.length} PHP files and ${front.length} frontend/config files; ${changed} frontend/config files ${check ? 'need changes' : 'updated'}.`,
  );
} finally {
  rmSync(temporary, { recursive: true, force: true });
}
process.exitCode = failed ? 1 : 0;
