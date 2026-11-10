import {
  MAX_Z,
  MIN_WIDTH,
  bringForward,
  clampLayer,
  defaultLayer,
  heightPercent,
  layerStyle,
  layersEqual,
  moveBy,
  pointerDelta,
  resizeBy,
  roundPercent,
  sendBackward,
} from '../../src/forum/dashboard/layerMaths';

const layer = (x: number, y: number, width: number, z = 1) => ({ x, y, width, z });

describe('roundPercent', () => {
  it('rounds to two decimals', () => {
    expect(roundPercent(12.3456)).toBe(12.35);
    expect(roundPercent(12.344)).toBe(12.34);
    expect(roundPercent(10)).toBe(10);
  });
});

describe('pointerDelta', () => {
  it('turns a pixel delta into percentages of the rendered canvas', () => {
    expect(pointerDelta(36, -18, { width: 360, height: 360 })).toEqual({ dx: 10, dy: -5 });
    expect(pointerDelta(50, 25, { width: 200, height: 100 })).toEqual({ dx: 25, dy: 25 });
  });

  it('ignores a canvas with no size', () => {
    expect(pointerDelta(36, 18, { width: 0, height: 0 })).toEqual({ dx: 0, dy: 0 });
  });
});

describe('heightPercent', () => {
  it('derives the height on the square canvas from the image aspect ratio', () => {
    expect(heightPercent(20, 0.5)).toBe(10);
    expect(heightPercent(20, 2)).toBe(40);
  });

  it('is unknown without an aspect ratio', () => {
    expect(heightPercent(20, null)).toBeNull();
    expect(heightPercent(20, undefined)).toBeNull();
  });
});

describe('clampLayer', () => {
  it('keeps the Part on the canvas horizontally', () => {
    expect(clampLayer(layer(-5, 10, 20))).toMatchObject({ x: 0 });
    expect(clampLayer(layer(90, 10, 20))).toMatchObject({ x: 80 });
  });

  it('keeps the whole Part on the canvas vertically when the aspect ratio is known', () => {
    expect(clampLayer(layer(10, 95, 20), 0.5)).toMatchObject({ y: 90 });
    expect(clampLayer(layer(10, -3, 20), 0.5)).toMatchObject({ y: 0 });
  });

  it('only keeps the top edge on the canvas when the aspect ratio is unknown', () => {
    expect(clampLayer(layer(10, 95, 20))).toMatchObject({ y: 95 });
    expect(clampLayer(layer(10, 120, 20))).toMatchObject({ y: 100 });
  });

  it('pins a Part taller than the canvas to the top', () => {
    expect(clampLayer(layer(10, 30, 60), 2)).toMatchObject({ y: 0 });
  });
});

describe('moveBy', () => {
  it('moves by percentages and rounds', () => {
    expect(moveBy(layer(10, 10, 20, 3), 5.556, 1.114, 1)).toEqual(layer(15.56, 11.11, 20, 3));
  });

  it('stops at the canvas edges', () => {
    expect(moveBy(layer(75, 10, 20), 10, 0)).toEqual(layer(80, 10, 20));
    expect(moveBy(layer(5, 5, 20), -10, -10)).toEqual(layer(0, 0, 20));
    expect(moveBy(layer(10, 70, 20), 0, 20, 1)).toEqual(layer(10, 80, 20));
  });
});

describe('resizeBy', () => {
  it('grows and shrinks the width and rounds', () => {
    expect(resizeBy(layer(10, 10, 20), 5.556)).toEqual(layer(10, 10, 25.56));
    expect(resizeBy(layer(10, 10, 20), -5)).toEqual(layer(10, 10, 15));
  });

  it('never shrinks below the minimum width', () => {
    expect(resizeBy(layer(10, 10, 20), -50)).toMatchObject({ width: MIN_WIDTH });
  });

  it('never grows past the right edge of the canvas', () => {
    expect(resizeBy(layer(70, 10, 20), 50)).toMatchObject({ x: 70, width: 30 });
  });

  it('never grows past the bottom edge when the aspect ratio is known', () => {
    expect(resizeBy(layer(10, 60, 20), 50, 1)).toMatchObject({ y: 60, width: 40 });
  });

  it('keeps a Part near the edge valid by moving it back on the canvas', () => {
    expect(resizeBy(layer(98, 10, 2), 0)).toEqual(layer(95, 10, MIN_WIDTH));
  });
});

describe('z order', () => {
  const at = (z: number) => layer(0, 0, 10, z);

  it('brings a Part just above the nearest Part at or above it', () => {
    expect(bringForward(at(4), [at(2), at(6), at(9)])).toMatchObject({ z: 7 });
    expect(bringForward(at(4), [at(5)])).toMatchObject({ z: 6 });
  });

  it('brings a Part above another Part on the same layer', () => {
    expect(bringForward(at(3), [at(3), at(8)])).toMatchObject({ z: 4 });
  });

  it('leaves a Part already on top where it is', () => {
    expect(bringForward(at(4), [at(1), at(3)])).toEqual(at(4));
    expect(bringForward(at(4), [])).toEqual(at(4));
  });

  it('never brings a Part past the server maximum', () => {
    expect(bringForward(at(MAX_Z - 1), [at(MAX_Z)])).toMatchObject({ z: MAX_Z });
    expect(bringForward(at(MAX_Z), [at(MAX_Z)])).toMatchObject({ z: MAX_Z });
  });

  it('sends a Part just below the nearest Part at or below it', () => {
    expect(sendBackward(at(6), [at(2), at(4), at(9)])).toMatchObject({ z: 3 });
    expect(sendBackward(at(6), [at(5)])).toMatchObject({ z: 4 });
  });

  it('sends a Part below another Part on the same layer', () => {
    expect(sendBackward(at(3), [at(3), at(1)])).toMatchObject({ z: 2 });
  });

  it('leaves a Part already at the back where it is', () => {
    expect(sendBackward(at(4), [at(5), at(9)])).toEqual(at(4));
    expect(sendBackward(at(4), [])).toEqual(at(4));
  });

  it('never sends a Part below zero', () => {
    expect(sendBackward(at(1), [at(0)])).toMatchObject({ z: 0 });
    expect(sendBackward(at(0), [at(0)])).toMatchObject({ z: 0 });
  });
});

describe('defaultLayer', () => {
  it('centres a 20% wide Part above every other layer', () => {
    expect(defaultLayer([layer(0, 0, 10, 2), layer(0, 0, 10, 7)], 0.5)).toEqual(layer(40, 45, 20, 8));
  });

  it('starts at z 1 on an empty canvas and assumes a square image without an aspect ratio', () => {
    expect(defaultLayer([])).toEqual(layer(40, 40, 20, 1));
  });

  it('never exceeds the server maximum z', () => {
    expect(defaultLayer([layer(0, 0, 10, MAX_Z)])).toMatchObject({ z: MAX_Z });
  });
});

describe('layerStyle', () => {
  it('positions a layer in percentages of the canvas', () => {
    expect(layerStyle(layer(12.5, 30, 20, 4))).toEqual({ left: '12.5%', top: '30%', width: '20%', zIndex: '4' });
  });
});

describe('layersEqual', () => {
  it('compares every field, treating null as no layer', () => {
    expect(layersEqual(layer(1, 2, 3, 4), layer(1, 2, 3, 4))).toBe(true);
    expect(layersEqual(layer(1, 2, 3, 4), layer(1, 2, 3, 5))).toBe(false);
    expect(layersEqual(null, null)).toBe(true);
    expect(layersEqual(layer(1, 2, 3, 4), null)).toBe(false);
  });
});
