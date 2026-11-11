import bootstrapForum from '@flarum/jest-config/src/bootstrap/forum';
import app from 'flarum/forum/app';
import Stream from 'flarum/common/utils/Stream';
import mq from 'mithril-query';
import { jest } from '@jest/globals';
import commonExtend from '../../src/common/extend';
import ImagePickerModal from '../../src/forum/dashboard/ImagePickerModal';
import ImageField from '../../src/forum/dashboard/ImageField';
import HuntForm from '../../src/forum/dashboard/HuntForm';
import PartEditor from '../../src/forum/dashboard/PartEditor';
import type Hunt from '../../src/common/models/Hunt';
import type Part from '../../src/common/models/Part';
import { backgroundCheck } from '../../src/common/utils/artworkFit';

beforeAll(() => {
  bootstrapForum();
  app.bootExtensions({ 'fof-scavenger-hunt': { extend: commonExtend } });
  app.boot();
});

afterEach(() => {
  jest.restoreAllMocks();
});

const templateUrl = (file: string) => `https://example.com/assets/extensions/fof-scavenger-hunt/templates/${file}`;

function template(key: string, title: string, parts: string[]) {
  return {
    type: 'scavenger-hunt-templates',
    id: key,
    attributes: {
      title,
      occasion: title,
      imageUrl: templateUrl(`${key}/base.svg`),
      imageReference: `template:${key}/base.svg`,
      completedImageReference: null,
      parts: parts.map((part) => ({
        key: part,
        name: part.toUpperCase(),
        imageUrl: templateUrl(`${key}/${part}.svg`),
        reference: `template:${key}/${part}.svg`,
        layer: { x: 0, y: 0, width: 10, z: 1 },
      })),
    },
  };
}

function asset(id: string, name: string, width = 400, height = 300) {
  return {
    type: 'scavenger-hunt-assets',
    id,
    attributes: {
      name,
      url: `https://example.com/assets/scavenger-hunt/${id}.png`,
      mimeType: 'image/png',
      width,
      height,
      size: 2048,
      createdAt: '2026-11-10T12:00:00+00:00',
      isInUse: false,
    },
  };
}

type AssetPage = { data: object[]; next?: boolean } | 'error';

// Fresh payloads per call: the store keeps pushed attributes by reference.
function mockApi(assets: AssetPage[] = [{ data: [asset('2', 'Gift'), asset('1', 'Bell')] }], templates: ('ok' | 'error')[] = ['ok']) {
  let assetPage = 0;
  let templateCall = 0;

  return jest.spyOn(app, 'request').mockImplementation(((options: { url: string; method?: string; body?: any }) => {
    if (options.url.includes('scavenger-hunt-templates')) {
      if (templates[Math.min(templateCall++, templates.length - 1)] === 'error') return Promise.reject(new Error('500'));

      return Promise.resolve({ data: [template('winter', 'Build a Snowman', ['hat', 'nose']), template('spring', 'Flower pot', ['bloom'])] });
    }

    if (options.url.includes('scavenger-hunt-assets')) {
      const page = assets[Math.min(assetPage++, assets.length - 1)];

      if (page === 'error') return Promise.reject(new Error('500'));

      return Promise.resolve({ data: page.data, links: page.next ? { next: 'https://example.com/api/scavenger-hunt-assets?page[offset]=2' } : {} });
    }

    // A PATCH: echo the saved attributes back.
    const data = options.body.data;

    return Promise.resolve({ data: { type: data.type, id: data.id, attributes: { ...data.attributes, imageUrl: null, completedImageUrl: null } } });
  }) as never);
}

async function settle(tree: { redraw: () => void }) {
  await new Promise((resolve) => setTimeout(resolve, 0));
  tree.redraw();
}

async function renderPicker(selected: string | null = null, template: string | null = null, purpose?: string) {
  const onselect = jest.fn();
  const animateHide = jest.fn();
  const picker = mq(ImagePickerModal, { state: app.modal, animateShow: () => {}, animateHide, selected, onselect, template, purpose });

  await settle(picker);

  return { picker, onselect, animateHide };
}

