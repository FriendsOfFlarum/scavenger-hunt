import bootstrapAdmin from '@flarum/jest-config/src/bootstrap/admin';
import app from 'flarum/admin/app';
import mq from 'mithril-query';
import { jest } from '@jest/globals';
import { fileURLToPath } from 'node:url';

let attempts = 0;

// The first import of the library chunk fails, like a ChunkLoadError; the next one succeeds.
jest.unstable_mockModule(fileURLToPath(new URL('../../src/admin/components/AssetLibrary.tsx', import.meta.url)), () => {
  if (++attempts === 1) throw new Error('ChunkLoadError');

  return { default: { view: () => <div className="FakeAssetLibrary" /> } };
});

let AssetLibrarySection: typeof import('../../src/admin/components/AssetLibrarySection').default;

beforeAll(async () => {
  bootstrapAdmin();
  app.boot();
  ({ default: AssetLibrarySection } = await import('../../src/admin/components/AssetLibrarySection'));
});

async function settle(tree: { redraw: () => void }) {
  await new Promise((resolve) => setTimeout(resolve, 0));
  tree.redraw();
}

describe('AssetLibrarySection', () => {
  it('offers a retry when the library fails to load, and loads it again', async () => {
    const section = mq(AssetLibrarySection);
    await settle(section);

    expect(section).toHaveElement('.AssetLibrary-error');
    expect(section).not.toHaveElement('.FakeAssetLibrary');

    section.click('.AssetLibrarySection-retry');
    await settle(section);

    expect(attempts).toBe(2);
    expect(section).not.toHaveElement('.AssetLibrary-error');
    expect(section).toHaveElement('.FakeAssetLibrary');
  });
});
