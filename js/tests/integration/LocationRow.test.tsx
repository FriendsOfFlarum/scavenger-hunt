import bootstrapForum from '@flarum/jest-config/src/bootstrap/forum';
import app from 'flarum/forum/app';
import mq from 'mithril-query';
import { jest } from '@jest/globals';
import commonExtend from '../../src/common/extend';
import LocationRow from '../../src/forum/dashboard/LocationRow';
import type HidingSpotDefinition from '../../src/common/models/HidingSpotDefinition';
import type Location from '../../src/common/models/Location';
import type Part from '../../src/common/models/Part';

beforeAll(() => {
  bootstrapForum();
  app.bootExtensions({ 'fof-scavenger-hunt': { extend: commonExtend } });
  app.boot();
});

afterEach(() => {
  jest.restoreAllMocks();
});

function spots(): HidingSpotDefinition[] {
  return app.store.pushPayload<HidingSpotDefinition[]>({
    data: [
      { type: 'scavenger-hunt-spots', id: 'post.footer', attributes: { label: 'Post footer', routes: ['discussion'], contextType: 'post' } },
      { type: 'scavenger-hunt-spots', id: 'page.footer', attributes: { label: 'Page footer', routes: null, contextType: null } },
    ],
  }) as unknown as HidingSpotDefinition[];
}

function mockApi() {
  return jest.spyOn(app, 'request').mockImplementation(((options: { body: any }) => {
    const data = options.body.data;

    return Promise.resolve({ data: { type: data.type, id: data.id ?? '500', attributes: data.attributes } });
  }) as never);
}

function sent(request: ReturnType<typeof mockApi>) {
  return (request.mock.calls[0][0] as unknown as { body: any }).body.data;
}

const part = () =>
  app.store.pushPayload<Part>({ data: { type: 'scavenger-hunt-parts', id: '40', attributes: { name: 'Hat', mode: 'fixed', sort: 0 } } });

describe('LocationRow', () => {
  it('saves a new Location with the first spot, any page and the typed item', async () => {
    const request = mockApi();
    const row = mq(LocationRow, {
      location: app.store.createRecord<Location>('scavenger-hunt-locations'),
      part: part(),
      spots: spots(),
      routes: [{ name: 'discussion', path: '/d/{id}' }],
      ondeleted: () => {},
    });

    row.setValue('.LocationRow input', '12');
    row.click('.LocationRow-actions .Button:not(.Button--danger)');
    await Promise.resolve();

    expect(sent(request).attributes).toEqual({ spot: 'post.footer', route: null, contextType: 'post', contextId: 12 });
    expect(sent(request).relationships.part.data).toEqual({ type: 'scavenger-hunt-parts', id: '40' });
  });

  it('shows and saves an existing Location without an item for a page-level spot', async () => {
    const request = mockApi();
    const location = app.store.pushPayload<Location>({
      data: { type: 'scavenger-hunt-locations', id: '41', attributes: { spot: 'page.footer', route: 'index', contextType: null, contextId: null, isAvailable: true } },
    });
    const row = mq(LocationRow, { location, part: part(), spots: spots(), routes: [{ name: 'index', path: '/' }], ondeleted: () => {} });

    expect(row).not.toHaveElement('.LocationRow input');
    expect(row.find('.LocationRow-field')).toHaveLength(2);

    row.click('.LocationRow-actions .Button:not(.Button--danger)');
    await Promise.resolve();

    expect(sent(request).attributes).toEqual({ spot: 'page.footer', route: 'index', contextType: null, contextId: null });
  });
});
