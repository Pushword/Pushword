import { execFileSync, spawnSync } from 'node:child_process'
import { cpSync, existsSync, mkdirSync, mkdtempSync, rmSync, symlinkSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { dirname, join } from 'node:path'

const repoDir = dirname(import.meta.dirname)
const packagesDir = join(repoDir, 'packages')
const projects = {
  admin: {
    sources: ['src/Resources/assets/**/*.js'],
    entries: ['src/Resources/assets', 'tests/Js', 'vitest.config.js'],
    siblings: ['core', 'newsletter'],
  },
  'admin-block-editor': {
    sources: ['src/assets/**/*.ts'],
    entries: ['src', 'tsconfig.json', 'vitest.config.mts'],
    siblings: ['admin', 'admin-monaco-editor', 'js-helper'],
    vitest: { configFile: 'vitest.config.mts' },
  },
  'admin-monaco-editor': {
    sources: ['markdown/**/*.js', 'MonacoHelper.js', 'index.js', 'mermaid.js'],
    entries: ['markdown', 'MonacoHelper.js', 'index.js', 'mermaid.js', 'build.test.js', 'vitest.config.js'],
    siblings: ['admin'],
  },
  'js-helper': {
    sources: ['src/**/*.js'],
    entries: ['src', 'vitest.config.mjs'],
    siblings: ['core', 'flat'],
    vitest: { configFile: 'vitest.config.mjs' },
  },
  newsletter: {
    sources: ['src/Resources/assets/**/*.js'],
    entries: ['src/Resources/assets'],
    command: 'node --test src/Resources/assets/*.test.js',
  },
  quiz: {
    sources: ['src/Resources/public/quiz.js'],
    entries: ['src/Resources/public', 'tests/Js', 'vitest.config.js'],
    // The tests evaluate the script after setting up the DOM.
    vitest: { configFile: 'vitest.config.js', related: false },
  },
  repurpose: {
    sources: ['src/Resources/motion/src/**/*.mjs', 'src/Resources/public/repurpose.js', 'examples/nord/*.mjs'],
    entries: ['src/Resources/motion', 'src/Resources/public/repurpose.js', 'src/Resources/font', 'examples/nord', 'tests/Motion'],
    command: 'node --test tests/Motion/*.test.mjs',
  },
}
const testFiles = ['**/*.test.*', '**/*.spec.*', '**/*.d.ts']

function git(...args) {
  return execFileSync('git', args, { cwd: repoDir, encoding: 'utf8' })
}

function baseCommit(args) {
  if (args.length !== 1) throw new Error('Pass one base commit.')
  return git('rev-parse', '--verify', '--end-of-options', `${args[0]}^{commit}`).trim()
}

function changedLines(name, commit) {
  const prefix = `packages/${name}/`
  const files = git(
    'diff', '--name-only', '-z', '--no-renames', '--diff-filter=AM', commit, 'HEAD', '--',
    ...projects[name].sources.map((pattern) => `:(glob)${prefix}${pattern}`),
    ...testFiles.map((pattern) => `:(exclude,glob)${prefix}${pattern}`),
  ).split('\0').filter(Boolean)

  return files.flatMap((file) => {
    const diff = git('diff', '--no-ext-diff', '--no-color', '--no-renames', '-U0', commit, 'HEAD', '--', file)
    return Array.from(diff.matchAll(/^@@ -\d+(?:,\d+)? \+(\d+)(?:,(\d+))? @@/gm))
      .filter(([, , count]) => count !== '0')
      .map(([, start, count = '1']) => {
        const end = Number(start) + Number(count) - 1
        return `${file.slice(prefix.length)}:${start}-${end}`
      })
  })
}

function config(project, packageDir) {
  const runner = project.command
    ? { testRunner: 'command', coverageAnalysis: 'off', commandRunner: { command: project.command } }
    : {
        testRunner: 'vitest',
        plugins: [import.meta.resolve('@stryker-mutator/vitest-runner')],
        vitest: project.vitest ?? { configFile: 'vitest.config.js' },
      }

  return {
    ...runner,
    mutate: [...project.sources, ...testFiles.map((pattern) => `!${pattern}`)],
    concurrency: 2,
    reporters: ['clear-text', 'progress', 'json'],
    clearTextReporter: { reportTests: false },
    jsonReporter: { fileName: join(packageDir, 'reports/mutation/mutation.json') },
  }
}

function runMutation(name, args) {
  const project = projects[name]
  const packageDir = join(packagesDir, name)
  // Mutate a copy and preserve the relative imports to sibling packages.
  const workspace = mkdtempSync(join(tmpdir(), 'pushword-stryker-'))
  try {
    const copy = join(workspace, name)
    mkdirSync(copy)
    for (const entry of project.entries) {
      const destination = join(copy, entry)
      mkdirSync(dirname(destination), { recursive: true })
      cpSync(join(packageDir, entry), destination, { recursive: true })
    }
    const manifest = join(packageDir, 'package.json')
    if (existsSync(manifest)) cpSync(manifest, join(copy, 'package.json'))
    const dependencies = join(packageDir, 'node_modules')
    if (existsSync(dependencies)) symlinkSync(dependencies, join(copy, 'node_modules'), 'dir')
    for (const sibling of project.siblings ?? []) {
      symlinkSync(join(packagesDir, sibling), join(workspace, sibling), 'dir')
    }
    writeFileSync(join(copy, 'stryker.config.json'), JSON.stringify(config(project, packageDir)))

    const cli = join(repoDir, 'node_modules/@stryker-mutator/core/bin/stryker.js')
    const result = spawnSync(process.execPath, [cli, 'run', '--inPlace', ...args], {
      cwd: copy,
      stdio: 'inherit',
    })
    if (result.error) throw result.error
    process.exitCode = result.status ?? 1
  } finally {
    rmSync(workspace, { recursive: true, force: true })
  }
}

const [name, ...args] = process.argv.slice(2)
if (name === '--list-changed') {
  const commit = baseCommit(args)
  console.log(JSON.stringify(Object.keys(projects).filter((key) => changedLines(key, commit).length > 0)))
} else if (!Object.hasOwn(projects, name)) {
  throw new Error(`Select a mutation test package (${Object.keys(projects).join(', ')}).`)
} else if (args[0] === '--diff') {
  const ranges = changedLines(name, baseCommit(args.slice(1)))
  if (ranges.length > 0) {
    runMutation(name, ['--mutate', ranges.join(',')])
  } else {
    console.log(`No added or modified JavaScript source lines to mutate in ${name}.`)
  }
} else {
  runMutation(name, args)
}
