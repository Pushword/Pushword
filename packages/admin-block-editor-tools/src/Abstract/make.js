import SelectIcon from "./folder.svg";
import UploadIcon from "./upload.svg";

export default class make {
    static element(tagName, classNames = null, attributes = {}) {
        const el = document.createElement(tagName);

        if (Array.isArray(classNames)) {
            el.classList.add(...classNames);
        } else if (classNames) {
            el.classList.add(classNames);
        }

        for (const attrName in attributes) {
            el.setAttribute(attrName, attributes[attrName]);
        }

        return el;
    }

    static input(Tool, classNames, placeholder, value = "") {
        const input = make.element("div", classNames, { contentEditable: !Tool.readOnly });

        input.dataset.placeholder = Tool.api.i18n.t(placeholder);

        if (value) input.textContent = value;

        return input;
    }

    static option(select, key, value = null) {
        const option = document.createElement("option");
        option.text = value || key;
        option.value = key;
        select.add(option);
    }

    static options(select, options) {
        options.forEach((option) => make.option(select, option));
    }

    static fileButtons(Tool, buttonClass = []) {
        const buttonWrapper = make.element("div", [
            "flex",
            "cdx-input-labeled-preview",
            "cdx-input-labeled",
            "cdx-input",
            "cdx-input-editable",
            ...buttonClass,
        ]);
        const selectButton = make.element("div", [Tool.api.styles.button]);
        selectButton.innerHTML = SelectIcon + " " + Tool.api.i18n.t("Select");

        selectButton.addEventListener("click", (event) => Tool.onSelectFile(Tool, event));
        buttonWrapper.appendChild(selectButton);

        if (Tool.onUploadFile) {
            const uploadButton = make.element("div", [Tool.api.styles.button]);

            uploadButton.innerHTML = `${UploadIcon} ${Tool.api.i18n.t("Upload")}`;
            uploadButton.style.marginLeft = "-2px";
            uploadButton.addEventListener("click", (event) => Tool.onUploadFile(Tool, event));
            buttonWrapper.appendChild(uploadButton);
        }
        return buttonWrapper;
    }

    createPaginateCheckbox() {
        const checkbox = make.element("div", ["checkbox", "cdx-checkbox"]);
        const label = e.element("label");
        label.textContent = "Paginate";
        this.nodes.paginateCheckbox = document.createElement("input");
        this.nodes.paginateCheckbox.type = "checkbox";
        this.nodes.paginateCheckbox.value = this._data.display || 0;
        checkbox.appendChild(this.nodes.paginateCheckbox), checkbox.appendChild(label);

        return checkbox;
    }
}
