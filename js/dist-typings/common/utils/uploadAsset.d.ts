import type Asset from '../models/Asset';
/** The types the server accepts, for a file input's `accept`. */
export declare const ACCEPTED_IMAGE_TYPES = "image/png,image/jpeg,image/gif,image/webp,image/svg+xml";
/**
 * Uploads an image to the Asset library. Type and size errors come back as a 422, which core shows as an
 * alert; reaching the hourly limit gets its own message.
 */
export default function uploadAsset(file: File): Promise<Asset>;
