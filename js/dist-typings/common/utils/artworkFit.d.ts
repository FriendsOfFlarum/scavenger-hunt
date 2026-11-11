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
export declare const MIN_PICTURE_SIDE = 800;
/** The longer side a Part image should reach. */
export declare const MIN_PART_SIDE = 256;
/**
 * What doesn't fit the purpose. Images of unknown size get no warnings; SVGs scale, so they are never small.
 */
export declare function artworkFit(purpose: ArtworkPurpose, image: ImageFacts): FitIssue[];
/**
 * Whether all four corners of RGBA pixel data are opaque.
 */
export declare function opaqueCorners(pixels: ArrayLike<number>, width: number, height: number): boolean;
/**
 * Checks, in the browser, whether a Part image has a solid background. `null` when it can't be told.
 * Kept on an object so tests can stub it.
 */
export declare const backgroundCheck: {
    isSolid(url: string): Promise<boolean | null>;
};
