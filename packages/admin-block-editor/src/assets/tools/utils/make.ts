import { BaseTool } from '../Abstract/BaseTool'
import './switch.css'

export default class make {
  private static switchCount = 0

  public static element(
    tagName: string,
    classNames: string | string[] | null = null,
    attributes: Record<string, any> = {},
    innerHTML: string = '',
    onclick: ((event: Event) => void) | null = null,
  ): HTMLElement {
    const el = document.createElement(tagName)

    if (Array.isArray(classNames)) {
      el.classList.add(...classNames)
    } else if (classNames) {
      el.classList.add(classNames)
    }

    for (const attrName in attributes) {
      el.setAttribute(attrName, attributes[attrName])
    }

    if (innerHTML !== '') {
      el.innerHTML = innerHTML
    }

    if (onclick) {
      el.addEventListener('click', onclick)
    }

    return el
  }

  public static input(
    Tool: BaseTool,
    classNames: string[],
    placeholder: string,
    value: string = '',
  ): HTMLElement {
    const input = make.element('div', classNames, {
      contentEditable: !Tool.readOnly,
    })

    input.dataset.placeholder = Tool.api.i18n.t(placeholder)

    if (value) {
      input.textContent = value
    }

    return input
  }

  public static option(
    select: HTMLSelectElement,
    key: string,
    value: string | null = null,
    attributes: Record<string, any> = {},
    selectedValue: any = null,
  ): void {
    const option = document.createElement('option')
    option.text = value || key
    option.value = key
    for (const attrName in attributes) {
      option.setAttribute(attrName, attributes[attrName])
    }
    if (selectedValue !== null && selectedValue === value) {
      option.selected = true
    }
    select.add(option)
  }

  public static options(
    select: HTMLSelectElement,
    options: string[],
    selectedValue: any = null,
  ): void {
    options.forEach((option) => make.option(select, option, null, {}, selectedValue))
  }

  public static switchInput(
    name: string,
    labelText: string,
    checked: boolean = false,
  ): HTMLElement {
    // The link tool and the link tune both render a `targetBlank` switch: with a
    // shared id, both labels bind to whichever input the document holds first,
    // and clicking one toggles the other.
    const id = `${name}-${++make.switchCount}`

    const wrapper = make.element('div', 'editor-switch')
    const checkbox = make.element('input', null, {
      type: 'checkbox',
      id,
      role: 'switch',
    }) as HTMLInputElement
    const switchElement = make.element('label', 'label-default', { for: id })
    const label = make.element('label', '', { for: id })
    label.textContent = labelText
    // Text first, track second: a settings row reads label then control, and it
    // lets the link panel drop the pair straight into its own grid columns.
    wrapper.append(checkbox, label, switchElement)

    if (checked) {
      checkbox.checked = checked
    }

    return wrapper
  }

  public static selectionCollapseToEnd(): void {
    const sel = window.getSelection()
    if (!sel || !sel.focusNode) return

    const range = document.createRange()
    range.selectNodeContents(sel.focusNode)
    range.collapse(false)
    sel.removeAllRanges()
    sel.addRange(range)
  }

  public static moveCaretToTheEnd(element: HTMLElement) {
    if (!element.focus) return
    element.focus()
    const range = document.createRange()
    range.selectNodeContents(element)
    range.collapse(false)
    const selection = window.getSelection()
    if (!selection) return
    selection.removeAllRanges()
    selection.addRange(range)
  }
}