describe('ImagePickerModal', () => {
  it('lists every Template image grouped by Template', async () => {
    mockApi();
    const { picker } = await renderPicker();

    expect(picker.find('.ImagePicker-group')).toHaveLength(2);
    expect(picker.find('.ImagePicker-groupTitle').map((el) => el.textContent)).toEqual(['Build a Snowman', 'Flower pot']);
    expect(picker.find('.ImagePicker-item')).toHaveLength(5);
    expect(picker).toHaveElementAttr('[data-reference="template:winter/hat.svg"] img', 'src', templateUrl('winter/hat.svg'));
  });

  it("puts the Hunt's own Template first", async () => {
    mockApi();
    const { picker } = await renderPicker(null, 'spring');

    expect(picker.find('.ImagePicker-groupTitle').map((el) => el.textContent)).toEqual(['Flower pot', 'Build a Snowman']);
  });

  it('keeps the API order when the Hunt has no Template', async () => {
    mockApi();
    const { picker } = await renderPicker(null, null);

    expect(picker.find('.ImagePicker-groupTitle').map((el) => el.textContent)).toEqual(['Build a Snowman', 'Flower pot']);
  });

  it('gives every image its full name as a title', async () => {
    mockApi();
    const { picker } = await renderPicker();

    expect(picker).toHaveElementAttr('[data-reference="template:winter/hat.svg"] .ImagePicker-label', 'title', 'HAT');
  });

  it('selects a Template image by its template: reference and closes', async () => {
    mockApi();
    const { picker, onselect, animateHide } = await renderPicker();

    picker.click('[data-reference="template:winter/hat.svg"]');

    expect(onselect).toHaveBeenCalledWith('template:winter/hat.svg', templateUrl('winter/hat.svg'));
    expect(animateHide).toHaveBeenCalled();
  });

  it('selects an uploaded image by its asset: reference', async () => {
    const request = mockApi();
    const { picker, onselect } = await renderPicker();

    picker.click('.ImagePicker-tab--assets');
    await settle(picker);

    expect(request.mock.calls.some(([options]) => (options as { url: string }).url.includes('scavenger-hunt-assets'))).toBe(true);
    expect(picker.find('.ImagePicker-item').map((el) => el.getAttribute('data-reference'))).toEqual(['asset:2', 'asset:1']);

    picker.click('[data-reference="asset:1"]');

    expect(onselect).toHaveBeenCalledWith('asset:1', 'https://example.com/assets/scavenger-hunt/1.png');
  });

  it('selects null for "No image"', async () => {
    mockApi();
    const { picker, onselect, animateHide } = await renderPicker('template:winter/hat.svg');

    picker.click('.ImagePicker-none');

    expect(onselect).toHaveBeenCalledWith(null, null);
    expect(animateHide).toHaveBeenCalled();
  });

  it('highlights the current image', async () => {
    mockApi();
    const { picker } = await renderPicker('template:winter/nose.svg');

    expect(picker.find('.ImagePicker-item--selected')).toHaveLength(1);
    expect(picker).toHaveElement('.ImagePicker-item--selected[data-reference="template:winter/nose.svg"]');
    expect(picker).toHaveElementAttr('[data-reference="template:winter/nose.svg"]', 'aria-pressed', 'true');
    expect(picker).toHaveElementAttr('[data-reference="template:winter/hat.svg"]', 'aria-pressed', 'false');
    expect(picker).toHaveElementAttr('.ImagePicker-none', 'aria-pressed', 'false');
  });

  it('marks "No image" as pressed when nothing is chosen', async () => {
    mockApi();
    const { picker } = await renderPicker(null);

    expect(picker).toHaveElementAttr('.ImagePicker-none', 'aria-pressed', 'true');
  });

  it('switches between Templates and uploads with toggle buttons', async () => {
    mockApi();
    const { picker } = await renderPicker();

    expect(picker).not.toHaveElement('[role="tablist"]');
    expect(picker).not.toHaveElement('[role="tab"]');
    expect(picker).not.toHaveElement('[role="tabpanel"]');
    expect(picker).toHaveElementAttr('.ImagePicker-tab--templates', 'aria-pressed', 'true');
    expect(picker).toHaveElementAttr('.ImagePicker-tab--assets', 'aria-pressed', 'false');

    picker.click('.ImagePicker-tab--assets');
    await settle(picker);

    expect(picker).toHaveElementAttr('.ImagePicker-tab--templates', 'aria-pressed', 'false');
    expect(picker).toHaveElementAttr('.ImagePicker-tab--assets', 'aria-pressed', 'true');
  });

  it('offers a retry when the Templates fail to load', async () => {
    const request = mockApi(undefined, ['error', 'ok']);
    const { picker } = await renderPicker();

    expect(picker).toHaveElement('.ImagePicker-error');
    expect(picker).not.toHaveElement('.ImagePicker-empty');
    expect(picker).not.toHaveElement('.ImagePicker-group');

    picker.click('.ImagePicker-retry');
    await settle(picker);

    expect(request).toHaveBeenCalledTimes(2);
    expect(picker).not.toHaveElement('.ImagePicker-error');
    expect(picker.find('.ImagePicker-group')).toHaveLength(2);
  });

  it('offers a retry when the uploaded images fail to load', async () => {
    mockApi(['error', { data: [asset('1', 'Bell')] }]);
    const { picker } = await renderPicker();

    picker.click('.ImagePicker-tab--assets');
    await settle(picker);

    expect(picker).toHaveElement('.ImagePicker-error');
    expect(picker).not.toHaveElement('.ImagePicker-empty');

    picker.click('.ImagePicker-retry');
    await settle(picker);

    expect(picker).not.toHaveElement('.ImagePicker-error');
    expect(picker.find('.ImagePicker-item').map((el) => el.getAttribute('data-reference'))).toEqual(['asset:1']);
  });

  it('keeps the uploaded images shown when loading more fails', async () => {
    mockApi([{ data: [asset('3', 'Star')], next: true }, 'error']);
    const { picker } = await renderPicker();

    picker.click('.ImagePicker-tab--assets');
    await settle(picker);
    picker.click('.ImagePicker-more');
    await settle(picker);

    expect(picker.find('.ImagePicker-item')).toHaveLength(1);
    expect(picker).toHaveElement('.ImagePicker-more');
  });

  it('opens on the uploaded images when the current image is an Asset', async () => {
    mockApi();
    const { picker } = await renderPicker('asset:2');
    await settle(picker);

    expect(picker).toHaveElement('.ImagePicker-item--selected[data-reference="asset:2"]');
  });

  it('explains where uploads come from when there are none', async () => {
    mockApi([{ data: [] }]);
    const { picker } = await renderPicker();

    picker.click('.ImagePicker-tab--assets');
    await settle(picker);

    expect(picker).toHaveElement('.ImagePicker-empty');
    expect(picker).toContainRaw('fof-scavenger-hunt.forum.dashboard.images.empty_ask_admin');
    expect(picker).not.toHaveElement('.ImagePicker-item');
    expect(picker).not.toHaveElement('.ImagePicker-uploadButton');
  });

  it('loads more uploaded images when there is a next page', async () => {
    mockApi([{ data: [asset('3', 'Star'), asset('2', 'Gift')], next: true }, { data: [asset('1', 'Bell')] }]);
    const { picker } = await renderPicker();

    picker.click('.ImagePicker-tab--assets');
    await settle(picker);
    picker.click('.ImagePicker-more');
    await settle(picker);

    expect(picker.find('.ImagePicker-item')).toHaveLength(3);
    expect(picker).not.toHaveElement('.ImagePicker-more');
  });
});

