const FALLBACK_URL = '/bundles/pushwordadmin/monaco/app.js'

/**
 * Loads the Monaco bundle, and only on the pages holding a field it drives — it
 * weighs a few megabytes, which every admin list would otherwise pay for
 * nothing. The bundle picks the fields up itself once it runs.
 *
 * The in-flight promise is parked on window because the block editor, a separate
 * bundle that cannot import this one, injects the same script when it switches to
 * markdown or JSON mode. Sharing the guard keeps a form carrying both a Monaco
 * field and the block editor from fetching those megabytes twice.
 */
export function initMonacoEditors() {
  if (window.monacoHelper || null === document.querySelector('textarea[data-editor]')) {
    return
  }

  if (window.pwMonacoLoading) return

  window.pwMonacoLoading = new Promise((resolve) => {
    const script = document.createElement('script')
    script.src = window.pwMonacoUrl || FALLBACK_URL
    script.async = true
    script.addEventListener('load', () => resolve(true))
    script.addEventListener('error', () => {
      window.pwMonacoLoading = null
      resolve(false)
    })
    document.head.append(script)
  })
}
