/**
 * Browser-side image scaling before upload
 * Scales down images to match the `default` filter set (max 1980x1280)
 * Quality is preserved (no compression) - backend handles quality optimization
 */
import imageCompression from 'browser-image-compression'
import { debugLog } from './admin.constants'

const MODULE_NAME = 'imageCompressor'

// Scale down options matching the `default` filter set (1980x1280)
// Quality is left at 1 to avoid double compression (backend will handle quality)
const COMPRESSION_OPTIONS = {
  maxWidthOrHeight: 1980,
  initialQuality: 1,
  useWebWorker: true,
  preserveExif: false,
}

// File types that should be scaled down
const COMPRESSIBLE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/avif']

/**
 * Check if a file should be compressed
 * @param {File} file
 * @returns {boolean}
 */
function shouldCompress(file) {
  return COMPRESSIBLE_TYPES.includes(file.type)
}

/**
 * Scale down an image file
 * @param {File} file - Original file
 * @returns {Promise<File>} - Scaled down file
 */
async function scaleDownImage(file) {
  if (!shouldCompress(file)) {
    debugLog(MODULE_NAME, `Skipping scale down for ${file.name} (type: ${file.type})`)
    return file
  }

  debugLog(MODULE_NAME, `Scaling down ${file.name}...`, {
    originalSize: (file.size / 1024 / 1024).toFixed(2) + ' MB',
    type: file.type,
  })

  try {
    const options = {
      ...COMPRESSION_OPTIONS,
      // Keep original format
      fileType: file.type,
    }

    const compressedBlob = await imageCompression(file, options)

    // Convert Blob back to File (preserving original filename)
    const scaledFile = new File([compressedBlob], file.name, {
      type: compressedBlob.type,
      lastModified: file.lastModified,
    })

    debugLog(MODULE_NAME, `Scaled down ${file.name}`, {
      originalSize: (file.size / 1024 / 1024).toFixed(2) + ' MB',
      newSize: (scaledFile.size / 1024 / 1024).toFixed(2) + ' MB',
      reduction: ((1 - scaledFile.size / file.size) * 100).toFixed(1) + '%',
    })

    // Return scaled file only if it's smaller
    if (scaledFile.size < file.size) {
      return scaledFile
    }

    debugLog(MODULE_NAME, `Original file is smaller, keeping original`)
    return file
  } catch (error) {
    console.error(`[${MODULE_NAME}] Scale down failed for ${file.name}:`, error)
    return file
  }
}

/**
 * Handle file input change event
 * @param {Event} event
 */
async function handleFileChange(event) {
  const input = event.target
  if (!input.files || input.files.length === 0) {
    return
  }

  const file = input.files[0]

  // Only process images
  if (!file.type.startsWith('image/')) {
    return
  }

  // Show loading state
  const wrapper = input.closest('.form-group') || input.parentElement
  const originalOpacity = wrapper.style.opacity
  wrapper.style.opacity = '0.6'
  wrapper.style.pointerEvents = 'none'

  try {
    const scaledFile = await scaleDownImage(file)

    // Create a new FileList with the scaled file
    const dataTransfer = new DataTransfer()
    dataTransfer.items.add(scaledFile)
    input.files = dataTransfer.files

    debugLog(MODULE_NAME, 'File replaced with scaled version')
  } finally {
    // Restore state
    wrapper.style.opacity = originalOpacity
    wrapper.style.pointerEvents = ''
  }
}

/**
 * Initialize image compression for media file inputs
 */
export function initImageCompressor() {
  // Target the media file input (field name is mediaFile)
  const mediaFileInputs = document.querySelectorAll(
    'input[type="file"][id$="_mediaFile"], input[type="file"][data-pw-compress-image]'
  )

  mediaFileInputs.forEach((input) => {
    if (input.dataset.pwCompressorInitialized) {
      return
    }

    input.addEventListener('change', handleFileChange)
    input.dataset.pwCompressorInitialized = 'true'

    debugLog(MODULE_NAME, 'Initialized compressor for', input.id || input.name)
  })

  if (mediaFileInputs.length > 0) {
    debugLog(MODULE_NAME, `Initialized ${mediaFileInputs.length} input(s)`)
  }
}
