import { afterEach, describe, expect, it, vi } from 'vitest'
import Undo, { type UndoOptions } from './Undo'

const caretText = () => {
  const selection = getSelection()!
  const range = document.createRange()
  const element =
    selection.anchorNode instanceof Element
      ? selection.anchorNode
      : selection.anchorNode!.parentElement!
  range.selectNodeContents(element.closest('[contenteditable]')!)
  range.setEnd(selection.anchorNode!, selection.anchorOffset)
  return range.toString()
}
const pause = (ms = 0) => new Promise((resolve) => setTimeout(resolve, ms))
const histories: Undo[] = []
const paragraph = (id: string, text: string) => ({
  id,
  type: 'paragraph',
  data: { text },
})

function fixture(
  initial = [paragraph('a', 'original')],
  options: Pick<UndoOptions, 'maxLength' | 'onApply' | 'normalizeBlockData'> = {},
) {
  document.body.innerHTML =
    '<div id="history"><div class="codex-editor__redactor"></div><div class="ce-toolbox"></div></div>'
  const holder = document.getElementById('history')!
  const redactor = holder.firstElementChild!
  let states = structuredClone(initial)
  const mount = () => {
    redactor.innerHTML = states
      .map(
        (b) =>
          `<div class="ce-block" data-id="${b.id}"><div class="ce-block__content"><div class="ce-paragraph" contenteditable="true">${b.data.text}</div></div></div>`,
      )
      .join('')
  }
  mount()
  const all = () =>
    Array.from(redactor.children).map((node) => {
      const id = (node as HTMLElement).dataset.id!
      return {
        id,
        type: 'paragraph',
        data: { text: node.querySelector('[contenteditable]')!.innerHTML },
      }
    })
  const block = (index: number): any => {
    const node = redactor.children[index] as HTMLElement | undefined
    return (
      node && {
        id: node.dataset.id,
        name: 'paragraph',
        holder: node,
        call() {},
        save: async () => ({ ...all()[index], tool: 'paragraph', tunes: {} }),
      }
    )
  }
  const editor: any = {
    configuration: { holder, defaultBlock: 'paragraph', readOnly: false },
    readOnly: { isEnabled: false },
    inlineToolbar: { close: vi.fn() },
    blocks: {
      getBlocksCount: () => redactor.children.length,
      getBlockByIndex: block,
      getBlockIndex: (id: string) => all().findIndex((b) => b.id === id),
      getById: (id: string) => block(all().findIndex((b) => b.id === id)) ?? null,
      getCurrentBlockIndex: () =>
        all().findIndex(
          (b) =>
            b.id ===
            document.activeElement?.closest<HTMLElement>('.ce-block')?.dataset.id,
        ),
      render: async ({ blocks }: any) => {
        states = structuredClone(blocks)
        mount()
      },
      update: async (id: string, data: any) => {
        const node = redactor.querySelector(`[data-id="${id}"] [contenteditable]`)!
        node.innerHTML = data.text
        return block(all().findIndex((b) => b.id === id))
      },
      delete: (index: number) => redactor.children[index]?.remove(),
      insert: (
        _type: string,
        data: any,
        _config: any,
        index: number,
        _focus: boolean,
        _replace: boolean,
        id: string,
      ) => {
        const node = document.createElement('div')
        node.className = 'ce-block'
        node.dataset.id = id
        node.innerHTML = `<div class="ce-block__content"><div class="ce-paragraph" contenteditable="true">${data.text ?? ''}</div></div>`
        redactor.insertBefore(node, redactor.children[index] ?? null)
        return block(index)
      },
      move: (to: number, from: number) => {
        const node = redactor.children[from]!
        node.remove()
        redactor.insertBefore(node, redactor.children[to] ?? null)
      },
    },
    caret: {
      setToBlock: (i: number) =>
        (
          redactor.children[i]?.querySelector('[contenteditable]') as HTMLElement
        )?.focus(),
    },
    saver: {
      save: async () => ({ blocks: all().filter((b) => b.data.text !== '') }),
    },
    events: { on() {}, off() {} },
  }
  const undo = new Undo({ editor, ...options })
  histories.push(undo)
  const focus = (index = 0, offset?: number) => {
    const field = redactor.children[index]!.querySelector(
      '[contenteditable]',
    ) as HTMLElement
    field.focus()
    const range = document.createRange()
    range.selectNodeContents(field)
    range.collapse(false)
    if (offset !== undefined) range.setStart(field.firstChild ?? field, offset)
    range.collapse(true)
    const selection = getSelection()!
    selection.removeAllRanges()
    selection.addRange(range)
    document.dispatchEvent(new Event('selectionchange'))
    return field
  }
  const type = (text: string, index = 0) => {
    const field = focus(index)
    field.dispatchEvent(
      new InputEvent('beforeinput', {
        bubbles: true,
        inputType: 'insertText',
        data: text,
      }),
    )
    field.textContent += text
    focus(index)
    field.dispatchEvent(
      new InputEvent('input', {
        bubbles: true,
        inputType: 'insertText',
        data: text,
      }),
    )
  }
  return { undo, editor, holder, all, focus, type }
}

