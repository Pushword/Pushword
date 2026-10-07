import { enhanceMermaid } from './mermaid.js'
import './mermaid.css'

const render = () => void enhanceMermaid()
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', render, { once: true })
} else {
  render()
}
document.addEventListener('DOMChanged', render)