function captureShow() {
  return jest.spyOn(app.modal, 'show').mockResolvedValue(undefined as never);
}

function pick(show: ReturnType<typeof captureShow>, reference: string | null, url: string | null) {
  const attrs = show.mock.calls[show.mock.calls.length - 1][1] as { onselect: (reference: string | null, url: string | null) => void };
  attrs.onselect(reference, url);
}

describe('ImagePickerModal guidance', () => {
  beforeEach(() => {
    // jsdom has no canvas; tests that care stub a result of their own.
    jest.spyOn(backgroundCheck, 'isSolid').mockResolvedValue(null);
  });

  afterEach(() => {
    app.forum.pushAttributes({ scavengerHuntCanUploadImages: false });
  });

  it.each(['collectable', 'completed', 'part'])('says what a %s image should be, with a link to the Artwork guide', async (purpose) => {
    mockApi();
    const { picker } = await renderPicker(null, null, purpose);

    expect(picker).toContainRaw(`fof-scavenger-hunt.lib.artwork.hints.${purpose}`);

    const show = jest.spyOn(app.modal, 'show').mockResolvedValue(undefined as never);
    picker.click('.ImagePicker-guide');

    // Stacked on the picker, so closing the guide goes back to it.
    expect(show.mock.calls[0][2]).toBe(true);
  });

  it('gives no hint when it is not told what the image is for', async () => {
    mockApi();
    const { picker } = await renderPicker();

    expect(picker).not.toHaveElement('.ImagePicker-hint');
  });

  it('warns about uploaded pictures that are not square or too small, without blocking them', async () => {
    mockApi([{ data: [asset('2', 'Wide', 1600, 900), asset('1', 'Square', 1000, 1000)] }]);
    const { picker, onselect } = await renderPicker(null, null, 'collectable');

    picker.click('.ImagePicker-tab--assets');
    await settle(picker);

    expect(picker.find('[data-reference="asset:2"] .ArtworkWarning').map((el) => el.className)).toEqual([
      'ArtworkWarning ArtworkWarning--not_square',
    ]);
    expect(picker).not.toHaveElement('[data-reference="asset:1"] .ArtworkWarning');

    picker.click('[data-reference="asset:2"]');
    expect(onselect).toHaveBeenCalledWith('asset:2', 'https://example.com/assets/scavenger-hunt/2.png');
  });

  it('warns about Part images with a solid background', async () => {
    jest.spyOn(backgroundCheck, 'isSolid').mockImplementation((url: string) => Promise.resolve(url.endsWith('/2.png')));
    mockApi([{ data: [asset('2', 'Boxed', 400, 400), asset('1', 'Cut out', 400, 400)] }]);
    const { picker } = await renderPicker(null, null, 'part');

    picker.click('.ImagePicker-tab--assets');
    await settle(picker);
    await settle(picker);

    expect(picker).toHaveElement('[data-reference="asset:2"] .ArtworkWarning--solid_background');
    expect(picker).not.toHaveElement('[data-reference="asset:1"] .ArtworkWarning');
  });

  it('lets those allowed to upload add an image, which is marked but not chosen', async () => {
    app.forum.pushAttributes({ scavengerHuntCanUploadImages: true });
    const request = mockApi([{ data: [] }]);
    const { picker, onselect } = await renderPicker(null, null, 'part');

    picker.click('.ImagePicker-tab--assets');
    await settle(picker);

    expect(picker).toContainRaw('fof-scavenger-hunt.forum.dashboard.images.empty_can_upload');

    request.mockImplementation(((options: { url: string; body: FormData }) => {
      expect(options.url).toContain('/scavenger-hunt/assets');
      expect((options.body.get('file') as File).name).toBe('star.png');

      return Promise.resolve({ data: asset('9', 'Star') });
    }) as never);

    const input = picker.first('.ImagePicker-fileInput') as HTMLInputElement;
    Object.defineProperty(input, 'files', { value: [new File(['x'], 'star.png', { type: 'image/png' })] });
    picker.trigger('.ImagePicker-fileInput', 'change', { target: input } as unknown as Event);
    await settle(picker);

    expect(picker.find('.ImagePicker-item').map((el) => el.getAttribute('data-reference'))).toEqual(['asset:9']);
    expect(picker).toHaveElement('[data-reference="asset:9"] .ImagePicker-new');
    expect(onselect).not.toHaveBeenCalled();
  });
});

