import bootstrapForum from '@flarum/jest-config/src/bootstrap/forum';
import app from 'flarum/forum/app';
import { jest } from '@jest/globals';
import EncounterState from '../../src/forum/states/EncounterState';

const hunt = { id: 1, slug: 'snowman', title: 'Snowman', partCount: 7, foundCount: 0, canClaim: true };

beforeAll(() => {
  bootstrapForum();
  app.boot();
});

beforeEach(() => {
  jest.useFakeTimers();
  app.forum.pushAttributes({ apiUrl: 'https://forum.test/api', scavengerHunts: [hunt] });
});

afterEach(() => {
  jest.useRealTimers();
  jest.restoreAllMocks();
});

function mockRequest() {
  return jest.spyOn(app, 'request').mockResolvedValue({ data: [], included: [] } as never);
}

function sentSpots(request: ReturnType<typeof mockRequest>, call = 0): unknown[] {
  return (request.mock.calls[call][0] as { body: { data: { attributes: { spots: unknown[] } } } }).body.data.attributes.spots;
}

describe('EncounterState', () => {
  it('sends nothing while no Hunt is visible', async () => {
    app.forum.pushAttributes({ scavengerHunts: [] });
    const request = mockRequest();
    const state = new EncounterState();

    state.pageChanged('index', {});
    state.register({ spot: 'page.corner' });
    await jest.advanceTimersByTimeAsync(1000);

    expect(request).not.toHaveBeenCalled();
  });

  it('looks nothing up on the hunt dashboard', async () => {
    const request = mockRequest();
    const state = new EncounterState();

    for (const route of ['fof-scavenger-hunt.dashboard', 'fof-scavenger-hunt.dashboard.hunt']) {
      state.pageChanged(route, { id: '2' });
      state.register({ spot: 'page.corner' });
      await jest.advanceTimersByTimeAsync(1000);
    }

    expect(request).not.toHaveBeenCalled();
  });

  it('sends one debounced lookup with de-duplicated spots', async () => {
    const request = mockRequest();
    const state = new EncounterState();

    state.pageChanged('discussion', { id: '12-snow' });
    state.register({ spot: 'page.corner' });
    state.register({ spot: 'page.corner' });
    state.register({ spot: 'post.footer', contextType: 'post', contextId: 5 });
    await jest.advanceTimersByTimeAsync(499);
    expect(request).not.toHaveBeenCalled();

    await jest.advanceTimersByTimeAsync(1);
    expect(request).toHaveBeenCalledTimes(1);
    expect(sentSpots(request)).toEqual([{ spot: 'page.corner' }, { spot: 'post.footer', contextType: 'post', contextId: 5 }]);
    expect((request.mock.calls[0][0] as { body: { data: { attributes: { route: string } } } }).body.data.attributes.route).toBe('discussion');
  });

  it('only reports spots that have not been reported on this page', async () => {
    const request = mockRequest();
    const state = new EncounterState();

    state.pageChanged('discussion', { id: '12' });
    state.register({ spot: 'page.corner' });
    await jest.advanceTimersByTimeAsync(500);

    state.register({ spot: 'post.footer', contextType: 'post', contextId: 9 });
    await jest.advanceTimersByTimeAsync(500);

    expect(request).toHaveBeenCalledTimes(2);
    expect(sentSpots(request, 1)).toEqual([{ spot: 'post.footer', contextType: 'post', contextId: 9 }]);
  });

  it('reports every mounted spot again on a new page', async () => {
    const request = mockRequest();
    const state = new EncounterState();

    state.pageChanged('index', {});
    state.register({ spot: 'page.header' });
    await jest.advanceTimersByTimeAsync(500);

    state.pageChanged('tags', {});
    await jest.advanceTimersByTimeAsync(500);

    expect(request).toHaveBeenCalledTimes(2);
    expect(sentSpots(request, 1)).toEqual([{ spot: 'page.header' }]);
  });
  it('holds back a spot while it is out of sight and reports it once it shows', async () => {
    const request = mockRequest();
    const state = new EncounterState();
    let hidden = true;

    state.pageChanged('index', {});
    state.register({ spot: 'page.header' }, () => hidden);
    state.register({ spot: 'page.corner' });
    await jest.advanceTimersByTimeAsync(500);

    expect(sentSpots(request)).toEqual([{ spot: 'page.corner' }]);

    hidden = false;
    state.schedule();
    await jest.advanceTimersByTimeAsync(500);

    expect(request).toHaveBeenCalledTimes(2);
    expect(sentSpots(request, 1)).toEqual([{ spot: 'page.header' }]);
  });

  it('reports a spot drawn twice when one of them is in sight', async () => {
    const request = mockRequest();
    const state = new EncounterState();
    const hidden = () => true;

    state.pageChanged('index', {});
    state.register({ spot: 'page.header' }, hidden);
    state.register({ spot: 'page.header' });
    await jest.advanceTimersByTimeAsync(500);

    expect(sentSpots(request)).toEqual([{ spot: 'page.header' }]);
  });

  it('forgets a held-back spot once it unmounts', async () => {
    const request = mockRequest();
    const state = new EncounterState();
    let hidden = true;
    const isHidden = () => hidden;

    state.pageChanged('index', {});
    state.register({ spot: 'page.header' }, isHidden);
    state.unregister({ spot: 'page.header' }, isHidden);
    hidden = false;
    state.schedule();
    await jest.advanceTimersByTimeAsync(500);

    expect(request).not.toHaveBeenCalled();
  });
});
