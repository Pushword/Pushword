import { m as make } from "./make-D456KYKV.mjs";
import { IconLink } from "@codexteam/icons";
class HyperlinkTune {
  constructor({
    api,
    data,
    config,
    block
  }) {
    this._CSS = {
      classWrapper: "cdx-anchor-tune-wrapper",
      classIcon: "cdx-anchor-tune-icon",
      classInput: "cdx-anchor-tune-input"
    };
    this.api = api;
    this.data = data || { url: "", hideForBot: true, targetBlank: false };
    this.block = block;
    this.nodes = {};
    this.i18n = api.i18n;
  }
  static get isTune() {
    return true;
  }
  /**
   * Rendering tune wrapper
   * @returns {*}
   */
  render(value = null) {
    console.log(this.data, value);
    const wrapper = document.createElement("div");
    wrapper.classList.add("cdx-anchor-tune-wrapper");
    wrapper.style.display = "block";
    wrapper.style.position = "relative";
    const wrapperIcon = document.createElement("div");
    wrapperIcon.classList.add("cdx-anchor-tune-icon");
    wrapperIcon.style.position = "absolute";
    wrapperIcon.style.left = "-2px";
    wrapperIcon.style.width = "25px";
    wrapperIcon.style.opacity = "0.9";
    wrapperIcon.style.height = "25px";
    wrapperIcon.innerHTML = IconLink;
    wrapper.appendChild(wrapperIcon);
    this.nodes.url = make.input(
      this,
      ["cdx-input-labeled", "cdx-input-full"],
      ":self OR /url",
      this.data.url
    );
    this.nodes.url.style.backgroundColor = "white";
    this.nodes.url.style.borderRadius = "6px";
    this.nodes.url.style.padding = "4px";
    this.nodes.url.style.paddingLeft = "22px";
    this.nodes.url.style.fontSize = "14px";
    this.nodes.hideForBot = make.switchInput(
      "hideForBot",
      this.i18n.t("Obfusquer"),
      this.data.hideForBot || false
    );
    this.nodes.targetBlank = make.switchInput(
      "targetBlank",
      this.i18n.t("Nouvel onglet"),
      this.data.targetBlank || false
    );
    wrapper.appendChild(this.nodes.url);
    wrapper.appendChild(this.nodes.hideForBot);
    wrapper.appendChild(this.nodes.targetBlank);
    const urlChangeHandler = () => {
      this._updateData();
    };
    this.nodes.url.addEventListener("input", urlChangeHandler);
    this.nodes.url.addEventListener("blur", urlChangeHandler);
    this.nodes.url.addEventListener("keyup", urlChangeHandler);
    this.nodes.hideForBot.addEventListener("change", () => {
      this._updateData();
    });
    this.nodes.targetBlank.addEventListener("change", () => {
      this._updateData();
    });
    return wrapper;
  }
  /**
   * Return tool's data
   * @returns {*}
   */
  save() {
    return {
      url: this.data.url ?? "",
      hideForBot: this.data.hideForBot ?? true,
      targetBlank: this.data.targetBlank ?? false
    };
  }
  _updateData() {
    var _a;
    this.data.url = this.nodes.url.textContent || "";
    this.data.hideForBot = this.nodes.hideForBot.querySelector("input").checked;
    this.data.targetBlank = this.nodes.targetBlank.querySelector("input").checked;
    console.log(this.block);
    (_a = this.block) == null ? void 0 : _a.dispatchChange();
    console.log(this.api);
    this.api.saver.save();
    return this.data;
  }
}
export {
  HyperlinkTune as default
};
