export function submenuFilter() {
  document.querySelectorAll('#main-menu .menu-item.has-submenu').forEach((menuItem) => {
    const submenu = menuItem.querySelector('.submenu')
    if (!submenu) return

    const items = submenu.querySelectorAll(':scope > li.menu-item')
    if (items.length <= 5) return

    const input = document.createElement('input')
    input.type = 'text'
    input.placeholder = 'Filter...'
    input.style.cssText = 'width:100%;padding:4px 8px;margin-bottom:4px;border:1px solid #d1d5db;border-radius:3px;font-size:0.85rem;'

    const li = document.createElement('li')
    li.style.cssText = 'padding:4px 8px;list-style:none;'
    li.appendChild(input)
    submenu.prepend(li)

    input.addEventListener('input', () => {
      const query = input.value.toLowerCase()
      items.forEach((item) => {
        if (item.classList.contains('d-none')) return
        const label = item.querySelector('.menu-item-label')
        if (!label) return
        const text = label.textContent.toLowerCase()
        if (text.includes('see all') || text.includes('voir tout')) return
        item.style.display = query && !text.includes(query) ? 'none' : ''
      })
    })
  })
}
