import EditorJS from "@editorjs/editorjs";
import Header from "editorjs-header-with-anchor"; //from "@editorjs/header";
import List from "@editorjs/list";
import Raw from "@editorjs/raw";
import Attaches from "@editorjs/attaches";
import Image from "@editorjs/image";
import Delimiter from "@editorjs/delimiter";
import Quote from "@editorjs/quote";
import Marker from "@editorjs/marker";
import Code from "@editorjs/code";
import InlineCode from "@editorjs/inline-code";
import { StyleInlineTool } from "editorjs-style";
import Hyperlink from "editorjs-hyperlink";
import Paragraph from "editorjs-paragraph-with-alignment";
import Table from "@editorjs/table";
import {
    ItalicInlineTool,
    UnderlineInlineTool,
    StrongInlineTool,
} from "editorjs-inline-tool";
import DragDrop from "editorjs-drag-drop";
import Undo from "editorjs-undo";
import Button from "editorjs-button";

export class editorJs {
    constructor() {
        if (typeof editorjsConfigs === "undefined") return;

        this.editors = [];
        this.editorjsTools = // className only
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
                      Button: Button,
                  };

        editorjsConfigs.forEach((config) => this.initEditor(config));
    }

    initEditor(config) {
        if (typeof config.holder === "undefined") {
            return;
        }
        if (typeof config.tools !== "undefined") {
            // set tool classes
            Object.keys(config.tools).forEach((toolName) => {
                if (
                    typeof this.editorjsTools[
                        config.tools[toolName].className
                    ] !== "undefined"
                ) {
                    config.tools[toolName].class = this.editorjsTools[
                        config.tools[toolName].className
                    ];
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
            console.log("here");
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
        const editorInput = document.getElementById(
            editorHolder.getAttribute("data-input-id")
        );
        const editor = this.editors[holderId];

        const savePromise = editor.save().then((outputData) => {
            editorInput.value = JSON.stringify(outputData);
        });

        await savePromise;
    }
}
