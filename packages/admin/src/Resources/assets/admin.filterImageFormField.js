/**
 * Ajoute des filtres MIME type pour les champs d'image
 * Restreint la sélection aux formats image/jpeg, image/gif, image/png
 */
export function filterImageFormField() {
  /**
   * Ajoute les filtres MIME type à l'URL d'un lien
   * @param {HTMLAnchorElement} element - L'élément lien à modifier
   */
  const addImageFiltersToHref = (element) => {
    const imageFilters =
      '&filter[mimeType][value][]=image/jpeg&filter[mimeType][value][]=image/gif&filter[mimeType][value][]=image/png'
    element.href = element.href + imageFilters
  }

  const mainImageLink = document.querySelector('span[id$="_mainImage"] a')
  if (mainImageLink) {
    addImageFiltersToHref(mainImageLink)
  }

  const inlineImageLink = document.querySelector('span[id$="_inline_image"] a')
  if (inlineImageLink) {
    addImageFiltersToHref(inlineImageLink)
  }
}
