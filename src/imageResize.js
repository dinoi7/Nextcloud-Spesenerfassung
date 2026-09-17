const MAX_EDGE = 1600
const JPEG_QUALITY = 0.8
const MAX_SIZE = 1048576

const RESIZABLE_TYPES = ['image/jpeg', 'image/png']

export function isResizableImage(file) {
  return RESIZABLE_TYPES.includes(file?.type)
}

export async function resizeImageIfNeeded(file, options = {}) {
  const maxEdge = options.maxEdge ?? MAX_EDGE
  const jpegQuality = options.jpegQuality ?? JPEG_QUALITY

  if (!isResizableImage(file)) return file

  try {
    const bitmap = await loadBitmap(file)
    const longest = Math.max(bitmap.width, bitmap.height)

    if (longest <= maxEdge && file.size <= MAX_SIZE) {
      bitmap.close()
      return file
    }

    const scale = Math.min(1, maxEdge / longest)
    const width = Math.max(1, Math.round(bitmap.width * scale))
    const height = Math.max(1, Math.round(bitmap.height * scale))

    const canvas = document.createElement('canvas')
    canvas.width = width
    canvas.height = height
    const ctx = canvas.getContext('2d')
    if (!ctx) {
      bitmap.close()
      return file
    }
    ctx.drawImage(bitmap.source, 0, 0, width, height)
    bitmap.close()

    const type = file.type === 'image/png' ? 'image/png' : 'image/jpeg'
    const blob = await new Promise((resolve) => canvas.toBlob(resolve, type, jpegQuality))
    if (!blob || blob.size === 0) return file
    if (blob.size >= file.size && longest <= maxEdge) return file

    return new File([blob], file.name, { type, lastModified: file.lastModified })
  } catch {
    return file
  }
}

async function loadBitmap(file) {
  if (typeof createImageBitmap === 'function') {
    const bitmap = await createImageBitmap(file)
    return {
      source: bitmap,
      width: bitmap.width,
      height: bitmap.height,
      close: () => bitmap.close?.(),
    }
  }
  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file)
    const img = new Image()
    img.onload = () => {
      URL.revokeObjectURL(url)
      resolve({
        source: img,
        width: img.naturalWidth,
        height: img.naturalHeight,
        close: () => {},
      })
    }
    img.onerror = () => {
      URL.revokeObjectURL(url)
      reject(new Error('Image decode failed'))
    }
    img.src = url
  })
}

export default resizeImageIfNeeded
