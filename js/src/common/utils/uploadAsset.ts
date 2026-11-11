import app from 'flarum/common/app';
import type { ApiPayloadSingle } from 'flarum/common/Store';
import type Asset from '../models/Asset';

/** The types the server accepts, for a file input's `accept`. */
export const ACCEPTED_IMAGE_TYPES = 'image/png,image/jpeg,image/gif,image/webp,image/svg+xml';

/**
 * Uploads an image to the Asset library. Type and size errors come back as a 422, which core shows as an
 * alert; reaching the hourly limit gets its own message.
 */
export default function uploadAsset(file: File): Promise<Asset> {
  const body = new FormData();
  body.append('file', file);

  return app
    .request<ApiPayloadSingle>({
      method: 'POST',
      url: `${app.forum.attribute('apiUrl')}/scavenger-hunt/assets`,
      serialize: (raw: FormData) => raw,
      body,
      errorHandler: (error) => {
        if (error.status === 429 && error.alert) {
          error.alert.content = app.translator.trans('fof-scavenger-hunt.lib.artwork.upload_limit');
        }

        // Core's handler shows the alert.
        return false;
      },
    })
    .then((payload) => app.store.pushPayload<Asset>(payload));
}
