class Small {
    static get isInline() {
        return true;
    }

    constructor() {
        this.button = null;
        this.state = false;
    }

    render() {
        this.button = document.createElement("button");
        this.button.type = "button";
        this.button.textContent = "Aa";

        return this.button;
    }

    surround(range) {
        if (this.state) {
            return;
        }

        const selectedText = range.extractContents();
        const mark = document.createElement("small");
        mark.appendChild(selectedText);
        range.insertNode(mark);
    }

    checkState(selection) {
        const text = selection.anchorNode;

        if (!text) {
            return;
        }

        const anchorElement = text instanceof Element ? text : text.parentElement;

        this.state = !!anchorElement.closest("small");
    }
}
