// @vitest-environment jsdom
import { afterEach, describe, expect, it } from 'vitest'
import { describePasswordHelp } from '../../src/Resources/assets/admin.formHelpers.js'

afterEach(() => {
  document.body.innerHTML = ''
})

describe('password guidance', () => {
  it('describes the password field with its visible guidance and keeps validation errors', () => {
    document.body.innerHTML = `<div class="form-widget"><div class="input-group">
      <input id="User_plainPassword" type="password" aria-describedby="password_errors">
      <button type="button">Show password</button></div>
      <small class="form-help">Leave blank to sign in with an email link.</small>
      <div id="password_errors">Password too short.</div></div>`

    describePasswordHelp()
    describePasswordHelp()

    const input = document.querySelector('input')
    const help = document.querySelector('.form-help')
    expect(help.id).toBe('User_plainPassword_help')
    expect(input.getAttribute('aria-describedby')).toBe(`password_errors ${help.id}`)
    expect(help.closest('.form-widget')).toBe(input.closest('.form-widget'))
    expect(document.querySelector('button').hasAttribute('aria-describedby')).toBe(false)
  })

  it('uses an existing help identifier and leaves passwords without guidance alone', () => {
    document.body.innerHTML = `<div class="form-widget"><input id="password" type="password">
      <small class="form-help" id="password-guidance">Optional password.</small></div>
      <div class="form-widget"><input id="confirmation" type="password"></div>`

    describePasswordHelp()

    expect(document.getElementById('password').getAttribute('aria-describedby')).toBe('password-guidance')
    expect(document.getElementById('confirmation').hasAttribute('aria-describedby')).toBe(false)
  })
})
