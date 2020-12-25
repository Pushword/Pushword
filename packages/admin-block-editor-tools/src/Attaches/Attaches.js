import AttachesTool from '@editorjs/attaches/src/index.js';
import css from './index.css';
import make from './../Abstract/make.js';
//import ajax from "@codexteam/ajax";

export default class Attaches extends AttachesTool {
    constructor({ data, config, api, readOnly }) {
        super({ data, config, api, readOnly });

        this.onSelectFile = config.onSelectFile;
        this.onUploadFile = config.onUploadFile || '';
    }

    enableFileUpload() {
        //this.onSelectFile();
    }
    onUpload(response) {
        response.file.title = response.file.name;
        super.onUpload(response);
        if (response.success && response.file) this.nodes.buttonWrapper.remove();
    }

    prepareUploadButton() {
        this.nodes.buttonWrapper = make.fileButtons(this);
        this.nodes.button = this.nodes.buttonWrapper.childNodes[0];
        this.nodes.wrapper.appendChild(this.nodes.buttonWrapper);
    }

    fileConvertSize(size) {
        size = Math.abs(parseInt(size, 10));
        for (
            var t = [
                    [1, 'octets'],
                    [1024, 'ko'],
                    [1048576, 'Mo'],
                    [1073741824, 'Go'],
                    [1099511627776, 'To'],
                ],
                n = 0;
            n < t.length;
            n++
        )
            if (size < t[n][0]) return (size / t[n - 1][0]).toFixed(2) + ' ' + t[n - 1][1];
        return size;
    }
}
