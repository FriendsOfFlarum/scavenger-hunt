import bootstrapForum from '@flarum/jest-config/src/bootstrap/forum';
import app from 'flarum/forum/app';
import mq from 'mithril-query';
import { jest } from '@jest/globals';
import { readFileSync } from 'fs';
import { resolve } from 'path';
import ArtworkGuideModal, { EXAMPLE_PARTS } from '../../src/common/components/ArtworkGuideModal';
import { artworkFit, opaqueCorners } from '../../src/common/utils/artworkFit';
import uploadAsset from '../../src/common/utils/uploadAsset';

beforeAll(() => {
  bootstrapForum();
  app.boot();
  app.forum.pushAttributes({ apiUrl: 'https://example.com/api', assetsBaseUrl: 'https://example.com/assets' });
});

afterEach(() => {
  jest.restoreAllMocks();
});

describe('artworkFit', () => {
  const png = (width: number | null, height: number | null) => ({ width, height, mimeType: 'image/png' });

  it.each([
    ['collectable', png(1000, 1000), []],
    ['collectable', png(1000, 990), []],
    ['collectable', png(1600, 900), ['not_square']],
    ['collectable', png(600, 600), ['small']],
    ['collectable', png(600, 400), ['not_square', 'small']],
    ['completed', png(1600, 900), ['not_square']],
    ['part', png(1600, 300), []],
    ['part', png(200, 120), ['small']],
    ['part', png(256, 40), []],
  ])('%s %o → %o', (purpose, image, issues) => {
    expect(artworkFit(purpose as 'collectable', image)).toEqual(issues);
  });

  it('never calls an SVG small, since it scales', () => {
    expect(artworkFit('collectable', { width: 400, height: 400, mimeType: 'image/svg+xml' })).toEqual([]);
    expect(artworkFit('part', { width: 56, height: 16, mimeType: 'image/svg+xml' })).toEqual([]);
    expect(artworkFit('collectable', { width: 400, height: 200, mimeType: 'image/svg+xml' })).toEqual(['not_square']);
  });

  it('gives no warnings when the size is unknown', () => {
    expect(artworkFit('collectable', png(null, null))).toEqual([]);
  });
});

describe('opaqueCorners', () => {
  function pixels(width: number, height: number, alpha: (x: number, y: number) => number) {
    const data = new Uint8ClampedArray(width * height * 4);

    for (let y = 0; y < height; y++) for (let x = 0; x < width; x++) data[(y * width + x) * 4 + 3] = alpha(x, y);

    return data;
  }

  it('is true when every corner is opaque', () => {
    expect(
      opaqueCorners(
        pixels(4, 3, () => 255),
        4,
        3
      )
    ).toBe(true);
  });

  it('is false when any corner lets the background through', () => {
    expect(
      opaqueCorners(
        pixels(4, 3, (x, y) => (x === 3 && y === 2 ? 0 : 255)),
        4,
        3
      )
    ).toBe(false);
    expect(
      opaqueCorners(
        pixels(4, 3, (x, y) => (x === 1 && y === 1 ? 255 : 0)),
        4,
        3
      )
    ).toBe(false);
  });
});

describe('uploadAsset', () => {
  it('names the hourly limit instead of core’s “too quickly” message', async () => {
    let handler: ((error: any) => unknown) | undefined;
    jest.spyOn(app, 'request').mockImplementation(((options: { errorHandler: (error: any) => unknown }) => {
      handler = options.errorHandler;

      return Promise.reject(new Error('429'));
    }) as never);

    await uploadAsset(new File(['x'], 'star.png')).catch(() => {});

    const limited = { status: 429, alert: { content: 'core' } };
    expect(handler!(limited)).toBe(false);
    expect(limited.alert.content).toBe('fof-scavenger-hunt.lib.artwork.upload_limit');

    const invalid = { status: 422, alert: { content: 'Upload a PNG…' } };
    expect(handler!(invalid)).toBe(false);
    expect(invalid.alert.content).toBe('Upload a PNG…');
  });
});

describe('ArtworkGuideModal', () => {
  it('explains each kind of artwork and the file rules', () => {
    const guide = mq(ArtworkGuideModal, { state: app.modal, animateShow: () => {}, animateHide: () => {} });

    for (const section of ['collectable', 'parts', 'completed', 'files', 'tips']) {
      expect(guide).toHaveElement(`.ArtworkGuide-section--${section}`);
    }

    expect(guide).toHaveElementAttr(
      '.ArtworkGuide-tile img',
      'src',
      'https://example.com/assets/extensions/fof-scavenger-hunt/templates/winter/base.svg'
    );
    expect(guide.find('.ArtworkGuide-tile--transparent')).toHaveLength(EXAMPLE_PARTS.length);
    expect(guide.find('.ArtworkGuide-layer')).toHaveLength(EXAMPLE_PARTS.length);
  });

  it('assembles the example exactly as the winter Template does', () => {
    const template = JSON.parse(readFileSync(resolve(process.cwd(), '../assets/templates/winter/template.json'), 'utf8'));

    for (const part of EXAMPLE_PARTS) {
      const source = template.parts.find((candidate: { key: string }) => candidate.key === part.key);

      expect(source.image).toBe(part.file);
      expect(source.layer).toEqual({ x: part.x, y: part.y, width: part.width, z: part.z });
    }
  });
});
