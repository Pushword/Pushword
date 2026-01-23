import { m as make } from "./make-0i_R_m69.mjs";
import Raw from "./Raw.js";
const Icon = "data:image/svg+xml,%3csvg%20xmlns='http://www.w3.org/2000/svg'%20width='16'%20height='16'%20fill='currentColor'%20class='bi%20bi-braces'%20viewBox='0%200%2016%2016'%3e%3cpath%20d='M2.114%208.063V7.9c1.005-.102%201.497-.615%201.497-1.6V4.503c0-1.094.39-1.538%201.354-1.538h.273V2h-.376C3.25%202%202.49%202.759%202.49%204.352v1.524c0%201.094-.376%201.456-1.49%201.456v1.299c1.114%200%201.49.362%201.49%201.456v1.524c0%201.593.759%202.352%202.372%202.352h.376v-.964h-.273c-.964%200-1.354-.444-1.354-1.538V9.663c0-.984-.492-1.497-1.497-1.6M13.886%207.9v.163c-1.005.103-1.497.616-1.497%201.6v1.798c0%201.094-.39%201.538-1.354%201.538h-.273v.964h.376c1.613%200%202.372-.759%202.372-2.352v-1.524c0-1.094.376-1.456%201.49-1.456V7.332c-1.114%200-1.49-.362-1.49-1.456V4.352C13.51%202.759%2012.75%202%2011.138%202h-.376v.964h.273c.964%200%201.354.444%201.354%201.538V6.3c0%20.984.492%201.497%201.497%201.6'/%3e%3c/svg%3e";
class CodeBlock extends Raw {
  constructor({ data, config, api, readOnly }) {
    super({ data, config, api, readOnly });
    this.data = {
      html: data.html || "",
      language: data.language || "html"
    };
  }
  render() {
    const wrapper = super.render();
    const select = make.element("select", this.api.styles.input, {
      style: "max-width: 100px;padding: 5px 6px;margin: auto; position: absolute; right: 5px; z-index: 5; background: white"
    });
    make.options(select, ["html", "twig", "javascript", "php", "json", "yaml"]);
    select.value = this.data.language;
    select.addEventListener("change", (event) => {
      this.data.language = event.target.value;
      this.editorInstance.getModel().setLanguage(this.data.language);
    });
    const editorWrapper = wrapper.firstChild;
    wrapper.insertBefore(select, editorWrapper);
    wrapper.style.marginBottom = "35px";
    wrapper.style.position = "relative";
    wrapper.classList.add("monaco-codeblock-wrapper");
    return wrapper;
  }
  /**
   * Extract Tool's data from the view
   *
   * @param {HTMLDivElement} wrapper - RawTool's wrapper, containing textarea with raw HTML code
   * @returns {RawData} - raw HTML code
   * @public
   */
  save(wrapper) {
    let html = "";
    try {
      html = this.editorInstance.getValue();
    } catch (error) {
      console.error(error);
    }
    this.data = {
      html,
      language: this.data.language
      // wrapper.querySelector('select.CodeBlock_language').value
    };
    return this.data;
  }
  static get toolbox() {
    return {
      icon: Icon,
      title: "Raw"
    };
  }
}
export {
  CodeBlock as default
};
