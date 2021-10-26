import RawTool from "@editorjs/raw/src/index.js";
import { transformTextareaToAce } from "./../../../admin/src/Resources/assets/admin.ace-editor.js";
//import css from "@editorjs/raw/src/index.css";

export default class Raw extends RawTool {
    // Wait for PR  https://github.com/editor-js/raw/pull/25 merged
    constructor({ data, config, api, readOnly }) {
        super({ data, config, api, readOnly });
        this.defaultHeight = config.defaultHeight || 200;
    }

    // Wait for PR  https://github.com/editor-js/raw/pull/27 merged
    static get conversionConfig() {
        return {
            export: "html",
            import: "html",
        };
    }

    render() {
        let wrapper = super.render();

        //transformTextareaToAce(this.textarea);

        return wrapper;
    }
}
