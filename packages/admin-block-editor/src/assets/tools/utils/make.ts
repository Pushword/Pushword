import { BaseTool } from '../Abstract/BaseTool'

export default class make {
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
    const wrapper = make.element('div', 'editor-switch')
    const checkbox = make.element('input', null, {
      type: 'checkbox',
      id: name,
    }) as HTMLInputElement
    const switchElement = make.element('label', 'label-default', {
      for: name,
    })
    const label = make.element('label', '', { for: name })
    label.innerHTML = labelText
    wrapper.append(checkbox, switchElement, label)

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
