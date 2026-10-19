/* Use the configured inline tools, including their real toolbar event handlers. */
window.undoInlineChecks = { done: false, results: [] }
void (async () => {
  const report = window.undoInlineChecks
  const holder = document.querySelector('.editorjs-holder')
  const editor = window.editors[holder.id]
  const pause = (ms = 40) => new Promise((resolve) => setTimeout(resolve, ms))
  const check = (condition, message) => {
    if (!condition) throw new Error(message)
  }
  const test = async (name, run) => {
    try {
      await run()
      report.results.push({ name, passed: true })
    } catch (error) {
      report.results.push({ name, passed: false, error: String(error) })
    }
  }
  const field = () => holder.querySelector('[data-id="inline"] .ce-paragraph')
  const html = () => field()?.innerHTML
  const render = async (text = 'Format me') => {
    editor.inlineToolbar.close()
    await editor.blocks.render({
      blocks: [
        { id: 'inline', type: 'paragraph', data: { text } },
        { id: 'empty', type: 'paragraph', data: { text: '' } },
      ],
    })
    field().scrollIntoView({ block: 'center', behavior: 'instant' })
    await pause(650)
  }
  const wait = async (condition) => {
    for (let i = 0; i < 80; i++) {
      if (await condition()) return
      await pause(25)
    }
    throw new Error(`Timed out: ${html()}`)
  }
  const point = (offset) => {
    const walker = document.createTreeWalker(field(), NodeFilter.SHOW_TEXT)
    while (walker.nextNode()) {
      if (offset <= walker.currentNode.length) return [walker.currentNode, offset]
      offset -= walker.currentNode.length
    }
    throw new Error('Selection offset outside the paragraph')
  }
  const select = async (start = 0, end = 6) => {
    field().focus({ preventScroll: true })
    getSelection().setBaseAndExtent(...point(start), ...point(end))
    field().dispatchEvent(
      new KeyboardEvent('keyup', { key: 'Shift', keyCode: 16, bubbles: true }),
    )
    await wait(() => holder.querySelector('.ce-inline-toolbar [data-item-name="bold"]'))
  }
  const tool = async (name) => {
    const item = holder.querySelector(`.ce-inline-toolbar [data-item-name="${name}"]`)
    check(item, `Missing configured tool: ${name}`)
    const button = item.matches('button') ? item : item.querySelector('button')
    const rect = button.getBoundingClientRect()
    const position = { clientX: rect.left + rect.width / 2, clientY: rect.top + rect.height / 2 }
    button.dispatchEvent(new PointerEvent('pointerdown', { ...position, bubbles: true }))
    button.dispatchEvent(new MouseEvent('mousedown', { ...position, bubbles: true, cancelable: true }))
    button.click()
    await pause()
  }
  const key = async (key, shiftKey = false, target = field()) => {
    target.dispatchEvent(
      new KeyboardEvent('keydown', {
        key,
        ctrlKey: true,
        shiftKey,
        bubbles: true,
        cancelable: true,
      }),
    )
    await pause(80)
  }
  const type = async (text) => {
    field().dispatchEvent(
      new InputEvent('beforeinput', {
        inputType: 'insertText',
        data: text,
        bubbles: true,
      }),
    )
    document.execCommand('insertText', false, text)
    await pause()
  }
  const range = (backward) => {
    check(getSelection().toString() === 'Format', 'The formatted selection was lost')
    const selection = getSelection()
    const anchor = document.createRange()
    anchor.selectNodeContents(field())
    anchor.setEnd(selection.anchorNode, selection.anchorOffset)
    check(anchor.toString().length === (backward ? 6 : 0), 'Selection direction was lost')
  }
  const preserved = (scroll) => {
    check(Math.abs(scrollY - scroll) < 2, `History scrolled ${scrollY - scroll}px`)
    check(
      editor.blocks.getById('empty')?.holder.textContent === '',
      'The empty block was lost',
    )
  }

  for (const [name, selector] of [
    ['bold', 'b,strong'],
    ['italic', 'i,em'],
    ['inline-code', 'code'],
    ['marker', 'mark'],
    ['Strikethrough', 's,strike'],
    ['small', 'small'],
  ]) {
    for (const backward of [false, true]) {
      await test(`${name}: apply/remove, ${backward ? 'backward' : 'forward'} selection`, async () => {
        await render()
        await select(backward ? 6 : 0, backward ? 0 : 6)
        const scroll = scrollY
        await tool(name)
        check(
          field().querySelector(selector)?.textContent === 'Format',
          'Formatting was not applied',
        )
        await key('z')
        check(html() === 'Format me', 'Undo did not remove formatting')
        range(backward)
        preserved(scroll)
        await key('y')
        check(
          field().querySelector(selector)?.textContent === 'Format',
          'Redo did not restore formatting',
        )
        check(getSelection().toString() === 'Format', 'Redo lost the formatted range')
        preserved(scroll)
        await select()
        await tool(name)
        check(!field().querySelector(selector), 'Formatting was not removed')
        await key('z')
        check(
          field().querySelector(selector)?.textContent === 'Format',
          'Undo did not restore removed formatting',
        )
        await key('z', true)
        check(!field().querySelector(selector), 'Shift+Ctrl+Z did not redo removal')
      })
    }
  }

  await test('typing and combined bold/italic have separate undo steps', async () => {
    await render()
    field().focus()
    getSelection().setBaseAndExtent(...point(9), ...point(9))
    await type('!')
    await select()
    await tool('bold')
    await select()
    await tool('italic')
    check(
      field().querySelector('b i,b em,strong i,strong em,i b,em b'),
      'Combined formatting missing',
    )
    await key('z')
    check(
      field().querySelector('b,strong') && !field().querySelector('i,em'),
      'Undo must remove italic alone',
    )
    await key('z')
    check(html() === 'Format me!', 'Undo must remove bold before typing')
    await key('z')
    check(html() === 'Format me', 'Undo must remove typing last')
    await key('y')
    await key('y')
    await key('y')
    check(
      field().querySelector('b i,b em,strong i,strong em,i b,em b') &&
        field().textContent === 'Format me!',
      'Redo lost typing or combined formatting',
    )
  })

  const address = async (value) => {
    const input = holder.querySelector('.link-options__url')
    input.focus()
    input.value = value
    input.dispatchEvent(new Event('input', { bubbles: true }))
    input.dispatchEvent(
      new KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true }),
    )
    await pause()
  }
  await test('link creation, address update and unlink each round trip once', async () => {
    await render()
    await select()
    await tool('link')
    await address('https://example.org/first')
    check(
      field().querySelector('a')?.getAttribute('href') === 'https://example.org/first',
      'Link not created',
    )
    await key('z')
    check(
      html() === 'Format me',
      'Link creation must undo in one step without a draft anchor',
    )
    range(false)
    await key('y')
    check(
      field().querySelector('a')?.getAttribute('href') === 'https://example.org/first',
      'Link redo failed',
    )
    await select()
    await address('https://example.org/second')
    await key('z')
    check(
      field().querySelector('a')?.getAttribute('href') === 'https://example.org/first',
      'Address undo failed',
    )
    await key('y')
    check(
      field().querySelector('a')?.getAttribute('href') === 'https://example.org/second',
      'Address redo failed',
    )
    await select()
    await tool('link')
    check(!field().querySelector('a'), 'Unlink failed')
    await key('z')
    check(
      field().querySelector('a')?.getAttribute('href') === 'https://example.org/second',
      'Unlink undo failed',
    )
    await key('y')
    check(
      !field().querySelector('a') && field().textContent === 'Format me',
      'Unlink redo lost text',
    )
  })
  await test('abandoning the link form leaves typing as the next undo', async () => {
    await render()
    field().focus()
    getSelection().setBaseAndExtent(...point(9), ...point(9))
    await type('!')
    await select()
    await tool('link')
    await pause(600)
    editor.inlineToolbar.close()
    await pause()
    await key('z')
    check(html() === 'Format me', 'An abandoned link added a phantom undo step')
    await key('y')
    check(html() === 'Format me!', 'Redo resurrected an abandoned link')
  })
  await test('link options round trip without losing nested formatting or other attributes', async () => {
    await render('<a href="/first" title="Keep"><b><i>Format</i></b></a> me')
    const initial = html()
    await select()
    check(html() === initial, 'Opening an existing link changed its HTML')
    // The selects belong to separate labels, so address them through their field order.
    const controls = () => [
      holder.querySelector('.link-options__fields input[type="checkbox"]'),
      ...holder.querySelectorAll('.link-options__select'),
    ]
    for (const [index, attribute, value] of [
      [0, 'target', '_blank'],
      [1, 'rel', 'nofollow'],
      [2, 'class', 'link-btn'],
    ]) {
      await select()
      const before = html()
      const control = controls()[index]
      control.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true }))
      if (index === 0) control.checked = true
      else control.value = value
      control.dispatchEvent(new Event('change', { bubbles: true }))
      await pause()
      check(
        field().querySelector('a').getAttribute(attribute) === value,
        `${attribute} was not applied`,
      )
      await key('z', false, control)
      check(html() === before, `${attribute} undo changed other link data`)
      await key('y')
      check(
        field().querySelector('a').getAttribute(attribute) === value,
        `${attribute} redo failed`,
      )
      check(
        field().querySelector('a b i')?.textContent === 'Format',
        'Nested formatting was lost',
      )
      check(
        field().querySelector('a').getAttribute('title') === 'Keep',
        'Link title was lost',
      )
    }
  })
  await test('typing inside a linked formatted range round trips without losing its link', async () => {
    await render('<a href="/first"><b>Format</b></a> me')
    field().focus()
    getSelection().setBaseAndExtent(...point(3), ...point(3))
    await type('X')
    await key('z')
    check(
      field().querySelector('a b')?.textContent === 'Format',
      'Undo lost the linked formatting',
    )
    check(
      getSelection().isCollapsed && getSelection().focusOffset === 3,
      'Undo lost the typing caret',
    )
    await key('y')
    check(
      field().querySelector('a b')?.textContent === 'ForXmat',
      'Redo lost linked text',
    )
    check(
      field().querySelector('a').getAttribute('href') === '/first',
      'Redo lost the address',
    )
  })
  report.done = true
})().catch((error) => {
  window.undoInlineChecks.results.push({
    name: 'suite',
    passed: false,
    error: String(error),
  })
  window.undoInlineChecks.done = true
})
