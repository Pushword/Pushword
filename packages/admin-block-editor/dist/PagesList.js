import { A as Abstract } from "./Abstract-DQgJkkKe.mjs";
import { m as make } from "./make-D456KYKV.mjs";
import ajax from "@codexteam/ajax";
const ToolboxIcon = "data:image/svg+xml,%3csvg%20xmlns='http://www.w3.org/2000/svg'%20width='16'%20height='16'%20fill='currentColor'%20class='bi%20bi-stack'%20viewBox='0%200%2016%2016'%3e%3cpath%20d='m14.12%2010.163%201.715.858c.22.11.22.424%200%20.534L8.267%2015.34a.6.6%200%200%201-.534%200L.165%2011.555a.299.299%200%200%201%200-.534l1.716-.858%205.317%202.659c.505.252%201.1.252%201.604%200l5.317-2.66zM7.733.063a.6.6%200%200%201%20.534%200l7.568%203.784a.3.3%200%200%201%200%20.535L8.267%208.165a.6.6%200%200%201-.534%200L.165%204.382a.299.299%200%200%201%200-.535z'/%3e%3cpath%20d='m14.12%206.576%201.715.858c.22.11.22.424%200%20.534l-7.568%203.784a.6.6%200%200%201-.534%200L.165%207.968a.299.299%200%200%201%200-.534l1.716-.858%205.317%202.659c.505.252%201.1.252%201.604%200z'/%3e%3c/svg%3e";
class PagesList extends Abstract {
  constructor({
    data,
    config,
    api,
    readOnly
  }) {
    super({ data, config, api, readOnly });
    this.nodes = {};
  }
  static get toolbox() {
    return {
      title: "Pages",
      icon: ToolboxIcon
    };
  }
  getTags() {
    let tags = [
      "children",
      "sisters",
      "grandchildren",
      "related",
      "title: exampleSearchValue",
      "content:",
      "slug:"
    ];
    const dataTags = document.querySelector("[data-tags]");
    if (dataTags) {
      tags = JSON.parse(dataTags.dataset.tags || "[]").concat(tags);
    }
    if (window.pagesUriList) {
      tags = tags.concat(
        window.pagesUriList.map((str) => str.replace(/^\//, "slug:"))
      );
    }
    return tags;
  }
  createInputs() {
    this.nodes.kwInput = make.input(
      this,
      ["cdx-input-labeled", "cdx-input-labeled-pageslist-kw", ...this.CSS.inputClass],
      "...",
      this._data.kw
    );
    this.nodes.suggester = make.element("div", "textSuggester", {
      style: "display:none"
    });
    this.nodes.displaySelect = document.createElement("select");
    this.nodes.displaySelect.classList.add("cdx-select");
    this.nodes.displaySelect.classList.add("mr-5px");
    make.option(this.nodes.displaySelect, "", "format", { disabled: true });
    make.options(this.nodes.displaySelect, ["list", "card"]);
    if (this._data.display) {
      this.nodes.displaySelect.value = this._data.display;
    }
    const detailsWrapper = make.element("div", ["flex"]);
    detailsWrapper.style.marginBottom = "15px";
    this.nodes.maxInput = make.input(
      this,
      [
        "cdx-input-labeled",
        "cdx-input-labeled-pageslist-max",
        "text-right",
        ...this.CSS.inputClass
      ],
      "9",
      this._data.max
    );
    this.nodes.maxInput.title = "max Items per Page";
    this.nodes.maxPagesInput = make.input(
      this,
      [
        "cdx-input-labeled",
        "cdx-input-labeled-pageslist-maxpages",
        "text-right",
        ...this.CSS.inputClass
      ],
      "1",
      this._data.maxPages
    );
    this.nodes.maxPagesInput.title = "max Pages";
    detailsWrapper.appendChild(this.nodes.displaySelect);
    detailsWrapper.appendChild(this.createOrderSelect());
    detailsWrapper.appendChild(this.nodes.maxInput);
    detailsWrapper.appendChild(this.nodes.maxPagesInput);
    const inputsWrapper = make.element("div");
    inputsWrapper.appendChild(this.nodes.kwInput);
    inputsWrapper.appendChild(this.nodes.suggester);
    inputsWrapper.appendChild(detailsWrapper);
    return inputsWrapper;
  }
  createOrderSelect() {
    this.nodes.orderSelect = document.createElement("select");
    this.nodes.orderSelect.classList.add("cdx-select");
    make.option(this.nodes.orderSelect, "", "orderBy", { disabled: true });
    make.option(this.nodes.orderSelect, "publishedAt ↓");
    make.option(this.nodes.orderSelect, "priority ↓, publishedAt ↓");
    make.option(this.nodes.orderSelect, "publishedAt ↑");
    if (this._data.order) {
      this.nodes.orderSelect.value = this._data.order;
    }
    return this.nodes.orderSelect;
  }
  show(state) {
    if (state === this.STATE.VIEW) {
      if (this.validate()) {
        this.getPreviewFromServer();
      } else {
        this.api.notifier.show({
          message: this.api.i18n.t(
            "Something is missing to properly render the the pages list."
          ),
          style: "error"
        });
      }
    }
    super.show(state);
  }
  updateData() {
    this._data.kw = this.nodes.kwInput.textContent || "";
    this._data.display = this.nodes.displaySelect.value;
    this._data.order = this.nodes.orderSelect.value;
    this._data.max = this.nodes.maxInput.textContent || "";
    this._data.maxPages = this.nodes.maxPagesInput.textContent || "";
  }
  validate() {
    return !!this._data.kw;
  }
  getPreviewFromServer() {
    this.updateData();
    const Tool = this;
    ajax.post({
      url: this.config.preview,
      data: this._data,
      type: ajax.contentType.JSON
    }).then(function(response) {
      Tool.updatePreview(response.body.content);
    }).catch(function(error) {
      console.log(error);
      Tool.updatePreview("An error occured (see console log for more info)");
    });
  }
  /**
   * Export block data to Markdown
   * @returns {string} Markdown representation
   */
  exportToMarkdown() {
    if (!this.data || !this.data.kw) {
      return "";
    }
    const max = this.data.max || "9";
    const maxPages = this.data.maxPages || "1";
    const order = this.data.order || "publishedAt,priority";
    const display = this.data.display || "list";
    let markdown = `{{ pages_list('${this.data.kw}', [${max}, ${maxPages}], '${order}', '${display}', '', page) }}`;
    if (this.data.anchor) {
      markdown = `{#${this.data.anchor} }
${markdown}`;
    }
    return markdown;
  }
  updatePreview(content) {
    if (this.nodes.preview) {
      this.nodes.preview.innerHTML = '<div class="preview-wrapper-overlay"></div>' + content;
    }
  }
}
export {
  PagesList as default
};
