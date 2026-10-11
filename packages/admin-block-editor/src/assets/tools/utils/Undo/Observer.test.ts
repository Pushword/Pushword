import { describe, expect, it, vi } from 'vitest'
import Observer from './Observer'

const tick = () => new Promise((resolve) => setTimeout(resolve, 10))
function fixture() {
  const holder = document.createElement('div')
  holder.innerHTML =
    '<div class="codex-editor__redactor"><div contenteditable="true">Text</div><input></div>'
  document.body.append(holder)
  const changed = vi.fn()
  const observer = new Observer(changed, holder)
  observer.setMutationObserver()
  return { holder, changed, observer }
}

describe('History observation', () => {
  it('captures both contenteditable edits and input values', async () => {
    const f = fixture()
    f.holder.querySelector('[contenteditable]')!.textContent = 'Changed'
    await tick()
    expect(f.changed).toHaveBeenCalledTimes(1)
    f.holder.querySelector('input')!.dispatchEvent(new Event('input', { bubbles: true }))
    await tick()
    expect(f.changed).toHaveBeenCalledTimes(2)
    f.observer.destroy()
  })
  it('does not serialize an unchanged document at keyboard boundaries', () => {
    const f = fixture()
    f.observer.flush()
    f.observer.flush()
    expect(f.changed).not.toHaveBeenCalled()
    f.observer.destroy()
  })
  it('flushes immediately and cancels the scheduled callback', async () => {
    const f = fixture()
    f.holder.dispatchEvent(new Event('input'))
    f.holder.querySelector('[contenteditable]')!.textContent = 'Pending DOM edit'
    f.observer.flush()
    expect(f.changed).toHaveBeenCalledTimes(1)
    await tick()
    expect(f.changed).toHaveBeenCalledTimes(1)
    f.observer.destroy()
  })
  it('does not record restoration and disconnects on destruction', async () => {
    const f = fixture()
    f.holder.dispatchEvent(new Event('input'))
    f.observer.pause()
    f.holder.querySelector('[contenteditable]')!.textContent = 'Restored'
    await tick()
    expect(f.changed).not.toHaveBeenCalled()
    f.observer.setMutationObserver()
    f.holder.dispatchEvent(new Event('input'))
    await tick()
    expect(f.changed).toHaveBeenCalledTimes(1)
    f.observer.destroy()
    f.holder.dispatchEvent(new Event('input'))
    await tick()
    expect(f.changed).toHaveBeenCalledTimes(1)
  })
})

it('collects every affected block from DOM edits, controls and embedded editors', () => {
  const f = fixture()
  f.holder.firstElementChild!.innerHTML =
    '<div class="ce-block" data-id="a"><div contenteditable>Text</div></div><div class="ce-block" data-id="b"><input></div>'
  f.observer.flush()
  f.changed.mockClear()
  f.holder.querySelector('[contenteditable]')!.textContent = 'Changed'
  f.holder.querySelector('input')!.dispatchEvent(new Event('input', { bubbles: true }))
  f.observer.flush()
  expect(f.changed).toHaveBeenCalledExactlyOnceWith(new Set(['a', 'b']))
  f.changed.mockClear()
  f.holder
    .querySelector('[data-id="a"]')!
    .dispatchEvent(new CustomEvent('pw:history-change', { bubbles: true }))
  f.observer.flush()
  expect(f.changed).toHaveBeenCalledExactlyOnceWith(new Set(['a']))
  f.observer.destroy()
})

it('captures tune changes outside block DOM instead of reusing stale private data', () => {
  const f = fixture()
  const settings = document.createElement('div')
  settings.className = 'ce-settings'
  const input = document.createElement('input')
  settings.append(input)
  f.holder.append(settings)
  input.dispatchEvent(new Event('input', { bubbles: true }))
  f.observer.flush()
  expect(f.changed).toHaveBeenCalledExactlyOnceWith(undefined)
  f.observer.destroy()
})
