import { editorJs } from "./editor.js";
import { editorJsHelper } from "./editorJsHelper.js";

window.editorJsHelper = new editorJsHelper();

window.addEventListener("load", function () {
    new editorJs();
});
