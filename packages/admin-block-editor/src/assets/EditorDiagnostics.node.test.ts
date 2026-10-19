// @vitest-environment node
import { expect, it } from 'vitest'

it('keeps shared tool diagnostics importable by the Node converter without a browser DOM', async () => {
  expect(typeof document).toBe('undefined')
  const { reportEditorError } = await import('./EditorDiagnostics')
  expect(() => reportEditorError(null, 'tool', new Error('CLI failure'))).not.toThrow()
})
