/**
 * Gestion de l'état de la page (host)
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
