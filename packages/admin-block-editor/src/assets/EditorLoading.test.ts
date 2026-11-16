import { beforeEach, expect, it } from 'vitest'
import { EditorLoading } from './EditorLoading'

function widget(id: string): string {
  return `<div data-pw-editor-widget="${id}" data-pw-editor-state="loading">
    <button class="pw-editor-mode" disabled>Markdown</button>
    <button class="pw-editor-mode" disabled>Copy</button>
    <div data-pw-editor-status role="status">
      <p data-pw-editor-loading>Loading content…</p>
      <p data-pw-editor-load-error hidden>Loading failed</p>
      <button data-pw-editor-reload hidden>Reload page</button>
    </div>
    <div id="${id}" aria-busy="true" inert></div>
    <textarea>Original content</textarea>
  </div>`
}

beforeEach(() => {
  document.body.innerHTML = widget('first') + widget('second')
})

it('reveals the editor and enables its controls when content is ready', () => {
  const holder = document.getElementById('first')!
  new EditorLoading(holder).ready()
  expect(holder.hasAttribute('inert')).toBe(false)
  expect(holder.getAttribute('aria-busy')).toBe('false')
  expect(holder.parentElement!.dataset.pwEditorState).toBe('ready')
  expect(document.querySelector<HTMLElement>('[data-pw-editor-status]')!.hidden).toBe(true)
  expect(holder.parentElement!.querySelectorAll('button:disabled')).toHaveLength(0)
  expect(document.querySelector('textarea')!.value).toBe('Original content')
})

it('leaves other editors loading when one editor is ready', () => {
  new EditorLoading(document.getElementById('first')!).ready()
  const other = document.getElementById('second')!
  expect(other.hasAttribute('inert')).toBe(true)
  expect(other.getAttribute('aria-busy')).toBe('true')
  expect(other.parentElement!.dataset.pwEditorState).toBe('loading')
  expect(other.parentElement!.querySelectorAll('button:disabled')).toHaveLength(2)
})

it('replaces loading with a recovery action without exposing incomplete content', () => {
  const holder = document.getElementById('first')!
  new EditorLoading(holder).fail()
  const status = holder.parentElement!.querySelector<HTMLElement>('[data-pw-editor-status]')!
  expect(holder.parentElement!.dataset.pwEditorState).toBe('failed')
  expect(holder.getAttribute('aria-busy')).toBe('false')
  expect(holder.hasAttribute('inert')).toBe(true)
  expect(status.getAttribute('role')).toBe('alert')
  expect(status.querySelector<HTMLElement>('[data-pw-editor-loading]')!.hidden).toBe(true)
  expect(status.querySelector<HTMLElement>('[data-pw-editor-load-error]')!.hidden).toBe(false)
  expect(status.querySelector<HTMLElement>('[data-pw-editor-reload]')!.hidden).toBe(false)
  expect(holder.parentElement!.querySelectorAll('button:disabled')).toHaveLength(2)
  expect(document.querySelector('textarea')!.value).toBe('Original content')
})

it('keeps older site templates without a placeholder working', () => {
  document.body.innerHTML = '<div id="legacy"></div>'
  const loading = new EditorLoading(document.getElementById('legacy')!)
  loading.ready()
  loading.fail()
  expect(document.body.innerHTML).toBe('<div id="legacy"></div>')
})
