import type { PartLayer } from '../../common/models/Part';
/** Bounds from PartResource's layer validation, plus a usable minimum width. */
export declare const MIN_WIDTH = 5;
export declare const MIN_Z = 0;
export declare const MAX_Z = 1000;
export declare const DEFAULT_WIDTH = 20;
export interface CanvasSize {
    width: number;
    height: number;
}
export declare function roundPercent(value: number): number;
/** A pointer movement in pixels as percentages of the rendered canvas. */
export declare function pointerDelta(dxPixels: number, dyPixels: number, canvas: CanvasSize): {
    dx: number;
    dy: number;
};
/**
 * The Part's height in percent of the (square) canvas, from the image's
 * height / width ratio; null while the ratio is unknown.
 */
export declare function heightPercent(width: number, aspect?: number | null): number | null;
/** Keeps the Part on the canvas: its whole box when the aspect ratio is known, else its top-left corner. */
export declare function clampLayer(layer: PartLayer, aspect?: number | null): PartLayer;
export declare function moveBy(layer: PartLayer, dx: number, dy: number, aspect?: number | null): PartLayer;
/** Resizes from the bottom-right corner, so x and y stay put unless the Part has to move back on the canvas. */
export declare function resizeBy(layer: PartLayer, dWidth: number, aspect?: number | null): PartLayer;
/**
 * Moves the Part just above the nearest other Part at or above it, so the visible order changes
 * even where layers tie (ties are drawn in document order). A Part already on top stays put.
 */
export declare function bringForward(layer: PartLayer, others: PartLayer[]): PartLayer;
/** The reverse of bringForward: just below the nearest other Part at or below it. */
export declare function sendBackward(layer: PartLayer, others: PartLayer[]): PartLayer;
/** A centred, 20% wide layer drawn above every existing one. */
export declare function defaultLayer(existing: PartLayer[], aspect?: number | null): PartLayer;
export declare function layerStyle(layer: PartLayer): Record<string, string>;
export declare function layersEqual(a: PartLayer | null | undefined, b: PartLayer | null | undefined): boolean;
