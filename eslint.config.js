import js from '@eslint/js';
import tseslint from 'typescript-eslint';
import globals from 'globals';

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
  'packages/skeleton/var/',
  'packages/skeleton/public/',
];

// Common globals
const commonGlobals = {
  ...globals.browser,
  ...globals.node,
  ...globals.es2021,
};

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
);
