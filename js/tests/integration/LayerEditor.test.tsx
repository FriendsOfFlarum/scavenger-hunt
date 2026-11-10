import bootstrapForum from '@flarum/jest-config/src/bootstrap/forum';
import app from 'flarum/forum/app';
import mq from 'mithril-query';
import { jest } from '@jest/globals';
import commonExtend from '../../src/common/extend';
import LayerEditor from '../../src/forum/dashboard/LayerEditor';
import PartEditor from '../../src/forum/dashboard/PartEditor';
import type Hunt from '../../src/common/models/Hunt';
import type Part from '../../src/common/models/Part';
import type { PartLayer } from '../../src/common/models/Part';

beforeAll(() => {
  bootstrapForum();
  app.bootExtensions({ 'fof-scavenger-hunt': { extend: commonExtend } });
  app.boot();
  app.translator.addTranslations({
    'fof-scavenger-hunt.forum.dashboard.layout.position': '{name}: left {x}%, top {y}%, width {width}%, layer {z}',
  });
});

afterEach(() => {
  jest.restoreAllMocks();
});

let nextId = 0;

function setup(layers: (PartLayer | null)[], { withoutImage = false } = {}) {
  const hunt = app.store.pushPayload<Hunt>({
    data: { type: 'scavenger-hunts', id: String(++nextId), attributes: { title: 'Snowman', imageUrl: 'https://example.com/base.svg' } },
  });

  const parts = layers.map((layer, index) => {
    const part = app.store.pushPayload<Part>({
      data: {
        type: 'scavenger-hunt-parts',
        id: String(++nextId),
        attributes: { name: `Part ${index + 1}`, mode: 'fixed', sort: index, image: null, imageUrl: `https://example.com/${index}.svg`, layer },
      },
    });
    jest.spyOn(part, 'locations').mockReturnValue([]);

    return part;
  });

  if (withoutImage) {
    parts.push(
      app.store.pushPayload<Part>({
        data: { type: 'scavenger-hunt-parts', id: String(++nextId), attributes: { name: 'Imageless', sort: 9, imageUrl: null, layer: null } },
      })
    );
  }

  return { hunt, parts };
}

// Echo each PATCH back, so the store holds what was saved.
function mockApi() {
  return jest.spyOn(app, 'request').mockImplementation(((options: { body: any }) => {
    const data = options.body.data;

    return Promise.resolve({ data: { type: data.type, id: data.id, attributes: data.attributes } });
  }) as never);
}

function savedLayers(request: ReturnType<typeof mockApi>): Record<string, PartLayer> {
  return Object.fromEntries(
    request.mock.calls.map(([options]) => {
      const data = (options as unknown as { body: any }).body.data;

      return [data.id, data.attributes.layer];
    })
  );
}

async function settle(tree: { redraw: () => void }) {
  await new Promise((resolve) => setTimeout(resolve, 0));
  tree.redraw();
}

function render(hunt: Hunt, parts: Part[]) {
  return mq({ view: () => <LayerEditor hunt={hunt} parts={parts} /> });
}

const layerSelector = (part: Part) => `.LayerEditor-layer[data-part-id="${part.id()}"]`;

function press(tree: ReturnType<typeof mq>, part: Part, key: string, shiftKey = false, modifiers: Partial<KeyboardEvent> = {}) {
  tree.keydown(layerSelector(part), key.replace('Arrow', '').toLowerCase(), { key, shiftKey, ...modifiers } as unknown as Event);
}

const left = (tree: ReturnType<typeof mq>, part: Part) => (tree.first(layerSelector(part)) as HTMLElement).style.left;

// domino's HTMLElement#focus is a read-only no-op, so shadow it on the <div> prototype.
function spyOnFocus(tree: ReturnType<typeof mq>) {
  const proto = Object.getPrototypeOf(tree.first('.LayerEditor-canvas'));
  const focus = jest.fn();

  Object.defineProperty(proto, 'focus', { value: focus, configurable: true, writable: true });
  restoreFocus = () => delete proto.focus;

  return focus;
}

