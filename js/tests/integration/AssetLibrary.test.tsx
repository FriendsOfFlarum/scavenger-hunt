import bootstrapAdmin from '@flarum/jest-config/src/bootstrap/admin';
import app from 'flarum/admin/app';
import mq from 'mithril-query';
import { jest } from '@jest/globals';
import commonExtend from '../../src/common/extend';
import AssetLibrary from '../../src/admin/components/AssetLibrary';

beforeAll(() => {
  bootstrapAdmin();
  app.bootExtensions({ 'fof-scavenger-hunt': { extend: commonExtend } });
  app.boot();
  app.translator.addTranslations({ 'fof-scavenger-hunt.admin.assets.name_label': 'Name of {name}' });
});

afterEach(() => {
  jest.restoreAllMocks();
});

function asset(id: string, name: string, isInUse: boolean) {
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
      isInUse,
    },
  };
}

// A fresh payload per call: the store keeps pushed attributes by reference.
function mockIndex() {
  return jest.spyOn(app, 'request').mockImplementation(() =>
    Promise.resolve({ data: [asset('1', 'Snowman hat', true), asset('2', 'Carrot nose', false)], links: {} }) as never
  );
}

function mockFailingIndex() {
  let calls = 0;

  return jest.spyOn(app, 'request').mockImplementation(() =>
    ++calls === 1
      ? (Promise.reject(new Error('500')) as never)
      : (Promise.resolve({ data: [asset('2', 'Carrot nose', false)], links: {} }) as never)
  );
}

async function settle(tree: { redraw: () => void }) {
  await new Promise((resolve) => setTimeout(resolve, 0));
  tree.redraw();
}

async function renderLibrary() {
  const library = mq(AssetLibrary);

  await new Promise((resolve) => setTimeout(resolve, 0));
  library.redraw();

  return library;
}

describe('AssetLibrary', () => {
  it('renders a card for every Asset in the store', async () => {
    const request = mockIndex();
    const library = await renderLibrary();

    expect(request).toHaveBeenCalledTimes(1);
    expect(library.find('.AssetLibrary-item')).toHaveLength(2);
    expect(library).toHaveElementAttr('.AssetLibrary-name', 'value', 'Snowman hat');
    expect(library).toHaveElementAttr('.AssetLibrary-thumbnail img', 'src', 'https://example.com/assets/scavenger-hunt/1.png');
    expect(library).toHaveElement('.AssetLibrary-meta');
    expect(library).not.toHaveElement('.AssetLibrary-empty');
  });

  it('disables Delete for an Asset in use and enables it otherwise', async () => {
    mockIndex();
    const library = await renderLibrary();

    const [inUse, unused] = library.find('.AssetLibrary-delete');

    expect(inUse.hasAttribute('disabled')).toBe(true);
    expect(unused.hasAttribute('disabled')).toBe(false);
    expect(library.find('.AssetLibrary-item--inUse')).toHaveLength(1);
  });

  it('offers a retry instead of the empty state when the images fail to load', async () => {
    const request = mockFailingIndex();
    const library = await renderLibrary();

    expect(library).toHaveElement('.AssetLibrary-error');
    expect(library).not.toHaveElement('.AssetLibrary-empty');

    library.click('.AssetLibrary-retry');
    await settle(library);

    expect(request).toHaveBeenCalledTimes(2);
    expect(library).not.toHaveElement('.AssetLibrary-error');
    expect(library.find('.AssetLibrary-item')).toHaveLength(1);
  });

  it('gives every name field its own label', async () => {
    mockIndex();
    const library = await renderLibrary();

    expect(library.find('.AssetLibrary-name').map((input) => input.getAttribute('aria-label'))).toEqual([
      'Name of Snowman hat',
      'Name of Carrot nose',
    ]);
  });

  it('keeps the name field focusable while a rename saves', async () => {
    mockIndex();
    const library = await renderLibrary();
    const save = jest.spyOn(app.store.getById('scavenger-hunt-assets', '2')!, 'save').mockReturnValue(new Promise(() => {}) as never);

    const input = library.find('.AssetLibrary-name')[1] as HTMLInputElement;
    input.value = 'Carrot';
    library.trigger('.AssetLibrary-item:nth-child(2) .AssetLibrary-name', 'input', { target: input } as unknown as Event);
    library.trigger('.AssetLibrary-item:nth-child(2) .AssetLibrary-name', 'change', {} as Event);

    expect(save).toHaveBeenCalledWith({ name: 'Carrot' });

    const saving = library.find('.AssetLibrary-name')[1];

    expect(saving.hasAttribute('disabled')).toBe(false);
    expect(saving.hasAttribute('readonly')).toBe(true);
    expect(saving.getAttribute('aria-busy')).toBe('true');
  });

  it('describes why Delete is unavailable for an Asset in use', async () => {
    mockIndex();
    const library = await renderLibrary();

    const [inUse, unused] = library.find('.AssetLibrary-delete');
    const description = inUse.getAttribute('aria-describedby')!.split(' ').map((id: string) => library.first(`#${id}`).textContent);

    expect(description).toEqual(['fof-scavenger-hunt.admin.assets.in_use', 'fof-scavenger-hunt.admin.assets.in_use_help']);
    expect(unused.hasAttribute('aria-describedby')).toBe(false);
  });
});
