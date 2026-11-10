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

function press(tree: ReturnType<typeof mq>, part: Part, key: string, shiftKey = false) {
  tree.keydown(layerSelector(part), key.replace('Arrow', '').toLowerCase(), { key, shiftKey } as unknown as Event);
}

describe('LayerEditor', () => {
  it('draws placed Parts on the canvas and lists the rest beside it', () => {
    const { hunt, parts } = setup([{ x: 10, y: 10, width: 20, z: 1 }, null], { withoutImage: true });
    const editor = render(hunt, parts);

    expect(editor).toHaveElementAttr('.LayerEditor-base', 'src', 'https://example.com/base.svg');
    expect(editor.find('.LayerEditor-layer')).toHaveLength(1);
    expect(editor).toHaveElement(layerSelector(parts[0]));
    expect(editor.find('.LayerEditor-unplacedItem')).toHaveLength(1);
    expect(editor).toContainRaw('Part 2');
    expect(editor).not.toContainRaw('Imageless');
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

    // Pull the corner handle 30px right: 7.5% wider.
    editor.trigger('.LayerEditor-handle', 'pointerdown', { pointerId: 2, button: 0, clientX: 200, clientY: 200, bubbles: true } as never);
    editor.trigger(layerSelector(parts[0]), 'pointermove', { pointerId: 2, clientX: 230, clientY: 260 } as never);
    editor.trigger(layerSelector(parts[0]), 'pointerup', { pointerId: 2, clientX: 230, clientY: 260 } as never);

    editor.click('.LayerEditor-save');
    await settle(editor);

    expect(savedLayers(request)[parts[0].id()!]).toEqual({ x: 20, y: 15, width: 27.5, z: 1 });
  });

  it('brings the selected Part forward and sends it back', async () => {
    const request = mockApi();
    const { hunt, parts } = setup([
      { x: 10, y: 10, width: 20, z: 1 },
      { x: 50, y: 50, width: 10, z: 3 },
    ]);
    const editor = render(hunt, parts);

    expect(editor).not.toHaveElement('.LayerEditor-forward');

    editor.trigger(layerSelector(parts[1]), 'focus', {} as Event);
    editor.click('.LayerEditor-forward');
    editor.click('.LayerEditor-forward');
    editor.click('.LayerEditor-backward');

    expect(editor).toHaveElementAttr(layerSelector(parts[1]), 'aria-pressed', 'true');

    editor.click('.LayerEditor-save');
    await settle(editor);

    expect(savedLayers(request)).toEqual({ [parts[1].id()!]: { x: 50, y: 50, width: 10, z: 4 } });
  });

  it('places an unplaced Part in the centre above every other Part', async () => {
    const request = mockApi();
    const { hunt, parts } = setup([{ x: 10, y: 10, width: 20, z: 6 }, null]);
    const editor = render(hunt, parts);

    editor.click('.LayerEditor-place');

    expect(editor).toHaveElement(layerSelector(parts[1]));
    expect(editor).not.toHaveElement('.LayerEditor-unplacedItem');

    editor.click('.LayerEditor-save');
    await settle(editor);

    expect(savedLayers(request)).toEqual({ [parts[1].id()!]: { x: 40, y: 40, width: 20, z: 7 } });
  });

  it('discards local changes on Reset', () => {
    const request = mockApi();
    const { hunt, parts } = setup([{ x: 10, y: 10, width: 20, z: 1 }]);
    const editor = render(hunt, parts);

    const left = () => (editor.first(layerSelector(parts[0])) as HTMLElement).style.left;

    press(editor, parts[0], 'ArrowRight');
    expect(left()).toBe('11%');

    editor.click('.LayerEditor-reset');

    expect(left()).toBe('10%');
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
