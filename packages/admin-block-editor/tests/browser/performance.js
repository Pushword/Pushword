/* Count repeated work instead of imposing machine-dependent timing thresholds. */
window.editorPerformanceChecks = { done: false, results: [] }
void (async () => {
  const report = window.editorPerformanceChecks
  const holder = document.querySelector('.editorjs-holder')
  const editor = window.editors[holder.id]
  const pause = (ms) => new Promise((resolve) => setTimeout(resolve, ms))
  const check = (name, value, detail) => {
    report.results.push({ name, passed: Boolean(value), detail })
  }
  const wait = async (condition, operation) => {
    for (let i = 0; i < 100; i++) {
      if (condition()) return
      await pause(25)
    }
    throw new Error(`Timed out waiting for ${operation}`)
  }
  const paragraph = (id, text) => ({ id, type: 'paragraph', data: { text } })
  const exportMarkdown = async () =>
    new window.EditorJsExportMarkdown(editor, await editor.save()).exportToMarkdown()
  const key = (name) =>
    holder.dispatchEvent(
      new KeyboardEvent('keydown', {
        key: name,
        ctrlKey: true,
        bubbles: true,
        cancelable: true,
      }),
    )
  const tool = editor.configuration.tools.paragraph.class
  const save = tool.prototype.save
  const exportBlock = tool.exportToMarkdown
  const onChange = editor.configuration.onChange
  let untouchedReads = 0
  let editedReads = 0
  let exports = 0
  try {
    await editor.blocks.render({
      blocks: Array.from({ length: 300 }, (_, i) =>
        paragraph(`perf-${i}`, `Paragraph ${i} with <b>formatting</b>`),
      ),
    })
    await pause(800)
    await exportMarkdown()
    editor.configuration.onChange = () => {}
    tool.prototype.save = function (element) {
      if (element.closest('.ce-block').dataset.id === 'perf-0') editedReads++
      else untouchedReads++
      return save.call(this, element)
    }
    tool.exportToMarkdown = function (...args) {
      exports++
      return exportBlock.apply(this, args)
    }
    const field = editor.blocks
      .getById('perf-0')
      .holder.querySelector('[contenteditable]')
    field.focus()
    const range = document.createRange()
    range.selectNodeContents(field)
    range.collapse(false)
    getSelection().removeAllRanges()
    getSelection().addRange(range)
    await pause(40)
    editedReads = untouchedReads = 0
    for (const character of '12345') {
      field.dispatchEvent(
        new InputEvent('beforeinput', {
          inputType: 'insertText',
          data: character,
          bubbles: true,
        }),
      )
      document.execCommand('insertText', false, character)
      await pause(30)
    }
    check(
      'history reads only the edited block among 300 blocks',
      untouchedReads === 0 && editedReads >= 5 && editedReads <= 10,
      { untouchedReads, editedReads },
    )
    await exportMarkdown()
    const formatted = exports
    await exportMarkdown()
    check(
      'only the changed paragraph is converted, with repeated export cached',
      formatted === 1 && exports === 1,
      { formatted, repeated: exports },
    )
    key('z')
    await wait(
      () => !editor.blocks.getById('perf-0').holder.textContent.includes('12345'),
      '300-block undo',
    )
    key('y')
    await wait(
      () => editor.blocks.getById('perf-0').holder.textContent.includes('12345'),
      '300-block redo',
    )
    check(
      'long-document undo and redo retain formatting and all blocks',
      editor.blocks.getBlocksCount() === 300 &&
        editor.blocks
          .getById('perf-0')
          .holder.querySelector('b')
          .textContent.startsWith('formatting'),
    )

    tool.prototype.save = save
    tool.exportToMarkdown = exportBlock
    editor.configuration.onChange = onChange
    await editor.blocks.render({
      blocks: [
        ...Array.from({ length: 80 }, (_, i) =>
          paragraph(`spacer-${i}`, 'Text before code'),
        ),
        { id: 'deferred-raw', type: 'raw', data: { html: '<p>Deferred raw</p>' } },
        {
          id: 'deferred-code',
          type: 'codeBlock',
          data: { html: 'const deferred = true', language: 'javascript' },
        },
      ],
    })
    window.scrollTo({ top: 0, behavior: 'instant' })
    await pause(800)
    const instance = (id) =>
      window.monaco?.editor
        .getEditors()
        .find((item) => editor.blocks.getById(id).holder.contains(item.getDomNode()))
    const saved = await editor.save()
    check(
      'offscreen code is saved without constructing Monaco instances',
      !instance('deferred-raw') &&
        !instance('deferred-code') &&
        saved.blocks.find((block) => block.id === 'deferred-raw').data.html ===
          '<p>Deferred raw</p>' &&
        saved.blocks.find((block) => block.id === 'deferred-code').data.html ===
          'const deferred = true',
    )
    editor.blocks
      .getById('deferred-raw')
      .holder.querySelector('textarea')
      .focus({ preventScroll: true })
    await wait(
      () => Boolean(instance('deferred-raw')),
      'Raw editor activation after focus',
    )
    editor.blocks
      .getById('deferred-code')
      .holder.scrollIntoView({ block: 'center', behavior: 'instant' })
    await wait(
      () => Boolean(instance('deferred-code')),
      'Code editor activation after scroll',
    )
    check(
      'deferred editors activate on keyboard focus and viewport entry',
      instance('deferred-raw').getValue() === '<p>Deferred raw</p>' &&
        instance('deferred-code').getValue() === 'const deferred = true',
    )

    const cases = [
      {
        name: 'anchor',
        type: 'header',
        data: { text: 'Heading', level: 2 },
        selector: 'input.cdx-anchor-tune-input',
        value: 'perf-anchor',
      },
      {
        name: 'class',
        type: 'header',
        data: { text: 'Heading', level: 2 },
        selector: 'textarea.cdx-anchor-tune-input',
        value: 'perf-class',
      },
      {
        name: 'alignment',
        type: 'header',
        data: { text: 'Heading', level: 2 },
        selector: '[title="Align center"]',
      },
      {
        name: 'link',
        type: 'image',
        data: { media: '1.jpg', caption: 'Image' },
        selector: '[contenteditable="true"]',
        value: 'https://example.org/performance',
      },
      {
        name: 'clickable',
        type: 'gallery',
        data: { items: [{ media: '1.jpg', caption: 'Image' }] },
        selector: '.ce-inline-tool.cdx-input',
      },
    ]
    for (const tune of cases) {
      editor.toolbar.close()
      await editor.blocks.render({
        blocks: [{ id: 'tune-subject', type: tune.type, data: tune.data }],
      })
      await pause(650)
      const state = async () => {
        const block = editor.blocks.getById('tune-subject')
        if (!block) return null
        const saved = await block.save()
        return JSON.stringify({ data: saved.data, tunes: saved.tunes })
      }
      const before = await state()
      if (tune.type === 'image') {
        await wait(
          () => editor.blocks.getById('tune-subject')
            .holder.querySelector('.image-tool--filled'),
          'image preview before link settings',
        )
      }
      editor.caret.setToBlock(0, 'start')
      editor.toolbar.open()
      editor.toolbar.toggleBlockSettings(true)
      await wait(
        () => holder.querySelector(`.ce-settings ${tune.selector}`),
        `${tune.name} settings control`,
      )
      const control = holder.querySelector(`.ce-settings ${tune.selector}`)
      if (!control) throw new Error(`Missing ${tune.name} control`)
      control.focus()
      if (tune.value !== undefined) {
        control.dispatchEvent(
          new InputEvent('beforeinput', { inputType: 'insertText', bubbles: true }),
        )
        if ('value' in control) control.value = tune.value
        else control.textContent = tune.value
        control.dispatchEvent(new Event('input', { bubbles: true }))
      } else control.click()
      await pause(30)
      const after = await state()
      key('z')
      await pause(80)
      const undone = await state()
      key('y')
      await pause(80)
      const redone = await state()
      check(
        `${tune.name} tune keeps undo and redo through the real settings control`,
        before !== after && undone === before && redone === after,
        { before, after, undone, redone },
      )
    }
  } catch (error) {
    report.results.push({
      name: 'performance scenario',
      passed: false,
      error: String(error),
    })
  } finally {
    tool.prototype.save = save
    tool.exportToMarkdown = exportBlock
    editor.configuration.onChange = onChange
    report.done = true
  }
})()
