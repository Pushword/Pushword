/**
 * Ouvre le panneau repliable qui cache un champ invalide.
 *
 * EasyAdmin (`bundles/easyadmin/form.js`) intercepte le clic sur les boutons de
 * sauvegarde : si un contrôle est invalide, il annule le submit
 * (`preventDefault()` + `stopPropagation()`), marque le `div.form-fieldset` fautif
 * avec `has-fieldset-error`, puis tente de le déplier — mais uniquement s'il trouve
 * un `.form-fieldset-body.collapse:not(.show)` (le markup Bootstrap).
 *
 * Les panneaux `pw-settings-accordion` cachent leur corps avec `display: none`
 * (cf. admin.memorizeOpenPanel), donc ce dépliage ne trouve rien. Résultat :
 * le bouton « Sauvegarder » ne fait *rien*, sans le moindre message — EasyAdmin lit
 * `validity.valid`, une simple propriété, qui ne déclenche aucun événement `invalid`
 * ni aucun log console.
 *
 * On écoute donc le marqueur qu'EasyAdmin pose, et on ouvre le panneau nous-mêmes.
 * (Le badge d'erreur d'EasyAdmin, lui, vise `.form-fieldset-title-content` : nos
 * en-têtes ne portent pas cette classe, et l'adopter importerait sa mise en forme.
 * Le message natif du navigateur ci-dessous fait le travail.)
 */

const PANEL_SELECTOR = '.pw-settings-accordion'
const ERROR_CLASS = 'has-fieldset-error'

/** Ouvre le panneau via son bouton : memorizeOpenPanel reste seul maître de l'état. */
const openPanel = (panel) => {
  if (!panel.classList.contains('pw-settings-collapsed')) return false

  panel.querySelector('.pw-settings-toggle')?.click()

  return true
}

/** reportValidity() focus le champ et affiche la bulle native du navigateur. */
const reportFirstInvalidField = (panel) => {
  Array.from(panel.querySelectorAll('input, select, textarea'))
    .find((element) => !element.disabled && !element.validity.valid)
    ?.reportValidity()
}

export function revealInvalidField() {
  const panels = Array.from(document.querySelectorAll(PANEL_SELECTOR))
  if (!panels.length) return

  // EasyAdmin retire puis repose le marqueur à chaque clic : on ne réagit qu'à sa
  // pose, sinon replier le panneau à la main le rouvrirait aussitôt.
  const flagged = new WeakSet()

  const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      const panel = mutation.target
      if (!(panel instanceof HTMLElement)) return

      if (!panel.classList.contains(ERROR_CLASS)) {
        flagged.delete(panel)

        return
      }

      if (flagged.has(panel)) return
      flagged.add(panel)

      if (!openPanel(panel)) return

      window.setTimeout(() => reportFirstInvalidField(panel), 0)
    })
  })

  panels.forEach((panel) =>
    observer.observe(panel, { attributes: true, attributeFilter: ['class'] }),
  )
}
