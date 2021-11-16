import HeaderTool from "@editorjs/header/src/index.js";

// This class permit avoid BC break from previous 0.0.974 using  editorjs-header-with-anchor
export default class Header extends HeaderTool {
    normalizeData(data) {
        const newData = {};

        if (typeof data !== "object") {
            data = {};
        }

        newData.text = data.text || "";
        newData.level = parseInt(data.level) || this.defaultLevel.number;
        newData.anchor = data.anchor || "";

        return newData;
    }

    save(toolsContent) {
        return {
            text: toolsContent.innerHTML,
            level: this.currentLevel.number,
            anchor: this.data.anchor,
        };
    }
}