describe('ImageField', () => {
  it('shows the saved image, then the picked one, and stores the picked reference', () => {
    const show = captureShow();
    const stream = Stream<string | null>('template:winter/hat.svg');
    const field = mq({ view: () => <ImageField stream={stream} url={templateUrl('winter/hat.svg')} /> });

    expect(field).toHaveElementAttr('.ImageField-preview img', 'src', templateUrl('winter/hat.svg'));

    field.click('.ImageField-choose');
    expect((show.mock.calls[0][1] as { selected: string | null }).selected).toBe('template:winter/hat.svg');

    pick(show, 'asset:7', 'https://example.com/assets/scavenger-hunt/7.png');
    field.redraw();

    expect(stream()).toBe('asset:7');
    expect(field).toHaveElementAttr('.ImageField-preview img', 'src', 'https://example.com/assets/scavenger-hunt/7.png');
  });

  it('tells the picker which Template the Hunt was made from', () => {
    const show = captureShow();
    const field = mq({ view: () => <ImageField stream={Stream<string | null>(null)} url={null} template="spring" /> });

    field.click('.ImageField-choose');

    expect((show.mock.calls[0][1] as { template?: string | null }).template).toBe('spring');
  });

  it('clears the reference with Remove', () => {
    const stream = Stream<string | null>('asset:7');
    const field = mq({ view: () => <ImageField stream={stream} url="https://example.com/assets/scavenger-hunt/7.png" /> });

    field.click('.ImageField-remove');

    expect(stream()).toBeNull();
    expect(field).not.toHaveElement('.ImageField-preview img');
    expect(field).not.toHaveElement('.ImageField-remove');
  });
});

function sentAttributes(request: ReturnType<typeof mockApi>) {
  const patch = request.mock.calls.map(([options]) => options as { method?: string; body?: any }).find((options) => options.method === 'PATCH');

  return patch?.body.data.attributes;
}

