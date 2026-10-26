export function initMediaFileDrop(root = document) {
  const input = root.querySelector('input[type="file"][id$="_mediaFile"]')
  if (!input || input.dataset.pwFileDropReady) return
  input.dataset.pwFileDropReady = '1'

  root.addEventListener('dragover', (event) => {
    if (input.disabled || !event.dataTransfer?.types.includes('Files')) return
    event.preventDefault()
    event.dataTransfer.dropEffect = 'copy'
  })

  root.addEventListener('drop', (event) => {
    if (input.disabled || !event.dataTransfer?.files.length) return
    event.preventDefault()

    // The media form edits one file, even when several are dropped.
    const transfer = new DataTransfer()
    transfer.items.add(event.dataTransfer.files[0])
    input.files = transfer.files
    input.dispatchEvent(new Event('change', { bubbles: true }))
    input.focus()
  })
}

window.addEventListener('load', () => initMediaFileDrop())
