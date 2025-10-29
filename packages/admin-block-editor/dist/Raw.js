const Icon = "data:image/svg+xml,%3csvg%20xmlns='http://www.w3.org/2000/svg'%20width='16'%20height='16'%20fill='currentColor'%20class='bi%20bi-code-square'%20viewBox='0%200%2016%2016'%3e%3cpath%20d='M14%201a1%201%200%200%201%201%201v12a1%201%200%200%201-1%201H2a1%201%200%200%201-1-1V2a1%201%200%200%201%201-1zM2%200a2%202%200%200%200-2%202v12a2%202%200%200%200%202%202h12a2%202%200%200%200%202-2V2a2%202%200%200%200-2-2z'/%3e%3cpath%20d='M6.854%204.646a.5.5%200%200%201%200%20.708L4.207%208l2.647%202.646a.5.5%200%200%201-.708.708l-3-3a.5.5%200%200%201%200-.708l3-3a.5.5%200%200%201%20.708%200m2.292%200a.5.5%200%200%200%200%20.708L11.793%208l-2.647%202.646a.5.5%200%200%200%20.708.708l3-3a.5.5%200%200%200%200-.708l-3-3a.5.5%200%200%200-.708%200'/%3e%3c/svg%3e";
class Raw {
  static get enableLineBreaks() {
    return true;
  }
  constructor({ api, data }) {
    this.api = api;
    this.html = data.html === void 0 ? "" : data.html;
  }
  instantiateEditor(editorElem) {
    return monaco.editor.create(editorElem, {
      value: this.html,
      language: "twig",
      ...monacoHelper.defaultSettings
    });
  }
  render() {
    this.wrapper = document.createElement("div");
    this.wrapper.classList.add("editorjs-monaco-wrapper");
    let editorElem = document.createElement("div");
    editorElem.classList.add("editorjs-monaco-editor");
    editorElem.style.height = "100%";
    this.wrapper.appendChild(editorElem);
    if (typeof window.monaco === "undefined") {
      console.log("monaco is not defined");
      return this.wrapper;
    }
    const monacoHelper2 = window.monacoHelper;
    this.editorInstance = this.instantiateEditor(editorElem);
    const monacoHelperInstance = new monacoHelper2(this.editorInstance);
    monacoHelperInstance.updateHeight(this.wrapper);
    this.editorInstance.onDidChangeModelContent(() => {
      monacoHelperInstance.updateHeight(this.wrapper);
      monacoHelperInstance.autocloseTag();
    });
    return this.wrapper;
  }
  // debounce(func, timeout = 500) {
  //   let timer
  //   return (...args) => {
  //     clearTimeout(timer)
  //     timer = setTimeout(() => func(...args), timeout)
  //   }
  // }
  save() {
    this.html = this.editorInstance.getValue();
    return { html: this.html };
  }
  static get isReadOnlySupported() {
    return true;
  }
  static get toolbox() {
    return {
      icon: Icon,
      title: "Raw"
    };
  }
}
export {
  Raw as default
};
