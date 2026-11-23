import { execFileSync, spawnSync } from 'node:child_process'
import { cpSync, mkdirSync, mkdtempSync, rmSync, symlinkSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { dirname, join } from 'node:path'

const packageDir = dirname(import.meta.dirname)
const packagesDir = dirname(packageDir)
const repoDir = dirname(packagesDir)
const sourcePrefix = 'packages/admin-block-editor/'

function git(...args) {
  return execFileSync('git', args, { cwd: repoDir, encoding: 'utf8' })
}

function changedLines(base) {
  const commit = git('rev-parse', '--verify', '--end-of-options', `${base}^{commit}`).trim()
  const files = git(
    'diff', '--name-only', '-z', '--no-renames', '--diff-filter=AM', commit, 'HEAD', '--',
    `:(glob)${sourcePrefix}src/assets/**/*.ts`,
    `:(exclude,glob)${sourcePrefix}src/assets/**/*.test.ts`,
    `:(exclude,glob)${sourcePrefix}src/assets/**/*.d.ts`,
  ).split('\0').filter(Boolean)
  return files.flatMap((file) => {
    const diff = git('diff', '--no-ext-diff', '--no-color', '--no-renames', '-U0', commit, 'HEAD', '--', file)
    return Array.from(diff.matchAll(/^@@ -\d+(?:,\d+)? \+(\d+)(?:,(\d+))? @@/gm))
      .filter(([, , count]) => count !== '0')
      .map(([, start, count = '1']) => `${file.slice(sourcePrefix.length)}:${start}-${Number(start) + Number(count) - 1}`)
  })
}

let args = process.argv.slice(2)
if (args[0] === '--diff') {
  if (args.length !== 2) {
    throw new Error('Pass one base commit after --diff.')
  }
  const ranges = changedLines(args[1])
  if (ranges.length === 0) {
    console.log('No added or modified TypeScript source lines to mutate.')
    process.exit(0)
  }
  args = ['--mutate', ranges.join(',')]
}

// Preserve relative imports into sibling packages while mutating only a copy of the editor.
const workspace = mkdtempSync(join(tmpdir(), 'pushword-stryker-'))
try {
  const editorCopy = join(workspace, 'admin-block-editor')
  mkdirSync(editorCopy)
  for (const entry of ['src', 'package.json', 'tsconfig.json', 'vitest.config.mts', 'stryker.config.mjs']) {
    cpSync(join(packageDir, entry), join(editorCopy, entry), { recursive: true })
  }
  for (const sibling of ['admin', 'admin-monaco-editor', 'js-helper']) {
    symlinkSync(join(packagesDir, sibling), join(workspace, sibling), 'dir')
  }
  symlinkSync(join(packageDir, 'node_modules'), join(editorCopy, 'node_modules'), 'dir')

  const cli = join(packageDir, 'node_modules/@stryker-mutator/core/bin/stryker.js')
  const result = spawnSync(process.execPath, [cli, 'run', '--inPlace', ...args], {
    cwd: editorCopy,
    env: { ...process.env, STRYKER_REPORT_PATH: join(packageDir, 'reports/mutation/mutation.json') },
    stdio: 'inherit',
  })
  if (result.error) throw result.error
  process.exitCode = result.status ?? 1
} finally {
  rmSync(workspace, { recursive: true, force: true })
}
