import RawTool from "@editorjs/raw/src/index.js";
//import css from "@editorjs/raw/src/index.css";

export default class Raw extends RawTool {
    // Wait for PR  https://github.com/editor-js/raw/pull/25 merged
    constructor({ data, config, api, readOnly }) {
        super({ data, config, api, readOnly });
        this.defaultHeight = config.defaultHeight || 200;
        console.log(this.defaultHeight);
    }

    onInput() {
        if (this.resizeDebounce) {
            clearTimeout(this.resizeDebounce);
        }

        this.resizeDebounce = setTimeout(() => {
            this.resize();
        }, this.defaultHeight);
        console.log(this.textarea.scrollHeight);
    }

    // Wait for PR  https://github.com/editor-js/raw/pull/27 merged
    static get conversionConfig() {
        return {
            export: "html",
            import: "html",
        };
    }
}
