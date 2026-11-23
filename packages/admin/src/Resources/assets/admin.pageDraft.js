import { markdownModel, openDraft, textChange } from './admin.draftSession.js'

const controls = document.querySelector('[data-pw-page-draft]')
if (controls) bindPageDraft(controls)

function isLegacyBlockContent(value) {
  if (!value.trimStart().startsWith('{')) return false
  try {
    return Array.isArray(JSON.parse(value).blocks)
  } catch {
    return false
  }
}

export function bindPageDraft(controls) {
  const form = document.querySelector('form[data-pw-page-revision-form]')
  const button = controls.querySelector('button')
  const status = controls.querySelector('[role="status"]')
  const revision = form.querySelector('[data-pw-page-revision]')
  const body = () => form.querySelector('[name$="[mainContent]"]')
  if (!body() || isLegacyBlockContent(body().value)) {
    controls.hidden = true
    return
  }
  const publication = form.querySelector('[name$="[publishedAt]"]')
  let initialPublication = publication?.value
  const publicationPending = () => publication?.value !== initialPublication
  const saveLabels = new Map(Array.from(
    document.querySelectorAll('.action-saveAndReturn .action-label, .action-saveAndContinue .action-label'),
    (label) => [label, label.textContent],
  ))
  const holderId = body().getAttribute('data-editorjs')
  const holder = holderId ? document.getElementById(holderId) : null
  let initial = body().value
  let session = null
  let editedAt = -Infinity
  let preparedAt = -Infinity
  let composing = false
  let approved = null
  let saving = false
  let stale = false
  const modeLabel = button.textContent
  const view = {
    saved(current, complete) {
      if (revision.value === current && !complete) return
      revision.value = current
      if (complete) {
        initial = view.read()
        form.dispatchEvent(new CustomEvent('pw:live-saved', { detail: body() }))
      }
    },
    read: () => body()?.getAttribute('data-editor') === 'json' ? undefined : body()?.value,
    equal: (left, right) => left === right,
    wait: () => publicationPending() || composing || Boolean(form.querySelector('[data-pw-editor-switching]')) ||
      body()?.hasAttribute('data-pw-baseline-pending') ||
      view.read() === undefined,
    deferRemote: () => performance.now() - editedAt < 420,
    async prepare() {
      if (preparedAt >= editedAt) return
      const edit = editedAt
      await body()?.pwEditor?.flushDraft?.()
      preparedAt = edit
    },
    async apply(markdown) {
      const field = body()
      if (field.value === markdown) return
      if (field.pwEditor?.applyDraft) {
        await field.pwEditor.applyDraft(markdown)
      } else if (field.pwMonaco) {
        const { editor } = field.pwMonaco
        const model = editor.getModel()
        const { from, to, insert } = textChange(field.value, markdown)
        const start = model.getPositionAt(from)
        const end = model.getPositionAt(to)
        editor.executeEdits('pushword-draft', [{ range: {
          startLineNumber: start.lineNumber, startColumn: start.column,
          endLineNumber: end.lineNumber, endColumn: end.column,
        }, text: insert }])
      } else if (field instanceof HTMLTextAreaElement) {
        const { from, to, insert } = textChange(field.value, markdown)
        field.setRangeText(insert, from, to, 'preserve')
        field.dispatchEvent(new Event('input', { bubbles: true }))
      }
    },
  }

  function display(state) {
    if (state === 'stale') stale = true
    if (state === 'pending' && publicationPending()) state = 'settingsPending'
    const message = controls.dataset[state]
    if (state === 'connected' && status.textContent === controls.dataset.updated) return
    if (message !== status.textContent) status.textContent = message
  }

  if (body().hasAttribute('data-pw-baseline-pending')) {
    form.addEventListener('pw:baseline-ready', () => { initial = body().value }, { once: true })
  }

  function leave() {
    session?.close()
    session = null
    button.textContent = modeLabel
    button.setAttribute('aria-pressed', 'false')
    delete form.dataset.pwDraftActive
    for (const [label, text] of saveLabels) label.textContent = text
    if (!stale) status.textContent = ''
  }

  button.addEventListener('click', async () => {
    if (saving || view.read() === undefined) return
    if (session) {
      saving = true
      try {
        await body()?.pwEditor?.flushDraft?.()
        if (!stale && !publicationPending()) await session.flush()
        leave()
      } finally {
        saving = false
      }
      return
    }

    try {
      await body()?.pwEditor?.flushDraft?.()
    } catch {
      display('offline')
      return
    }
    stale = false
    button.textContent = controls.dataset.classic
    button.setAttribute('aria-pressed', 'true')
    form.dataset.pwDraftActive = ''
    for (const label of saveLabels.keys()) label.textContent = controls.dataset.settings
    session = openDraft({
      url: controls.dataset.url,
      revision: revision.value,
      token: controls.dataset.token,
      model: markdownModel,
      initial,
      view,
      status: display,
    })
    if (publicationPending()) display('settingsPending')
  })

  form.addEventListener('input', (event) => {
    if (event.target === body()) session?.capture()
    if (holder?.contains(event.target)) editedAt = performance.now()
  })
  publication?.addEventListener('change', () => {
    if (session) display(publicationPending() ? 'settingsPending' : 'pending')
  })
  form.addEventListener('compositionstart', () => { composing = true })
  form.addEventListener('compositionend', () => { composing = false })

  window.addEventListener('submit', async (event) => {
    if (event.target !== form || !session || publicationPending() || (approved?.revision === revision.value && approved?.body === view.read())) return
    event.preventDefault()
    event.stopImmediatePropagation()
    if (saving) return
    saving = true
    try {
      await body()?.pwEditor?.flushDraft?.()
      if (await session.flush()) {
        approved = { revision: revision.value, body: view.read() }
        form.requestSubmit(event.submitter)
      }
    } catch {
      display('offline')
    } finally {
      saving = false
    }
  }, { capture: true })

  form.addEventListener('htmx:after:request', (event) => {
    const saved = event.detail?.ctx?.response?.headers?.get('X-Pushword-Page-Revision')
    if (saved) {
      initial = approved?.body ?? view.read()
      initialPublication = publication?.value
    }
    if (session && saved && saved !== approved?.revision) leave()
    approved = null
  })
  window.addEventListener('pagehide', (event) => {
    if (!event.persisted) session?.close()
  })
}
