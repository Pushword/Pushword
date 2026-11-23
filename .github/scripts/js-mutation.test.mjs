import assert from 'node:assert/strict'
import { execFileSync } from 'node:child_process'
import { copyFileSync, mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { dirname, join } from 'node:path'
import { test } from 'node:test'

function fixture(t) {
  const cwd = mkdtempSync(join(tmpdir(), 'pushword-stryker-diff-'))
  t.after(() => rmSync(cwd, { recursive: true, force: true }))
  const git = (...args) => execFileSync('git', args, { cwd, encoding: 'utf8' }).trim()
  git('init', '-q')
  mkdirSync(join(cwd, '.scripts'))
  const script = '.scripts/test-js-mutation.mjs'
  copyFileSync(new URL(`../../${script}`, import.meta.url), join(cwd, script))

  function write(files) {
    for (const [path, contents] of Object.entries(files)) {
      const file = join(cwd, path)
      mkdirSync(dirname(file), { recursive: true })
      writeFileSync(file, contents)
    }
  }

  function run(...args) {
    return execFileSync(process.execPath, [script, ...args], { cwd, encoding: 'utf8' }).trim()
  }

  return {
    write,
    run,
    commit(files) {
      write(files)
      const paths = Object.keys(files)
      git('add', '--', ...paths)
      git('-c', 'user.name=Fixture', '-c', 'user.email=fixture@example.invalid',
        '-c', 'core.hooksPath=/dev/null', 'commit', '-qm', 'Update fixture', '--only', '--', ...paths)
      return git('rev-parse', 'HEAD')
    },
    changedPackages(base) {
      return JSON.parse(run('--list-changed', base))
    },
  }
}

test('selects packages with changed source lines', (t) => {
  const repo = fixture(t)
  const base = repo.commit({ 'README.md': 'Fixture\n' })
  repo.commit({
    'packages/admin/src/Resources/assets/example.js': 'export const value = 1\n',
    'packages/admin-block-editor/src/assets/example.ts': 'export const value = 1\n',
    'packages/admin-monaco-editor/markdown/example.js': 'export const value = 1\n',
    'packages/js-helper/src/example.js': 'export const value = 1\n',
    'packages/newsletter/src/Resources/assets/example.js': 'export const value = 1\n',
    'packages/quiz/src/Resources/public/quiz.js': 'export const value = 1\n',
    'packages/repurpose/tests/Motion/example.test.mjs': 'export const value = 1\n',
  })
  assert.deepEqual(repo.changedPackages(base), [
    'admin', 'admin-block-editor', 'admin-monaco-editor', 'js-helper', 'newsletter', 'quiz',
  ])

  repo.commit({ 'packages/repurpose/src/Resources/public/repurpose.js': 'export const value = 1\n' })
  assert.ok(repo.changedPackages(base).includes('repurpose'))
})

test('ignores test files, declarations, generated bundles and deletion-only hunks', (t) => {
  const repo = fixture(t)
  const source = 'packages/admin-block-editor/src/assets/example.ts'
  const base = repo.commit({ [source]: 'export const obsolete = 0\nexport const value = 1\n' })
  repo.commit({
    [source]: 'export const value = 1\n',
    'packages/admin-block-editor/src/assets/example.test.ts': 'export const value = 1\n',
    'packages/admin-block-editor/src/assets/example.d.ts': 'export declare const value: number\n',
    'packages/admin/src/Resources/public/admin.js': 'export const value = 1\n',
  })
  assert.deepEqual(repo.changedPackages(base), [])
})

test('runs only the Studio suite when every mutation targets Studio', (t) => {
  const repo = fixture(t)
  const studio = 'src/Resources/public/repurpose.js'
  const motion = 'src/Resources/motion/src/example.mjs'
  repo.write({
    'node_modules/@stryker-mutator/core/bin/stryker.js':
      "console.log(JSON.parse(require('node:fs').readFileSync('stryker.config.json')).commandRunner.command)",
  })
  const base = repo.commit({
    'packages/repurpose/examples/nord/example.mjs': '',
    'packages/repurpose/src/Resources/font/example.txt': '',
    'packages/repurpose/tests/Motion/studio.test.mjs': '',
    [`packages/repurpose/${motion}`]: 'export const value = 1\n',
    [`packages/repurpose/${studio}`]: 'export const value = 1\n',
  })
  repo.commit({ [`packages/repurpose/${studio}`]: 'export const value = 2\n' })

  const studioTests = 'node --test tests/Motion/studio.test.mjs'
  const allMotionTests = 'node --test tests/Motion/*.test.mjs'
  assert.equal(repo.run('repurpose', '--diff', base), studioTests)
  assert.equal(repo.run('repurpose', '--mutate', studio), studioTests)
  assert.equal(repo.run('repurpose', '--mutate', `${studio}:1-1,${motion}:1-1`), allMotionTests)
  assert.equal(repo.run('repurpose', '--mutate', studio, '-m', motion), allMotionTests)
  assert.equal(repo.run('repurpose'), allMotionTests)
})