describe('saving the chosen image', () => {
  it('PartEditor saves the picked reference', async () => {
    const request = mockApi();
    const show = captureShow();
    const part = app.store.pushPayload<Part>({
      data: {
        type: 'scavenger-hunt-parts',
        id: '1',
        attributes: { name: 'Hat', mode: 'fixed', sort: 0, image: 'template:winter/hat.svg', imageUrl: null, layer: null },
      },
    });
    jest.spyOn(part, 'locations').mockReturnValue([]);

    const editor = mq(PartEditor, { part, spots: [], routes: [], ondeleted: () => {} });

    expect(editor).not.toHaveElement('.PartEditor-imageField input');

    editor.click('.PartEditor-imageField .ImageField-choose');
    pick(show, 'asset:3', 'https://example.com/assets/scavenger-hunt/3.png');
    editor.click('.PartEditor-save');
    await settle(editor);

    expect(sentAttributes(request)).toMatchObject({ name: 'Hat', image: 'asset:3' });
  });

  it('PartEditor saves null once the image is removed', async () => {
    const request = mockApi();
    const part = app.store.pushPayload<Part>({
      data: {
        type: 'scavenger-hunt-parts',
        id: '2',
        attributes: { name: 'Nose', mode: 'fixed', sort: 0, image: 'template:winter/nose.svg', imageUrl: null, layer: null },
      },
    });
    jest.spyOn(part, 'locations').mockReturnValue([]);

    const editor = mq(PartEditor, { part, spots: [], routes: [], ondeleted: () => {} });

    editor.click('.PartEditor-imageField .ImageField-remove');
    editor.click('.PartEditor-save');
    await settle(editor);

    expect(sentAttributes(request)).toMatchObject({ image: null });
  });

  it('HuntForm saves the image and completed image on the Details tab', async () => {
    const request = mockApi();
    const show = captureShow();
    const hunt = app.store.pushPayload<Hunt>({
      data: {
        type: 'scavenger-hunts',
        id: '1',
        attributes: {
          title: 'Snowman',
          slug: 'snowman',
          description: null,
          image: 'template:winter/base.svg',
          completedImage: null,
          imageUrl: null,
          completedImageUrl: null,
        },
      },
    });

    const form = mq(HuntForm, { hunt, section: 'details' });

    expect(form.find('.ImageField')).toHaveLength(2);

    form.click('.HuntForm-completedImage .ImageField-choose');
    pick(show, 'asset:5', 'https://example.com/assets/scavenger-hunt/5.png');
    form.click('.HuntForm-image .ImageField-remove');
    form.trigger('form', 'submit', {} as Event);
    await settle(form);

    expect(sentAttributes(request)).toMatchObject({ title: 'Snowman', image: null, completedImage: 'asset:5' });
  });

  it("HuntForm and PartEditor open the picker with the Hunt's Template", () => {
    const show = captureShow();
    const hunt = app.store.pushPayload<Hunt>({
      data: {
        type: 'scavenger-hunts',
        id: '3',
        attributes: { title: 'Pumpkin', slug: 'pumpkin', template: 'halloween', image: null, completedImage: null },
      },
    });
    const part = app.store.pushPayload<Part>({
      data: { type: 'scavenger-hunt-parts', id: '3', attributes: { name: 'Stem', mode: 'fixed', sort: 0, image: null, imageUrl: null, layer: null } },
    });
    jest.spyOn(part, 'locations').mockReturnValue([]);

    mq(HuntForm, { hunt, section: 'details' }).click('.HuntForm-completedImage .ImageField-choose');
    mq(PartEditor, { part, spots: [], routes: [], ondeleted: () => {}, template: 'halloween' }).click('.PartEditor-imageField .ImageField-choose');

    expect(show.mock.calls.map((call) => (call[1] as { template?: string | null }).template)).toEqual(['halloween', 'halloween']);
    expect(show.mock.calls.map((call) => (call[1] as { purpose?: string }).purpose)).toEqual(['completed', 'part']);

    mq(HuntForm, { hunt, section: 'details' }).click('.HuntForm-image .ImageField-choose');
    expect((show.mock.calls[2][1] as { purpose?: string }).purpose).toBe('collectable');
  });

  it('HuntForm leaves the images alone on the Schedule tab', async () => {
    const request = mockApi();
    const hunt = app.store.pushPayload<Hunt>({
      data: {
        type: 'scavenger-hunts',
        id: '2',
        attributes: { title: 'Tree', slug: 'tree', image: 'template:christmas/base.svg', isEnabled: false, guestsCanSee: true },
      },
    });

    const form = mq(HuntForm, { hunt, section: 'schedule' });

    expect(form).not.toHaveElement('.ImageField');

    form.trigger('form', 'submit', {} as Event);
    await settle(form);

    expect(sentAttributes(request)).not.toHaveProperty('image');
  });
});
