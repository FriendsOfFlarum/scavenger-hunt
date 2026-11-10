import bootstrapForum from '@flarum/jest-config/src/bootstrap/forum';
import app from 'flarum/forum/app';
import mq from 'mithril-query';
import { jest } from '@jest/globals';
import HidingSpot from '../../src/forum/components/HidingSpot';
import EncounterState, { encounters, lookUpWhenDrawerOpens } from '../../src/forum/states/EncounterState';
import Drawer from 'flarum/common/utils/Drawer';
import Encounter from '../../src/common/models/Encounter';
import Part from '../../src/common/models/Part';

beforeAll(() => {
  bootstrapForum();
  app.boot();
});

afterEach(() => {
  jest.restoreAllMocks();
});

describe('HidingSpot', () => {
  it('renders nothing until the server places a Part in it', () => {
    jest.spyOn(encounters, 'encounterAt').mockReturnValue(undefined);

    const spot = mq(HidingSpot, { spot: 'page.corner' });

    expect(spot).not.toHaveElement('.HiddenPart');
  });

  it('renders a labelled button for an Encounter', () => {
    const part = new Part({ type: 'scavenger-hunt-parts', id: '1', attributes: { name: 'Carrot nose', imageUrl: null } });
    const encounter = new Encounter({ type: 'scavenger-hunt-encounters', id: '1:page.corner::', attributes: { spot: 'page.corner', token: 'abc' } });
    jest.spyOn(encounter, 'part').mockReturnValue(part);
    jest.spyOn(encounters, 'encounterAt').mockReturnValue(encounter);

    const spot = mq(HidingSpot, { spot: 'page.corner' });

    expect(spot).toHaveElement('button.HiddenPart');
    expect(spot).toHaveElementAttr('button.HiddenPart', 'aria-label', 'fof-scavenger-hunt.forum.part.collect');
  });
});

describe('HidingSpot in the phone drawer', () => {
  const hunt = { id: 1, slug: 'snowman', title: 'Snowman', partCount: 7, foundCount: 0, canClaim: true };
  let page = 0;

  beforeAll(() => {
    lookUpWhenDrawerOpens();

    // Core's Drawer traps focus in #drawer, which has to exist when the Drawer is made and
    // hold something focusable (jsdom has no layout, so the button gets a box).
    const drawer = document.createElement('div');
    drawer.id = 'drawer';
    drawer.innerHTML = '<button type="button">Menu</button>';
    drawer.querySelector('button')!.getClientRects = () => [{ width: 10, height: 10 }] as unknown as DOMRectList;
    document.body.appendChild(drawer);
    app.drawer = new Drawer();
  });

  beforeEach(() => {
    jest.useFakeTimers();
    app.forum.pushAttributes({ apiUrl: 'https://forum.test/api', scavengerHunts: [hunt] });
    encounters.pageChanged('index', { page: ++page });
  });

  afterEach(() => {
    app.drawer.hide();
    jest.useRealTimers();
  });

  function mockRequest() {
    return jest.spyOn(app, 'request').mockResolvedValue({ data: [], included: [] } as never);
  }

  function sentSpots(request: ReturnType<typeof mockRequest>): unknown[] {
    return request.mock.calls.flatMap((call) => (call[0] as { body: { data: { attributes: { spots: unknown[] } } } }).body.data.attributes.spots);
  }

  function inDrawer(spot: string) {
    return mq({
      view: () => (
        <div id="drawer" className="App-drawer">
          <HidingSpot spot={spot} />
        </div>
      ),
    });
  }

  it('is not reported while the drawer is closed on a phone', async () => {
    jest.spyOn(app, 'screen').mockReturnValue('phone');
    const request = mockRequest();

    inDrawer('page.header.closed');
    await jest.advanceTimersByTimeAsync(1000);

    expect(sentSpots(request)).not.toContainEqual({ spot: 'page.header.closed' });
  });

  it('is reported once the drawer opens', async () => {
    jest.spyOn(app, 'screen').mockReturnValue('phone');
    const request = mockRequest();

    inDrawer('page.header.opened');
    await jest.advanceTimersByTimeAsync(1000);
    expect(sentSpots(request)).not.toContainEqual({ spot: 'page.header.opened' });
    request.mockClear();

    app.drawer.show();
    await jest.advanceTimersByTimeAsync(EncounterState.DEBOUNCE_MS);

    expect(request).toHaveBeenCalledTimes(1);
    expect(sentSpots(request)).toContainEqual({ spot: 'page.header.opened' });
  });

  it('is reported straight away on wider screens, where the drawer is the header', async () => {
    jest.spyOn(app, 'screen').mockReturnValue('desktop');
    const request = mockRequest();

    inDrawer('page.header.desktop');
    await jest.advanceTimersByTimeAsync(EncounterState.DEBOUNCE_MS);

    expect(sentSpots(request)).toContainEqual({ spot: 'page.header.desktop' });
  });
});
