require("./Anchor.css").toString();
class Anchor {
  /**
   * Current anchor
   * @returns {bool}
   */
  static get isTune() {
    return true;
  }
  getAnchor() {
    return this.data || "";
  }
  /**
   * Constructor
   *
   * @param api - Editor.js API
   * @param data — previously saved data
   */
  constructor({ api, data, config, block }) {
    this.api = api;
    this.data = data || "";
    this.block = block;
    this._CSS = {
      classWrapper: "cdx-anchor-tune-wrapper",
      classIcon: "cdx-anchor-tune-icon",
      classInput: "cdx-anchor-tune-input"
    };
  }
  /**
   * Rendering tune wrapper
   * @returns {*}
   */
  render(value = null) {
    const wrapper = document.createElement("div");
    wrapper.classList.add(this._CSS.classWrapper);
    const wrapperIcon = document.createElement("div");
    wrapperIcon.classList.add(this._CSS.classIcon);
    wrapperIcon.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" data-slot="icon" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 8.25h15m-16.5 7.5h15m-1.8-13.5-3.9 19.5m-2.1-19.5-3.9 19.5" /></svg>';
    const wrapperInput = document.createElement("input");
    wrapperInput.placeholder = this.api.i18n.t("Anchor");
    wrapperInput.classList.add(this._CSS.classInput);
    wrapperInput.value = value ? value : this.getAnchor();
    wrapperInput.addEventListener("input", (event) => {
      var _a;
      let value2 = event.target.value.replace(/[^a-z0-9_-]/gi, "");
      if (value2.length > 0) {
        this.data = value2;
      } else {
        this.data = "";
      }
      (_a = this.block) == null ? void 0 : _a.dispatchChange();
    });
    this.input = wrapperInput;
    wrapper.appendChild(wrapperIcon);
    wrapper.appendChild(wrapperInput);
    return wrapper;
  }
  /**
   * Save
   * @returns {*}
   */
  save() {
    return this.data;
  }
}
export {
  Anchor as default
};
