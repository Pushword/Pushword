/**
 * Filtre les options de page parente en fonction du host sélectionné
 */
export function filterParentPageFromHost() {
  const parentPageSelect = document.querySelector('select[name$="[parentPage]"]')
  const hostSelect = document.querySelector('select[name$="[host]"]')

  if (!parentPageSelect || !hostSelect) {
    return
  }

  /**
   * Attend qu'un sélecteur soit disponible dans le DOM
   * @param {string} selector - Le sélecteur CSS à attendre
   * @returns {Promise<Element>} L'élément trouvé
   */
  const waitFor = async (selector) => {
    while (document.querySelector(selector) === null) {
      await new Promise((resolve) => requestAnimationFrame(resolve))
    }
    return document.querySelector(selector)
  }

  /**
   * Met à jour les options de page parente selon le host
   * @param {string} host - Le host sélectionné
   * @param {Array<HTMLOptionElement>} allParentPages - Toutes les options disponibles
   */
  const updateParentPageOptions = (host, allParentPages) => {
    // Supprime toutes les options actuelles
    parentPageSelect.querySelectorAll('option').forEach((option) => {
      option.remove()
    })

    // Filtre les pages pour le host courant (ou les options vides)
    const parentPageForCurrentHost = allParentPages.filter((optionNode) => {
      return optionNode.text.startsWith(host) || !optionNode.value
    })

    // Inverse pour éviter de sélectionner la dernière option (qui devient l'option vide)
    parentPageForCurrentHost.reverse().forEach((option) => {
      parentPageSelect.appendChild(option)
    })
  }

  /**
   * Initialise le filtrage
   */
  const initialize = () => {
    const allParentPages = Array.from(parentPageSelect.querySelectorAll('option'))

    // Filtre initial si un host est déjà sélectionné
    if (hostSelect.value) {
      updateParentPageOptions(hostSelect.value, allParentPages)
    }

    // Écoute les changements de host
    hostSelect.addEventListener('change', (event) => {
      updateParentPageOptions(event.currentTarget.value, allParentPages)
    })
  }

  // Attend que le sélecteur Select2 soit initialisé
  waitFor('#s2id_' + hostSelect.id).then(() => {
    initialize()
  })
}
