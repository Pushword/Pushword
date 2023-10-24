import RawTool from '@editorjs/raw/src/index.js';
//import { transformTextareaToAce } from './../../../admin/src/Resources/assets/admin.ace-editor.js';
import css from '@editorjs/raw/src/index.css';
import './Raw.css';

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

        this.fullscreenBtn = document.createElement('div');
        this.fullscreenBtn.className = 'aceFsBtn'; // Add the 'test' class
        this.fullscreenBtn.innerHTML =
            '<svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%" fill="currentColor" class="bi bi-arrows-fullscreen" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M5.828 10.172a.5.5 0 0 0-.707 0l-4.096 4.096V11.5a.5.5 0 0 0-1 0v3.975a.5.5 0 0 0 .5.5H4.5a.5.5 0 0 0 0-1H1.732l4.096-4.096a.5.5 0 0 0 0-.707zm4.344 0a.5.5 0 0 1 .707 0l4.096 4.096V11.5a.5.5 0 1 1 1 0v3.975a.5.5 0 0 1-.5.5H11.5a.5.5 0 0 1 0-1h2.768l-4.096-4.096a.5.5 0 0 1 0-.707zm0-4.344a.5.5 0 0 0 .707 0l4.096-4.096V4.5a.5.5 0 1 0 1 0V.525a.5.5 0 0 0-.5-.5H11.5a.5.5 0 0 0 0 1h2.768l-4.096 4.096a.5.5 0 0 0 0 .707zm-4.344 0a.5.5 0 0 1-.707 0L1.025 1.732V4.5a.5.5 0 0 1-1 0V.525a.5.5 0 0 1 .5-.5H4.5a.5.5 0 0 1 0 1H1.732l4.096 4.096a.5.5 0 0 1 0 .707z"/></svg>';
        this.fullscreenBtn.onclick = () => {
            wrapper.classList.toggle('ce-rawtool-full');
            this.editor.resize();
        };
        wrapper.appendChild(this.fullscreenBtn);

        return wrapper;
    }

    transformTextareaToAce() {
        var textarea = $(this.textarea);
        var editDiv = $('<div>', {
            position: 'absolute',
            width: '100%',
            class: 'aceInsideEditorJs',
        }).insertBefore(textarea);
        textarea.css('display', 'none');
        var editor = ace.edit(editDiv[0]);
        editor.renderer.setShowGutter(false);
        editor.getSession().setValue(textarea.val() || '');
        editor.getSession().setMode('ace/mode/twig');
        editor.setFontSize('16px');
        editor.container.style.lineHeight = '1.5em';
        editor.renderer.updateFontSize();
        editor.setOptions({
            maxLines: Infinity,
        });
        editor.session.setTabSize(2);

        editor.getSession().on('change', () => {
            if (textarea) textarea.val(editor.getSession().getValue() || '');
        });
        editor.renderer.setScrollMargin(10, 10);
        editor.focus();
        setTimeout(function () {
            editor.focus();
        }, 1);

        return editor;
    }
}
