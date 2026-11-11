/** What an image is chosen for. */
export type ArtworkPurpose = 'collectable' | 'completed' | 'part';

/** A soft warning: the image can still be used. */
export type FitIssue = 'not_square' | 'small';

export interface ImageFacts {
  width: number | null;
  height: number | null;
  mimeType?: string | null;
}

/** The shorter side a Collectable or completed picture should reach: the canvas is drawn up to 360 px, so 720 px on high-density screens. */
export const MIN_PICTURE_SIDE = 800;

/** The longer side a Part image should reach. */
export const MIN_PART_SIDE = 256;

/** Width and height may differ by this much before a picture counts as not square. */
const SQUARE_TOLERANCE = 0.02;

/** Corner pixels at least this opaque (0–255) mean the image has a solid background. */
const OPAQUE_ALPHA = 250;

/**
 * What doesn't fit the purpose. Images of unknown size get no warnings; SVGs scale, so they are never small.
 */
export function artworkFit(purpose: ArtworkPurpose, image: ImageFacts): FitIssue[] {
  const { width, height } = image;

  if (!width || !height) return [];

  const issues: FitIssue[] = [];
  const vector = image.mimeType === 'image/svg+xml';

  if (purpose === 'part') {
    if (!vector && Math.max(width, height) < MIN_PART_SIDE) issues.push('small');

    return issues;
  }

  if (Math.abs(width - height) / Math.max(width, height) > SQUARE_TOLERANCE) issues.push('not_square');
  if (!vector && Math.min(width, height) < MIN_PICTURE_SIDE) issues.push('small');

  return issues;
}

/**
 * Whether all four corners of RGBA pixel data are opaque.
 */
export function opaqueCorners(pixels: ArrayLike<number>, width: number, height: number): boolean {
  const alphaAt = (x: number, y: number) => pixels[(y * width + x) * 4 + 3];

  return [alphaAt(0, 0), alphaAt(width - 1, 0), alphaAt(0, height - 1), alphaAt(width - 1, height - 1)].every((alpha) => alpha >= OPAQUE_ALPHA);
}

const SAMPLE_SIZE = 64;
const checked = new Map<string, Promise<boolean | null>>();

function measure(url: string): Promise<boolean | null> {
  return new Promise((resolve) => {
    const context = typeof document === 'undefined' ? null : document.createElement('canvas').getContext?.('2d', { willReadFrequently: true });

    if (!context) return resolve(null);

    const image = new Image();
    // Images on a CDN without CORS headers then fail to load, and get no warning.
    image.crossOrigin = 'anonymous';
    image.decoding = 'async';
    image.onerror = () => resolve(null);
    image.onload = () => {
      try {
        context.canvas.width = SAMPLE_SIZE;
        context.canvas.height = SAMPLE_SIZE;
        context.drawImage(image, 0, 0, SAMPLE_SIZE, SAMPLE_SIZE);

        resolve(opaqueCorners(context.getImageData(0, 0, SAMPLE_SIZE, SAMPLE_SIZE).data, SAMPLE_SIZE, SAMPLE_SIZE));
      } catch {
        resolve(null);
      }
    };
    image.src = url;
  });
}

/**
 * Checks, in the browser, whether a Part image has a solid background. `null` when it can't be told.
 * Kept on an object so tests can stub it.
 */
export const backgroundCheck = {
  isSolid(url: string): Promise<boolean | null> {
    let result = checked.get(url);

    if (!result) {
      result = measure(url);
      checked.set(url, result);
    }

    return result;
  },
};
