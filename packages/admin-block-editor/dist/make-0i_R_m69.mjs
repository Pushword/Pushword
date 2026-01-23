import { S as SelectIcon, U as UploadIcon } from "./upload-CneyImFQ.mjs";
class make {
  static element(tagName, classNames = null, attributes = {}, innerHTML = "") {
    const el = document.createElement(tagName);
    if (Array.isArray(classNames)) {
      el.classList.add(...classNames);
    } else if (classNames) {
      el.classList.add(classNames);
    }
    for (const attrName in attributes) {
      el.setAttribute(attrName, attributes[attrName]);
    }
    if (innerHTML !== "") {
      el.innerHTML = innerHTML;
    }
    return el;
  }
  /**
   * @returns HTMLElement
   */
  static input(Tool, classNames, placeholder, value = "") {
    const input = make.element("div", classNames, { contentEditable: !Tool.readOnly });
    input.dataset.placeholder = Tool.api.i18n.t(placeholder);
    if (value) input.textContent = value;
    return input;
  }
  static option(select, key, value = null, attributes = {}) {
    const option = document.createElement("option");
    option.text = value || key;
    option.value = key;
    for (const attrName in attributes) {
      option.setAttribute(attrName, attributes[attrName]);
    }
    select.add(option);
  }
  static options(select, options) {
    options.forEach((option) => make.option(select, option));
  }
  static fileButtons(Tool, buttonClass = []) {
    const buttonWrapper = make.element("div", [
      "flex",
      "cdx-input-labeled-preview",
      "cdx-input-labeled",
      "cdx-input",
      "cdx-input-editable",
      ...buttonClass
    ]);
    const selectButton = make.element("div", [Tool.api.styles.button]);
    selectButton.innerHTML = SelectIcon + " " + Tool.api.i18n.t("Select");
    selectButton.addEventListener("click", (event) => Tool.onSelectFile(Tool, event));
    buttonWrapper.appendChild(selectButton);
    if (Tool.onUploadFile) {
      const uploadButton = make.element("div", [Tool.api.styles.button]);
      uploadButton.innerHTML = `${UploadIcon} ${Tool.api.i18n.t("Upload")}`;
      uploadButton.style.marginLeft = "-2px";
      uploadButton.addEventListener("click", (event) => Tool.onUploadFile(Tool, event));
      buttonWrapper.appendChild(uploadButton);
    }
    return buttonWrapper;
  }
  static switchInput(name, labelText, checked = false) {
    let wrapper = make.element("div", "editor-switch");
    let checkbox = make.element("input", null, { type: "checkbox", id: name });
    let switchElement = make.element("label", "label-default", { for: name });
    let label = make.element("label", "", { for: name });
    label.innerHTML = labelText;
    wrapper.append(checkbox, switchElement, label);
    if (checked) {
      checkbox.checked = checked;
    }
    return wrapper;
  }
  static selectionCollapseToEnd() {
    const sel = window.getSelection();
    const range = document.createRange();
    range.selectNodeContents(sel.focusNode);
    range.collapse(false);
    sel.removeAllRanges();
    sel.addRange(range);
  }
}
export {
  make as m
};
