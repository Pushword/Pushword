import { beforeEach, describe, expect, it, vi } from 'vitest'

const { initialize, render } = vi.hoisted(() => ({
  initialize: vi.fn(),
  render: vi.fn(),
}))
vi.mock('mermaid', () => ({ default: { initialize, render } }))

beforeEach(() => {
  vi.resetModules()
  initialize.mockReset()
  render.mockReset().mockImplementation(async (id) => ({ svg: `<svg id="${id}"></svg>` }))
  document.body.innerHTML = ''
})

describe('Mermaid enhancement', () => {
  it('can retry initialization after a loading failure', async () => {
    initialize.mockImplementationOnce(() => {
      throw new Error('Unavailable')
    })
    const { renderMermaid } = await import('./mermaid.js')
    await expect(renderMermaid('flowchart LR\n A --> B')).rejects.toThrow('Unavailable')
    await expect(renderMermaid('flowchart LR\n A --> B')).resolves.toContain('<svg')
    expect(initialize).toHaveBeenCalledTimes(2)
  })

  it('does not initialize Mermaid for ordinary code or an empty page', async () => {
    const { enhanceMermaid } = await import('./mermaid.js')
    await enhanceMermaid()
    document.body.innerHTML = '<pre><code class="language-js">const a = 1</code></pre>'
    await enhanceMermaid()
    expect(initialize).not.toHaveBeenCalled()
    expect(render).not.toHaveBeenCalled()
  })

  it('renders decoded source once, preserves anchors, and uses unique SVG IDs', async () => {
    document.body.innerHTML =
      '<pre><code id="flow" class="language-mermaid">flowchart LR\n A --&gt; B</code></pre><pre><code class="language-mermaid">sequenceDiagram\n Alice-&gt;&gt;Bob: Hi</code></pre>'
    const { enhanceMermaid } = await import('./mermaid.js')
    await Promise.all([enhanceMermaid(), enhanceMermaid()])
    await enhanceMermaid()
    expect(render).toHaveBeenCalledTimes(2)
    expect(render.mock.calls.map(([id]) => id)).toEqual(['pw-mermaid-1', 'pw-mermaid-2'])
    expect(render.mock.calls[0][1]).toBe('flowchart LR\n A --> B')
    expect(document.querySelectorAll('.pw-mermaid svg')).toHaveLength(2)
    expect(document.querySelector('#flow svg')).not.toBeNull()
    expect(initialize).toHaveBeenCalledExactlyOnceWith({
      startOnLoad: false,
      securityLevel: 'strict',
      suppressErrorRendering: true,
      theme: 'neutral',
    })
  })

  it('leaves a failed diagram readable without preventing another from rendering', async () => {
    document.body.innerHTML =
      '<pre><code class="language-mermaid">&lt;invalid&gt;</code></pre><pre><code class="language-mermaid">flowchart LR\n A --> B</code></pre>'
    render.mockRejectedValueOnce(new Error('Invalid syntax'))
    const { enhanceMermaid } = await import('./mermaid.js')
    await enhanceMermaid()
    expect(document.querySelector('code').textContent).toBe('<invalid>')
    expect(document.querySelector('invalid')).toBeNull()
    expect(document.querySelectorAll('.pw-mermaid svg')).toHaveLength(1)
  })

  it('renders diagrams added after the initial pass', async () => {
    const { enhanceMermaid } = await import('./mermaid.js')
    await enhanceMermaid()
    document.body.innerHTML =
      '<pre><code class="language-mermaid">flowchart LR\n A --> B</code></pre>'
    await enhanceMermaid()
    expect(document.querySelector('.pw-mermaid svg')).not.toBeNull()
  })
})
