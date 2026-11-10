import type { PartLayer } from '../../common/models/Part';

/** Bounds from PartResource's layer validation, plus a usable minimum width. */
export const MIN_WIDTH = 5;
export const MIN_Z = 0;
export const MAX_Z = 1000;
export const DEFAULT_WIDTH = 20;

export interface CanvasSize {
  width: number;
  height: number;
}

const clamp = (value: number, min: number, max: number) => Math.min(Math.max(value, min), max);

export function roundPercent(value: number): number {
  return Math.round(value * 100) / 100;
}

/** A pointer movement in pixels as percentages of the rendered canvas. */
export function pointerDelta(dxPixels: number, dyPixels: number, canvas: CanvasSize): { dx: number; dy: number } {
  return {
    dx: canvas.width > 0 ? (dxPixels / canvas.width) * 100 : 0,
    dy: canvas.height > 0 ? (dyPixels / canvas.height) * 100 : 0,
  };
}

/**
 * The Part's height in percent of the (square) canvas, from the image's
 * height / width ratio; null while the ratio is unknown.
 */
export function heightPercent(width: number, aspect?: number | null): number | null {
  return aspect ? width * aspect : null;
}

/** Keeps the Part on the canvas: its whole box when the aspect ratio is known, else its top-left corner. */
export function clampLayer(layer: PartLayer, aspect?: number | null): PartLayer {
  const height = heightPercent(layer.width, aspect);

  return {
    ...layer,
    x: roundPercent(clamp(layer.x, 0, Math.max(0, 100 - layer.width))),
    y: roundPercent(clamp(layer.y, 0, height === null ? 100 : Math.max(0, 100 - height))),
  };
}

export function moveBy(layer: PartLayer, dx: number, dy: number, aspect?: number | null): PartLayer {
  return clampLayer({ ...layer, x: layer.x + dx, y: layer.y + dy }, aspect);
}

/** Resizes from the bottom-right corner, so x and y stay put unless the Part has to move back on the canvas. */
export function resizeBy(layer: PartLayer, dWidth: number, aspect?: number | null): PartLayer {
  let max = 100 - layer.x;

  if (aspect) {
    max = Math.min(max, (100 - layer.y) / aspect);
  }

  const width = roundPercent(clamp(layer.width + dWidth, MIN_WIDTH, Math.max(MIN_WIDTH, max)));

  return clampLayer({ ...layer, width }, aspect);
}

/**
 * Moves the Part just above the nearest other Part at or above it, so the visible order changes
 * even where layers tie (ties are drawn in document order). A Part already on top stays put.
 */
export function bringForward(layer: PartLayer, others: PartLayer[]): PartLayer {
  const above = others.filter((other) => other.z >= layer.z).map((other) => other.z);

  return above.length ? { ...layer, z: clamp(Math.min(...above) + 1, MIN_Z, MAX_Z) } : layer;
}

/** The reverse of bringForward: just below the nearest other Part at or below it. */
export function sendBackward(layer: PartLayer, others: PartLayer[]): PartLayer {
  const below = others.filter((other) => other.z <= layer.z).map((other) => other.z);

  return below.length ? { ...layer, z: clamp(Math.max(...below) - 1, MIN_Z, MAX_Z) } : layer;
}

/** A centred, 20% wide layer drawn above every existing one. */
export function defaultLayer(existing: PartLayer[], aspect?: number | null): PartLayer {
  const top = existing.reduce((highest, layer) => Math.max(highest, layer.z), 0);
  const height = heightPercent(DEFAULT_WIDTH, aspect) ?? DEFAULT_WIDTH;

  return clampLayer({ x: 50 - DEFAULT_WIDTH / 2, y: 50 - height / 2, width: DEFAULT_WIDTH, z: Math.min(MAX_Z, top + 1) }, aspect);
}

export function layerStyle(layer: PartLayer): Record<string, string> {
  return { left: `${layer.x}%`, top: `${layer.y}%`, width: `${layer.width}%`, zIndex: String(layer.z) };
}

export function layersEqual(a: PartLayer | null | undefined, b: PartLayer | null | undefined): boolean {
  if (!a || !b) return !a && !b;

  return a.x === b.x && a.y === b.y && a.width === b.width && a.z === b.z;
}
