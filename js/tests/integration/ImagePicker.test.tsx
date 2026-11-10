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

function asset(id: string, name: string) {
  return {
    type: 'scavenger-hunt-assets',
    id,
    attributes: {
      name,
      url: `https://example.com/assets/scavenger-hunt/${id}.png`,
      mimeType: 'image/png',
      width: 400,
      height: 300,
      size: 2048,
      createdAt: '2026-11-10T12:00:00+00:00',
      isInUse: false,
    },
  };
}

// Fresh payloads per call: the store keeps pushed attributes by reference.
function mockApi(assets: { data: object[]; next?: boolean }[] = [{ data: [asset('2', 'Gift'), asset('1', 'Bell')] }]) {
  let assetPage = 0;

  return jest.spyOn(app, 'request').mockImplementation(((options: { url: string; method?: string; body?: any }) => {
    if (options.url.includes('scavenger-hunt-templates')) {
      return Promise.resolve({ data: [template('winter', 'Build a Snowman', ['hat', 'nose']), template('spring', 'Flower pot', ['bloom'])] });
    }

    if (options.url.includes('scavenger-hunt-assets')) {
      const page = assets[Math.min(assetPage++, assets.length - 1)];

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

async function renderPicker(selected: string | null = null) {
  const onselect = jest.fn();
  const animateHide = jest.fn();
  const picker = mq(ImagePickerModal, { state: app.modal, animateShow: () => {}, animateHide, selected, onselect });

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
    expect(picker).not.toHaveElement('.ImagePicker-item');
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
      data: { type: 'scavenger-hunt-parts', id: '1', attributes: { name: 'Hat', mode: 'fixed', sort: 0, image: 'template:winter/hat.svg', imageUrl: null, layer: null } },
    });
    jest.spyOn(part, 'locations').mockReturnValue([]);

    const editor = mq(PartEditor, { part, spots: [], routes: [], ondeleted: () => {} });

    expect(editor).not.toHaveElement('.PartEditor-imageField input');

    editor.click('.PartEditor-imageField .ImageField-choose');
    pick(show, 'asset:3', 'https://example.com/assets/scavenger-hunt/3.png');
    editor.click('.PartEditor-actions .Button--primary');
    await settle(editor);

    expect(sentAttributes(request)).toMatchObject({ name: 'Hat', image: 'asset:3' });
  });

  it('PartEditor saves null once the image is removed', async () => {
    const request = mockApi();
    const part = app.store.pushPayload<Part>({
      data: { type: 'scavenger-hunt-parts', id: '2', attributes: { name: 'Nose', mode: 'fixed', sort: 0, image: 'template:winter/nose.svg', imageUrl: null, layer: null } },
    });
    jest.spyOn(part, 'locations').mockReturnValue([]);

    const editor = mq(PartEditor, { part, spots: [], routes: [], ondeleted: () => {} });

    editor.click('.PartEditor-imageField .ImageField-remove');
    editor.click('.PartEditor-actions .Button--primary');
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
        attributes: { title: 'Snowman', slug: 'snowman', description: null, image: 'template:winter/base.svg', completedImage: null, imageUrl: null, completedImageUrl: null },
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

  it('HuntForm leaves the images alone on the Schedule tab', async () => {
    const request = mockApi();
    const hunt = app.store.pushPayload<Hunt>({
      data: { type: 'scavenger-hunts', id: '2', attributes: { title: 'Tree', slug: 'tree', image: 'template:christmas/base.svg', isEnabled: false, guestsCanSee: true } },
    });

    const form = mq(HuntForm, { hunt, section: 'schedule' });

    expect(form).not.toHaveElement('.ImageField');

    form.trigger('form', 'submit', {} as Event);
    await settle(form);

    expect(sentAttributes(request)).not.toHaveProperty('image');
  });
});