afterEach(() => {
  vi.restoreAllMocks()
  histories.splice(0).forEach((history) => history.destroy())
  document.body.replaceChildren()
})

describe('Undo history regressions', () => {
  it('compares and restores normalized snapshots while keeping empty blocks', async () => {
    const f = fixture([paragraph('a', 'original '), paragraph('b', '')], {
      normalizeBlockData: (data) => ({ ...data, text: data.text.trimEnd() }),
    })
    await f.undo.initialize()
    f.type(' ')
    await f.undo.flush()
    expect(f.undo.count()).toBe(0)

    f.type('changed ')
    await f.undo.flush()
    expect(f.undo.count()).toBe(1)
    await f.undo.undo()
    expect(f.all()).toEqual([paragraph('a', 'original'), paragraph('b', '')])
    await f.undo.redo()
    expect(f.all()).toEqual([paragraph('a', 'original  changed'), paragraph('b', '')])

    await f.undo.clear()
    f.type(' ')
    await f.undo.flush()
    expect(f.undo.count()).toBe(0)
  })

  it('captures pending typing before undo and makes it available to redo', async () => {
    const f = fixture()
    await f.undo.initialize({ blocks: f.all() })
    f.type(' first')
    await pause(550)
    f.type(' second')
    await f.undo.undo()
    expect(f.all()[0]!.data.text).toBe('original first')
    await f.undo.redo()
    expect(f.all()[0]!.data.text).toBe('original first second')
  })

  it('records an empty document rather than skipping the deletion', async () => {
    const f = fixture()
    await f.undo.initialize({ blocks: f.all() })
    f.type(' added')
    await pause(550)
    const field = f.focus()
    field.dispatchEvent(
      new InputEvent('beforeinput', {
        bubbles: true,
        inputType: 'deleteContentBackward',
      }),
    )
    field.textContent = ''
    field.dispatchEvent(
      new InputEvent('input', {
        bubbles: true,
        inputType: 'deleteContentBackward',
      }),
    )
    await pause(250)
    await f.undo.undo()
    expect(f.all()[0]!.data.text).toBe('original added')
    await f.undo.redo()
    expect(f.all()[0]!.data.text).toBe('')
  })

  it('keeps untouched blocks alive when restoring an edit', async () => {
    const f = fixture([paragraph('a', 'original'), paragraph('b', '')])
    await f.undo.initialize({ blocks: f.all() })
    const untouched = f.editor.blocks.getBlockByIndex(1).holder
    f.type(' edited')
    await pause(250)
    await f.undo.undo()
    expect(f.editor.blocks.getBlockByIndex(1).holder).toBe(untouched)
  })
})

