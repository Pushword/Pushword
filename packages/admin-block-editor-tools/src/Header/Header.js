import HeaderTool from "editorjs-header-with-anchor/src/index.js";
//import css from "@editorjs/raw/src/index.css";

export default class Header extends HeaderTool {
    /**
     * Allow Header to be converted to/from other blocks
     */
    static get conversionConfig() {
        return {
            export: "text", // use 'text' property for other blocks
            import: "text", // fill 'text' property from other block's export string
        };
    }

    static get isReadOnlySupported() {
        return true;
    }
}
