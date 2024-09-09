//import ImageTool from "@editorjs/image/src/index.js";
import ImageTool from './ParentImage.js'
//import css from "@editorjs/image/src/index.css";
import css from './index.css'
import Ui from './ui.js' //

export default class Image extends ImageTool {
  constructor({ data, config, api, readOnly, block }) {
    super({ data, config, api, readOnly, block })

    this.onSelectFile = config.onSelectFile || this.defaultOnSelectFile
    this.onUploadFile = config.onUploadFile || null

    this.patterns = this.config.imagePatterns || /(https?:\/\/|\/media\/default\/)\S+\.(gif|jpe?g|png)$/i
    this.ui = new Ui({
      api: api,
      config: this.config,
      onSelectFile: this.onSelectFile,
      readOnly: readOnly,
      onUploadFile: this.onUploadFile,
      tool: this,
    })
    this._data = {}
    this.data = data
  }

  defaultOnSelectFile() {
    this.uploader.uploadSelectedFile({
      onPreview: (src) => {
        this.ui.showPreloader(src)
      },
    })
  }

  get pasteConfig() {
    return {
      tags: ['img'],
      patterns: { image: n.patterns },
      files: { mimeTypes: ['image/*'] },
    }
  }

  onUpload(response) {
    super.onUpload(response)
    if (response.success && response.file) {
      this._data.caption = response.file.name
      this.ui.nodes.caption.innerHTML = this._data.caption
    }
  }
}
