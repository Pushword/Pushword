import assert from 'node:assert/strict'
import { spawnSync } from 'node:child_process'
import { fileURLToPath } from 'node:url'
import test from 'node:test'

const policy = fileURLToPath(new URL('./js-audit.jq', import.meta.url))
const summary = { type: 'auditSummary', data: {} }
const advisory = {
  type: 'auditAdvisory',
  data: {
    advisory: {
      module_name: 'braces',
      severity: 'high',
      url: 'https://github.com/advisories/GHSA-vfj7-8cjw-p6xm',
    },
    resolution: { path: 'vite-plugin-static-copy>chokidar>braces' },
  },
}

const picomatchAdvisory = {
  type: 'auditAdvisory',
  data: {
    advisory: {
      module_name: 'picomatch',
      severity: 'high',
      url: 'https://github.com/advisories/GHSA-fc5q-39xc-vjpf',
    },
    resolution: { path: 'vite>picomatch' },
  },
}

function evaluate(events) {
  const result = spawnSync(
    'jq',
    ['--slurp', '--exit-status', '--from-file', policy],
    {
      input: events.map((event) => JSON.stringify(event)).join('\n'),
      encoding: 'utf8',
    },
  )
  assert.equal(result.error, undefined)
  return result.status
}

test('accepts a clean report', () => {
  assert.equal(evaluate([summary]), 0)
})

for (const path of [
  'vite-plugin-static-copy>chokidar>braces',
  '@pushword/js-helper>vite-plugin-static-copy>chokidar>braces',
  'vite-plugin-symfony>fast-glob>micromatch>braces',
  '@pushword/js-helper>vite-plugin-symfony>fast-glob>micromatch>braces',
]) {
  test(`accepts the build-only advisory through ${path}`, () => {
    assert.equal(
      evaluate([
        { ...advisory, data: { ...advisory.data, resolution: { path } } },
        summary,
      ]),
      0,
    )
  })
}

for (const path of [
  '@pushword/js-helper>vite-plugin-compression2>@rollup/pluginutils>picomatch',
  '@pushword/js-helper>vite-plugin-static-copy>chokidar>anymatch>picomatch',
  '@pushword/js-helper>vite-plugin-static-copy>chokidar>readdirp>picomatch',
  '@pushword/js-helper>vite-plugin-symfony>fast-glob>micromatch>picomatch',
  '@pushword/js-helper>vite>picomatch',
  '@pushword/js-helper>vite>tinyglobby>picomatch',
  '@typescript-eslint/eslint-plugin>@typescript-eslint/type-utils>@typescript-eslint/typescript-estree>tinyglobby>picomatch',
  'sass-embedded>sass-embedded-all-unknown>sass>@parcel/watcher>picomatch',
  'typescript-eslint>@typescript-eslint/eslint-plugin>@typescript-eslint/type-utils>@typescript-eslint/typescript-estree>tinyglobby>picomatch',
  'typescript-eslint>@typescript-eslint/eslint-plugin>@typescript-eslint/type-utils>@typescript-eslint/utils>@typescript-eslint/typescript-estree>tinyglobby>picomatch',
  'vite-plugin-static-copy>chokidar>anymatch>picomatch',
  'vite-plugin-static-copy>chokidar>readdirp>picomatch',
  'vite-plugin-symfony>fast-glob>micromatch>picomatch',
  'vite>picomatch',
  'vite>tinyglobby>picomatch',
  'vitest>picomatch',
  'vitest>tinyglobby>picomatch',
]) {
  test(`accepts the reviewed picomatch advisory through ${path}`, () => {
    assert.equal(
      evaluate([
        {
          ...picomatchAdvisory,
          data: { ...picomatchAdvisory.data, resolution: { path } },
        },
        summary,
      ]),
      0,
    )
  })
}

for (const change of [
  { url: 'https://github.com/advisories/GHSA-grv7-fg5c-xmjg' },
  { module_name: 'another-package' },
  { severity: 'critical' },
]) {
  test(`blocks an advisory with ${JSON.stringify(change)}`, () => {
    const finding = {
      ...advisory,
      data: {
        ...advisory.data,
        advisory: { ...advisory.data.advisory, ...change },
      },
    }
    assert.equal(evaluate([finding, summary]), 1)
  })
}

test('blocks the same advisory through a runtime dependency', () => {
  const finding = {
    ...advisory,
    data: { ...advisory.data, resolution: { path: 'runtime>braces' } },
  }
  assert.equal(evaluate([finding, summary]), 1)
})

test('an accepted advisory cannot hide another high-severity finding', () => {
  const finding = {
    ...advisory,
    data: {
      ...advisory.data,
      advisory: {
        ...advisory.data.advisory,
        url: 'https://example.test/advisory',
      },
    },
  }
  assert.equal(evaluate([finding, advisory, summary]), 1)
})

for (const change of [
  { url: 'https://github.com/advisories/GHSA-unreviewed-advisory' },
  { module_name: 'another-package' },
  { severity: 'critical' },
]) {
  test(`blocks picomatch with ${JSON.stringify(change)}`, () => {
    const finding = {
      ...picomatchAdvisory,
      data: {
        ...picomatchAdvisory.data,
        advisory: { ...picomatchAdvisory.data.advisory, ...change },
      },
    }
    assert.equal(evaluate([finding, summary]), 1)
  })
}

for (const path of [
  'runtime>picomatch',
  'unreviewed-build-plugin>picomatch',
  'unreviewed>vite>picomatch',
  'vite>picomatch>another-package',
]) {
  test(`blocks picomatch through an unreviewed path: ${path}`, () => {
    const finding = {
      ...picomatchAdvisory,
      data: { ...picomatchAdvisory.data, resolution: { path } },
    }
    assert.equal(evaluate([finding, summary]), 1)
  })
}

test('an accepted picomatch finding cannot hide another advisory or critical finding', () => {
  for (const change of [
    { url: 'https://example.test/advisory' },
    { severity: 'critical' },
  ]) {
    const finding = {
      ...picomatchAdvisory,
      data: {
        ...picomatchAdvisory.data,
        advisory: { ...picomatchAdvisory.data.advisory, ...change },
      },
    }
    assert.equal(evaluate([advisory, picomatchAdvisory, finding, summary]), 1)
  }
})

test('accepted picomatch findings still require a complete report', () => {
  assert.equal(evaluate([picomatchAdvisory]), 1)
})

test('preserves the existing non-blocking policy for low and moderate findings', () => {
  const findings = ['low', 'moderate'].map((severity) => ({
    ...advisory,
    data: {
      ...advisory.data,
      advisory: { ...advisory.data.advisory, severity },
    },
  }))
  assert.equal(evaluate([...findings, summary]), 0)
})

test('rejects an incomplete or failed audit', () => {
  assert.equal(evaluate([]), 1)
  assert.equal(evaluate([advisory]), 1)
  assert.equal(evaluate([{ type: 'error', data: 'registry unavailable' }]), 1)
})

test('rejects invalid JSON', () => {
  assert.ok(
    spawnSync('jq', ['--slurp', '--exit-status', '--from-file', policy], {
      input: '{',
    }).status > 0,
  )
})