it('groups continuous typing and starts a new branch after undo', async () => {
  const f = fixture()
  await f.undo.initialize()
  f.type('a')
  await f.undo.flush()
  f.type('b')
  await f.undo.flush()
  expect(f.undo.count()).toBe(1)
  await f.undo.undo()
  expect(f.all()[0]!.data.text).toBe('original')
  f.type('c')
  await f.undo.flush()
  expect(f.undo.canRedo()).toBe(false)
  await f.undo.undo()
  expect(f.all()[0]!.data.text).toBe('original')
  await f.undo.redo()
  expect(f.all()[0]!.data.text).toBe('originalc')
})

it('keeps edits in different blocks separate and restores the edited selection', async () => {
  const f = fixture([paragraph('a', 'one'), paragraph('b', 'two')])
  await f.undo.initialize()
  f.type('A')
  await f.undo.flush()
  f.type('B', 1)
  await f.undo.flush()
  await f.undo.undo()
  expect(f.all().map((b) => b.data.text)).toEqual(['oneA', 'two'])
  expect(
    getSelection()!.anchorNode?.parentElement?.closest<HTMLElement>('.ce-block')?.dataset
      .id,
  ).toBe('b')
  expect(caretText()).toBe('two')
  await f.undo.redo()
  expect(caretText()).toBe('twoB')
})

it('serializes rapid keyboard undo and redo even while focus is temporarily on the body', async () => {
  const f = fixture([
    paragraph('a', 'one'),
    paragraph('b', 'two'),
    paragraph('c', 'three'),
  ])
  await f.undo.initialize()
  for (let index = 0; index < 3; index++) {
    f.type('!', index)
    await f.undo.flush()
  }
  const shortcut = (key: string) =>
    document.activeElement!.dispatchEvent(
      new KeyboardEvent('keydown', {
        key,
        ctrlKey: true,
        bubbles: true,
        cancelable: true,
      }),
    )
  shortcut('z')
  ;(document.activeElement as HTMLElement).blur()
  shortcut('z')
  shortcut('z')
  shortcut('y')
  await f.undo.flush()
  expect(f.all().map((b) => b.data.text)).toEqual(['one!', 'two', 'three'])
})

it('restores pure moves, insertions and deletions by id, including empty blocks', async () => {
  const f = fixture([
    paragraph('a', 'one'),
    paragraph('empty', ''),
    paragraph('b', 'two'),
  ])
  await f.undo.initialize()
  f.editor.blocks.move(0, 2)
  await f.undo.flush()
  await f.undo.undo()
  expect(f.all().map((b) => b.id)).toEqual(['a', 'empty', 'b'])
  await f.undo.redo()
  expect(f.all().map((b) => b.id)).toEqual(['b', 'a', 'empty'])
  f.editor.blocks.delete(1)
  await f.undo.flush()
  await f.undo.undo()
  expect(f.all().map((b) => b.id)).toEqual(['b', 'a', 'empty'])
  await f.undo.redo()
  expect(f.all().map((b) => b.id)).toEqual(['b', 'empty'])
})

it('handles native history input events without invoking the browser history', async () => {
  const f = fixture()
  await f.undo.initialize()
  f.type('!')
  const event = new InputEvent('beforeinput', {
    inputType: 'historyUndo',
    cancelable: true,
    bubbles: true,
  })
  f.focus().dispatchEvent(event)
  expect(event.defaultPrevented).toBe(true)
  await f.undo.flush()
  expect(f.all()[0]!.data.text).toBe('original')
  f.focus().dispatchEvent(
    new InputEvent('beforeinput', {
      inputType: 'historyRedo',
      bubbles: true,
      cancelable: true,
    }),
  )
  await f.undo.flush()
  expect(f.all()[0]!.data.text).toBe('original!')
})

