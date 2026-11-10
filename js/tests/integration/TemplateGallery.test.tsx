import bootstrapForum from '@flarum/jest-config/src/bootstrap/forum';
import app from 'flarum/forum/app';
import mq from 'mithril-query';
import { jest } from '@jest/globals';
import commonExtend from '../../src/common/extend';
import TemplateGalleryModal from '../../src/forum/dashboard/TemplateGalleryModal';

beforeAll(() => {
  bootstrapForum();
  app.bootExtensions({ 'fof-scavenger-hunt': { extend: commonExtend } });
  app.boot();
});

afterEach(() => {
  jest.restoreAllMocks();
});

function template(key: string, title: string) {
  return {
    type: 'scavenger-hunt-templates',
    id: key,
    attributes: { title, occasion: title, imageUrl: null, imageReference: null, completedImageReference: null, parts: [] },
  };
}

describe('TemplateGalleryModal', () => {
  it('marks the chosen Template as checked', async () => {
    jest.spyOn(app, 'request').mockImplementation((() => Promise.resolve({ data: [template('winter', 'Build a Snowman')] })) as never);
    const gallery = mq(TemplateGalleryModal, { state: app.modal, animateShow: () => {}, animateHide: () => {} });

    await new Promise((resolve) => setTimeout(resolve, 0));
    gallery.redraw();

    const [blank, winter] = gallery.find('.TemplateGallery-item');

    expect(blank.getAttribute('aria-checked')).toBe('true');
    expect(winter.getAttribute('aria-checked')).toBe('false');

    gallery.click('.TemplateGallery-item:nth-child(2)');

    expect(gallery.find('.TemplateGallery-item').map((item) => item.getAttribute('aria-checked'))).toEqual(['false', 'true']);
  });
});
