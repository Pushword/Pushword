// Test stub for 'monaco-editor' (aliased in vitest.config.ts): the real
// package cannot load under happy-dom. Tests provide their own editor fakes.
export const editor = {}

export class Range {
  constructor(
    public startLineNumber: number,
    public startColumn: number,
    public endLineNumber: number,
    public endColumn: number,
  ) {}
}
