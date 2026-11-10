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
});
