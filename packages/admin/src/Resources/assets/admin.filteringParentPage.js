export function filterParentPageFromHost() {
  const waitFor = async (selector) => {
    while (document.querySelector(selector) === null) {
      await new Promise((resolve) => requestAnimationFrame(resolve))
    }
    return document.querySelector(selector)
  }

  const updateParentPageOptions = function (host, allParentPages) {
    parentPageSelect.querySelectorAll('option').forEach((e) => e.parentNode.removeChild(e))
    let parentPageForCurrentHost = allParentPages.filter(function (optionNode) {
      return optionNode.text.startsWith(host) || !optionNode.value
    })
    parentPageForCurrentHost.reverse().forEach((option) => parentPageSelect.appendChild(option))
    // .reverse() permit to avoid selecting last option (last option become the empty one)
  }

  const parentPageSelect = document.querySelector('select[name$="[parentPage]"]')
  const hostSelect = document.querySelector('select[name$="[host]"]')

  if (!parentPageSelect || !hostSelect) {
    return
  } else {
    waitFor('#s2id_' + hostSelect.id).then((hostSelect2) => {
      run()
    })
  }

  const run = function () {
    const allParentPages = Array.prototype.slice.call(parentPageSelect.querySelectorAll('option'))

    if (hostSelect.value) {
      updateParentPageOptions(hostSelect.value, allParentPages)
    }

    hostSelect.addEventListener('change', (event) => {
      updateParentPageOptions(event.currentTarget.value, allParentPages)
    })
  }
}