let restoreFocus = () => {};

afterEach(() => {
  restoreFocus();
  restoreFocus = () => {};
});

describe('LayerEditor', () => {
  it('draws placed Parts on the canvas and lists the rest beside it', () => {
    const { hunt, parts } = setup([{ x: 10, y: 10, width: 20, z: 1 }, null], { withoutImage: true });
    const editor = render(hunt, parts);

    expect(editor).toHaveElementAttr('.LayerEditor-base', 'src', 'https://example.com/base.svg');
    expect(editor.find('.LayerEditor-layer')).toHaveLength(1);
    expect(editor).toHaveElement(layerSelector(parts[0]));
    expect(editor.find('.LayerEditor-unplacedItem')).toHaveLength(2);
    expect(editor).toContainRaw('Part 2');
    expect(editor.find('.LayerEditor-place')).toHaveLength(1);
  });

  it('lists Parts without an image as not placeable', () => {
    const { hunt, parts } = setup([], { withoutImage: true });
    const editor = render(hunt, parts);

    expect(editor).toHaveElement('.LayerEditor-unplacedItem--noImage');
    expect(editor.first('.LayerEditor-unplacedItem--noImage').textContent).toContain('Imageless');
    expect(editor).toContainRaw('fof-scavenger-hunt.forum.dashboard.layout.no_image');
    expect(editor).not.toHaveElement('.LayerEditor-place');
  });

  it('exposes each Part as a named, movable group rather than a toggle', () => {
    const { hunt, parts } = setup([{ x: 10, y: 10, width: 20, z: 1 }]);
    const editor = render(hunt, parts);
    const layer = editor.first(layerSelector(parts[0]));

    expect(layer.getAttribute('role')).toBe('group');
    expect(layer.getAttribute('aria-roledescription')).toBe('fof-scavenger-hunt.forum.dashboard.layout.movable_part');
    expect(layer.getAttribute('aria-label')).toBe('Part 1');
    expect(layer.hasAttribute('aria-pressed')).toBe(false);
  });

  it('announces the new position after a nudge', () => {
    const { hunt, parts } = setup([{ x: 10, y: 10, width: 20, z: 1 }]);
    const editor = render(hunt, parts);

    expect(editor).toHaveElementAttr('.LayerEditor-status', 'aria-live', 'polite');
    expect(editor.first('.LayerEditor-status').textContent).toBe('');

    press(editor, parts[0], 'ArrowRight');

    expect(editor.first('.LayerEditor-status').textContent).toBe('Part 1: left 11%, top 10%, width 20%, layer 1');
  });

  it('leaves arrow keys with Alt, Ctrl or Meta to the browser', () => {
    const { hunt, parts } = setup([{ x: 10, y: 10, width: 20, z: 1 }]);
    const editor = render(hunt, parts);

    press(editor, parts[0], 'ArrowRight', false, { altKey: true });
    press(editor, parts[0], 'ArrowRight', false, { ctrlKey: true });
    press(editor, parts[0], 'ArrowRight', false, { metaKey: true });

    expect(left(editor, parts[0])).toBe('10%');
    expect(editor).toHaveElement('.LayerEditor-save[disabled]');
  });

  it('ignores nudges and layer buttons while saving', () => {
    jest.spyOn(app, 'request').mockReturnValue(new Promise(() => {}) as never);
    const { hunt, parts } = setup([
      { x: 10, y: 10, width: 20, z: 1 },
      { x: 50, y: 50, width: 10, z: 3 },
    ]);
    const editor = render(hunt, parts);

    press(editor, parts[0], 'ArrowRight');
    editor.click('.LayerEditor-save');
    press(editor, parts[0], 'ArrowRight');

    expect(left(editor, parts[0])).toBe('11%');
    expect(editor).toHaveElement('.LayerEditor-forward[disabled]');
    expect(editor).toHaveElement('.LayerEditor-backward[disabled]');
  });

  it('drops a local change once the Part is saved elsewhere', () => {
    const { hunt, parts } = setup([
      { x: 10, y: 10, width: 20, z: 1 },
      { x: 50, y: 50, width: 10, z: 3 },
    ]);
    const editor = render(hunt, parts);

    press(editor, parts[0], 'ArrowRight');
    expect(left(editor, parts[0])).toBe('11%');

    // E.g. the Part editor's numeric fields.
    app.store.pushPayload({
      data: { type: 'scavenger-hunt-parts', id: parts[0].id()!, attributes: { layer: { x: 30, y: 10, width: 20, z: 1 } } },
    });
    editor.redraw();

    expect(left(editor, parts[0])).toBe('30%');
    expect(editor).toHaveElement('.LayerEditor-save[disabled]');
  });

  it('keeps the local change of a Part that failed to save', async () => {
    const { hunt, parts } = setup([
      { x: 10, y: 10, width: 20, z: 1 },
      { x: 50, y: 50, width: 10, z: 3 },
    ]);
    jest.spyOn(app, 'request').mockImplementation(((options: { body: any }) => {
      const data = options.body.data;

      return data.id === parts[0].id()
        ? Promise.reject(new Error('422'))
        : Promise.resolve({ data: { type: data.type, id: data.id, attributes: data.attributes } });
    }) as never);
    const editor = render(hunt, parts);

    press(editor, parts[0], 'ArrowRight');
    press(editor, parts[1], 'ArrowRight');
    editor.click('.LayerEditor-save');
    await settle(editor);

    expect(parts[0].layer()).toEqual({ x: 10, y: 10, width: 20, z: 1 });
    expect(left(editor, parts[0])).toBe('11%');
    expect(parts[1].layer()).toEqual({ x: 51, y: 50, width: 10, z: 3 });
    expect(left(editor, parts[1])).toBe('51%');
    expect(editor).not.toHaveElement('.LayerEditor-save[disabled]');
  });

  it('keeps local changes to other Parts when one is saved elsewhere', () => {
    const { hunt, parts } = setup([
      { x: 10, y: 10, width: 20, z: 1 },
      { x: 50, y: 50, width: 10, z: 3 },
    ]);
    const editor = render(hunt, parts);

    press(editor, parts[0], 'ArrowRight');
    press(editor, parts[1], 'ArrowRight');

    app.store.pushPayload({
      data: { type: 'scavenger-hunt-parts', id: parts[0].id()!, attributes: { layer: { x: 30, y: 10, width: 20, z: 1 } } },
    });
    editor.redraw();

    expect(left(editor, parts[1])).toBe('51%');
    expect(editor).not.toHaveElement('.LayerEditor-save[disabled]');
  });

  it('saves a Part moved with the arrow keys', async () => {
    const request = mockApi();
    const { hunt, parts } = setup([
      { x: 10, y: 10, width: 20, z: 1 },
      { x: 50, y: 50, width: 10, z: 2 },
    ]);
    const editor = render(hunt, parts);

    press(editor, parts[0], 'ArrowRight', true);
    press(editor, parts[0], 'ArrowDown');
    press(editor, parts[0], 'ArrowLeft');

    expect(request).not.toHaveBeenCalled();

    editor.click('.LayerEditor-save');
    await settle(editor);

    expect(request).toHaveBeenCalledTimes(1);
    expect(savedLayers(request)).toEqual({ [parts[0].id()!]: { x: 14, y: 11, width: 20, z: 1 } });
    expect(parts[0].layer()).toEqual({ x: 14, y: 11, width: 20, z: 1 });
    expect(editor).toHaveElement('.LayerEditor-save[disabled]');
  });

  it('keeps a nudged Part on the canvas', async () => {
    const request = mockApi();
    const { hunt, parts } = setup([{ x: 1, y: 10, width: 20, z: 1 }]);
    const editor = render(hunt, parts);

    press(editor, parts[0], 'ArrowLeft', true);
    editor.click('.LayerEditor-save');
    await settle(editor);

    expect(savedLayers(request)[parts[0].id()!]).toEqual({ x: 0, y: 10, width: 20, z: 1 });
  });

  it('saves a Part dragged and resized with the pointer', async () => {
    const request = mockApi();
    const { hunt, parts } = setup([{ x: 10, y: 10, width: 20, z: 1 }]);
    const editor = render(hunt, parts);

    const canvas = editor.first('.LayerEditor-canvas') as HTMLElement;
    canvas.getBoundingClientRect = () => ({ left: 0, top: 0, width: 400, height: 400 }) as DOMRect;

    const layer = editor.first(layerSelector(parts[0])) as HTMLElement;
    layer.setPointerCapture = jest.fn();
    layer.releasePointerCapture = jest.fn();

    // Drag 40px right and 20px down on a 400px canvas: 10% and 5%.
    editor.trigger(layerSelector(parts[0]), 'pointerdown', { pointerId: 1, button: 0, clientX: 100, clientY: 100 } as never);
    editor.trigger(layerSelector(parts[0]), 'pointermove', { pointerId: 1, clientX: 140, clientY: 120 } as never);
    editor.trigger(layerSelector(parts[0]), 'pointerup', { pointerId: 1, clientX: 140, clientY: 120 } as never);

    expect(layer.setPointerCapture).toHaveBeenCalledWith(1);
    expect(editor.first('.LayerEditor-status').textContent).toBe('Part 1: left 20%, top 15%, width 20%, layer 1');

    // Pull the corner handle 30px right: 7.5% wider.
    editor.trigger('.LayerEditor-handle', 'pointerdown', { pointerId: 2, button: 0, clientX: 200, clientY: 200, bubbles: true } as never);
    editor.trigger(layerSelector(parts[0]), 'pointermove', { pointerId: 2, clientX: 230, clientY: 260 } as never);
    editor.trigger(layerSelector(parts[0]), 'pointerup', { pointerId: 2, clientX: 230, clientY: 260 } as never);

    editor.click('.LayerEditor-save');
    await settle(editor);

    expect(savedLayers(request)[parts[0].id()!]).toEqual({ x: 20, y: 15, width: 27.5, z: 1 });
  });

  it('keeps dragging when the browser refuses pointer capture', () => {
    const { hunt, parts } = setup([{ x: 10, y: 10, width: 20, z: 1 }]);
    const editor = render(hunt, parts);

    const canvas = editor.first('.LayerEditor-canvas') as HTMLElement;
    canvas.getBoundingClientRect = () => ({ left: 0, top: 0, width: 400, height: 400 }) as DOMRect;

    // Both throw once the pointer is no longer active (lifted, cancelled).
    const layer = editor.first(layerSelector(parts[0])) as HTMLElement;
    layer.setPointerCapture = () => {
      throw new Error('InvalidStateError');
    };
    layer.releasePointerCapture = () => {
      throw new Error('NotFoundError');
    };

    editor.trigger(layerSelector(parts[0]), 'pointerdown', { pointerId: 1, button: 0, clientX: 100, clientY: 100 } as never);
    editor.trigger(layerSelector(parts[0]), 'pointermove', { pointerId: 1, clientX: 140, clientY: 120 } as never);
    expect(() => editor.trigger(layerSelector(parts[0]), 'pointerup', { pointerId: 1, clientX: 140, clientY: 120 } as never)).not.toThrow();

    expect(editor.first('.LayerEditor-status').textContent).toBe('Part 1: left 20%, top 15%, width 20%, layer 1');
  });

  it('brings the selected Part forward past its neighbour and sends it back', async () => {
    const request = mockApi();
    const { hunt, parts } = setup([
      { x: 10, y: 10, width: 20, z: 1 },
      { x: 50, y: 50, width: 10, z: 3 },
    ]);
    const editor = render(hunt, parts);

    expect(editor).not.toHaveElement('.LayerEditor-forward');

    editor.trigger(layerSelector(parts[0]), 'focus', {} as Event);
    expect(editor).toHaveElement(`${layerSelector(parts[0])}.LayerEditor-layer--selected`);

    editor.click('.LayerEditor-forward');
    expect((editor.first(layerSelector(parts[0])) as HTMLElement).style.zIndex).toBe('4');
    expect(editor).toHaveElement('.LayerEditor-forward[disabled]');

    editor.click('.LayerEditor-backward');
    expect((editor.first(layerSelector(parts[0])) as HTMLElement).style.zIndex).toBe('2');

    editor.click('.LayerEditor-save');
    await settle(editor);

    expect(savedLayers(request)).toEqual({ [parts[0].id()!]: { x: 10, y: 10, width: 20, z: 2 } });
  });

  it('brings a Part above another on the same layer', () => {
    const { hunt, parts } = setup([
      { x: 10, y: 10, width: 20, z: 2 },
      { x: 50, y: 50, width: 10, z: 2 },
    ]);
    const editor = render(hunt, parts);

    editor.trigger(layerSelector(parts[0]), 'focus', {} as Event);
    editor.click('.LayerEditor-forward');

    expect((editor.first(layerSelector(parts[0])) as HTMLElement).style.zIndex).toBe('3');
  });

  it('places an unplaced Part in the centre above every other Part', async () => {
    const request = mockApi();
    const { hunt, parts } = setup([{ x: 10, y: 10, width: 20, z: 6 }, null]);
    const editor = render(hunt, parts);

    const focus = spyOnFocus(editor);

    editor.click('.LayerEditor-place');

    expect(editor).toHaveElement(layerSelector(parts[1]));
    expect(focus.mock.contexts).toContain(editor.first(layerSelector(parts[1])));
    expect(editor).not.toHaveElement('.LayerEditor-unplacedItem');

    editor.click('.LayerEditor-save');
    await settle(editor);

    expect(savedLayers(request)).toEqual({ [parts[1].id()!]: { x: 40, y: 40, width: 20, z: 7 } });
  });

  it('discards local changes on Reset', () => {
    const request = mockApi();
    const { hunt, parts } = setup([{ x: 10, y: 10, width: 20, z: 1 }]);
    const editor = render(hunt, parts);

    press(editor, parts[0], 'ArrowRight');
    expect(left(editor, parts[0])).toBe('11%');

    editor.click('.LayerEditor-reset');

    expect(left(editor, parts[0])).toBe('10%');
    expect(editor).toHaveElement('.LayerEditor-save[disabled]');
    expect(request).not.toHaveBeenCalled();
  });

  it('updates the Part editor fields after saving the layout', async () => {
    mockApi();
    const oninit = jest.spyOn(PartEditor.prototype, 'oninit');
    const { hunt, parts } = setup([{ x: 10, y: 10, width: 20, z: 1 }]);
    const page = mq({
      view: () => [<LayerEditor hunt={hunt} parts={parts} />, <PartEditor part={parts[0]} spots={[]} routes={[]} ondeleted={() => {}} />],
    });

    press(page, parts[0], 'ArrowRight', true);
    page.click('.LayerEditor-save');
    await settle(page);

    // The inputs are bound with bidi, which the test renderer leaves unpatched, so read the streams.
    const fields = (oninit.mock.contexts[0] as unknown as { layer: Record<string, () => string> }).layer;

    expect([fields.x(), fields.y(), fields.width(), fields.z()]).toEqual(['15', '10', '20', '1']);
  });
});
