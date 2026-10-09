/* Shared by Panther and the interactive admin browser validation. */
window.undoBrowserChecks = { done: false, results: [] }
void (async () => {
  const report = window.undoBrowserChecks
  const holder = document.querySelector('.editorjs-holder')
  const editor = window.editors[holder.id]
  const pause = (ms) => new Promise((resolve) => setTimeout(resolve, ms))
  const clone = (value) => JSON.parse(JSON.stringify(value))
  const canonical = (value) =>
    JSON.stringify(value, (_key, item) =>
      item && typeof item === 'object' && !Array.isArray(item)
        ? Object.fromEntries(Object.entries(item).sort(([a], [b]) => a.localeCompare(b)))
        : item,
    )
  const state = () =>
    Promise.all(
      Array.from({ length: editor.blocks.getBlocksCount() }, async (_, index) => {
        const block = editor.blocks.getBlockByIndex(index)
        const value = await block.save()
        return clone({
          id: block.id,
          type: block.name,
          data: value.data,
          tunes: value.tunes,
        })
      }),
    )
  const key = (name) =>
    holder.dispatchEvent(
      new KeyboardEvent('keydown', {
        key: name,
        ctrlKey: true,
        bubbles: true,
        cancelable: true,
      }),
    )
  const check = (condition, message) => {
    if (!condition) throw new Error(message)
  }
  const expectState = async (expected) => {
    for (let attempt = 0; attempt < 80; attempt++) {
      if (canonical(await state()) === canonical(expected)) return
      await pause(25)
    }
    throw new Error(
      `Expected ${canonical(expected)}; received ${canonical(await state())}`,
    )
  }
  const paragraph = (id, text) => ({ id, type: 'paragraph', data: { text } })
  const fixtures = {
    header: [
      { text: 'Heading', level: 2 },
      { text: 'Changed heading', level: 3 },
    ],
    list: [
      { style: 'unordered', items: [{ content: 'First', items: [], meta: {} }] },
      {
        style: 'ordered',
        items: [
          {
            content: 'Changed',
            items: [{ content: 'Nested', items: [], meta: {} }],
            meta: {},
          },
        ],
      },
    ],
    image: [
      { media: '3.jpg', caption: 'Image' },
      { media: '1.jpg', caption: 'Changed image' },
    ],
    gallery: [
      { items: [{ media: '3.jpg', caption: 'One' }] },
      {
        items: [
          { media: '1.jpg', caption: 'Two' },
          { media: '3.jpg', caption: 'Three' },
        ],
      },
    ],
    embed: [
      {
        serviceUrl: 'https://www.youtube.com/watch?v=Nwyylc9GQuQ',
        alternativeText: 'Video',
        media: '3.jpg',
      },
      { alternativeText: 'Changed video' },
    ],
    delimiter: [{}, {}],
    groupStart: [
      { anchor: '', class: 'group', collapsible: false, legacy: false },
      { class: 'changed-group' },
    ],
    groupEnd: [{ collapsible: true, legacy: false, args: '' }, { args: "'background'" }],
    notice: [
      { level: 'note', title: 'Notice', text: 'Text' },
      { level: 'warning', title: 'Changed', text: 'Changed text' },
    ],
    quote: [
      { text: 'Quote', caption: 'Author', alignment: 'left' },
      { text: 'Changed quote', caption: 'Changed author', alignment: 'center' },
    ],
    table: [
      {
        withHeadings: true,
        content: [
          ['A', 'B'],
          ['C', 'D'],
        ],
      },
      {
        content: [
          ['Changed', 'B'],
          ['C', 'D'],
          ['E', 'F'],
        ],
      },
    ],
    attaches: [
      { title: 'Download', file: { media: '1.jpg', size: 42 } },
      { title: 'Changed download' },
    ],
    pages_list: [
      {
        kw: 'content:fun',
        display: 'list',
        order: 'publishedAt ↓',
        max: '9',
        maxPages: '0',
      },
      { kw: 'content:changed', max: '3' },
    ],
    card_list: [
      {
        items: [
          {
            id: 'custom-card',
            title: 'Card',
            image: '1.jpg',
            link: 'https://example.org',
            description: 'Description',
          },
        ],
      },
      {
        items: [
          {
            id: 'custom-card',
            title: 'Changed card',
            image: '3.jpg',
            link: 'https://example.org/changed',
            description: 'Changed description',
          },
        ],
      },
    ],
    codeBlock: [
      { html: 'const before = 1', language: 'javascript' },
      { html: 'const after = 2', language: 'javascript' },
    ],
    paragraph: [
      { text: 'Paragraph' },
      {
        text: '<b>Bold</b> <i>Italic</i> <code>Code</code> <mark>Marker</mark> <small>Small</small> <a href="https://example.org">Link</a> <s>Strike</s>',
      },
    ],
    raw: [{ html: '<div>Before</div>' }, { html: '<div>After</div>' }],
    snippet: [
      { name: 'history-fixture', params: { title: 'Before' } },
      { params: { title: 'After', count: 2 } },
    ],
    quiz: [
      {
        title: 'Quiz',
        questions: [
          {
            q: 'Question',
            answers: [{ a: 'Yes', correct: true }, { a: 'No' }],
          },
        ],
      },
      {
        title: 'Changed quiz',
        questions: [
          {
            q: 'Changed question',
            answers: [{ a: 'Maybe' }, { a: 'Certainly', correct: true }],
          },
        ],
      },
    ],
  }
  const tuneChanges = {
    header: {
      anchor: 'history-heading',
      class: 'history-class',
      textAlign: 'center',
    },
    image: {
      linkTune: { url: 'https://example.org', targetBlank: true, hideForBot: true },
    },
    gallery: { clickableTune: { value: true } },
    delimiter: { anchor: 'changed' },
  }
  try {
    const configured = Object.entries(window.editorjsConfig.tools)
      .filter(([, tool]) => !tool.class.isInline && !tool.class.isTune)
      .map(([name]) => name)
      .sort()
    check(
      canonical(configured) === canonical(Object.keys(fixtures).sort()),
      `Fixture coverage differs from configured blocks: ${configured}`,
    )
    report.results.push({
      name: 'all configured block types have fixtures',
      passed: true,
    })
    const tunes = Object.entries(window.editorjsConfig.tools)
      .filter(([, tool]) => tool.class.isTune)
      .map(([name]) => name)
      .sort()
    check(
      canonical(tunes) ===
        canonical([...new Set(Object.values(tuneChanges).flatMap(Object.keys))].sort()),
      'All configured tunes need a round-trip fixture',
    )
    report.results.push({ name: 'all configured tunes have fixtures', passed: true })
    for (const [type, [data, changes]] of Object.entries(fixtures)) {
      try {
        await editor.blocks.render({
          blocks: [
            paragraph('before', 'Before'),
            { id: 'subject', type, data },
            paragraph('empty', ''),
            paragraph('after', 'After'),
          ],
        })
        await pause(650)
        const original = await state()
        const untouched = editor.blocks.getById('after').holder
        holder.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true }))
        await editor.blocks.update('subject', changes, tuneChanges[type])
        await pause(100)
        const changed = await state()
        for (const [name, value] of Object.entries(tuneChanges[type] ?? {})) {
          check(
            canonical(changed.find((block) => block.id === 'subject').tunes[name]) ===
              canonical(value),
            `${type}: ${name} fixture must apply`,
          )
        }
        check(
          canonical(original) !== canonical(changed),
          `${type} fixture must change saved data`,
        )
        key('z')
        await expectState(original)
        check(
          editor.blocks.getById('after').holder === untouched,
          `${type} remounted an untouched block`,
        )
        key('y')
        await expectState(changed)
        report.results.push({
          name: `${type}: edit, undo, redo, empty neighbour and untouched identity`,
          passed: true,
        })
        holder.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true }))
        editor.blocks.move(0, editor.blocks.getBlockIndex('subject'))
        await pause(80)
        const moved = await state()
        key('z')
        await expectState(changed)
        key('y')
        await expectState(moved)
        report.results.push({
          name: `${type}: reorder, undo and redo`,
          passed: true,
        })
        holder.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true }))
        editor.blocks.delete(editor.blocks.getBlockIndex('subject'))
        await pause(80)
        const deleted = await state()
        key('z')
        await expectState(moved)
        key('y')
        await expectState(deleted)
        report.results.push({
          name: `${type}: delete, recreate and delete again`,
          passed: true,
        })
      } catch (error) {
        report.results.push({ name: type, passed: false, error: String(error) })
      }
    }
  } catch (error) {
    report.results.push({ name: 'setup', passed: false, error: String(error) })
  }
  report.done = true
})()
