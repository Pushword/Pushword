const MONACO_FALLBACK_URL = '/bundles/pushwordadmin/monaco/app.js'

const loading = new Map<string, Promise<void>>()

/**
 * Injects the script at `src` once, however many callers ask for it, and
 * resolves when it has run. A failed load is forgotten so a later call retries.
 */
export function loadScriptOnce(src: string): Promise<void> {
  const pending = loading.get(src)
  if (pending) return pending

  const loaded = new Promise<void>((resolve, reject) => {
    const script = document.createElement('script')
    script.src = src
    script.async = true
    script.addEventListener('load', () => resolve())
    script.addEventListener('error', () => {
      loading.delete(src)
      reject(new Error(`Failed to load ${src}`))
    })
    document.head.appendChild(script)
  })
  loading.set(src, loaded)

  return loaded
}

/**
 * Resolves to whether Monaco is ready, and never rejects. Its bundle weighs a few
 * megabytes: pushword/admin fetches it on any page holding a Monaco field and
 * parks the in-flight promise on window.pwMonacoLoading. Adopting that promise,
 * and parking ours there, keeps a page from fetching the bundle twice.
 */
export function loadMonaco(): Promise<boolean> {
  if (window.monaco && window.monacoHelper) return Promise.resolve(true)

  window.pwMonacoLoading ??= loadScriptOnce(
    window.pwMonacoUrl || MONACO_FALLBACK_URL,
  ).then(
    () => true,
    () => {
      window.pwMonacoLoading = null
      return false
    },
  )

  return window.pwMonacoLoading
}
