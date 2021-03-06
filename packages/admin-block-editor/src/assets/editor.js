import EditorJS from "@editorjs/editorjs";
import Header from "@pushword/editorjs-tools/dist/Header.js"; //"editorjs-header-with-anchor"; //from "@editorjs/header";
import List from "@pushword/editorjs-tools/dist/NestedList.js"; // "@editorjs/nested-list";
import Raw from "@pushword/editorjs-tools/dist/Raw.js";
import Delimiter from "@editorjs/delimiter";
import Quote from "@editorjs/quote";
import Marker from "@editorjs/marker";
import Code from "@editorjs/code";
import InlineCode from "@editorjs/inline-code";
//import { StyleInlineTool } from "editorjs-style";
import Hyperlink from "@pushword/editorjs-tools/dist/Hyperlink.js"; //from "editorjs-hyperlink";
import Paragraph from "editorjs-paragraph-with-alignment";
import Table from "@editorjs/table";
import { ItalicInlineTool, UnderlineInlineTool, StrongInlineTool } from "editorjs-inline-tool";
import DragDrop from "editorjs-drag-drop";
import Undo from "editorjs-undo";

import Attaches from "@pushword/editorjs-tools/dist/Attaches.js"; //@editorjs/attaches";
import Image from "@pushword/editorjs-tools/dist/Image.js"; // "@editorjs/image";
import Embed from "@pushword/editorjs-tools/dist/Embed.js"; //"@editorjs/embed";
import PagesList from "@pushword/editorjs-tools/dist/PagesList.js";
import Gallery from "@pushword/editorjs-tools/dist/Gallery.js"; //"@vietlongn/editorjs-carousel";
import NestedList from "@pushword/editorjs-tools/src/NestedList/NestedList";
//import Button from "@pushword/editorjs-tools/dist/Button.js"; //import Button from "editorjs-button";

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
                      Delimiter: Delimiter,
                      Quote: Quote,
                      Marker: Marker,
                      Hyperlink: Hyperlink,
                      Code: Code,
                      InlineCode: InlineCode,
                      Paragraph: Paragraph,
                      Table: Table,
                      Attaches: Attaches,
                      Image: Image,
                      Embed: Embed,
                      PagesList: PagesList,
                      Gallery: Gallery,
                      //Button: Button,
                      //StyleInlineTool: StyleInlineTool,
                  };

        this.initEditor(editorjsConfig);
    }

    getEditors() {
        return this.editors;
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
