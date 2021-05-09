import ListTool from "@editorjs/nested-list/src/index.js";
//import css from "@editorjs/raw/src/index.css";

export default class NestedList extends ListTool {
    /**
     * Wait for PR https://github.com/editor-js/nested-list/pull/13 to be merged
     * Allow List Tool to be converted to/from other block
     *
     * @returns {{export: Function, import: Function}}
     */
    static get conversionConfig() {
        return {
            /**
             * To create exported string from list, concatenate items by dot-symbol.
             *
             * @param {ListData} data - list data to create a string from thats
             * @returns {string}
             */
            export: (data) => {
                console.log(data.items);
                return NestedList.itemsToText(data.items);
            },
            /**
             * To create a list from other block's string, just put it at the first item
             *
             * @param {string} string - string to create list tool data from that
             * @returns {ListData}
             */
            import: (string) => {
                return {
                    items: [{ content: string, items: [] }],
                    style: "unordered",
                };
            },
        };
    }

    static itemsToText(items) {
        var text = "";
        var nestedText = "";

        items.forEach(function (item) {
            text += (text ? ", " : "") + item.content;
            if (item.items) {
                nestedText = NestedList.itemsToText(item.items);
                if (nestedText) text += (text ? ", " : "") + nestedText;
            }
        });

        console.log(text);

        return text;
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
            tags: ["OL", "UL", "LI"],
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
            case "OL":
                style = "ordered";
                break;
            case "UL":
            case "LI":
                style = "unordered";
        }

        const data = {
            style,
            items: [],
        };

        if (tag === "LI") {
            data.items.push({ content: element.innerHTML, items: [] });
        } else {
            const items = Array.from(element.querySelectorAll("LI"));

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
