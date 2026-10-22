// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { initCriteriaBuilder, parseRule, pruneGroup, serializeRule } from '../../src/Resources/assets/admin.criteriaBuilder.js'

const roundTrip = (text) => serializeRule(parseRule(text))

it('exposes the selected conjunction and history scope to assistive technology', async () => {
  vi.useFakeTimers()
  document.body.innerHTML = '<form><textarea name="Automation[triggerWhen]" data-pw-criteria="trigger" data-pw-criteria-vocabulary="/vocabulary" data-pw-criteria-preview="/preview"></textarea></form>'
  vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, json: async () => ({ fields: {}, labels: { all: 'All', any: 'Any', sinceAll: 'Include history' }, propertyPrefix: 'prop.' }) }))
  try {
    initCriteriaBuilder()
    await vi.advanceTimersByTimeAsync(0)
    expect(document.querySelector('.pw-criteria-conjunction button').getAttribute('aria-pressed')).toBe('true')
    document.querySelectorAll('.pw-criteria-conjunction button')[1].click()
    expect(document.querySelectorAll('.pw-criteria-conjunction button')[1].getAttribute('aria-pressed')).toBe('true')
    expect(document.querySelector('.pw-criteria-conjunction button').getAttribute('aria-pressed')).toBe('false')
    const history = Array.from(document.querySelectorAll('.pw-criteria-foot button')).find((button) => button.textContent === 'Include history')
    expect(history.getAttribute('aria-pressed')).toBe('false')
    history.click()
    expect(history.getAttribute('aria-pressed')).toBe('true')
  } finally {
    vi.clearAllTimers()
    vi.useRealTimers()
    vi.unstubAllGlobals()
    document.body.innerHTML = ''
  }
})

describe('parseRule', () => {
  it('reads a bare list as an "all"', () => {
    expect(parseRule('[{"field":"tag","op":"has","value":"Hiking"}]')).toEqual({
      any: false,
      children: [{ field: 'tag', op: 'has', value: 'Hiking' }],
    })
  })

  it('reads the operator a group names', () => {
    const rule = parseRule('{"any":[{"field":"tag","op":"has","value":"a"},{"all":[{"field":"locale","op":"=","value":"fr"}]}]}')

    expect(rule.any).toBe(true)
    expect(rule.children[1]).toEqual({ any: false, children: [{ field: 'locale', op: '=', value: 'fr' }] })
  })

  it('reads an empty rule as an empty group, not as nothing to show', () => {
    expect(parseRule('')).toEqual({ any: false, children: [] })
    expect(parseRule('   ')).toEqual({ any: false, children: [] })
  })

  it('gives up on a search string, which is a legal rule with no rows', () => {
    expect(parseRule('ancestor:blog AND tag:featured')).toBeNull()
  })

  it('gives up on malformed JSON rather than dropping what was typed', () => {
    expect(parseRule('[{"field":"tag",')).toBeNull()
    expect(parseRule('[{"op":"has"}]')).toBeNull()
    expect(parseRule('["tag"]')).toBeNull()
    expect(parseRule('{"any":{}}')).toBeNull()
  })
})

describe('serializeRule', () => {
  it('writes an empty rule as an empty field — every subject, not none', () => {
    expect(serializeRule({ any: false, children: [] })).toBe('')
  })

  it('keeps the operator with the group it belongs to', () => {
    expect(JSON.parse(serializeRule({ any: true, children: [{ field: 'tag', op: 'has', value: 'a' }] }))).toEqual({
      any: [{ field: 'tag', op: 'has', value: 'a' }],
    })
  })

  it('names a nested group operator, "all" included', () => {
    const rule = { any: true, children: [{ any: false, children: [{ field: 'tag', op: 'has', value: 'a' }] }] }

    expect(JSON.parse(serializeRule(rule))).toEqual({ any: [{ all: [{ field: 'tag', op: 'has', value: 'a' }] }] })
  })

  it('drops the value of an operator that carries none', () => {
    expect(JSON.parse(serializeRule({ any: false, children: [{ field: 'prop.x', op: 'isSet', value: '' }] }))).toEqual([
      { field: 'prop.x', op: 'isSet' },
    ])
  })
})

// Changing an automation's source changes the language its rule is written in:
// the fields of the one just left mostly do not exist in the one just picked.
describe('pruneGroup', () => {
  const pageVocabulary = { propertyPrefix: 'prop.', fields: { slug: {}, tag: {}, ancestor: {} } }

  it('keeps what the new language also knows and drops the rest', () => {
    const pruned = pruneGroup(
      {
        any: false,
        children: [
          { field: 'tag', op: 'has', value: 'a' },
          { field: 'confirmedAt', op: 'olderThan', value: '7d' },
        ],
      },
      pageVocabulary,
    )

    expect(pruned.children).toEqual([{ field: 'tag', op: 'has', value: 'a' }])
  })

  // Both languages read `prop.<key>`, and no vocabulary can list every key.
  it('keeps any property, which every language takes', () => {
    const pruned = pruneGroup({ any: false, children: [{ field: 'prop.whatever', op: 'isSet', value: '' }] }, pageVocabulary)

    expect(pruned.children).toHaveLength(1)
  })

  // `slug` is a field of both; `slugish` is a field of neither.
  it('does not keep a field merely because a known one prefixes it', () => {
    const pruned = pruneGroup({ any: false, children: [{ field: 'slugish', op: '=', value: 'x' }] }, pageVocabulary)

    expect(pruned.children).toEqual([])
  })

  it('drops a group left empty, and keeps the operator of one that survives', () => {
    const pruned = pruneGroup(
      {
        any: true,
        children: [
          { any: false, children: [{ field: 'locale', op: '=', value: 'fr' }] },
          { any: false, children: [{ field: 'slug', op: 'startsWith', value: 'blog/' }] },
        ],
      },
      pageVocabulary,
    )

    expect(pruned.any).toBe(true)
    expect(pruned.children).toEqual([{ any: false, children: [{ field: 'slug', op: 'startsWith', value: 'blog/' }] }])
  })
})

