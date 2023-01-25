//require("./index.css").toString(); /
import css from './index.css';

import CarouselTool from '@vietlongn/editorjs-carousel/src/index.js';
import make from './../Abstract/make.js';
import ToolboxIcon from './toolbox-icon.svg';

export default class Gallery extends CarouselTool {
    static get toolbox() {
        return {
            title: 'Gallery',
            icon: ToolboxIcon,
        };
    }

    constructor({ data, config, api, readOnly }) {
        super({ data, config, api, readOnly });

        this.onSelectFile = config.onSelectFile || this.defaultOnSelectFile;
        this.onUploadFile = config.onUploadFile || null;
        this.nodes = {};
    }

    createAddButton() {
        return this.createImageInput();
    }

    createImageInput() {
        this.nodes.imagePreloader = make.element('div', 'image-tool__image-preloader');
        this.nodes.imagePreloader.style.display = 'none';
        this.nodes.fileButton = make.fileButtons(this, ['cdx-input-gallery']);
        this.nodes.fileButton.appendChild(this.nodes.imagePreloader);
        return this.nodes.fileButton;
    }

    onUpload(response) {
        super.onUpload(response);
        this.list.childNodes[this.list.childNodes.length - 2].firstChild.firstChild.dataset.file =
            JSON.stringify(response.file);
        this.list.childNodes[this.list.childNodes.length - 2].firstChild.lastChild.value =
            response.file.name;
    }

    render() {
        super.render();
        if (this.data.length > 0) {
            for (const load of this.data) {
                this.list.querySelectorAll('.cdxcarousel-inputUrl').forEach(function (item) {
                    item.dataset.file = JSON.stringify(load.file);
                });
            }
        }
        return this.wrapper;
    }

    isJson(string) {
        try {
            JSON.parse(string);
        } catch (e) {
            return false;
        }
        return true;
    }

    save(blockContent) {
        const list = blockContent.getElementsByClassName(this.CSS.item);
        const data = [];

        if (list.length > 0) {
            for (const item of list) {
                if (item.firstChild.value) {
                    data.push({
                        file:
                            item.firstChild.dataset.file &&
                            this.isJson(item.firstChild.dataset.file)
                                ? JSON.parse(item.firstChild.dataset.file)
                                : {},
                        url: item.firstChild.value,
                        caption: item.lastChild.value,
                    });
                }
            }
        }
        return data;
    }

    onFileLoading() {
        const newItem = this.creteNewItem('', '');
        this.list.insertBefore(newItem, this.addButton);
    }
}