it('leaves shortcuts outside the editor, in read-only mode and after destruction alone', async () => {
  const f = fixture()
  await f.undo.initialize()
  f.type('!')
  await f.undo.flush()
  const outside = document.createElement('input')
  document.body.append(outside)
  outside.focus()
  const key = () =>
    new KeyboardEvent('keydown', {
      key: 'z',
      ctrlKey: true,
      bubbles: true,
      cancelable: true,
    })
  let event = key()
  outside.dispatchEvent(event)
  expect(event.defaultPrevented).toBe(false)
  f.editor.readOnly.isEnabled = true
  event = key()
  f.focus().dispatchEvent(event)
  expect(event.defaultPrevented).toBe(false)
  f.editor.readOnly.isEnabled = false
  f.undo.destroy()
  event = key()
  f.focus().dispatchEvent(event)
  expect(event.defaultPrevented).toBe(false)
  expect(f.all()[0]!.data.text).toBe('original!')
})

it('reinitializes history without carrying edits from a replaced document', async () => {
  const f = fixture()
  await f.undo.initialize()
  f.type('!')
  await f.undo.flush()
  await f.undo.clear()
  await f.undo.undo()
  expect(f.undo.count()).toBe(0)
  expect(f.all()[0]!.data.text).toBe('original!')
})

it.each(['<input value="/draft">', '<textarea>/draft</textarea>'])(
  'leaves inline toolbar draft history native for %s',
  async (control) => {
    const f = fixture()
    await f.undo.initialize()
    f.type('!')
    await f.undo.flush()
    const toolbar = document.createElement('div')
    toolbar.className = 'ce-inline-toolbar'
    toolbar.innerHTML = control
    f.holder.append(toolbar)
    const input = toolbar.querySelector<HTMLInputElement | HTMLTextAreaElement>(
      'input, textarea',
    )!
    input.focus()
    for (const key of ['z', 'y']) {
      const event = new KeyboardEvent('keydown', {
        key,
        ctrlKey: true,
        bubbles: true,
        cancelable: true,
      })
      input.dispatchEvent(event)
      expect(event.defaultPrevented).toBe(false)
    }
    for (const inputType of ['historyUndo', 'historyRedo']) {
      const event = new InputEvent('beforeinput', {
        inputType,
        bubbles: true,
        cancelable: true,
      })
      input.dispatchEvent(event)
      expect(event.defaultPrevented).toBe(false)
    }
    await f.undo.flush()
    expect(f.all()[0]!.data.text).toBe('original!')
    expect(f.undo.canUndo()).toBe(true)
    await f.undo.undo()
    expect(f.all()[0]!.data.text).toBe('original')
  },
)

it.each(['checkbox', 'radio'])(
  'keeps document undo active when an inline toolbar %s has focus',
  async (type) => {
    const f = fixture()
    await f.undo.initialize()
    f.type('!')
    await f.undo.flush()
    const toolbar = document.createElement('div')
    toolbar.className = 'ce-inline-toolbar'
    toolbar.innerHTML = `<input type="${type}">`
    f.holder.append(toolbar)
    const input = toolbar.querySelector('input')!
    input.focus()
    const event = new KeyboardEvent('keydown', {
      key: 'z',
      ctrlKey: true,
      bubbles: true,
      cancelable: true,
    })
    input.dispatchEvent(event)
    await f.undo.flush()
    expect(event.defaultPrevented).toBe(true)
    expect(f.all()[0]!.data.text).toBe('original')
  },
)

it('closes stale inline toolbar ranges before applying an undo snapshot', async () => {
  const f = fixture()
  await f.undo.initialize()
  f.type('!')
  await f.undo.flush()
  const update = vi.spyOn(f.editor.blocks, 'update')
  await f.undo.undo()
  expect(f.editor.inlineToolbar.close).toHaveBeenCalledOnce()
  expect(update).toHaveBeenCalledOnce()
  expect(f.editor.inlineToolbar.close.mock.invocationCallOrder[0]).toBeLessThan(
    update.mock.invocationCallOrder[0]!,
  )
})

