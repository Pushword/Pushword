import { m as make } from "./make-D456KYKV.mjs";
import { U as Uploader } from "./AbstractUploader-6bIsS53s.mjs";
import { IconPicture } from "@codexteam/icons";
import { l as logger } from "./logger-5AkeQ-mP.mjs";
class Image {
  static get toolbox() {
    return {
      title: "Image",
      icon: IconPicture
    };
  }
  /**
   * CSS classes
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
      imageEl: "image-tool__image-picture",
      imagePreloader: "image-tool__image-preloader",
      caption: "image-tool__caption"
    };
  }
  /**
   * Ui statuses:
   * - empty
   * - uploading
   * - filled
   */
  static get status() {
    return {
      EMPTY: "empty",
      UPLOADING: "loading",
      FILLED: "filled"
    };
  }
  constructor({
    data,
    config,
    api,
    readOnly
  }) {
    this.api = api;
    this.config = config || {};
    this.readOnly = readOnly || false;
    this.data = data || {};
    this.imageUrl = null;
    this.caption = "";
    this.onSelectFile = (config == null ? void 0 : config.onSelectFile) || this.defaultOnSelectFile;
    this.onUploadFile = (config == null ? void 0 : config.onUploadFile) || this.defaultOnUploadFile;
    this.nodes = {};
    this.uploader = new Uploader({
      config: {
        endpoints: (config == null ? void 0 : config.endpoints) || "",
        additionalRequestData: (config == null ? void 0 : config.additionalRequestData) || {},
        additionalRequestHeaders: (config == null ? void 0 : config.additionalRequestHeaders) || {},
        field: (config == null ? void 0 : config.field) || "image",
        types: (config == null ? void 0 : config.types) || "image/*",
        captionPlaceholder: this.api.i18n.t("Caption"),
        buttonContent: (config == null ? void 0 : config.buttonContent) || "",
        uploader: (config == null ? void 0 : config.uploader) || void 0
      },
      onUpload: (response) => this.onUpload(response),
      onError: (error) => this.uploadingFailed(error)
    });
  }
  extractMediaName(url) {
    if (!url) return "";
    const urlParts = url.split("/");
    return urlParts[urlParts.length - 1];
  }
  isFullUrl(data) {
    if (!data || typeof data !== "string") return false;
    return data.startsWith("http://") || data.startsWith("https://") || data.startsWith("/") || data.includes("/");
  }
  buildFullUrl(mediaNameOrUrl) {
    if (this.isFullUrl(mediaNameOrUrl)) {
      return mediaNameOrUrl;
    }
    return `/media/md/${mediaNameOrUrl}`;
  }
  defaultOnSelectFile() {
    this.uploader.uploadSelectedFile({
      onPreview: (src) => {
        this.showPreloader(src);
      }
    });
  }
  defaultOnUploadFile() {
    this.uploader.uploadSelectedFile({
      onPreview: (src) => {
        this.showPreloader(src);
      }
    });
  }
  /**
   * Toggle tool's status
   */
  toggleStatus(status) {
    for (const statusType in Image.status) {
      if (Object.prototype.hasOwnProperty.call(Image.status, statusType)) {
        this.nodes.wrapper.classList.toggle(
          `${this.CSS.wrapper}--${Image.status[statusType]}`,
          status === Image.status[statusType]
        );
      }
    }
  }
  showPreloader(src) {
    if (this.nodes.imagePreloader && src) {
      this.nodes.imagePreloader.style.backgroundImage = `url(${src})`;
    }
    this.toggleStatus(Image.status.UPLOADING);
  }
  hidePreloader() {
    if (this.nodes.imagePreloader) {
      this.nodes.imagePreloader.style.backgroundImage = "";
    }
    this.toggleStatus(Image.status.EMPTY);
  }
  onUpload(response) {
    if (response.success && response.file) {
      this.fillImage(response.file.url);
      this.imageUrl = response.file.url;
      if (response.file.name) {
        this.caption = response.file.name;
        this.fillCaption(this.caption);
      }
    } else {
      this.uploadingFailed("incorrect response: " + JSON.stringify(response));
    }
  }
  uploadingFailed(errorText) {
    logger.error("Image: uploading failed", errorText);
    this.hidePreloader();
    this.showFileButton();
    this.api.notifier.show({
      message: this.api.i18n.t("Échec du téléchargement de l'image"),
      style: "error"
    });
  }
  fillImage(url) {
    if (this.nodes.imageContainer) {
      if (this.nodes.imageEl) {
        this.nodes.imageEl.remove();
      }
      const img = make.element("img", this.CSS.imageEl);
      img.src = url;
      img.addEventListener("load", () => {
        this.toggleStatus(Image.status.FILLED);
        if (this.nodes.imagePreloader) {
          this.nodes.imagePreloader.style.backgroundImage = "";
        }
      });
      this.nodes.imageEl = img;
      this.nodes.imageContainer.appendChild(img);
    }
  }
  fillCaption(text) {
    if (this.nodes.caption) {
      this.nodes.caption.textContent = text || "";
    }
  }
  showFileButton() {
    this.toggleStatus(Image.status.EMPTY);
  }
  createImageInput() {
    this.nodes = {
      wrapper: make.element("div", [this.CSS.baseClass, this.CSS.wrapper]),
      imageContainer: make.element("div", [this.CSS.imageContainer]),
      fileButton: this.createFileButton(),
      imageEl: void 0,
      imagePreloader: make.element("div", this.CSS.imagePreloader),
      caption: make.element("div", [this.CSS.input, this.CSS.caption], {
        contentEditable: !this.readOnly
      })
    };
    this.nodes.caption.dataset.placeholder = this.config.captionPlaceholder || this.api.i18n.t("Caption");
    this.nodes.imageContainer.appendChild(this.nodes.imagePreloader);
    this.nodes.wrapper.appendChild(this.nodes.imageContainer);
    this.nodes.wrapper.appendChild(this.nodes.caption);
    this.nodes.wrapper.appendChild(this.nodes.fileButton);
    return this.nodes.wrapper;
  }
  createFileButton() {
    try {
      return make.fileButtons(this, ["cdx-input-gallery"]);
    } catch (error) {
      logger.warn("Erreur lors de la création du bouton fichier", error);
      const button = make.element("div", [this.CSS.button]);
      button.textContent = this.api.i18n.t("Select an Image");
      button.addEventListener("click", () => this.defaultOnSelectFile());
      return button;
    }
  }
  render() {
    const wrapper = this.createImageInput();
    if (!this.data.media && (!this.data.file || Object.keys(this.data.file || {}).length === 0)) {
      this.toggleStatus(Image.status.EMPTY);
    } else {
      let url = "";
      if (this.data.media) {
        url = this.buildFullUrl(this.data.media);
      } else if (this.data.file) {
        if (typeof this.data.file === "string") {
          url = this.buildFullUrl(this.data.file);
        } else if (this.data.file.url) {
          url = this.data.file.url;
        }
      }
      if (url) {
        this.fillImage(url);
        this.imageUrl = url;
        if (this.data.caption) {
          this.caption = this.data.caption;
          this.fillCaption(this.caption);
        }
      } else {
        this.toggleStatus(Image.status.EMPTY);
      }
    }
    return wrapper;
  }
  save() {
    var _a;
    if (this.imageUrl) {
      const mediaName = this.extractMediaName(this.imageUrl);
      let caption = "";
      if (this.nodes.caption) {
        caption = ((_a = this.nodes.caption.textContent) == null ? void 0 : _a.trim()) || "";
      }
      return {
        media: mediaName,
        caption
      };
    }
    return {};
  }
  validate() {
    return !!this.imageUrl;
  }
  /**
   * Export block data to Markdown
   * @returns {string} Markdown representation
   */
  exportToMarkdown() {
    if (!this.data || !this.data.media) {
      return "";
    }
    let markdown = `![${this.data.caption || ""}](/media/md/${this.data.media})`;
    if (this.data.anchor) {
      markdown = `{#${this.data.anchor} }
${markdown}`;
    }
    return markdown;
  }
  static get pasteConfig() {
    return {
      tags: ["img"],
      patterns: {
        image: /(https?:\/\/|\/media\/)\S+\.(gif|jpe?g|png|webp)$/i
      },
      files: {
        mimeTypes: ["image/*"]
      }
    };
  }
  onPaste(event) {
    switch (event.type) {
      case "tag": {
        const img = event.detail.data;
        const url = img.src;
        if (url) {
          this.fillImage(url);
          this.imageUrl = url;
        }
        break;
      }
      case "pattern": {
        const url = event.detail.data;
        if (url) {
          this.fillImage(url);
          this.imageUrl = url;
        }
        break;
      }
      case "file": {
        event.detail.file;
        this.uploader.uploadSelectedFile({
          onPreview: (src) => {
            this.showPreloader(src);
          }
        });
        break;
      }
    }
  }
}
export {
  Image as default
};
