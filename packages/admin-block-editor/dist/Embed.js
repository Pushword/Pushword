import { U as Uploader } from "./AbstractUploader-6bIsS53s.mjs";
import { IconPicture } from "@codexteam/icons";
import { A as Abstract } from "./Abstract-DQgJkkKe.mjs";
import { m as make } from "./make-D456KYKV.mjs";
import { l as logger } from "./logger-5AkeQ-mP.mjs";
class Ui {
  /**
   * @param {object} ui - image tool Ui module
   * @param {object} ui.api - Editor.js API
   * @param {ImageConfig} ui.config - user config
   * @param {Function} ui.onSelectFile - callback for clicks on Select file button
   * @param {boolean} ui.readOnly - read-only mode flag
   */
  constructor({
    api,
    config,
    onSelectFile,
    readOnly
  }) {
    this.api = api;
    this.config = config;
    this.onSelectFile = onSelectFile;
    this.readOnly = readOnly || false;
    this.nodes = {
      wrapper: this.make("div", [this.CSS.baseClass, this.CSS.wrapper]),
      imageContainer: this.make("div", [this.CSS.imageContainer]),
      fileButton: this.createFileButton(),
      imageEl: void 0,
      imagePreloader: this.make("div", this.CSS.imagePreloader),
      caption: this.make("div", [this.CSS.input, this.CSS.caption], {
        contentEditable: !this.readOnly
      })
    };
    this.nodes.caption.dataset.placeholder = this.config.captionPlaceholder || "";
    this.nodes.imageContainer.appendChild(this.nodes.imagePreloader);
    this.nodes.wrapper.appendChild(this.nodes.imageContainer);
    this.nodes.wrapper.appendChild(this.nodes.caption);
    this.nodes.wrapper.appendChild(this.nodes.fileButton);
  }
  /**
   * CSS classes
   *
   * @returns {object}
   */
  get CSS() {
    return {
      baseClass: this.api.styles.block,
      loading: this.api.styles.loader,
      input: this.api.styles.input,
      button: this.api.styles.button,
      /**
       * Tool's classes
       */
      wrapper: "image-tool",
      imageContainer: "image-tool__image",
      imagePreloader: "image-tool__image-preloader",
      imageEl: "image-tool__image-picture",
      caption: "image-tool__caption"
    };
  }
  /**
   * Ui statuses:
   * - empty
   * - uploading
   * - filled
   *
   * @returns {{EMPTY: string, UPLOADING: string, FILLED: string}}
   */
  static get status() {
    return {
      EMPTY: "empty",
      UPLOADING: "loading",
      FILLED: "filled"
    };
  }
  /**
   * Renders tool UI
   *
   * @param {ImageToolData} toolData - saved tool data
   * @returns {Element}
   */
  render(toolData) {
    if (!toolData.file || Object.keys(toolData.file).length === 0) {
      this.toggleStatus(Ui.status.EMPTY);
    } else {
      this.toggleStatus(Ui.status.UPLOADING);
    }
    return this.nodes.wrapper;
  }
  /**
   * Creates upload-file button
   *
   * @returns {Element}
   */
  createFileButton() {
    const button = this.make("div", [this.CSS.button]);
    button.innerHTML = this.config.buttonContent || `${IconPicture} ${this.api.i18n.t("Select an Image")}`;
    button.addEventListener("click", () => {
      this.onSelectFile();
    });
    return button;
  }
  /**
   * Shows uploading preloader
   *
   * @param {string} src - preview source
   * @returns {void}
   */
  showPreloader(src) {
    this.nodes.imagePreloader.style.backgroundImage = `url(${src})`;
    this.toggleStatus(Ui.status.UPLOADING);
  }
  /**
   * Hide uploading preloader
   *
   * @returns {void}
   */
  hidePreloader() {
    this.nodes.imagePreloader.style.backgroundImage = "";
    this.toggleStatus(Ui.status.EMPTY);
  }
  /**
   * Shows an image
   *
   * @param {string} url - image source
   * @returns {void}
   */
  fillImage(url) {
    const tag = /\.mp4$/.test(url) ? "VIDEO" : "IMG";
    const attributes = {
      src: url
    };
    let eventName = "load";
    if (tag === "VIDEO") {
      attributes.autoplay = true;
      attributes.loop = true;
      attributes.muted = true;
      attributes.playsinline = true;
      eventName = "loadeddata";
    }
    this.nodes.imageEl = this.make(tag, this.CSS.imageEl, attributes);
    this.nodes.imageEl.addEventListener(eventName, () => {
      this.toggleStatus(Ui.status.FILLED);
      if (this.nodes.imagePreloader) {
        this.nodes.imagePreloader.style.backgroundImage = "";
      }
    });
    this.nodes.imageContainer.appendChild(this.nodes.imageEl);
  }
  /**
   * Shows caption input
   *
   * @param {string} text - caption text
   * @returns {void}
   */
  fillCaption(text) {
    if (this.nodes.caption) {
      this.nodes.caption.innerHTML = text;
    }
  }
  /**
   * Changes UI status
   *
   * @param {string} status - see {@link Ui.status} constants
   * @returns {void}
   */
  toggleStatus(status) {
    for (const statusType in Ui.status) {
      if (Object.prototype.hasOwnProperty.call(Ui.status, statusType)) {
        this.nodes.wrapper.classList.toggle(
          `${this.CSS.wrapper}--${Ui.status[statusType]}`,
          status === Ui.status[statusType]
        );
      }
    }
  }
  /**
   * Apply visual representation of activated tune
   *
   * @param {string} tuneName - one of available tunes {@link Tunes.tunes}
   * @param {boolean} status - true for enable, false for disable
   * @returns {void}
   */
  applyTune(tuneName, status) {
    this.nodes.wrapper.classList.toggle(
      `${this.CSS.wrapper}--${tuneName}`,
      status
    );
  }
  make(tagName, classNames = null, attributes = {}) {
    const el = document.createElement(tagName);
    if (Array.isArray(classNames)) {
      el.classList.add(...classNames);
    } else if (classNames) {
      el.classList.add(classNames);
    }
    for (const attrName in attributes) {
      el[attrName] = attributes[attrName];
    }
    return el;
  }
}
const ToolboxIcon = "data:image/svg+xml,%3csvg%20xmlns='http://www.w3.org/2000/svg'%20width='16'%20height='16'%20fill='currentColor'%20class='bi%20bi-play-fill'%20viewBox='0%200%2016%2016'%3e%3cpath%20d='m11.596%208.697-6.363%203.692c-.54.313-1.233-.066-1.233-.697V4.308c0-.63.692-1.01%201.233-.696l6.363%203.692a.802.802%200%200%201%200%201.393'/%3e%3c/svg%3e";
class Embed extends Abstract {
  static get toolbox() {
    return { title: "Embed", icon: ToolboxIcon };
  }
  constructor({
    data,
    config,
    api,
    readOnly
  }) {
    super({ data, config, api, readOnly });
    if (this._data.image) {
      this._data.media = this._data.image.media;
      delete this._data.image;
    }
    this.onSelectFile = (config == null ? void 0 : config.onSelectFile) || this.defaultOnSelectFile;
    this.onUploadFile = (config == null ? void 0 : config.onUploadFile) || "";
    this.uploader = new Uploader({
      config: this.config,
      onUpload: (response) => this.onUpload(response),
      onError: (response) => this.uploadingFailed(response)
    });
  }
  get defaultOnSelectFile() {
    return () => {
      this.uploader.uploadSelectedFile({
        onPreview: (src) => {
          this.showPreloader(src);
        }
      });
    };
  }
  onUpload(response) {
    if (response.success && response.file) {
      if (response.file.url) {
        this._data.media = response.file.media;
        this.fillImage("/media/md/" + this._data.media);
      } else {
        this.uploadingFailed("incorrect response: " + JSON.stringify(response));
      }
    }
  }
  uploadingFailed(error) {
    logger.error("Embed Tool: uploading failed", error);
    this.api.notifier.show({
      message: this.api.i18n.t("Couldn't upload image. Please try another."),
      style: "error"
    });
    this.hidePreloader();
  }
  createInputs() {
    this.nodes.inputAlternativeText = make.input(
      this,
      ["cdx-input-labeled", "cdx-input-labeled-embed-text", ...this.CSS.inputClass],
      "Alternative Text",
      this._data.alternativeText
    );
    this.nodes.inputServiceUrl = make.input(
      this,
      [
        "cdx-input-labeled",
        "cdx-input-labeled-embed-service-url",
        ...this.CSS.inputClass
      ],
      "Service URL",
      this._data.serviceUrl
    );
    this.createImageInput();
    const wrapper = make.element("div");
    wrapper.appendChild(this.nodes.inputAlternativeText);
    wrapper.appendChild(this.nodes.inputServiceUrl);
    wrapper.appendChild(this.nodes.fileButton);
    return wrapper;
  }
  createImageInput() {
    this.nodes.imagePreloader = make.element("div", "image-tool__image-preloader");
    this.nodes.imagePreloader.style.display = "none";
    this.nodes.fileButton = make.fileButtons(this);
    this.nodes.fileButton.appendChild(this.nodes.imagePreloader);
    if (this._data.media) {
      this.fillImage("/media/md/" + this._data.media);
    }
  }
  show(state) {
    if (state === this.STATE.VIEW) {
      if (this.validate()) {
        this.nodes.preview.innerHTML = `<div style="display:block;--aspect-ratio:16/9;background: center / cover no-repeat url('/media/md/` + this._data.media + `');"><div style="display: flex;justify-content: center;align-items: center; width:100%;height:100%;color:#c4302b">` + ToolboxIcon.replace('width="16"', 'width="100"').replace(
          'height="16"',
          'height="100"'
        ) + "</div></div>";
      } else {
        this.api.notifier.show({
          message: this.api.i18n.t(
            "Something is missing to properly render the embeded video."
          ),
          style: "error"
        });
      }
    }
    super.show(state);
  }
  updateData() {
    this._data.serviceUrl = this.nodes.inputServiceUrl.textContent || "";
    this._data.alternativeText = this.nodes.inputAlternativeText.textContent || "";
  }
  showPreloader(src) {
    this.nodes.imagePreloader.style.display = "block";
    this.nodes.imagePreloader.style.backgroundImage = "url(" + src + ")";
    this.toggleStatus(Ui.status.UPLOADING);
  }
  hidePreloader() {
    this.nodes.imagePreloader.style.display = "none";
    this.nodes.imagePreloader.style.backgroundImage = "";
    this.toggleStatus(Ui.status.EMPTY);
  }
  fillImage(src) {
    if (this.nodes.imageEl) {
      this.nodes.imageEl.remove();
    }
    this.nodes.imageEl = make.element("img", "image-tool__image-picture", {
      src,
      style: "max-height:47px;padding-left:1em"
    });
    this.showPreloader(src);
    const Tool = this;
    this.nodes.imageEl.addEventListener("load", function() {
      Tool.toggleStatus(Ui.status.FILLED);
      if (Tool.nodes.imagePreloader) {
        Tool.hidePreloader();
      }
    });
    this.nodes.fileButton.appendChild(this.nodes.imageEl);
    if (this.validate() && this.nodes.inputs) {
      this.show(this.STATE.VIEW);
    }
  }
  validate() {
    return !!(this._data.serviceUrl && this._data.alternativeText && this._data.media);
  }
  /**
   * Export block data to Markdown
   * @returns {string} Markdown representation
   */
  exportToMarkdown() {
    if (!this.data || !this.data.media || !this.data.serviceUrl) {
      return "";
    }
    let markdown = `[![${this.data.alternativeText || ""}](/media/md/${this.data.media})](${this.data.serviceUrl})`;
    if (this.data.anchor) {
      markdown = `{#${this.data.anchor} }
${markdown}`;
    }
    return markdown;
  }
  toggleStatus(status) {
    var _a;
    for (const statusType in Ui.status) {
      if (Object.prototype.hasOwnProperty.call(Ui.status, statusType)) {
        (_a = this.nodes.wrapper) == null ? void 0 : _a.classList.toggle(
          this.CSS.wrapper + "--" + Ui.status[statusType],
          status === Ui.status[statusType]
        );
      }
    }
  }
}
export {
  Embed as default
};
