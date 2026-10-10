import { describe, it, expect } from 'vitest'
import Small from './Small'

describe('Small sanitize rules', () => {
  it('declare the <small> it writes, which Editor.js would strip on save otherwise', () => {
    expect(Small.sanitize).toHaveProperty('small')
  })

  it('keep declaring <u>, which no registered inline tool writes but pasted text brings', () => {
    expect(Small.sanitize).toHaveProperty('u')
  })
})
