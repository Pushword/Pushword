// @vitest-environment jsdom
import { describe, expect, it } from 'vitest'
import { parseRule, pruneGroup, serializeRule } from '../../src/Resources/assets/admin.criteriaBuilder.js'

const roundTrip = (text) => serializeRule(parseRule(text))

describe('parseRule', () => {
  it('reads a bare list as an "all"', () => {
    expect(parseRule('[{"field":"tag","op":"has","value":"AmTrek"}]')).toEqual({
      any: false,
      children: [{ field: 'tag', op: 'has', value: 'AmTrek' }],
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
    '[{"field":"tag","op":"has","value":"AmTrek"}]',
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