describe('the round trip', () => {
  it.each([
    '[{"field":"tag","op":"has","value":"Hiking"}]',
    '{"any":[{"field":"tag","op":"has","value":"a"},{"field":"tag","op":"has","value":"b"}]}',
    '{"any":[{"field":"ancestor","op":"=","value":"blog"},{"all":[{"field":"template","op":"=","value":"article.html.twig"}]}]}',
    '[{"field":"createdAt","op":"olderThan","value":"7d"},{"field":"prop.lastSeenAt","op":"isNotSet"}]',
  ])('leaves %s meaning the same thing', (text) => {
    expect(JSON.parse(roundTrip(text))).toEqual(JSON.parse(roundTrip(roundTrip(text))))
  })

  // An `any` coming back bare would silently become an `all`, and a rule that
  // reached two audiences would reach one.
  it('never loses an "any" through the editor', () => {
    expect(roundTrip('{"any":[{"field":"tag","op":"has","value":"a"}]}')).toContain('"any"')
  })
})

describe('live criteria audience counts', () => {
  beforeEach(() => { vi.useFakeTimers() })
  afterEach(() => {
    vi.clearAllTimers()
    vi.useRealTimers()
    vi.unstubAllGlobals()
    document.body.innerHTML = ''
  })

  async function mount() {
    document.body.innerHTML = '<form><select name="Campaign[audience]"><option value="">Choose</option><option value="1">First</option><option value="2">Second</option></select><textarea name="Campaign[segmentAsJson]" data-pw-criteria="contact" data-pw-criteria-vocabulary="/vocabulary" data-pw-criteria-preview="/preview"></textarea></form>'
    vi.stubGlobal('fetch', vi.fn(async (url, options) => {
      const result = String(url).includes('/vocabulary')
        ? { fields: {}, labels: { previewNeedsAudience: 'Choose audience', previewContacts: 'contacts' }, propertyPrefix: 'prop.' }
        : { count: JSON.parse(options.body).audience }
      return { ok: true, json: async () => result }
    }))
    initCriteriaBuilder()
    await vi.advanceTimersByTimeAsync(0)
  }
  function choose(value) {
    const input = document.querySelector('select')
    input.value = value
    input.dispatchEvent(new Event('change'))
  }
  it('recounts when the audience changes and when it is cleared', async () => {
    await mount()
    expect(document.querySelector('.pw-criteria-count').textContent).toBe('Choose audience')
    choose('1')
    await vi.advanceTimersByTimeAsync(500)
    expect(document.querySelector('.pw-criteria-count').textContent).toBe('1 contacts')
    choose('2')
    await vi.advanceTimersByTimeAsync(500)
    expect(document.querySelector('.pw-criteria-count').textContent).toBe('2 contacts')
    choose('')
    await vi.advanceTimersByTimeAsync(500)
    expect(document.querySelector('.pw-criteria-count').textContent).toBe('Choose audience')
  })
  it('refreshes vocabulary and count when the watched site changes', async () => {
    document.body.innerHTML = '<form><input name="Automation[hosts][]" value="localhost.dev" type="checkbox"><textarea name="Automation[triggerWhen]" data-pw-criteria="trigger" data-pw-criteria-vocabulary="/vocabulary" data-pw-criteria-preview="/preview"></textarea></form>'
    const vocabulary = { fields: {}, labels: {}, propertyPrefix: 'prop.' }
    vi.stubGlobal('fetch', vi.fn(async (url) => ({ ok: true, json: async () => String(url).includes('/vocabulary') ? vocabulary : { count: 0 } })))
    initCriteriaBuilder()
    await vi.advanceTimersByTimeAsync(0)
    const host = document.querySelector('input')
    host.checked = true
    host.dispatchEvent(new Event('change'))
    await vi.advanceTimersByTimeAsync(500)
    const vocabularyCalls = fetch.mock.calls.filter(([url]) => String(url).includes('/vocabulary'))
    expect(vocabularyCalls).toHaveLength(2)
    expect(vocabularyCalls[1][0].searchParams.getAll('hosts[]')).toEqual(['localhost.dev'])
    const previews = fetch.mock.calls.filter(([url]) => url === '/preview')
    expect(JSON.parse(previews.at(-1)[1].body).hosts).toEqual(['localhost.dev'])
  })

  it('does not let a slow response replace the latest audience count', async () => {
    await mount()
    let resolveOld
    fetch.mockImplementationOnce(() => new Promise((resolve) => { resolveOld = resolve }))
    choose('1')
    await vi.advanceTimersByTimeAsync(500)
    choose('2')
    await vi.advanceTimersByTimeAsync(500)
    expect(document.querySelector('.pw-criteria-count').textContent).toBe('2 contacts')
    resolveOld({ json: async () => ({ count: 99 }) })
    await vi.advanceTimersByTimeAsync(0)
    expect(document.querySelector('.pw-criteria-count').textContent).toBe('2 contacts')
  })
})
