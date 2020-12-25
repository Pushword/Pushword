import ParentUi from './../../node_modules/@editorjs/image/src/ui.js'
import make from './../Abstract/make.js'

export default class Ui extends ParentUi {
  constructor({ api, config, onSelectFile, readOnly, onUploadFile, tool }) {
    super({ api, config, onSelectFile, readOnly })

    this.onSelectFile = onSelectFile
    this.onUploadFile = onUploadFile

    this.nodes.fileButton.remove()
    this.nodes.button = make.fileButtons(this)
    //this.nodes.button = this.nodes.fileButton.childNodes[0];

    this.nodes.wrapper.appendChild(this.nodes.button)

    this.tool = tool
  }

  createFileButton() {
    return make.fileButtons(this)
  }

  uploadingFailed(error) {
    this.tool.uploadingFailed(error)
  }

  onUpload(response) {
    this.tool.onUpload(response)
  }
}
