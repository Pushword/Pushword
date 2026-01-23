/**
 * Gestion de l'état de la page (host, locale)
 */

/**
 * Récupère et stocke le host de la page courante
 */
export function retrieveCurrentPageHost() {
  const element =
    document.querySelector('select#Page_host') ||
    document.querySelector('input[id$="_host"]')
  if (!element) return

  window.pageHost = element.value
}

/**
 * Récupère et stocke la locale de la page courante
 * Écoute les changements pour mettre à jour la valeur
 */
export function retrieveCurrentPageLocale() {
  const input = document.querySelector('input[id$="_locale"]')
  if (!input) return

  window.pageLocale = input.value

  input.addEventListener('change', () => {
    window.pageLocale = input.value
    console.log('Locale updated to:', window.pageLocale)
  })
}
