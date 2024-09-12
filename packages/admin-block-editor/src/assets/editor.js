import EditorJS from '@editorjs/editorjs'
import Header from '@editorjs/header'
import List from '@editorjs/nested-list'
//import Raw from '@editorjs/raw';
import Raw from '@pushword/editorjs-tools/dist/Raw.js'
import Delimiter from '@editorjs/delimiter'
import Quote from '@editorjs/quote'
import Marker from '@editorjs/marker'
import Code from '@editorjs/code'
import InlineCode from '@editorjs/inline-code'
//import { StyleInlineTool } from "editorjs-style";
import Hyperlink from '@pushword/editorjs-tools/dist/Hyperlink.js'
import Paragraph from 'editorjs-paragraph-with-alignment' // '@pushword/editorjs-tools/dist/Paragraph.js'
import Table from '@editorjs/table'
import DragDrop from 'editorjs-drag-drop'
import Undo from 'editorjs-undo'
import Strikethrough from '@sotaproject/strikethrough'

import Attaches from '@pushword/editorjs-tools/dist/Attaches.js'
import Image from '@pushword/editorjs-tools/dist/Image.js'
import Embed from '@pushword/editorjs-tools/dist/Embed.js'
import PagesList from '@pushword/editorjs-tools/dist/PagesList.js'
import Gallery from '@pushword/editorjs-tools/dist/Gallery.js'
import Anchor from '@pushword/editorjs-tools/dist/Anchor.js'
import Class from '@pushword/editorjs-tools/dist/Class.js'
import AlignementTune from '@pushword/editorjs-tools/dist/AlignementTune.js'
import HyperlinkTune from '@pushword/editorjs-tools/dist/HyperlinkTune.js'
import PasteLink from '@pushword/editorjs-tools/dist/PasteLink.js'
import CodeBlock from '@pushword/editorjs-tools/dist/CodeBlock.js'

/** Was initially design to permit multiple editor.js in one page */
export class editorJs {
  constructor() {
    if (typeof editorjsConfig === 'undefined') return

    this.editors = []
    this.editorjsTools =
      typeof editorjsTools !== 'undefined'
        ? editorjsTools
        : {
            HyperlinkTune: HyperlinkTune,
            Anchor: Anchor,
            Class: Class,
            AlignmentTuneTool: AlignementTune,
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
            Strikethrough: Strikethrough,
            CodeBlock: CodeBlock,
            //StyleInlineTool: StyleInlineTool,
          }

    this.initEditor(editorjsConfig)
  }

  getEditors() {
    return this.editors
  }

  initEditor(config) {
    if (typeof config.holder === 'undefined') {
      return
    }
    if (typeof config.tools !== 'undefined') {
      // set tool classes
      Object.keys(config.tools).forEach((toolName) => {
        if (typeof this.editorjsTools[config.tools[toolName].className] !== 'undefined') {
          config.tools[toolName].class = this.editorjsTools[config.tools[toolName].className]
        } else {
          delete config.tools[toolName]
        }
      })
    }

    // save
    var self = this
    config.onChange = async function () {
      await self.editorjsSave(this.holder)
    }

    // drag'n drop
    config.onReady = function () {
      new DragDrop(editor)
      new Undo({ editor })
    }

    var editor = new EditorJS(
      Object.assign(config, {
        onReady: () => new DragDrop(editor) && new Undo({ editor }) && new PasteLink(editor),
      }),
    )

    this.editors[config.holder] = editor
  }

  async editorjsSave(holderId) {
    const editorHolder = document.getElementById(holderId)
    const editorInput = document.getElementById(editorHolder.getAttribute('data-input-id'))
    const editor = this.editors[holderId]

    const savePromise = editor.save().then((outputData) => {
      editorInput.value = JSON.stringify(outputData)
    })

    await savePromise
  }
}
