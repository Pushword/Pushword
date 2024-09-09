import css from './index.css'
import Abstract from './../Abstract/Abstract.js'
import make from './../Abstract/make.js'
import ToolboxIcon from './toolbox-icon.svg'
import ajax from '@codexteam/ajax'
import { Suggest } from '../../../admin/src/Resources/assets/suggest.js'

export default class PagesList extends Abstract {
  constructor({ data, config, api, readOnly }) {
    super({ data, config, api, readOnly })
  }

  static get toolbox() {
    return {
      title: 'Pages',
      icon: ToolboxIcon,
    }
  }

  getTags() {
    var tags = ['children', 'sisters', 'grandchildren', 'related', 'title: exampleSearchValue', 'content:', 'slug:']
    const dataTags = document.querySelector('[data-tags]')
    if (dataTags) tags = JSON.parse(dataTags.dataset.tags).concat(tags)
    if (window.pagesUriList) tags = tags.concat(window.pagesUriList.map((str) => str.replace(/^\//, 'slug:')))
    return tags
  }

  createInputs() {
    this.nodes.kwInput = make.input(this, ['cdx-input-labeled', 'cdx-input-labeled-pageslist-kw', ...this.CSS.inputClass], '...', this._data.kw)
    this.nodes.suggester = make.element('div', 'textSuggester', { style: 'display:none' })
    //const list = this.getTags() // this.nodes.kwInput.dataset.tags = this.getTags()
    const options = { highlight: true, dispMax: 10, dispAllKey: true, hookSearchResults: 'suggestSearchHookForPageTags' }
    new Suggest.LocalMulti(this.nodes.kwInput, this.nodes.suggester, this.getTags(), options)

    this.nodes.displaySelect = document.createElement('select')
    this.nodes.displaySelect.classList.add('cdx-select')
    this.nodes.displaySelect.classList.add('mr-5px')
    make.option(this.nodes.displaySelect, null, 'format', { disabled: true })
    make.options(this.nodes.displaySelect, ['list', 'card'])
    if (this._data.display) this.nodes.displaySelect.value = this._data.display

    const detailsWrapper = make.element('div', ['flex'])
    detailsWrapper.style.marginBottom = '15px'

    this.nodes.maxInput = make.input(this, ['cdx-input-labeled', 'cdx-input-labeled-pageslist-max', 'text-right', ...this.CSS.inputClass], '9', this._data.max)
    this.nodes.maxInput.title = 'max Items per Page'

    this.nodes.maxPagesInput = make.input(this, ['cdx-input-labeled', 'cdx-input-labeled-pageslist-maxpages', 'text-right', ...this.CSS.inputClass], '1', this._data.maxPages)
    this.nodes.maxPagesInput.title = 'max Pages'

    detailsWrapper.appendChild(this.nodes.displaySelect)
    detailsWrapper.appendChild(this.createOrderSelect())
    detailsWrapper.appendChild(this.nodes.maxInput)
    detailsWrapper.appendChild(this.nodes.maxPagesInput)

    const inputsWrapper = make.element('div')
    inputsWrapper.appendChild(this.nodes.kwInput)
    inputsWrapper.appendChild(this.nodes.suggester)
    inputsWrapper.appendChild(detailsWrapper)

    return inputsWrapper
  }
  createOrderSelect() {
    this.nodes.orderSelect = document.createElement('select')
    this.nodes.orderSelect.classList.add('cdx-select')
    make.option(this.nodes.orderSelect, null, 'orderBy', { disabled: true })
    make.option(this.nodes.orderSelect, 'publishedAt ↓')
    make.option(this.nodes.orderSelect, 'priority ↓, publishedAt ↓')
    make.option(this.nodes.orderSelect, 'publishedAt ↑')
    if (this._data.order) this.nodes.orderSelect.value = this._data.order
    return this.nodes.orderSelect
  }
  show(state) {
    if (state === this.STATE.VIEW) {
      if (this.validate()) {
        this.getPreviewFromServer()
      } else {
        this.api.notifier.show({
          message: this.api.i18n.t('Something is missing to properly render the the pages list.'),
          style: 'error',
        })
      }
    }
    super.show(state)
  }

  updateData() {
    this._data.kw = this.nodes.kwInput.textContent
    this._data.display = this.nodes.displaySelect.value
    this._data.order = this.nodes.orderSelect.value
    this._data.max = this.nodes.maxInput.textContent
    this._data.maxPages = this.nodes.maxPagesInput.textContent
  }

  validate() {
    return !!this._data.kw
  }

  getPreviewFromServer() {
    this.updateData()
    const Tool = this
    ajax
      .post({
        url: this.config.preview,
        data: this._data,
        type: ajax.contentType.JSON,
      })
      .then(function (response) {
        Tool.updatePreview(response.body.content)
      })
      .catch(function (error) {
        console.log(error)
        Tool.updatePreview('An error occured (see console log for more info)')
      })
  }

  updatePreview(content) {
    this.nodes.preview.innerHTML = '<div class="preview-wrapper-overlay"></div>' + content
  }
}
