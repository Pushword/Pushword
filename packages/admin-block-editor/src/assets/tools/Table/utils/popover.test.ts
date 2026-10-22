import { afterEach, describe, expect, it, vi } from 'vitest'
import Toolbox from '../toolbox'

afterEach(() => document.body.replaceChildren())

describe('table menu keyboard and deletion', () => {
  function menu() {
    const onDelete = vi.fn()
    const onClose = vi.fn()
    const toolbox = new Toolbox({
      api: { i18n: { t: (text: string) => `t(${text})` } },
      cssModifier: 'column',
      items: [
        { label: 'Hidden', icon: '', hideIf: () => true, onClick: vi.fn() },
        { label: 'Insert', icon: '', onClick: vi.fn() },
        { label: 'Delete', icon: '', confirmationRequired: true, onClick: onDelete },
      ],
      onOpen: vi.fn(),
      onClose,
    })
    document.body.append(toolbox.element)
    const toggle = toolbox.element.querySelector('button')!
    toggle.click()
    return { toolbox, toggle, onDelete, onClose }
  }

  it('focuses visible actions, navigates arrows, and closes on Escape with focus returned', () => {
    const { toolbox, toggle, onClose } = menu()
    expect(toggle.getAttribute('aria-label')).toBe('t(Column options)')
    expect(toggle.getAttribute('aria-expanded')).toBe('true')
    expect(document.activeElement?.textContent).toBe('Insert')
    document.activeElement!.dispatchEvent(
      new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }),
    )
    expect(document.activeElement?.textContent).toBe('Delete')
    document.activeElement!.dispatchEvent(
      new KeyboardEvent('keydown', { key: 'Home', bubbles: true }),
    )
    expect(document.activeElement?.textContent).toBe('Insert')
    document.activeElement!.dispatchEvent(
      new KeyboardEvent('keydown', { key: 'ArrowUp', bubbles: true }),
    )
    expect(document.activeElement?.textContent).toBe('Delete')
    document.activeElement!.dispatchEvent(
      new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }),
    )
    expect(toolbox.popover!.opened).toBe(false)
    expect(toggle.getAttribute('aria-expanded')).toBe('false')
    expect(document.activeElement).toBe(toggle)
    expect(onClose).toHaveBeenCalledOnce()
  })

  it('requires an explicit second activation and resets confirmation when closed', () => {
    const { toolbox, toggle, onDelete } = menu()
    const action = toolbox.popover!.itemEls[2]!
    action.click()
    expect(action.textContent).toBe('t(Confirm deletion)')
    expect(onDelete).not.toHaveBeenCalled()
    toggle.click()
    toggle.click()
    expect(action.textContent).toBe('Delete')
    action.click()
    action.click()
    expect(onDelete).toHaveBeenCalledOnce()
  })

  it('releases the menu when tabbing out', () => {
    const { toolbox, toggle } = menu()
    document.activeElement!.dispatchEvent(
      new KeyboardEvent('keydown', { key: 'Tab', bubbles: true }),
    )
    expect(toolbox.popover!.opened).toBe(false)
    expect(document.activeElement).toBe(toggle)
  })
})
