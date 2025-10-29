class AlignmentTune {
  /**
   * Default alignment
   *
   * @public
   * @returns {string}
   */
  static get DEFAULT_ALIGNMENT() {
    return "left";
  }
  static get isTune() {
    return true;
  }
  getAlignment() {
    var _a, _b;
    if (!!((_a = this.settings) == null ? void 0 : _a.blocks) && this.settings.blocks.hasOwnProperty(this.block.name)) {
      return this.settings.blocks[this.block.name];
    }
    if (!!((_b = this.settings) == null ? void 0 : _b.default)) {
      return this.settings.default;
    }
    return AlignmentBlockTune.DEFAULT_ALIGNMENT;
  }
  /**
   *
   * @param api
   * @param data 既に設定されているデータ
   * @param settings tuneに設定項目
   * @param block tuneに設定されてるblock
   */
  constructor({ api, data, config, block }) {
    this.api = api;
    this.block = block;
    this.settings = config;
    this.data = data || { alignment: this.getAlignment() };
    this.alignmentSettings = [
      {
        name: "left",
        icon: `<svg xmlns="http://www.w3.org/2000/svg" id="Layer" enable-background="new 0 0 64 64" height="20" viewBox="0 0 64 64" width="20"><path d="m54 8h-44c-1.104 0-2 .896-2 2s.896 2 2 2h44c1.104 0 2-.896 2-2s-.896-2-2-2z"/><path d="m54 52h-44c-1.104 0-2 .896-2 2s.896 2 2 2h44c1.104 0 2-.896 2-2s-.896-2-2-2z"/><path d="m10 23h28c1.104 0 2-.896 2-2s-.896-2-2-2h-28c-1.104 0-2 .896-2 2s.896 2 2 2z"/><path d="m54 30h-44c-1.104 0-2 .896-2 2s.896 2 2 2h44c1.104 0 2-.896 2-2s-.896-2-2-2z"/><path d="m10 45h28c1.104 0 2-.896 2-2s-.896-2-2-2h-28c-1.104 0-2 .896-2 2s.896 2 2 2z"/></svg>`
      },
      {
        name: "center",
        icon: `<svg xmlns="http://www.w3.org/2000/svg" id="Layer" enable-background="new 0 0 64 64" height="20" viewBox="0 0 64 64" width="20"><path d="m54 8h-44c-1.104 0-2 .896-2 2s.896 2 2 2h44c1.104 0 2-.896 2-2s-.896-2-2-2z"/><path d="m54 52h-44c-1.104 0-2 .896-2 2s.896 2 2 2h44c1.104 0 2-.896 2-2s-.896-2-2-2z"/><path d="m46 23c1.104 0 2-.896 2-2s-.896-2-2-2h-28c-1.104 0-2 .896-2 2s.896 2 2 2z"/><path d="m54 30h-44c-1.104 0-2 .896-2 2s.896 2 2 2h44c1.104 0 2-.896 2-2s-.896-2-2-2z"/><path d="m46 45c1.104 0 2-.896 2-2s-.896-2-2-2h-28c-1.104 0-2 .896-2 2s.896 2 2 2z"/></svg>`
      },
      {
        name: "right",
        icon: `<svg xmlns="http://www.w3.org/2000/svg" id="Layer" enable-background="new 0 0 64 64" height="20" viewBox="0 0 64 64" width="20"><path d="m54 8h-44c-1.104 0-2 .896-2 2s.896 2 2 2h44c1.104 0 2-.896 2-2s-.896-2-2-2z"/><path d="m54 52h-44c-1.104 0-2 .896-2 2s.896 2 2 2h44c1.104 0 2-.896 2-2s-.896-2-2-2z"/><path d="m54 19h-28c-1.104 0-2 .896-2 2s.896 2 2 2h28c1.104 0 2-.896 2-2s-.896-2-2-2z"/><path d="m54 30h-44c-1.104 0-2 .896-2 2s.896 2 2 2h44c1.104 0 2-.896 2-2s-.896-2-2-2z"/><path d="m54 41h-28c-1.104 0-2 .896-2 2s.896 2 2 2h28c1.104 0 2-.896 2-2s-.896-2-2-2z"/></svg>`
      }
    ];
    this._CSS = {
      alignment: {
        left: "ce-tune-alignment--left",
        center: "ce-tune-alignment--center",
        right: "ce-tune-alignment--right"
      },
      button: {
        default: "cdx-settings-button",
        active: "cdx-settings-button--active"
      }
    };
  }
  /**
   * @param blockContent
   */
  wrap(blockContent) {
    this.blockContent = blockContent;
    this.blockContent.classList.toggle(this._CSS.alignment[this.data.alignment]);
    return this.blockContent;
  }
  render() {
    const wrapper = document.createElement("div");
    this.alignmentSettings.map((tune) => {
      const button = document.createElement("div");
      button.classList.add(this._CSS.button.default);
      button.innerHTML = tune.icon;
      button.classList.toggle(
        this._CSS.button.active,
        tune.name === this.data.alignment
      );
      wrapper.appendChild(button);
      return button;
    }).forEach((element, index, elements) => {
      element.addEventListener("click", () => {
        this.updateAlign(this.alignmentSettings[index].name);
      });
    });
    return wrapper;
  }
  updateAlign(currentAlign) {
    var _a;
    this.data.alignment = currentAlign;
    (_a = this.block) == null ? void 0 : _a.dispatchChange();
    this.alignmentSettings.forEach((align) => {
      this.blockContent.classList.toggle(
        this._CSS.alignment[align.name],
        this.data.alignment === align.name
      );
    });
  }
  /**
   * save
   * @returns {*}
   */
  save() {
    return this.data;
  }
}
export {
  AlignmentTune as default
};
