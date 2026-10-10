import { describe, it, expect, vi } from 'vitest'
import AnchorTune from '../AnchorTune/AnchorTune'
import ClassTune from '../ClassTune/ClassTune'
import HashIcon from '../AnchorTune/Hash.svg?raw'
import ClassIcon from '../ClassTune/Class.svg?raw'

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
})
