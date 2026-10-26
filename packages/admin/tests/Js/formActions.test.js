// @vitest-environment jsdom
import { describe, expect, it } from 'vitest'
import { prioritizeSaveActions } from '../../src/Resources/assets/admin.formActions.js'

describe('prioritizeSaveActions', () => {
  it('puts saving before utility actions without replacing buttons or moving dropdown entries', () => {
    document.body.innerHTML = `<header class="content-header"><div class="page-actions">
      <a id="history">History</a><button class="action-saveAndContinue">Save and stay</button>
      <button class="action-saveAndReturn">Save</button><div id="more"><a class="action-saveAndReturn">Nested</a></div>
    </div></header>`
    const actions = document.querySelector('.page-actions')
    const save = document.querySelector('button.action-saveAndReturn')
    let clicked = false
    save.addEventListener('click', () => {
      clicked = true
    })
    prioritizeSaveActions()
    prioritizeSaveActions()
    expect([...actions.children].map((el) => el.textContent)).toEqual([
      'Save',
      'Save and stay',
      'History',
      'Nested',
    ])
    expect(document.querySelector('#more > a')).not.toBeNull()
    save.click()
    expect(clicked).toBe(true)
  })

  it('leaves pages without save actions unchanged', () => {
    document.body.innerHTML =
      '<header class="content-header"><div class="page-actions"><a>New</a><a>Upload</a></div></header>'
    const before = document.body.innerHTML
    prioritizeSaveActions()
    expect(document.body.innerHTML).toBe(before)
  })
})
