import ListTool from '@editorjs/nested-list/src/index.js';
//import css from "@editorjs/raw/src/index.css";

export default class NestedList extends ListTool {
    constructor({ data, config, api, readOnly }) {
        data = NestedList.importFromList(data);
        super({ data, config, api, readOnly });
    }

    /**
     * Import from @editorjs/list to @editorjs/nested-list data format
     *
     */
    static importFromList(data) {
        if (
            !(data && Object.keys(data).length) ||
            !(data.items && Object.keys(data.items).length) ||
            typeof data.items[0] !== 'string'
        )
            return data;

        const items = data.items;
        data.items = [];
        items.forEach(function (item) {
            data.items.push({ content: item, items: [] });
        });
        return data;
    }

    // wait for PR https://github.com/editor-js/nested-list/pull/22 to be merged

    /**
     * On paste callback that is fired from Editor
     *
     * @param {PasteEvent} event - event with pasted data
     */
    onPaste(event) {
        const list = event.detail.data;

        this.data = this.pasteHandler(list);

        const oldView = this.nodes.wrapper;
        if (oldView) {
            oldView.parentNode.replaceChild(this.render(), oldView);
        }
    }

    /**
     * List Tool on paste configuration
     *
     * @public
     */
    static get pasteConfig() {
        return {
            tags: ['OL', 'UL', 'LI'],
        };
    }

    /**
     * Handle UL, OL and LI tags paste and returns List data
     *
     * @param {HTMLUListElement|HTMLOListElement|HTMLLIElement} element
     * @returns {ListData}
     */
    pasteHandler(element) {
        const { tagName: tag } = element;
        let style;

        switch (tag) {
            case 'OL':
                style = 'ordered';
                break;
            case 'UL':
            case 'LI':
                style = 'unordered';
        }

        const data = {
            style,
            items: [],
        };

        if (tag === 'LI') {
            data.items.push({ content: element.innerHTML, items: [] });
        } else {
            const items = Array.from(element.querySelectorAll('LI'));

            const listItems = items.map((li) => li.innerHTML).filter((item) => !!item.trim());

            const that = this;

            listItems.forEach(function (item) {
                data.items.push({ content: item, items: [] });
                that.createItem(item, []);
            });
        }

        return data;
    }
}
