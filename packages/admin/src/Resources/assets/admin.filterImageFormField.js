export function filterImageFormField() {
    const addToHref = function (element) {
        element.href =
            element.href +
            "&[mimeType][value][]=image/gif&filter[mimeType][value][]=image/png&filter[mimeType][value][]=image/jpg&filter[mimeType][value][]=image/jpeg";
    };

    let mainImage = document.querySelector('span[id$="_mainImage"] a');
    if (mainImage) addToHref(mainImage);

    let inlineImage = document.querySelector('span[id$="_inline_image"] a');
    if (inlineImage) addToHref(inlineImage);
}
