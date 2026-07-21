import js from '@eslint/js'
import tseslint from 'typescript-eslint'
import globals from 'globals'

// Common ignore patterns
const ignorePatterns = [
  '**/dist/',
  '**/node_modules/',
  '**/vendor/',
  '**/var/',
  '**/Resources/assets/',
  '**/public/',
  '**/media/',
  '**/media~/',
  '**/*.min.js',
  '**/build/',
  'packages/dev-app/var/',
  'packages/dev-app/public/',
  'packages/admin-block-editor/src/Command/convert-json-to-markdown-built/', // Generated build files
]

// Common globals
const commonGlobals = {
  ...globals.browser,
  ...globals.node,
  ...globals.es2021,
}

export default tseslint.config(
  js.configs.recommended,
  ...tseslint.configs.recommended,

  { ignores: ignorePatterns },

  // TypeScript files
  {
    files: ['**/*.ts', '**/*.tsx'],
    languageOptions: {
      parserOptions: {
        ecmaVersion: 'latest',
        sourceType: 'module',
        project: './tsconfig.json',
        tsconfigRootDir: import.meta.dirname,
      },
      globals: commonGlobals,
    },
    rules: {
      // Allow intentionally unused identifiers prefixed with `_` (e.g. params
      // kept to satisfy a shared tool signature, or unused catch bindings).
      '@typescript-eslint/no-unused-vars': [
        'error',
        {
          argsIgnorePattern: '^_',
          varsIgnorePattern: '^_',
          caughtErrorsIgnorePattern: '^_',
        },
      ],
    },
  },

  // admin-block-editor wraps Editor.js, whose plugins ship without types: `any`
  // and `@ts-ignore` are unavoidable at those boundaries. Keep them visible as
  // warnings (so new ones are noticed) without failing the build, while every
  // structural rule stays an error.
  {
    files: ['packages/admin-block-editor/**/*.ts'],
    rules: {
      '@typescript-eslint/no-explicit-any': 'warn',
      '@typescript-eslint/ban-ts-comment': 'warn',
    },
  },

  // JavaScript files
  {
    files: ['**/*.js', '**/*.mjs', '**/*.cjs'],
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      globals: { ...commonGlobals, ...globals.jquery },
    },
  },

  // Config files
  {
    files: ['**/webpack.config.js', '**/vite.config.js', '**/*.config.js'],
    rules: { 'no-console': 'off' },
  },
)
