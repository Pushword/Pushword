// @vitest-environment jsdom
import { afterEach, describe, expect, it } from 'vitest'
import { initNewsletterDelays, initNewsletterIndexes, numberNewNewsletterStep } from '../../src/Resources/assets/admin.newsletter.js'

function renderDelay(value) {
  document.body.innerHTML = `<input id="delay" type="number" value="${value}" data-pw-delay-preview>
    <output for="delay" data-delay-output data-immediate="Immediately" data-days="d" data-hours="h" data-minutes="min"></output>`
  initNewsletterDelays()
  return document.querySelector('output')
}

describe('newsletter email delays', () => {
  afterEach(() => { document.body.innerHTML = '' })

  it('explains immediate delivery without a minutes conversion', () => {
    expect(renderDelay(0).textContent).toBe('Immediately')
  })

  it('shows days, hours and minutes as the delay changes', () => {
    const output = renderDelay(2880)
    expect(output.textContent).toBe('2 d')
    const input = document.querySelector('input')
    input.value = '1501'
    input.dispatchEvent(new Event('input'))
    expect(output.textContent).toBe('1 d 1 h 1 min')
  })

  it('keeps an empty or invalid delay blank', () => {
    expect(renderDelay('').textContent).toBe('')
    expect(renderDelay(-1).textContent).toBe('')
  })

  it('initializes newly added email fields without duplicating existing output', () => {
    renderDelay(60)
    document.body.insertAdjacentHTML('beforeend', '<input id="added" type="number" value="1440" data-pw-delay-preview><output for="added" data-delay-output data-days="d"></output>')
    initNewsletterDelays()
    expect(document.querySelector('[for="added"]').textContent).toBe('1 d')
    expect(document.querySelectorAll('output')).toHaveLength(2)
  })

  it('numbers a new email after existing steps and retains the collapse icon', () => {
    document.body.innerHTML = '<form><input data-pw-sequence-number value="1"><input data-pw-sequence-number value="3"><div id="added"><button class="accordion-button"><i></i> Email 1</button><input data-pw-sequence-number data-email-label="Email" value="1"></div></form>'
    numberNewNewsletterStep(document.querySelector('#added'))
    expect(document.querySelector('#added input').value).toBe('4')
    expect(document.querySelector('.accordion-button').textContent.trim()).toBe('Email 4')
    expect(document.querySelector('.accordion-button i')).not.toBeNull()
  })

  it('labels selection and action controls while preserving their original inputs', () => {
    document.body.innerHTML = '<div data-pw-newsletter-index data-select-all="Select all" data-select-row="Select %id%" data-row-actions="Actions %id%"><table><thead><tr><th><input class="form-batch-checkbox-all" type="checkbox" id="all"></th></tr></thead><tbody><tr data-id="42"><td><input class="form-batch-checkbox" id="row" type="checkbox" value="42"></td><td><a role="button" class="dropdown-toggle"></a></td></tr></tbody></table></div>'
    const input = document.querySelector('#row')
    initNewsletterIndexes()
    initNewsletterIndexes()
    expect(document.querySelector('th').getAttribute('aria-label')).toBe('Select all')
    expect(input.getAttribute('aria-label')).toBe('Select 42')
    expect(document.querySelector('.dropdown-toggle').getAttribute('aria-label')).toBe('Actions 42')
    document.querySelector('label[for="row"]').click()
    expect(input.checked).toBe(true)
    expect(document.querySelectorAll('label')).toHaveLength(2)
  })
})
