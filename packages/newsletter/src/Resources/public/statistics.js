const form = document.querySelector('[data-statistics-filters]')

if (form) {
  const from = form.elements.namedItem('from')
  const to = form.elements.namedItem('to')
  const syncRange = () => {
    to.min = from.value
  }

  from.addEventListener('input', syncRange)
  from.addEventListener('change', syncRange)
  syncRange()
}
