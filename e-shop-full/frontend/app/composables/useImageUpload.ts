import type { MaybeRefOrGetter } from 'vue'
import type { Resource, UploadedImage } from '~/types'

export const UPLOAD_ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp']
export const UPLOAD_MAX_BYTES = 4 * 1024 * 1024

/** Uploads an image to `/shops/{id}/uploads` (multipart field `image`) and returns its absolute URL. */
export function useImageUpload(shopId: MaybeRefOrGetter<number | string>) {
  const api = useApi()
  const uploading = ref(false)
  const error = ref('')

  async function upload(file: File): Promise<string | null> {
    error.value = ''

    if (!UPLOAD_ACCEPTED_TYPES.includes(file.type)) {
      error.value = 'Format non pris en charge : utilisez une image JPEG, PNG ou WebP.'

      return null
    }

    if (file.size > UPLOAD_MAX_BYTES) {
      error.value = 'L’image dépasse 4 Mo. Choisissez un fichier plus léger.'

      return null
    }

    const body = new FormData()
    body.append('image', file)
    uploading.value = true

    try {
      const { data } = await api<Resource<UploadedImage>>(`/shops/${toValue(shopId)}/uploads`, { method: 'POST', body })

      return data.url
    }
    catch (requestError) {
      error.value = validationErrors(requestError).image ?? errorMessage(requestError, 'Le téléversement a échoué.')

      return null
    }
    finally {
      uploading.value = false
    }
  }

  return { uploading, error, upload }
}
