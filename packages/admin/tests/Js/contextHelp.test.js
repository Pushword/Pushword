// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from 'vitest'
import '../../../core/src/Resources/assets/contextHelp.js'
const { initContextHelp } = window
import { showTitlePixelWidth } from '../../src/Resources/assets/admin.formHelpers.js'

afterEach(() => {
  document.body.innerHTML = ''
  vi.restoreAllMocks()
})

describe('contextual admin help', () => {
  it('keeps the live SEO counter outside the closed explanation and preserves its updates', () => {
    document.body.innerHTML = `<div data-pw-help="Help: Title" data-pw-help-summary="Characters:">
      <label for="title">Title</label><div class="form-widget"><input id="title" class="titleToMeasure" value="Example">
      <small class="form-help">Search engine title <span id="titleWidth"></span></small></div></div>`
    const counter = document.querySelector('#titleWidth')
    initContextHelp()
    showTitlePixelWidth()
    expect(document.querySelector('.pw-help-panel').contains(counter)).toBe(false)
    expect(counter.textContent).toBe('7')
    expect(document.querySelector('.pw-help-summary').textContent).toBe('Characters: 7')
    const input = document.querySelector('input')
    input.value = 'Another title'
    input.dispatchEvent(new Event('input'))
    expect(counter.textContent).toBe('13')
    expect(document.querySelector('label').htmlFor).toBe(input.id)
    expect(document.querySelector('button').type).toBe('button')
  })

  it('shows a title counter alone when the field has no summary', () => {
    document.body.innerHTML = `<div data-pw-help="Help: Title">
      <label for="title">Title</label><div class="form-widget"><input id="title" class="titleToMeasure" value="Example">
      <small class="form-help">Search engine title <span id="titleWidth"></span></small></div></div>`
    initContextHelp()
    showTitlePixelWidth()
    const summary = document.querySelector('.pw-help-summary')
    expect(summary.contains(document.querySelector('#titleWidth'))).toBe(true)
    expect(summary.textContent.trim()).toBe('7')
  })

  it('keeps a consequence visible and retains selectable HTML and links in the explanation', () => {
    document.body.innerHTML = `<div data-pw-help="Help: Consent" data-pw-help-summary="Clearing deletes clicks.">
      <label for="consent">Consent</label><div class="form-widget"><input id="consent" readonly aria-describedby="consent_errors">
      <small class="form-help"><code>consent</code> <a href="/documentation">Read more</a></small>
      <div id="consent_errors" role="alert">Invalid date</div></div></div>`
    const link = document.querySelector('a')
    const error = document.querySelector('[role="alert"]')
    initContextHelp()
    expect(document.querySelector('.pw-help-summary').textContent).toBe(
      'Clearing deletes clicks.',
    )
    expect(document.querySelector('.pw-help-panel a')).toBe(link)
    expect(document.querySelector('input').readOnly).toBe(true)
    expect(document.querySelector('input').getAttribute('aria-describedby')).toBe(
      error.id,
    )
    expect(error.closest('.pw-help-panel')).toBeNull()
    expect(document.querySelector('button').getAttribute('aria-controls')).toBe(
      document.querySelector('[popover]').id,
    )
  })

  it('leaves unclassified help controls, warnings and history untouched', () => {
    document.body.innerHTML =
      '<small class="form-help"><button>Publish</button><a href="/history">History</a>Missing image</small>'
    const original = document.body.innerHTML
    initContextHelp()
    expect(document.body.innerHTML).toBe(original)
  })

  it('enhances newly inserted help once, including a root that is itself a marker', () => {
    document.body.innerHTML =
      '<span data-pw-help="Help: Syntax"><span data-pw-help-content><code>en: text</code></span></span>'
    const root = document.body.firstElementChild
    initContextHelp(root)
    initContextHelp(root)
    expect(root.querySelectorAll('button')).toHaveLength(1)
    root.insertAdjacentHTML(
      'afterend',
      '<span data-pw-help="Help: Image"><span data-pw-help-content>Image help</span></span>',
    )
    document.dispatchEvent(new CustomEvent('htmx:after:swap'))
    const panels = [...document.querySelectorAll('[popover]')]
    expect(panels).toHaveLength(2)
    expect(new Set(panels.map((panel) => panel.id)).size).toBe(2)
  })

  it('keeps checkbox labels next to their input and reflects native popover state', () => {
    document.body.innerHTML = `<div data-pw-help="Help: Enabled"><div class="form-widget"><div class="form-check">
      <input id="enabled" type="checkbox"><label for="enabled">Enabled</label></div><small class="form-help">Pause and resume.</small></div></div>`
    initContextHelp()
    const input = document.querySelector('input')
    document.querySelector('label').click()
    expect(input.checked).toBe(true)
    expect(input.nextElementSibling.tagName).toBe('LABEL')
    const panel = document.querySelector('[popover]')
    const button = document.querySelector('button')
    for (const state of ['open', 'closed']) {
      const event = new Event('beforetoggle')
      Object.defineProperty(event, 'newState', { value: state })
      panel.dispatchEvent(event)
      expect(button.getAttribute('aria-expanded')).toBe(String(state === 'open'))
    }
  })

  it('preserves a collection legend and a collapsible section link as help is attached', () => {
    document.body.innerHTML = `<fieldset data-pw-help="Help: Translations"><legend>Translations</legend><div class="form-widget"><small class="form-help">One per line.</small></div></fieldset>
      <section data-pw-help="Help: Schedule"><div class="form-fieldset-title"><a class="form-fieldset-title-content" href="#schedule">Schedule</a><div class="form-fieldset-help">Save then schedule.</div></div></section>`
    initContextHelp()
    expect(document.querySelector('fieldset').firstElementChild.tagName).toBe('LEGEND')
    expect(document.querySelector('legend button')).not.toBeNull()
    expect(document.querySelector('a').getAttribute('href')).toBe('#schedule')
    expect(document.querySelector('a button')).toBeNull()
    expect(document.querySelector('.pw-help-heading button')).not.toBeNull()
  })
})
