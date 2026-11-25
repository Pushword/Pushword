import { describe, it, expect, vi } from 'vitest'
import AnchorTune from '../AnchorTune/AnchorTune'
import ClassTune from '../ClassTune/ClassTune'
import HashIcon from '../AnchorTune/Hash.svg?raw'
import ClassIcon from '../ClassTune/Class.svg?raw'
import { settingsInputConfirmations } from './editorPopovers'

type TuneField = HTMLInputElement | HTMLTextAreaElement

function tune<T extends AnchorTune | ClassTune>(
  Tune: new (options: any) => T,
  data?: string,
): {
  tool: T
  element: HTMLElement
  field: TuneField
  dispatchChange: () => void
} {
  const dispatchChange = vi.fn()
  const tool = new Tune({
    api: { i18n: { t: (key: string) => `t(${key})` } },
    data,
    block: { dispatchChange },
  })
  const element = tool.render()

  return {
    tool,
    element,
    field: fieldOf(element),
    dispatchChange,
  }
}

function fieldOf(element: HTMLElement): TuneField {
  return element.querySelector('.cdx-anchor-tune-input') as TuneField
}

/** `svg` as the DOM serialises it once parsed. */
function parsed(svg: string): string {
  const holder = document.createElement('div')
  holder.innerHTML = svg

  return holder.innerHTML
}

function type(field: TuneField, value: string): void {
  field.value = value
  field.dispatchEvent(new Event('input'))
}

function anchorConfirmationFixture({
  originText,
  targetEditable = true,
  withSettingsTrigger = false,
}: {
  originText?: string
  targetEditable?: boolean
  withSettingsTrigger?: boolean
} = {}): {
  origin: { id: string; holder: HTMLElement }
  target: { id: string; holder: HTMLElement; dispatchChange: () => void }
  api: {
    toolbar: { toggleBlockSettings: ReturnType<typeof vi.fn> }
    blocks: { getBlockByElement: ReturnType<typeof vi.fn> }
    caret: { setToBlock: ReturnType<typeof vi.fn> }
  }
  confirm: () => void
  originField: HTMLElement
  trigger: HTMLButtonElement | undefined
} {
  document.body.replaceChildren()
  const editor = document.createElement('div')
  editor.className = 'codex-editor'
  editor.innerHTML = `
    <div class="ce-block origin-block"><div class="ce-block__content">
      <div class="origin-field" contenteditable="true"></div>
    </div></div>
    <div class="ce-block target-block"><div class="ce-block__content">
      <div class="target-field" ${targetEditable ? 'contenteditable="true"' : ''}></div>
    </div></div>
    ${withSettingsTrigger ? '<button class="ce-toolbar__settings-btn"></button>' : ''}`
  const originField = editor.querySelector<HTMLElement>('.origin-field')!
  const targetField = editor.querySelector<HTMLElement>('.target-field')!
  originField.textContent = originText ?? 'Select this text'
  targetField.textContent = 'Target text'
  const origin = {
    id: 'origin',
    holder: editor.querySelector<HTMLElement>('.origin-block')!,
  }
  const target = {
    id: 'target',
    holder: editor.querySelector<HTMLElement>('.target-block')!,
    dispatchChange: vi.fn(),
  }
  const api = {
    i18n: { t: (key: string) => key },
    toolbar: { toggleBlockSettings: vi.fn() },
    blocks: { getBlockByElement: vi.fn(() => origin) },
    caret: { setToBlock: vi.fn() },
  }
  document.body.append(editor)
  const tool = new AnchorTune({ api: api as any, data: '', block: target as any })
  const input = tool.render().querySelector<HTMLInputElement>('.cdx-anchor-tune-input')!
  const confirm = settingsInputConfirmations.get(input)
  if (!confirm) throw new Error('Anchor confirmation was not registered')

  return {
    origin,
    target,
    api,
    confirm,
    originField,
    trigger: editor.querySelector<HTMLButtonElement>('.ce-toolbar__settings-btn') ?? undefined,
  }
}

