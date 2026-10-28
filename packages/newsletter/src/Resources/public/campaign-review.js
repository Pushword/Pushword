const frame = document.querySelector('.pw-campaign-preview iframe')

if (frame) {
  const resize = () => {
    const document = frame.contentDocument
    if (!document?.body) return
    frame.style.blockSize = `${Math.ceil(document.documentElement.getBoundingClientRect().height)}px`
  }
  const observeEmail = () => {
    const body = frame.contentDocument?.body
    if (!body) return
    new ResizeObserver(resize).observe(body)
    body.addEventListener('click', (event) => {
      const link = event.target.closest('a[href]')
      if (!link || link.getAttribute('href').startsWith('#') || !/^https?:/.test(link.href)) return
      event.preventDefault()
      window.open(link.href, '_blank', 'noopener,noreferrer')
    })
    resize()
  }
  frame.addEventListener('load', observeEmail)
  if (frame.contentDocument?.readyState === 'complete') observeEmail()
}
