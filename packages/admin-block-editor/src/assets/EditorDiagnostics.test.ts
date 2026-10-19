import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import type EditorJS from '@editorjs/editorjs'
import { EditorDiagnostics, reportEditorError } from './EditorDiagnostics'

let diagnostics: EditorDiagnostics
let holder: HTMLElement

beforeEach(() => {
  document.body.innerHTML = `<form>
    <div data-pw-diagnostics-for="editor">
      <p data-pw-editor-error hidden role="alert"></p>
      <input type="checkbox" data-pw-writing-suggestions>
      <button type="button" data-pw-download-diagnostic>Download</button>
    </div>
    <div id="editor"><div class="ce-block" data-id="block"><p contenteditable="true">Confidential page text</p></div></div>
  </form>`
  holder = document.getElementById('editor')!
  const editor = {
    blocks: { getBlocksCount: () => 1, getById: () => ({ name: 'paragraph' }) },
  } as unknown as EditorJS
  diagnostics = new EditorDiagnostics(holder, editor, {
    version: '1.2.3',
    editorjsVersion: '2.33.0',
    labels: { failure: 'Retry or download a diagnostic.' },
  })
})

afterEach(() => {
  diagnostics.destroy()
  vi.restoreAllMocks()
})

it('records useful action metadata while omitting input, clipboard and exception text', () => {
  const field = holder.querySelector('p')!
  field.dispatchEvent(
    new InputEvent('beforeinput', {
      bubbles: true,
      inputType: 'insertText',
      data: 'Secret input',
    }),
  )
  const error = new TypeError(
    'Secret exception containing https://example.org/?token=private',
  )
  error.stack =
    'TypeError: Secret exception\n at https://example.org/bundles/pushwordadminblockeditor/admin-block-editor.js?v=private:12:34'
  reportEditorError(holder, 'save', error)
  const report = diagnostics.report()
  expect(report).toMatchObject({
    version: '1.2.3',
    blocks: 1,
    editorjs: '2.33.0',
    actions: [{ action: 'input:insertText', block: 'paragraph', index: 0 }],
    errors: [
      {
        operation: 'save',
        name: 'TypeError',
        frames: [{ asset: 'admin-block-editor.js', line: 12, column: 34 }],
      },
    ],
  })
  const serialized = JSON.stringify(report)
  for (const value of ['Confidential', 'Secret', 'private', 'example.org'])
    expect(serialized).not.toContain(value)
  expect(document.querySelector('[role=alert]')?.textContent).toBe(
    'Retry or download a diagnostic.',
  )
  expect(document.querySelector<HTMLElement>('[role=alert]')?.hidden).toBe(false)
})

it('bounds the journal and does not record unknown input types or rejection payloads', () => {
  for (let i = 0; i < 80; i++)
    holder.dispatchEvent(new InputEvent('beforeinput', { inputType: 'Secret payload' }))
  for (let i = 0; i < 25; i++)
    reportEditorError(holder, 'copy', { privateText: 'Secret rejection' })
  const report = diagnostics.report()
  expect(report.actions).toHaveLength(60)
  expect(report.errors).toHaveLength(20)
  expect(JSON.stringify(report)).not.toContain('Secret')
})

it('captures uncaught failures and unhandled promises without suppressing browser reporting', () => {
  window.dispatchEvent(
    new ErrorEvent('error', { error: new RangeError('private'), cancelable: true }),
  )
  const rejection = new Event('unhandledrejection', { cancelable: true })
  Object.defineProperty(rejection, 'reason', {
    value: new DOMException('private', 'NotAllowedError'),
  })
  window.dispatchEvent(rejection)
  expect(diagnostics.report().errors).toMatchObject([
    { operation: 'uncaught', name: 'RangeError' },
    { operation: 'promise', name: 'NotAllowedError' },
  ])
  expect(rejection.defaultPrevented).toBe(false)
})

it('changes only writing suggestions for the current editor and records both settings', () => {
  const checkbox = document.querySelector<HTMLInputElement>(
    '[data-pw-writing-suggestions]',
  )!
  checkbox.click()
  expect(holder.getAttribute('writingsuggestions')).toBe('false')
  expect(holder.hasAttribute('spellcheck')).toBe(false)
  checkbox.click()
  expect(holder.getAttribute('writingsuggestions')).toBe('true')
  expect(diagnostics.report().actions).toMatchObject([
    { action: 'suggestions:off' },
    { action: 'suggestions:on' },
  ])
})

it('removes global and editor listeners when destroyed', () => {
  diagnostics.destroy()
  reportEditorError(holder, 'copy', new Error('ignored'))
  window.dispatchEvent(new ErrorEvent('error', { error: new Error('ignored') }))
  expect(diagnostics.report().errors).toEqual([])
})

it('does not show an editor failure for an unrelated image loading error', () => {
  const image = document.createElement('img')
  document.body.append(image)
  image.dispatchEvent(new Event('error'))
  expect(diagnostics.report().errors).toEqual([])
  expect(document.querySelector<HTMLElement>('[role=alert]')?.hidden).toBe(true)
})

it('can report an initialization failure before the Editor.js API is ready', () => {
  diagnostics.destroy()
  diagnostics = new EditorDiagnostics(holder, {} as EditorJS, {
    version: '1.2.3',
    editorjsVersion: '2.33.0',
    labels: { failure: 'Initialization failed.' },
  })
  reportEditorError(holder, 'uncaught', new TypeError('Private initialization failure'))
  expect(diagnostics.report()).toMatchObject({
    blocks: null,
    editorjs: '2.33.0',
    errors: [{ operation: 'uncaught', name: 'TypeError' }],
  })
})
