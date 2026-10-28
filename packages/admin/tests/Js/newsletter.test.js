// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from 'vitest'
import { initAutomationRecipients, initCampaignDesigns, initNewsletterDelays, initNewsletterIndexes, numberNewNewsletterStep } from '../../../newsletter/src/Resources/public/admin.js'

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
    const selectAllLabel = document.querySelector('label[for="all"]')
    expect(selectAllLabel.textContent).toBe('Select all')
    selectAllLabel.click()
    expect(document.querySelector('#all').checked).toBe(true)
    document.querySelector('label[for="row"]').click()
    expect(input.checked).toBe(true)
    expect(document.querySelectorAll('label')).toHaveLength(2)
  })
})

describe('campaign email design selection', () => {
  afterEach(() => { document.body.innerHTML = ''; vi.unstubAllGlobals() })

  function setup() {
    document.body.innerHTML = `<form>
      <select name="Campaign[audience]"><option value="">Choose</option><option value="1">First</option><option value="2">Second</option><option value="3">Third</option></select>
      <select data-pw-campaign-design data-choices-url="/templates/choices"><option value="">Audience default</option><option value="simple">Simple</option><option value="framed">Framed</option><option value="personal">Personal</option><optgroup label="Site templates"><option value="twig:brand">Brand</option></optgroup></select>
      <div data-campaign-design-summary data-current="In use: %theme%" data-empty="Choose an audience" data-custom="Site preset" data-shared="Identity from %audience%" data-edit-audience="Edit audience" data-audience-warning="Shared audience" data-customize="Customize identity" data-personal="Personal email with signature from %audience%" data-personal-customize="Customize signature" data-personal-edit="Edit signature" data-personal-warning="Shared signature">
        <p data-campaign-design-current></p><p data-campaign-design-description hidden></p><details data-campaign-design-edit-details hidden><summary>Customize identity</summary><a data-campaign-design-edit hidden target="_blank"></a><p data-campaign-design-warning hidden></p></details>
      </div>
      <div class="form-group"><input name="Campaign[preheader]" value="Saved preview text"></div>
      <div class="form-group"><input type="checkbox" name="Campaign[transactional]" checked></div>
      </form>`
    document.querySelector('[data-pw-campaign-design]').dataset.pwCampaignDesign = JSON.stringify({
      1: { name: 'First', layout: 'framed', url: '/audience/1/design' },
      2: { name: 'Second', layout: 'twig:brand', url: '/audience/2/design' },
      3: { name: 'Third', layout: 'personal', url: '/audience/3/design' },
    })
    initCampaignDesigns()
  }

  function choose(selector, value) {
    const select = document.querySelector(selector)
    select.value = value
    select.dispatchEvent(new Event('change'))
  }

  it('explains the audience dependency and follows the selected audience', () => {
    setup()
    expect(document.querySelector('[data-campaign-design-current]').textContent).toBe('Choose an audience')
    choose('[name="Campaign[audience]"]', '1')
    expect(document.querySelector('[data-campaign-design-current]').textContent).toBe('In use: Framed')
    expect(document.querySelector('[data-campaign-design-description]').textContent).toBe('Identity from First')
    expect(document.querySelector('[data-campaign-design-edit]').getAttribute('href')).toBe('/audience/1/design')
    expect(document.querySelector('[data-campaign-design-warning]').hidden).toBe(false)
    choose('[name="Campaign[audience]"]', '2')
    expect(document.querySelector('[data-campaign-design-current]').textContent).toBe('In use: Brand')
    expect(document.querySelector('[data-campaign-design-edit]').getAttribute('href')).toBe('/audience/2/design')
  })

  it('distinguishes a campaign preset from shared audience editing', () => {
    setup()
    choose('[name="Campaign[audience]"]', '1')
    choose('[data-pw-campaign-design]', 'twig:brand')
    expect(document.querySelector('[data-campaign-design-current]').textContent).toBe('In use: Brand')
    expect(document.querySelector('[data-campaign-design-edit]').hidden).toBe(true)
    expect(document.querySelector('[data-campaign-design-warning]').hidden).toBe(true)
    choose('[data-pw-campaign-design]', 'simple')
    expect(document.querySelector('[data-campaign-design-edit]').hidden).toBe(false)
    choose('[name="Campaign[audience]"]', '')
    expect(document.querySelector('[data-campaign-design-current]').textContent).toBe('Choose an audience')
    expect(document.querySelector('[data-campaign-design-edit]').hidden).toBe(true)
  })

  it('keeps transactional consent visible for personal designs and preserves settings', () => {
    setup()
    choose('[name="Campaign[audience]"]', '1')
    choose('[data-pw-campaign-design]', 'personal')
    const preheader = document.querySelector('[name="Campaign[preheader]"]')
    const transactional = document.querySelector('[name="Campaign[transactional]"]')
    expect(document.querySelector('[data-campaign-design-description]').textContent).toBe('Personal email with signature from First')
    expect(document.querySelector('[data-campaign-design-edit-details] summary').textContent).toBe('Customize signature')
    expect(document.querySelector('[data-campaign-design-edit]').getAttribute('href')).toBe('/audience/1/design#email_design_signatureMarkdown')
    expect(document.querySelector('[data-campaign-design-edit]').textContent).toBe('Edit signature')
    expect(document.querySelector('[data-campaign-design-warning]').textContent).toBe('Shared signature')
    expect(preheader.closest('.form-group').hidden).toBe(true)
    expect(transactional.closest('.form-group').hidden).toBe(false)
    choose('[data-pw-campaign-design]', 'simple')
    expect(preheader.closest('.form-group').hidden).toBe(false)
    expect(transactional.closest('.form-group').hidden).toBe(false)
    expect(preheader.value).toBe('Saved preview text')
    expect(transactional.checked).toBe(true)
    expect(document.querySelector('[data-campaign-design-edit-details] summary').textContent).toBe('Customize identity')
    expect(document.querySelector('[data-campaign-design-description]').textContent).toBe('Identity from First')
  })

  it('follows an inherited personal design and returns to ordinary settings with another audience', () => {
    setup()
    choose('[name="Campaign[audience]"]', '3')
    expect(document.querySelector('[data-campaign-design-current]').textContent).toBe('In use: Personal')
    expect(document.querySelector('[data-campaign-design-edit]').getAttribute('href')).toBe('/audience/3/design#email_design_signatureMarkdown')
    expect(document.querySelector('[name="Campaign[preheader]"]').closest('.form-group').hidden).toBe(true)
    choose('[name="Campaign[audience]"]', '2')
    expect(document.querySelector('[name="Campaign[preheader]"]').closest('.form-group').hidden).toBe(false)
    expect(document.querySelector('[data-campaign-design-description]').textContent).toBe('Site preset')
    choose('[name="Campaign[audience]"]', '')
    expect(document.querySelector('[data-campaign-design-current]').textContent).toBe('Choose an audience')
    expect(document.querySelector('[data-campaign-design-edit-details]').hidden).toBe(true)
  })

  it('refreshes configured Twig choices while preserving the campaign selection', async () => {
    setup()
    choose('[name="Campaign[audience]"]', '1')
    choose('[data-pw-campaign-design]', 'twig:brand')
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, json: async () => ({ groups: { 'Site templates': { Brand: 'twig:brand', Letter: 'twig:letter' } }, audienceLayouts: {} }) }))
    window.dispatchEvent(new Event('focus'))
    await vi.waitFor(() => expect(document.querySelector('option[value="twig:letter"]')).not.toBeNull())
    expect(document.querySelector('[data-pw-campaign-design]').value).toBe('twig:brand')
    expect(document.querySelectorAll('option[value="twig:brand"]')).toHaveLength(1)
    choose('[data-pw-campaign-design]', 'twig:letter')
    expect(document.querySelector('[data-campaign-design-current]').textContent).toBe('In use: Letter')
    expect(document.querySelector('[data-campaign-design-edit]').hidden).toBe(true)
    expect(document.querySelector('[data-campaign-design-warning]').hidden).toBe(true)
  })

  it('refreshes an inherited audience design after editing it in another tab', async () => {
    setup()
    choose('[name="Campaign[audience]"]', '1')
    expect(document.querySelector('[data-campaign-design-current]').textContent).toBe('In use: Framed')
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, json: async () => ({
      groups: { 'Site templates': { Brand: 'twig:brand' } },
      audienceLayouts: { 1: 'twig:brand' },
    }) }))
    window.dispatchEvent(new Event('focus'))
    await vi.waitFor(() => expect(document.querySelector('[data-campaign-design-current]').textContent).toBe('In use: Brand'))
    expect(document.querySelector('[data-pw-campaign-design]').value).toBe('')
    expect(document.querySelector('[data-campaign-design-edit]').getAttribute('href')).toBe('/audience/1/design')
  })

  it('keeps the current choices and selection when a background refresh fails', async () => {
    setup()
    choose('[name="Campaign[audience]"]', '1')
    choose('[data-pw-campaign-design]', 'twig:brand')
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('Offline')))
    window.dispatchEvent(new Event('focus'))
    await vi.waitFor(() => expect(fetch).toHaveBeenCalledTimes(1))
    expect(document.querySelector('[data-pw-campaign-design]').value).toBe('twig:brand')
    expect(document.querySelectorAll('option[value="twig:brand"]')).toHaveLength(1)
    expect(document.querySelector('[data-campaign-design-current]').textContent).toBe('In use: Brand')
  })
})

