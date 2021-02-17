import css from "./index.css";
import ToolboxIcon from "./toolbox-icon.svg";
import Abstract from "./../Abstract/Abstract";
import make from "./../Abstract/make";

export default class Button extends Abstract {
    static get toolbox() {
        return {
            title: "Button",
            icon: ToolboxIcon,
        };
    }

    set data(data) {
        this._data = Object.assign(
            {},
            {
                link: this.api.sanitizer.clean(data.link || "", Button.sanitize),
                text: this.api.sanitizer.clean(data.text || "", Button.sanitize),
            }
        );
    }

    updateData() {
        this.data = {
            text: this.nodes.textInput.textContent,
            link: this.nodes.linkInput.textContent,
        };
    }

    validate() {
        return this._data.link === "" || this._data.text === "" ? false : true;
    }

    static get sanitize() {
        return {
            text: false,
            link: false,
        };
    }

    createInputs() {
        this.nodes.textInput = make.input(
            this,
            ["cdx-input-labeled", "cdx-input-labeled-button-text", ...this.CSS.inputClass],

            "Alternative Text",
            this._data.text
        );

        this.nodes.linkInput = make.input(
            this,
            ["cdx-input-labeled", "cdx-input-labeled-button-url", ...this.CSS.inputClass],
            "Service URL",
            this._data.link
        );

        const wrapper = make.element("div");

        wrapper.appendChild(this.nodes.textInput);
        wrapper.appendChild(this.nodes.linkInput);

        return wrapper;
    }

    show(state) {
        console.log(state);
        if (state === this.STATE.VIEW) {
            if (this.validate()) {
                this.nodes.preview.innerHTML =
                    '<a href="' +
                    this._data.link +
                    '" class="btn btn-primary " target=_blank style="text-decoration:none">' +
                    this._data.text +
                    "</a>";
            } else {
                this.api.notifier.show({
                    message: this.api.i18n.t("Something is missing to properly render the button."),
                    style: "error",
                });
            }
        }
        super.show(state);
    }
}