it('records typing while the bound form is still being exported', async () => {
  let finish!: () => void
  const exporting = new Promise<void>((resolve) => {
    finish = resolve
  })
  const f = fixture(undefined, { onApply: () => exporting })
  await f.undo.initialize()
  f.type(' before')
  await f.undo.flush()
  const applying = f.undo.undo()
  await pause()
  f.type(' after')
  await pause()
  finish()
  await applying
  await f.undo.flush()
  expect(f.all()[0]!.data.text).toBe('original after')
  await f.undo.undo()
  expect(f.all()[0]!.data.text).toBe('original')
})

it('bounds history without losing the baseline of the retained edits', async () => {
  const f = fixture([paragraph('a', 'A'), paragraph('b', 'B'), paragraph('c', 'C')], {
    maxLength: 2,
  })
  await f.undo.initialize()
  for (let i = 0; i < 3; i++) {
    f.type('!', i)
    await f.undo.flush()
  }
  expect(f.undo.count()).toBe(2)
  await f.undo.undo()
  await f.undo.undo()
  await f.undo.undo()
  expect(f.all().map((b) => b.data.text)).toEqual(['A!', 'B', 'C'])
})

it('keeps a complete IME composition in one history entry', async () => {
  const f = fixture()
  await f.undo.initialize()
  f.focus().dispatchEvent(new CompositionEvent('compositionstart', { bubbles: true }))
  f.type('a')
  await pause(550)
  f.type('b')
  await pause(550)
  expect(f.undo.count()).toBe(0)
  f.focus().dispatchEvent(new CompositionEvent('compositionend', { bubbles: true }))
  await f.undo.flush()
  expect(f.undo.count()).toBe(1)
  await f.undo.undo()
  expect(f.all()[0]!.data.text).toBe('original')
  await f.undo.redo()
  expect(f.all()[0]!.data.text).toBe('originalab')
})

it('supports Command+Z and Command+Shift+Z on macOS', async () => {
  vi.spyOn(navigator, 'platform', 'get').mockReturnValue('MacIntel')
  const f = fixture()
  await f.undo.initialize()
  f.type('!')
  f.focus().dispatchEvent(
    new KeyboardEvent('keydown', {
      key: 'z',
      metaKey: true,
      bubbles: true,
      cancelable: true,
    }),
  )
  await f.undo.flush()
  expect(f.all()[0]!.data.text).toBe('original')
  f.focus().dispatchEvent(
    new KeyboardEvent('keydown', {
      key: 'Z',
      metaKey: true,
      shiftKey: true,
      bubbles: true,
      cancelable: true,
    }),
  )
  await f.undo.flush()
  expect(f.all()[0]!.data.text).toBe('original!')
})

it('captures only the edited block in a long document and retains history across undo branches', async () => {
  const normalize = vi.fn<NonNullable<UndoOptions['normalizeBlockData']>>((data) => data)
  const f = fixture(
    Array.from({ length: 200 }, (_, i) => paragraph(String(i), i === 199 ? '' : 'Text')),
    {
      normalizeBlockData: normalize,
    },
  )
  await f.undo.initialize()
  normalize.mockClear()
  f.type(' first', 3)
  await f.undo.flush()
  expect(normalize).toHaveBeenCalledOnce()
  expect(normalize.mock.calls[0]![1]).toEqual({ id: '3', type: 'paragraph' })
  f.type(' second', 4)
  await f.undo.flush()
  expect(normalize).toHaveBeenCalledTimes(2)
  await f.undo.undo()
  expect(f.all()[4]!.data.text).toBe('Text')
  expect(f.all()[3]!.data.text).toBe('Text first')
  f.type(' branch', 5)
  await f.undo.flush()
  await f.undo.undo()
  expect(f.all()[5]!.data.text).toBe('Text')
  await f.undo.redo()
  expect(f.all()[5]!.data.text).toBe('Text branch')
  expect(f.all()[199]!.data.text).toBe('')
})
