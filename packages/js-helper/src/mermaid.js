let renderer
let nextId = 0
const processed = new WeakSet()

/** Loaded only when the page or editor actually contains a diagram. */
export async function renderMermaid(source) {
  renderer ??= import('mermaid')
    .then(({ default: mermaid }) => {
      mermaid.initialize({
        startOnLoad: false,
        securityLevel: 'strict',
        suppressErrorRendering: true,
        theme: 'neutral',
      })
      return mermaid
    })
    .catch((error) => {
      renderer = undefined
      throw error
    })

  const mermaid = await renderer
  const { svg } = await mermaid.render(`pw-mermaid-${++nextId}`, source)
  return svg
}

/** Keep the escaped code as a fallback until rendering succeeds. */
export async function enhanceMermaid(root = document) {
  await Promise.all(
    Array.from(root.querySelectorAll('pre > code.language-mermaid'), async (code) => {
      if (processed.has(code)) return
      processed.add(code)

      try {
        const svg = await renderMermaid(code.textContent)
        const diagram = document.createElement('div')
        diagram.className = 'pw-mermaid not-prose'
        diagram.id = code.id || code.parentElement.id
        diagram.innerHTML = svg
        code.parentElement.replaceWith(diagram)
      } catch {
        // Invalid diagrams and unavailable assets leave the readable source intact.
        processed.delete(code)
      }
    }),
  )
}
