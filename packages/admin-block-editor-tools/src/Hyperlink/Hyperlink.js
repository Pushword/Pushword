import HyperlinkTool from "editorjs-hyperlink/src/Hyperlink.js";
import css from "editorjs-hyperlink/src/Hyperlink.css";
import css2 from "./Hyperlink.css";
import make from "./../Abstract/make.js";
import SelectionUtils from "editorjs-hyperlink/src/SelectionUtils";

export default class Hyperlink extends HyperlinkTool {
    constructor({ data, config, api, readOnly }) {
        super({ data, config, api, readOnly });

        this.avalaibleDesign = this.config.avalaibleDesign || [
            ["btn", "link-btn"],
            ["invisible", "ninja"], //text-current no-underline border-0 font-normal
        ];
    }

    renderActions() {
        super.renderActions();

        this.nodes.wrapper.getElementsByClassName("ce-inline-tool-hyperlink--button")[0].remove();
        // Design
        this.nodes.selectDesign = document.createElement("select");
        this.nodes.selectDesign.classList.add(this.CSS.selectRel);
        this.addOption(this.nodes.selectDesign, this.i18n.t("Select design"), "");
        for (let i = 0; i < this.avalaibleDesign.length; i++) {
            this.addOption(this.nodes.selectDesign, this.avalaibleDesign[i][0], this.avalaibleDesign[i][1]);
        }
        if (!!this.config.design) {
            this.nodes.selectDesign.value = this.config.design;
        }

        this.nodes.wrapper.appendChild(this.nodes.selectDesign);

        this.createSaveBtn();
        this.nodes.wrapper.appendChild(this.nodes.buttonSave);

        return this.nodes.wrapper;
    }

    createSaveBtn() {
        this.nodes.buttonSave = null;

        this.nodes.buttonSave = document.createElement("button");
        this.nodes.buttonSave.type = "button";
        this.nodes.buttonSave.classList.add(this.CSS.buttonSave);
        this.nodes.buttonSave.innerHTML = this.i18n.t("Save");
        this.nodes.buttonSave.addEventListener("click", (event) => {
            this.savePressed(event);
        });
    }

    static get sanitize() {
        return {
            a: {
                href: true,
                target: true,
                rel: true,
                class: true,
            },
        };
    }

    checkState(selection = null) {
        const anchorTag = this.selection.findParentTag("A");
        if (anchorTag) {
            this.nodes.button.classList.add(this.CSS.buttonUnlink);
            this.nodes.button.classList.add(this.CSS.buttonActive);
            this.openActions();
            const hrefAttr = anchorTag.getAttribute("href");
            const targetAttr = anchorTag.getAttribute("target");
            const relAttr = anchorTag.getAttribute("rel");
            const designAttr = anchorTag.getAttribute("class");
            this.nodes.input.value = !!hrefAttr ? hrefAttr : "";
            this.nodes.selectTarget.value = !!targetAttr ? targetAttr : "";
            this.nodes.selectRel.value = !!relAttr ? relAttr : "";
            this.nodes.selectDesign.value = !!designAttr ? designAttr : "";
            this.selection.save();
        } else {
            this.nodes.button.classList.remove(this.CSS.buttonUnlink);
            this.nodes.button.classList.remove(this.CSS.buttonActive);
        }
        return !!anchorTag;
    }

    savePressed(event) {
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();

        let value = this.nodes.input.value || "";
        let target = this.nodes.selectTarget.value || "";
        let rel = this.nodes.selectRel.value || "";
        let design = this.nodes.selectDesign.value || "";

        if (!value.trim()) {
            this.selection.restore();
            this.unlink();
            event.preventDefault();
            this.closeActions();
        }

        if (!!this.config.validate && !!this.config.validate === true && !this.validateURL(value)) {
            this.tooltip.show(this.nodes.input, "The URL is not valid.", {
                placement: "top",
            });
            setTimeout(() => {
                this.tooltip.hide();
            }, 1000);
            return;
        }

        value = this.prepareLink(value);

        this.selection.restore();
        this.selection.removeFakeBackground();

        this.insertLink(value, target, rel, design);

        this.selection.collapseToEnd();
        this.inlineToolbar.close();
    }

    insertLink(link, target = "", rel = "", design = "") {
        let anchorTag = this.selection.findParentTag("A");
        if (anchorTag) {
            this.selection.expandToTag(anchorTag);
            anchorTag["href"] = link;
        } else {
            document.execCommand(this.commandLink, false, link);
            anchorTag = this.selection.findParentTag("A");
        }
        if (anchorTag) {
            if (!!target) {
                anchorTag["target"] = target;
            } else {
                anchorTag.removeAttribute("target");
            }
            if (!!rel) {
                anchorTag["rel"] = rel;
            } else {
                anchorTag.removeAttribute("rel");
            }
            if (!!design) {
                anchorTag.className = design;
            } else {
                anchorTag.removeAttribute("class");
            }
        }
    }
}
