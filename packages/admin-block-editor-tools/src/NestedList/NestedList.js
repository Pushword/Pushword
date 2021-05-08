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
}
