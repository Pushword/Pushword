export default class Class {
  /**
   * @returns {bool}
   */
  static get isTune() {
    return true
  }

  getClass() {
    return this.data || ''
  }

  /**
   * Constructor
   *
   * @param api - Editor.js API
   * @param data — previously saved data
   */
  constructor({ api, data, config, block }) {
    console.log('init Class')
    this.api = api
    this.data = data || ''
    this.block = block

    this._CSS = {
      classWrapper: 'cdx-anchor-tune-wrapper',
      classIcon: 'cdx-anchor-tune-icon',
      classInput: 'cdx-anchor-tune-input',
    }
  }

  /**
   * Rendering tune wrapper
   * @returns {*}
   */
  render(value = null) {
    const wrapper = document.createElement('div')
    wrapper.classList.add(this._CSS.classWrapper)

    const wrapperIcon = document.createElement('div')
    wrapperIcon.classList.add(this._CSS.classIcon)
    wrapperIcon.innerHTML =
      '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" data-slot="icon" class="w-6 h-6"> <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" /></svg>'

    const wrapperInput = document.createElement('textarea')
    wrapperInput.placeholder = this.api.i18n.t('Class')
    wrapperInput.classList.add(this._CSS.classInput)
    wrapperInput.value = value ? value : this.getClass()

    wrapperInput.addEventListener('input', (event) => {
      let value = event.target.value

      // Save value
      if (value.length > 0) {
        this.data = value
      } else {
        this.data = ''
      }
      this.block?.dispatchChange()
    })

    this.input = wrapperInput

    wrapper.appendChild(wrapperIcon)
    wrapper.appendChild(wrapperInput)

    return wrapper
  }
  /**
   * Save
   * @returns {*}
   */
  save() {
    return this.data
  }
}
