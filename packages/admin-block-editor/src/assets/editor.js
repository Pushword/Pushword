import EditorJS from "@editorjs/editorjs";
import Header from "editorjs-header-with-anchor"; //from "@editorjs/header";
import List from "@editorjs/list";
import Raw from "@editorjs/raw";
import Attaches from "@pushword/editorjs-tools/dist/Attaches.js"; //@editorjs/attaches";
import Image from "@pushword/editorjs-tools/dist/Image.js"; // "@editorjs/image";
import Delimiter from "@editorjs/delimiter";
import Quote from "@editorjs/quote";
import Marker from "@editorjs/marker";
import Code from "@editorjs/code";
import InlineCode from "@editorjs/inline-code";
import { StyleInlineTool } from "editorjs-style";
import Hyperlink from "editorjs-hyperlink";
import Paragraph from "editorjs-paragraph-with-alignment";
import Table from "@editorjs/table";
import Embed from "@pushword/editorjs-tools/dist/Embed.js"; //"@editorjs/embed";
import PagesList from "@pushword/editorjs-tools/dist/PagesList.js"; //"@editorjs/embed";
import { ItalicInlineTool, UnderlineInlineTool, StrongInlineTool } from "editorjs-inline-tool";
import DragDrop from "editorjs-drag-drop";
import Undo from "editorjs-undo";
//import Button from "editorjs-button"; // this one break sonata design
import ajax from "@codexteam/ajax";

window.editorJsTool = {};
window.editorJsTool.onSelectFile = function (Tool = null) {
    //if (!typeof this instanceof Image) throw "Bad context";
    Tool = Tool ? Tool : this;
    var inlineImageField = document.querySelector('div[id*="inline_image"] a');
    inlineImageField.click();

    var id = document.querySelector("input[id*=inline_image]").getAttribute("id");
    jQuery("#" + id).one("change", function (event) {
        console.log("call onChange select file");
        var id = jQuery(this).val();

        var upload = ajax
            .post({
                url: "/admin/media/block",
                data: Object.assign({
                    id: id,
                }),
                type: ajax.contentType.JSON,
            })
            .then((response) => {
                //Tool.ui.showPreloader(response.body.file.url);
                //Tool.ui.fillImage(response.body.file.url);
                console.log(response);
                Tool.onUpload(response.body);
            })
            .catch((error) => {
                console.log(Tool);
                Tool.uploadingFailed(error);
            });
    });
};

window.editorJsTool.onUploadFile = function () {
    //if (!typeof this instanceof Image) throw "Bad context";
    const Tool = this;
    var inlineImageField = document.querySelector('div[id*="inline_image"] a:nth-child(2)');
    inlineImageField.click();

    var id = document.querySelector("input[id*=inline_image]").getAttribute("id");
    jQuery("#" + id).one("change", function (event) {
        var id = jQuery(this).val();

        var upload = ajax
            .post({
                url: "/admin/media/block",
                data: Object.assign({
                    id: id,
                }),
                type: ajax.contentType.JSON,
            })
            .then((response) => {
                Tool.onUpload(response);
            })
            .catch((error) => {
                Tool.uploadingFailed(error);
            });
    });
};

window.toggleEditorJs = function (editorId) {
    var editorJsInput = document.querySelector("input[data-editorjs]");
    var textareaInput = document.querySelector("textarea[data-editorjs]");
    var elementToReplace = editorJsInput ? editorJsInput : textareaInput;

    console.log(document.getElementById(editorId));
    document.getElementById(editorId).style.display = editorJsInput ? "none" : "block";

    var replaceElement = document.createElement(editorJsInput ? "textarea" : "input");

    for (var i = 0, l = elementToReplace.attributes.length; i < l; ++i) {
        var nodeName = elementToReplace.attributes.item(i).nodeName;
        var nodeValue = elementToReplace.attributes.item(i).nodeValue;

        replaceElement.setAttribute(nodeName, nodeValue);
    }

    if (editorJsInput) {
        replaceElement.innerHTML = editorJsInput.value;
        replaceElement.classList.add("form-control");
        replaceElement.style.border = 0;
    }
    //else replaceElement.setAttribute("value", replaceElement.innerHTML); // useless because editor.js doesn't listen value content

    elementToReplace.parentNode.replaceChild(replaceElement, elementToReplace);
};

/** Was initially design to permit multiple editor.js in one page */
export class editorJs {
    constructor() {
        if (typeof editorjsConfig === "undefined") return;

        this.editors = [];
        this.editorjsTools =
            typeof editorjsTools !== "undefined"
                ? editorjsTools
                : {
                      Bold: StrongInlineTool,
                      Italic: ItalicInlineTool,
                      Underline: UnderlineInlineTool,
                      Header: Header,
                      List: List,
                      Raw: Raw,
                      Attaches: Attaches,
                      Image: Image,
                      Delimiter: Delimiter,
                      Quote: Quote,
                      Marker: Marker,
                      Hyperlink: Hyperlink,
                      Code: Code,
                      InlineCode: InlineCode,
                      StyleInlineTool: StyleInlineTool,
                      Paragraph: Paragraph,
                      Table: Table,
                      Embed: Embed,
                      PagesList: PagesList,
                      //Button: Button,
                  };

        this.initEditor(editorjsConfig);
    }

    initEditor(config) {
        if (typeof config.holder === "undefined") {
            return;
        }
        if (typeof config.tools !== "undefined") {
            // set tool classes
            Object.keys(config.tools).forEach((toolName) => {
                if (typeof this.editorjsTools[config.tools[toolName].className] !== "undefined") {
                    config.tools[toolName].class = this.editorjsTools[config.tools[toolName].className];
                } else {
                    console.log(config.tools[toolName].className);
                    delete config.tools[toolName];
                }
            });
        }

        // save
        var self = this;
        config.onChange = async function () {
            await self.editorjsSave(this.holder);
        };

        // drag'n drop
        config.onReady = function () {
            new DragDrop(editor);
            new Undo({ editor });
        };

        var editor = new EditorJS(
            Object.assign(config, {
                onReady: function () {
                    new DragDrop(editor);
                    new Undo({ editor });
                },
            })
        );
        this.editors[config.holder] = editor;
    }

    async editorjsSave(holderId) {
        const editorHolder = document.getElementById(holderId);
        const editorInput = document.getElementById(editorHolder.getAttribute("data-input-id"));
        const editor = this.editors[holderId];

        const savePromise = editor.save().then((outputData) => {
            editorInput.value = JSON.stringify(outputData);
        });

        await savePromise;
    }
}
