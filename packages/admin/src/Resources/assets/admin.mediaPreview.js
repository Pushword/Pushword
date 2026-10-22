export function initMediaPreviews(root = document) {
  root.querySelectorAll('img[data-pw-media-preview]').forEach((image) => {
    const showUnavailable = () => {
      image.hidden = true
      const preview = image.closest('.mosaic-inner-box')
      preview.classList.add('pw-preview-unavailable')
      preview.querySelector('.media-mosaic__unavailable').hidden = false
    }
    image.addEventListener('error', showUnavailable, { once: true })
    if (image.complete && image.naturalWidth === 0) showUnavailable()
  })
}
