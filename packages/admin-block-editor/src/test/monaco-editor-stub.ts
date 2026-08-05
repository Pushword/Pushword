// Test stub for 'monaco-editor' (aliased in vitest.config.ts): the real
// package cannot load under happy-dom. Tests provide their own editor fakes.
export const editor = {}

// MarkdownToolbar builds its keybinding table at import time. Nothing under test
// presses a key, so any number will do — the real values live in monaco-editor.
export const KeyMod = new Proxy({}, { get: () => 0 })
export const KeyCode = new Proxy({}, { get: () => 0 })

export class Range {
  constructor(
    public startLineNumber: number,
    public startColumn: number,
    public endLineNumber: number,
    public endColumn: number,
  ) {}
}
