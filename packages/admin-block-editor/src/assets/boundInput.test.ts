import { describe, it, expect, beforeEach } from 'vitest'
import { boundInputOf } from './boundInput'

beforeEach(() => {
  document.body.innerHTML =
    '<div id="ed" data-input-id="inp"></div><textarea id="inp"></textarea>'
})

describe('boundInputOf', () => {
  it('finds the field the holder names', () => {
    expect(boundInputOf('ed')).toBe(document.getElementById('inp'))
  })

  it('finds the element a mode switch put in place of the field', () => {
    const replacement = document.createElement('input')
    replacement.id = 'inp'
    document.getElementById('inp')!.replaceWith(replacement)

    expect(boundInputOf('ed')).toBe(replacement)
  })

  it('is null when the holder, its attribute or the field is missing', () => {
    expect(boundInputOf('missing')).toBeNull()

    document.getElementById('ed')!.removeAttribute('data-input-id')
    expect(boundInputOf('ed')).toBeNull()

    document.getElementById('ed')!.setAttribute('data-input-id', 'gone')
    expect(boundInputOf('ed')).toBeNull()
  })
})
