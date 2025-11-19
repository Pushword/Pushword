export function filterImageFormField() {
  const addToHref = function (element) {
    element.href =
      element.href +
      '&filter[mimeType][value][]=image/jpeg&[mimeType][value][]=image/gif&filter[mimeType][value][]=image/png'
  }

  let mainImage = document.querySelector('span[id$="_mainImage"] a')
  if (mainImage) addToHref(mainImage)

  let inlineImage = document.querySelector('span[id$="_inline_image"] a')
  if (inlineImage) addToHref(inlineImage)
}
