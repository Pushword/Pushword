// @vitest-environment jsdom
import { expect, it } from 'vitest'
import { mediaPicker } from '../../src/Resources/assets/admin.mediaPicker.js'

it.each(['inline_image', 'inline_attaches', 'image'])('keeps inline picker %s from replacing its parent collection title', field => {
  document.body.innerHTML = `
    <div class="field-collection-item">
      <button class="accordion-button"><span class="accordion-title">Email 1</span></button>
      <div class="accordion-collapse">
        <select id="picker" data-pw-media-picker="1" data-pw-media-picker-field="${field}" data-pw-media-picker-empty-label="No image selected">
          <option value=""></option>
        </select>
      </div>
    </div>`
  mediaPicker()
  expect(document.querySelector('.accordion-title').textContent).toBe(field === 'image' ? 'No image selected' : 'Email 1')
})
