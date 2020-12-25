import ajax from "@codexteam/ajax";

export class editorJsHelper {
    constructor() {}

    static abstractOn(Tool, event, action = "select", inlineImageFieldSelector = '[id*="inline_image"]') {
        //
        const buttonClicked = event.target;
        const originalTextContent = buttonClicked.textContent;
        //buttonClicked.textContent = ". . . ";

        const inlineImageField = document.querySelector(
            "div" + inlineImageFieldSelector + " " + (action === "select" ? "a" : "a:nth-child(2)")
        );
        inlineImageField.click();

        document.querySelector("input" + inlineImageFieldSelector).onchange = function () {
            var id = jQuery(this).val();
            var upload = ajax
                .post({
                    url: "/admin/media/block",
                    data: Object.assign({
                        id: id,
                    }),
                    type: ajax.contentType.JSON,
                })
                .then((response) => {
                    if (Tool.onFileLoading) Tool.onFileLoading();
                    Tool.onUpload(response.body);
                    //buttonClicked.textContent = originalTextContent;
                })
                .catch((error) => {
                    Tool.uploadingFailed(error);
                });
        };
    }

    onSelectImage(Tool, event) {
        editorJsHelper.abstractOn(Tool, event, "select");
    }

    onSelectFile(Tool, event) {
        editorJsHelper.abstractOn(Tool, event, "select", '[id*="inline_attaches"]');
    }

    onUploadImage(Tool, event) {
        editorJsHelper.abstractOn(Tool, event, "upload");
    }

    onUploadFile(Tool, event) {
        editorJsHelper.abstractOn(Tool, event, "upload", '[id*="inline_attaches"]');
    }

    toggleEditorJs(editorId) {
        var editorJsInput = document.querySelector("input[data-editorjs]");
        var textareaInput = document.querySelector("textarea[data-editorjs]");
        var elementToReplace = editorJsInput ? editorJsInput : textareaInput;

        document.getElementById(editorId).style.display = editorJsInput ? "none" : "block";

        var replaceElement = document.createElement(editorJsInput ? "textarea" : "input");

        for (var i = 0, l = elementToReplace.attributes.length; i < l; ++i) {
            var nodeName = elementToReplace.attributes.item(i).nodeName;
            var nodeValue = elementToReplace.attributes.item(i).nodeValue;

            replaceElement.setAttribute(nodeName, nodeValue);
        }

        if (editorJsInput) {
            replaceElement.innerHTML = editorJsInput.value;
            replaceElement.classList.add("form-control");
            replaceElement.style.border = 0;
        }
        //else replaceElement.setAttribute("value", replaceElement.innerHTML); // useless because editor.js doesn't listen value content

        elementToReplace.parentNode.replaceChild(replaceElement, elementToReplace);
    }
}
