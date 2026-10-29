/** Keep saving ahead of utility actions in both the visual and keyboard order. */
export function prioritizeSaveActions() {
  document.querySelectorAll('.content-header .page-actions').forEach((actions) => {
    for (const name of ['saveAndAddAnother', 'saveAndContinue', 'saveAndReturn']) {
      const button = actions.querySelector(`:scope > .action-${name}`)
      if (button) actions.prepend(button)
    }
  })
}
