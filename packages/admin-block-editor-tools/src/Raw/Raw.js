import RawTool from '@editorjs/raw/src/index.js';
//import { transformTextareaToAce } from './../../../admin/src/Resources/assets/admin.ace-editor.js';
import css from '@editorjs/raw/src/index.css';

export default class Raw extends RawTool {
    // Wait for PR  https://github.com/editor-js/raw/pull/25 merged
    constructor({ data, config, api, readOnly }) {
        super({ data, config, api, readOnly });
        //this.defaultHeight = config.defaultHeight || 200;
        this.editor = null;
    }

    // Wait for PR  https://github.com/editor-js/raw/pull/27 merged
    static get conversionConfig() {
        return {
            export: 'html',
            import: 'html',
        };
    }

    render() {
        let wrapper = super.render();

        this.editor = this.transformTextareaToAce();

        return wrapper;
    }

    transformTextareaToAce() {
        var textarea = $(this.textarea);
        console.log(textarea.height());
        var editDiv = $('<div>', {
            position: 'absolute',
            width: '100%', // textarea.width(),
            class: 'aceInsideEditorJs',
        }).insertAfter(textarea);
        textarea.css('display', 'none');
        var editor = ace.edit(editDiv[0]);
        editor.renderer.setShowGutter(false);
        editor.getSession().setValue(textarea.val());
        editor.getSession().setMode('ace/mode/twig');
        editor.setFontSize('16px');
        editor.container.style.lineHeight = '1.5em';
        editor.renderer.updateFontSize();
        editor.setOptions({
            maxLines: Infinity,
        });

        editor.getSession().on('change', () => {
            textarea.val(editor.getSession().getValue());
        });
        editor.renderer.setScrollMargin(10, 10);

        return editor;
    }
}