describe('AnchorTune', () => {
  it('is a tune', () => {
    expect(AnchorTune.isTune).toBe(true)
  })

  it('renders its icon and a one-line field holding the saved anchor', () => {
    const { element, field } = tune(AnchorTune, 'faq')

    expect(element.classList.contains('cdx-anchor-tune-wrapper')).toBe(true)
    expect(element.querySelector('.cdx-anchor-tune-icon')!.innerHTML).toBe(
      parsed(HashIcon),
    )
    expect(field.tagName).toBe('INPUT')
    expect(field.placeholder).toBe('t(Anchor)')
    expect(field.value).toBe('faq')
    expect(field.closest('label')?.querySelector('.cdx-anchor-tune-label')?.textContent?.trim()).toBe('t(Anchor)')
  })

  it('saves an anchor never set as an empty string', () => {
    const { tool, field } = tune(AnchorTune)

    expect(field.value).toBe('')
    expect(tool.save()).toBe('')
  })

  it('keeps the characters an attribute line can read back, and reports the change', () => {
    const { tool, field, dispatchChange } = tune(AnchorTune, 'faq')

    type(field, 'My Anchor #2!')

    expect(tool.save()).toBe('MyAnchor2')
    expect(field.value).toBe('My Anchor #2!')
    expect(dispatchChange).toHaveBeenCalledOnce()
  })

  it('saves a stored anchor back untouched when the field is left alone', () => {
    expect(tune(AnchorTune, 'faq').tool.save()).toBe('faq')
  })

  it('saves an empty string once every typed character is dropped', () => {
    const { tool, field } = tune(AnchorTune, 'faq')

    type(field, '#!. é')

    expect(tool.save()).toBe('')
  })

  it('shows the cleaned anchor when the block settings reopen', () => {
    const { tool, field } = tune(AnchorTune)

    type(field, 'My Anchor')

    // Editor.js calls render() again each time the block settings open.
    expect(fieldOf(tool.render()).value).toBe('MyAnchor')
  })

  it('registers its input confirmation for plain Enter', () => {
    const { field } = tune(AnchorTune)

    expect(settingsInputConfirmations.get(field as HTMLInputElement)).toEqual(expect.any(Function))
  })

  it('closes and restores a selection in a different text block', () => {
    const { origin, api, confirm, originField } = anchorConfirmationFixture({
      originText: 'See the programme',
    })
    const selection = window.getSelection()!
    const text = originField.firstChild!
    document.body.appendChild(document.createElement('input')).focus()
    api.toolbar.toggleBlockSettings.mockImplementation(() => {
      selection.setBaseAndExtent(text, 8, text, 17)
    })
    api.caret.setToBlock.mockImplementation(() => selection.removeAllRanges())

    confirm()

    expect(api.toolbar.toggleBlockSettings).toHaveBeenCalledWith(false)
    expect(api.blocks.getBlockByElement).toHaveBeenCalledWith(originField)
    expect(api.caret.setToBlock).toHaveBeenCalledWith(origin)
    expect(document.activeElement).toBe(originField)
    expect(selection.toString()).toBe('programme')
  })

  it('returns to the end of the target text block when no selection is available', () => {
    const { target, api, confirm } = anchorConfirmationFixture()
    window.getSelection()!.removeAllRanges()

    confirm()

    expect(api.toolbar.toggleBlockSettings).toHaveBeenCalledWith(false)
    expect(api.caret.setToBlock).toHaveBeenCalledWith(target.id, 'end')
  })

  it('uses the text fallback if the restored range is outside this editor', () => {
    const { target, api, confirm } = anchorConfirmationFixture()
    const otherField = document.body.appendChild(document.createElement('div'))
    otherField.contentEditable = 'true'
    otherField.textContent = 'Another editor'
    window.getSelection()!.selectAllChildren(otherField)

    confirm()

    expect(api.blocks.getBlockByElement).not.toHaveBeenCalled()
    expect(api.caret.setToBlock).toHaveBeenCalledWith(target.id, 'end')
  })

  it('returns focus to the settings control when the target has no editable text', () => {
    const { api, confirm, trigger } = anchorConfirmationFixture({
      targetEditable: false,
      withSettingsTrigger: true,
    })
    window.getSelection()!.removeAllRanges()

    confirm()

    expect(api.toolbar.toggleBlockSettings).toHaveBeenCalledWith(false)
    expect(api.caret.setToBlock).not.toHaveBeenCalled()
    expect(trigger!.getAttribute('tabindex')).toBe('-1')
    expect(document.activeElement).toBe(trigger)
  })
})

describe('ClassTune', () => {
  it('is a tune', () => {
    expect(ClassTune.isTune).toBe(true)
  })

  it('renders its icon and a wrapping field holding the saved classes', () => {
    const { element, field } = tune(ClassTune, 'grid gap-4')

    expect(element.querySelector('.cdx-anchor-tune-icon')!.innerHTML).toBe(
      parsed(ClassIcon),
    )
    expect(field.tagName).toBe('TEXTAREA')
    expect(field.placeholder).toBe('t(Class)')
    expect(field.value).toBe('grid gap-4')
    expect(field.closest('label')?.querySelector('.cdx-anchor-tune-label')?.textContent?.trim()).toBe('t(Class)')
  })

  it('saves nothing for a class never set', () => {
    const { tool, field } = tune(ClassTune)

    expect(field.value).toBe('')
    expect(tool.save()).toBeUndefined()
  })

  it('saves what was typed verbatim, and reports the change', () => {
    const { tool, field, dispatchChange } = tune(ClassTune)

    type(field, 'md:grid-cols-2 .wide')

    expect(tool.save()).toBe('md:grid-cols-2 .wide')
    expect(dispatchChange).toHaveBeenCalledOnce()
  })

  it('saves stored classes back untouched when the field is left alone', () => {
    expect(tune(ClassTune, 'grid gap-4').tool.save()).toBe('grid gap-4')
  })

  it('saves an emptied field as an empty string', () => {
    const { tool, field } = tune(ClassTune, 'grid')

    type(field, '')

    expect(tool.save()).toBe('')
  })

  it('shows the typed classes when the block settings reopen', () => {
    const { tool, field } = tune(ClassTune)

    type(field, 'grid gap-4')

    expect(fieldOf(tool.render()).value).toBe('grid gap-4')
  })

  it('does not register a textarea as an input confirmation', () => {
    const { field } = tune(ClassTune)

    expect(settingsInputConfirmations.get(field as HTMLInputElement)).toBeUndefined()
  })
})
