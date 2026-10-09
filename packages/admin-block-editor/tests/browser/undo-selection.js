/* Exercise actual DOM editing, native selections and embedded Monaco editors. */
window.undoSelectionChecks = { done: false, results: [] }
void (async () => {
  const report = window.undoSelectionChecks
  const holder = document.querySelector('.editorjs-holder')
  const editor = window.editors[holder.id]
  const pause = (ms) => new Promise((resolve) => setTimeout(resolve, ms))
  const check = (value, message) => {
    if (!value) throw new Error(message)
  }
  const test = async (name, run) => {
    try {
      await run()
      report.results.push({ name, passed: true })
    } catch (error) {
      report.results.push({ name, passed: false, error: String(error) })
    }
  }
  const paragraph = (id, text) => ({ id, type: 'paragraph', data: { text } })
  const render = async (blocks) => {
    await editor.blocks.render({ blocks })
    await pause(650)
  }
  const key = (name, shiftKey = false) =>
    holder.dispatchEvent(
      new KeyboardEvent('keydown', {
        key: name,
        shiftKey,
        ctrlKey: true,
        bubbles: true,
        cancelable: true,
      }),
    )
  const focus = (element, offset = element.textContent.length) => {
    editor.caret.setToBlock(
      editor.blocks.getBlockIndex(element.closest('.ce-block').dataset.id),
    )
    element.scrollIntoView({ block: 'center', behavior: 'instant' })
    element.focus({ preventScroll: true })
    const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT)
    let node = element
    while (walker.nextNode()) {
      node = walker.currentNode
      if (offset <= node.textContent.length) break
      offset -= node.textContent.length
    }
    getSelection().setBaseAndExtent(node, offset, node, offset)
  }
  const type = (text) => {
    const element = document.activeElement
    element.dispatchEvent(
      new InputEvent('beforeinput', {
        inputType: 'insertText',
        data: text,
        bubbles: true,
      }),
    )
    document.execCommand('insertText', false, text)
  }
  const selection = () => {
    const native = getSelection()
    const element =
      native.focusNode instanceof Element
        ? native.focusNode
        : native.focusNode?.parentElement
    const field = element?.closest('[contenteditable]')
    if (!field) return null
    const range = document.createRange()
    range.selectNodeContents(field)
    range.setEnd(native.focusNode, native.focusOffset)
    return {
      block: field.closest('.ce-block').dataset.id,
      field,
      offset: range.toString().length,
    }
  }
  const wait = async (condition) => {
    for (let i = 0; i < 80; i++) {
      if (await condition()) return
      await pause(25)
    }
    throw new Error('Timed out waiting for history restoration')
  }
  const text = (id) =>
    editor.blocks.getById(id)?.holder.querySelector('.ce-paragraph')?.textContent
  await test('pending typing, redo, new branch and empty document', async () => {
    await render([paragraph('p', 'Original')])
    focus(holder.querySelector('.ce-paragraph'))
    type(' first')
    await pause(550)
    type(' second')
    key('z')
    await wait(() => text('p') === 'Original first')
    key('y')
    await wait(() => text('p') === 'Original first second')
    key('z')
    await wait(() => text('p') === 'Original first')
    type(' branch')
    await pause(30)
    key('y')
    await pause(50)
    check(
      text('p') === 'Original first branch',
      'A new edit must discard the redo branch',
    )
    const field = holder.querySelector('.ce-paragraph')
    focus(field, 0)
    getSelection().setBaseAndExtent(
      field.firstChild,
      0,
      field.firstChild,
      field.textContent.length,
    )
    field.dispatchEvent(
      new InputEvent('beforeinput', {
        inputType: 'deleteContentBackward',
        bubbles: true,
      }),
    )
    document.execCommand('delete')
    key('z')
    await wait(() => text('p') === 'Original first branch')
    key('y')
    await wait(() => text('p') === '')
  })
  await test('distant blocks keep the edited caret and viewport', async () => {
    await render(
      Array.from({ length: 60 }, (_, index) =>
        paragraph(`p${index}`, `Paragraph ${index}`),
      ),
    )
    focus(editor.blocks.getById('p5').holder.querySelector('[contenteditable]'))
    type(' one')
    await pause(80)
    focus(editor.blocks.getById('p45').holder.querySelector('[contenteditable]'))
    type(' two')
    await pause(40)
    const scroll = scrollY
    key('z')
    await wait(() => text('p45') === 'Paragraph 45')
    await pause(50)
    check(
      selection()?.block === 'p45' && selection()?.offset === 12,
      `Incorrect selection: ${JSON.stringify(selection())}`,
    )
    check(Math.abs(scrollY - scroll) < 2, `Undo scrolled ${scrollY - scroll}px`)
    key('y')
    await wait(() => text('p45') === 'Paragraph 45 two')
    await pause(50)
    check(selection()?.offset === 16, 'Redo must restore the end of the last edit')
    check(Math.abs(scrollY - scroll) < 2, `Redo scrolled ${scrollY - scroll}px`)
  })
  await test('first item in a long nested list keeps its caret and viewport', async () => {
    await render([
      {
        id: 'list',
        type: 'list',
        data: {
          style: 'unordered',
          items: Array.from({ length: 40 }, (_, i) => ({
            content: `Item ${i}`,
            items: i === 0 ? [{ content: 'Nested item', items: [], meta: {} }] : [],
            meta: {},
          })),
        },
      },
    ])
    focus(holder.querySelector('.cdx-list__item-content'))
    type(' extra')
    await pause(50)
    const scroll = scrollY
    key('z')
    await wait(
      () => holder.querySelector('.cdx-list__item-content').textContent === 'Item 0',
    )
    await pause(50)
    check(
      selection()?.field === holder.querySelector('.cdx-list__item-content') &&
        selection()?.offset === 6,
      'Undo moved to a different list item',
    )
    check(Math.abs(scrollY - scroll) < 2, `List undo scrolled ${scrollY - scroll}px`)
    key('y')
    await wait(
      () =>
        holder.querySelector('.cdx-list__item-content').textContent === 'Item 0 extra',
    )
  })
  await test('table cell selection survives undo and redo', async () => {
    await render([
      {
        id: 'table',
        type: 'table',
        data: {
          content: [
            ['AA', 'BB'],
            ['CC', 'DD'],
          ],
        },
      },
    ])
    focus(holder.querySelectorAll('.tc-cell')[3], 1)
    type('X')
    await pause(40)
    key('z')
    await wait(() => holder.querySelectorAll('.tc-cell')[3].textContent === 'DD')
    check(
      selection()?.field === holder.querySelectorAll('.tc-cell')[3] &&
        selection()?.offset === 1,
      'Undo lost the table cell or offset',
    )
    key('y')
    await wait(() => holder.querySelectorAll('.tc-cell')[3].textContent === 'DXD')
    check(selection()?.offset === 2, 'Redo lost the table cell offset')
  })
  await test('quiz input range survives text replacement', async () => {
    await render([
      {
        id: 'quiz',
        type: 'quiz',
        data: {
          title: 'Before quiz',
          questions: [
            {
              q: 'Question',
              answers: [{ a: 'Yes', correct: true }, { a: 'No' }],
            },
          ],
        },
      },
    ])
    const input = holder.querySelector('.cdx-quiz__title')
    input.focus()
    input.setSelectionRange(0, 6, 'backward')
    type('After')
    await pause(40)
    key('z')
    await wait(() => holder.querySelector('.cdx-quiz__title').value === 'Before quiz')
    const restored = holder.querySelector('.cdx-quiz__title')
    check(
      document.activeElement === restored &&
        restored.selectionStart === 0 &&
        restored.selectionEnd === 6 &&
        restored.selectionDirection === 'backward',
      'Undo lost the input range',
    )
    key('y')
    await wait(() => holder.querySelector('.cdx-quiz__title').value === 'After quiz')
  })
  await test('rapid undo and alternate redo keep their order', async () => {
    await render([
      paragraph('one', 'One'),
      paragraph('two', 'Two'),
      paragraph('three', 'Three'),
    ])
    for (const id of ['one', 'two', 'three']) {
      focus(editor.blocks.getById(id).holder.querySelector('[contenteditable]'))
      type('!')
      await pause(25)
    }
    key('z')
    key('z')
    key('z')
    key('z', true)
    await wait(
      () => text('one') === 'One!' && text('two') === 'Two' && text('three') === 'Three',
    )
    key('y')
    key('y')
    await wait(() => text('three') === 'Three!')
  })
  for (const kind of ['raw', 'codeBlock']) {
    await test(`${kind}: shared history and stable Monaco instance`, async () => {
      const source = 'initial' + '\n// Another line'.repeat(80)
      await render([
        paragraph('p', 'Text'),
        {
          id: 'code',
          type: kind,
          data: { html: source, language: 'javascript' },
        },
      ])
      const instance = () =>
        window.monaco.editor
          .getEditors()
          .find((item) =>
            editor.blocks.getById('code').holder.contains(item.getDomNode()),
          )
      await wait(() => Boolean(instance()))
      const embedded = instance()
      embedded.focus()
      embedded.setPosition({ lineNumber: 1, column: 8 })
      window.scrollTo({
        top:
          scrollY +
          editor.blocks.getById('code').holder.getBoundingClientRect().top -
          150,
        behavior: 'instant',
      })
      const scroll = scrollY
      const input = document.activeElement
      input.dispatchEvent(
        new InputEvent('beforeinput', {
          inputType: 'insertText',
          data: '!',
          bubbles: true,
        }),
      )
      embedded.trigger('keyboard', 'type', { text: '!' })
      await pause(40)
      key('z')
      await wait(() => instance().getValue() === source)
      check(instance() === embedded, 'Undo remounted Monaco')
      check(embedded.getPosition().column === 8, 'Undo lost the Monaco cursor')
      check(Math.abs(scrollY - scroll) < 2, `Monaco undo scrolled ${scrollY - scroll}px`)
      key('y')
      await wait(() => instance().getValue() === source.replace('initial', 'initial!'))
      check(embedded.getPosition().column === 9, 'Redo lost the Monaco cursor')
      focus(editor.blocks.getById('p').holder.querySelector('[contenteditable]'))
      type(' edited')
      key('z')
      await wait(() => text('p') === 'Text')
      check(
        instance() === embedded && selection()?.block === 'p',
        'An unrelated edit stole focus or rebuilt Monaco',
      )
    })
  }
  await test('snippet textarea stays editable and keeps its range', async () => {
    await render([
      {
        id: 'snippet',
        type: 'snippet',
        data: { name: 'history-fixture', params: { title: 'Before' } },
      },
    ])
    holder.querySelector('.preview-wrapper').click()
    const input = holder.querySelector('textarea')
    input.focus()
    const start = input.value.indexOf('Before')
    input.setSelectionRange(start, start + 6)
    type('After')
    await pause(40)
    key('z')
    await wait(() => holder.querySelector('textarea').value.includes('Before'))
    const restored = holder.querySelector('textarea')
    check(
      document.activeElement === restored &&
        restored.selectionStart === start &&
        restored.selectionEnd === start + 6,
      'Snippet edit mode or range was lost',
    )
    key('y')
    await wait(() => holder.querySelector('textarea').value.includes('After'))
  })
  await test('group pair deletion is atomic even with immediate undo', async () => {
    for (const collapsible of [false, true]) {
      await render([
        {
          id: 'start',
          type: 'groupStart',
          data: { anchor: '', class: '', collapsible, legacy: false },
        },
        paragraph('inside', 'Inside'),
        {
          id: 'end',
          type: 'groupEnd',
          data: { collapsible, legacy: false, args: '' },
        },
        paragraph('outside', 'Outside'),
      ])
      focus(editor.blocks.getById('inside').holder.querySelector('[contenteditable]'))
      holder.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true }))
      editor.blocks.delete(0)
      key('z')
      await wait(() => editor.blocks.getBlocksCount() === 4)
      await pause(40)
      check(
        editor.blocks.getById('start') && editor.blocks.getById('end'),
        'Undo must restore both markers',
      )
      key('y')
      await wait(() => editor.blocks.getBlocksCount() === 2)
      check(
        text('inside') === 'Inside' && text('outside') === 'Outside',
        'Marker deletion must retain content',
      )
    }
  })
  await test('fresh group insertion is one reversible action', async () => {
    await render([paragraph('p', 'Before')])
    focus(holder.querySelector('.ce-paragraph'))
    holder.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true }))
    editor.blocks.insert('groupStart', {}, {}, 1, false)
    key('z')
    await wait(() => editor.blocks.getBlocksCount() === 1)
    await pause(40)
    check(
      editor.blocks.getBlocksCount() === 1,
      'A delayed closing marker appeared after undo',
    )
    key('y')
    await wait(() => editor.blocks.getBlocksCount() === 4)
    check(
      editor.blocks.getBlockByIndex(3).name === 'groupEnd',
      'Redo must restore the complete group',
    )
  })
  await test('inline formatting and tune data survive history', async () => {
    await render([paragraph('p', 'Format me')])
    const field = holder.querySelector('.ce-paragraph')
    focus(field, 0)
    getSelection().setBaseAndExtent(field.firstChild, 0, field.firstChild, 6)
    field.dispatchEvent(
      new InputEvent('beforeinput', { inputType: 'formatBold', bubbles: true }),
    )
    document.execCommand('bold')
    await pause(40)
    check(
      field.querySelector('b,strong'),
      'The browser must actually format the selection',
    )
    key('z')
    await wait(() => !holder.querySelector('.ce-paragraph b,.ce-paragraph strong'))
    check(getSelection().toString() === 'Format', 'Undo lost the formatted range')
    key('y')
    await wait(() =>
      Boolean(holder.querySelector('.ce-paragraph b,.ce-paragraph strong')),
    )
    const before = await editor.blocks.getById('p').save()
    holder.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true }))
    await editor.blocks.update('p', {}, { anchor: 'history-anchor', textAlign: 'center' })
    await pause(40)
    key('z')
    await wait(
      async () =>
        (await editor.blocks.getById('p').save()).tunes.anchor === before.tunes.anchor,
    )
    key('y')
    await wait(
      async () =>
        (await editor.blocks.getById('p').save()).tunes.anchor === 'history-anchor',
    )
    check(
      (await editor.blocks.getById('p').save()).tunes.textAlign === 'center',
      'Redo lost alignment',
    )
  })
  await test('paragraph split and merge preserve text and caret', async () => {
    await render([paragraph('p', 'Hello world')])
    focus(holder.querySelector('.ce-paragraph'), 5)
    document.activeElement.dispatchEvent(
      new KeyboardEvent('keydown', {
        key: 'Enter',
        keyCode: 13,
        bubbles: true,
        cancelable: true,
      }),
    )
    await wait(() => editor.blocks.getBlocksCount() === 2)
    const split = Array.from(holder.querySelectorAll('.ce-paragraph')).map(
      (field) => field.textContent,
    )
    check(split.join('') === 'Hello world', `Split changed text: ${split}`)
    key('z')
    await wait(() => editor.blocks.getBlocksCount() === 1)
    check(
      text('p') === 'Hello world' && selection()?.offset === 5,
      'Split undo lost text or caret',
    )
    key('y')
    await wait(() => editor.blocks.getBlocksCount() === 2)
    focus(holder.querySelectorAll('.ce-paragraph')[1], 0)
    document.activeElement.dispatchEvent(
      new KeyboardEvent('keydown', {
        key: 'Backspace',
        keyCode: 8,
        bubbles: true,
        cancelable: true,
      }),
    )
    await wait(() => editor.blocks.getBlocksCount() === 1)
    key('z')
    await wait(() => editor.blocks.getBlocksCount() === 2)
    check(
      Array.from(holder.querySelectorAll('.ce-paragraph'))
        .map((field) => field.textContent)
        .join('') === 'Hello world',
      'Merge undo lost text',
    )
    key('y')
    await wait(() => editor.blocks.getBlocksCount() === 1)
  })
  await test('multi-block HTML paste is a single undo step', async () => {
    await render([paragraph('p', 'Before')])
    focus(holder.querySelector('.ce-paragraph'))
    const data = new DataTransfer()
    data.setData('text/plain', 'First\nSecond\nThird')
    data.setData('text/html', '<p>First</p><h2>Second</h2><p>Third</p>')
    document.activeElement.dispatchEvent(
      new ClipboardEvent('paste', {
        clipboardData: data,
        bubbles: true,
        cancelable: true,
      }),
    )
    await wait(() => editor.blocks.getBlocksCount() >= 3)
    await pause(50)
    const pasted = holder.querySelector('.codex-editor__redactor').textContent
    key('z')
    await wait(() => editor.blocks.getBlocksCount() === 1 && text('p') === 'Before')
    key('y')
    await wait(
      () => holder.querySelector('.codex-editor__redactor').textContent === pasted,
    )
  })
  await test('conversion of a block with the same id is reversible', async () => {
    await render([paragraph('p', 'Original')])
    focus(holder.querySelector('.ce-paragraph'))
    holder.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true }))
    editor.blocks.insert(
      'header',
      { text: 'Original', level: 2 },
      {},
      0,
      false,
      true,
      'p',
    )
    key('z')
    await wait(() => editor.blocks.getById('p').name === 'paragraph')
    check(text('p') === 'Original', 'Conversion undo lost the content')
    key('y')
    await wait(() => editor.blocks.getById('p').name === 'header')
  })
  await test('deleting selected blocks restores their selection and empty neighbours', async () => {
    await render([
      paragraph('a', 'First'),
      paragraph('b', 'Second'),
      paragraph('empty', ''),
    ])
    focus(editor.blocks.getById('b').holder.querySelector('[contenteditable]'))
    for (const id of ['a', 'b'])
      editor.blocks.getById(id).holder.classList.add('ce-block--selected')
    holder.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true }))
    editor.blocks.delete(1)
    editor.blocks.delete(0)
    key('z')
    await wait(() => editor.blocks.getBlocksCount() === 3)
    check(
      holder.querySelectorAll('.ce-block--selected').length === 2,
      'Undo lost the block selection',
    )
    check(
      text('a') === 'First' && text('b') === 'Second' && text('empty') === '',
      'Undo lost selected content',
    )
    key('y')
    await wait(() => editor.blocks.getBlocksCount() === 1)
    check(
      editor.blocks.getBlockByIndex(0).id === 'empty',
      'Redo removed the wrong empty block',
    )
  })
  report.done = true
})()