it('shows recipients according to the trigger and retains publication conditions', () => {
  document.body.innerHTML = `<form><select name="Automation[source]"><option value="contact">Contact</option><option value="page">Page</option></select><div data-pw-automation-recipients="The triggering contact"><div class="form-fieldset-body"><textarea>Saved rule</textarea></div></div><div class="form-group"><textarea name="Automation[stopWhenAsJson]">Stop rule</textarea></div></form>`
  initAutomationRecipients()
  initAutomationRecipients()
  const fields = document.querySelector('.form-fieldset-body')
  expect(fields.hidden).toBe(true)
  expect(document.querySelector('.form-group').hidden).toBe(false)
  expect(document.querySelectorAll('.pw-newsletter-recipient-note')).toHaveLength(1)
  const source = document.querySelector('select')
  source.value = 'page'
  source.dispatchEvent(new Event('change'))
  expect(fields.hidden).toBe(false)
  expect(document.querySelector('.pw-newsletter-recipient-note').hidden).toBe(true)
  expect(document.querySelector('.form-group').hidden).toBe(true)
  expect(document.querySelector('[name="Automation[stopWhenAsJson]"]').value).toBe('Stop rule')
  expect(document.querySelector('textarea').value).toBe('Saved rule')
  document.body.innerHTML = ''
})

describe('newsletter asset self-wiring', () => {
  afterEach(() => { document.body.innerHTML = '' })

  it('initialises the screen on window load', () => {
    document.body.innerHTML = `<input id="delay" type="number" value="2880" data-pw-delay-preview>
      <output for="delay" data-delay-output data-immediate="Immediately" data-days="d" data-hours="h" data-minutes="min"></output>`
    window.dispatchEvent(new Event('load'))
    expect(document.querySelector('output').textContent).toBe('2 d')
  })

  it('initialises a collection item added later', () => {
    document.body.innerHTML = `<form><div id="added"><input id="delay" type="number" value="60" data-pw-delay-preview>
      <output for="delay" data-delay-output data-immediate="Immediately" data-days="d" data-hours="h" data-minutes="min"></output></div></form>`
    document.dispatchEvent(new CustomEvent('ea.collection.item-added', { detail: { newElement: document.querySelector('#added') } }))
    expect(document.querySelector('output').textContent).toBe('1 h')
  })
})
