// @vitest-environment jsdom
import { describe, expect, it } from 'vitest'
import { showTitlePixelWidth } from '../../src/Resources/assets/admin.formHelpers.js'

describe('showTitlePixelWidth', () => {
  it('shows the title length without rendering the title as HTML', () => {
    document.body.innerHTML = '<input class="titleToMeasure"><span id="titleWidth"></span>'
    const input = document.querySelector('input')
    input.value = '<img src=x onerror=alert(1)>'

    showTitlePixelWidth()

    const width = document.getElementById('titleWidth')
    expect(width.textContent).toBe(String(input.value.length))
    expect(width.querySelector('img')).toBeNull()
  })
})
