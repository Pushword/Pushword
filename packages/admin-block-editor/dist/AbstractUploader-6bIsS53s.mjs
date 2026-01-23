import ajax from "@codexteam/ajax";
class Uploader {
  /**
   * @param {object} params - uploader module params
   * @param {ImageConfig} params.config - image tool config
   * @param {Function} params.onUpload - one callback for all uploading (file, url, d-n-d, pasting)
   * @param {Function} params.onError - callback for uploading errors
   */
  constructor({
    config,
    onUpload,
    onError
  }) {
    this.config = config;
    this.onUpload = onUpload;
    this.onError = onError;
  }
  /**
   * Handle clicks on the upload file button
   * Fires ajax.transport()
   *
   * @param {Function} onPreview - callback fired when preview is ready
   */
  uploadSelectedFile({
    onPreview
  }) {
    var _a;
    const preparePreview = (file) => {
      const reader = new FileReader();
      reader.readAsDataURL(file);
      reader.onload = (e) => {
        var _a2;
        if ((_a2 = e.target) == null ? void 0 : _a2.result) {
          onPreview(e.target.result);
        }
      };
    };
    let upload;
    if (this.config.uploader && typeof this.config.uploader.uploadByFile === "function") {
      upload = ajax.selectFiles({ accept: this.config.types }).then((files) => {
        preparePreview(files[0]);
        const customUpload = this.config.uploader.uploadByFile(files[0]);
        if (!this.isPromise(customUpload)) {
          console.warn(
            "Custom uploader method uploadByFile should return a Promise"
          );
        }
        return customUpload;
      });
    } else {
      upload = ajax.transport({
        url: ((_a = this.config.endpoints) == null ? void 0 : _a.byFile) || "",
        data: this.config.additionalRequestData,
        accept: this.config.types,
        headers: this.config.additionalRequestHeaders,
        beforeSend: (files) => {
          preparePreview(files[0]);
        },
        fieldName: this.config.field
      }).then((response) => response.body);
    }
    upload.then((response) => {
      this.onUpload(response);
    }).catch((error) => {
      this.onError(error);
    });
  }
  /**
   * Handle clicks on the upload file button
   * Fires ajax.post()
   *
   * @param {string} url - image source url
   */
  uploadByUrl(url) {
    var _a;
    let upload;
    if (this.config.uploader && typeof this.config.uploader.uploadByUrl === "function") {
      upload = this.config.uploader.uploadByUrl(url);
      if (!this.isPromise(upload)) {
        console.warn(
          "Custom uploader method uploadByUrl should return a Promise"
        );
      }
    } else {
      upload = ajax.post({
        url: ((_a = this.config.endpoints) == null ? void 0 : _a.byUrl) || "",
        data: Object.assign(
          {
            url
          },
          this.config.additionalRequestData
        ),
        type: ajax.contentType.JSON,
        headers: this.config.additionalRequestHeaders
      }).then((response) => response.body);
    }
    upload.then((response) => {
      this.onUpload(response);
    }).catch((error) => {
      this.onError(error);
    });
  }
  /**
   * Handle clicks on the upload file button
   * Fires ajax.post()
   *
   * @param {File} file - file pasted by drag-n-drop
   * @param {Function} onPreview - file pasted by drag-n-drop
   */
  uploadByFile(file, { onPreview }) {
    var _a;
    const reader = new FileReader();
    reader.readAsDataURL(file);
    reader.onload = (e) => {
      var _a2;
      if ((_a2 = e.target) == null ? void 0 : _a2.result) {
        onPreview(e.target.result);
      }
    };
    let upload;
    if (this.config.uploader && typeof this.config.uploader.uploadByFile === "function") {
      upload = this.config.uploader.uploadByFile(file);
      if (!this.isPromise(upload)) {
        console.warn(
          "Custom uploader method uploadByFile should return a Promise"
        );
      }
    } else {
      const formData = new FormData();
      formData.append(this.config.field || "file", file);
      if (this.config.additionalRequestData && Object.keys(this.config.additionalRequestData).length) {
        Object.entries(this.config.additionalRequestData).forEach(
          ([name, value]) => {
            formData.append(name, value);
          }
        );
      }
      upload = ajax.post({
        url: ((_a = this.config.endpoints) == null ? void 0 : _a.byFile) || "",
        data: formData,
        type: ajax.contentType.JSON,
        headers: this.config.additionalRequestHeaders
      }).then((response) => response.body);
    }
    upload.then((response) => {
      this.onUpload(response);
    }).catch((error) => {
      this.onError(error);
    });
  }
  isPromise(object) {
    return object && typeof object.then === "function";
  }
}
export {
  Uploader as U
};
