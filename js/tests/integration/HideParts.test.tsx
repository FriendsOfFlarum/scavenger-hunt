import bootstrapForum from '@flarum/jest-config/src/bootstrap/forum';
import app from 'flarum/forum/app';
import mq from 'mithril-query';
import { jest } from '@jest/globals';
import forumExtend from '../../src/forum/extend';
import HidePartsModal from '../../src/forum/dashboard/HidePartsModal';
import HuntDashboardPage from '../../src/forum/dashboard/HuntDashboardPage';
import TemplateGalleryModal from '../../src/forum/dashboard/TemplateGalleryModal';
import type HidingSpotDefinition from '../../src/common/models/HidingSpotDefinition';
import type Hunt from '../../src/common/models/Hunt';
import type Part from '../../src/common/models/Part';

beforeAll(() => {
  bootstrapForum();
  app.bootExtensions({ 'fof-scavenger-hunt': { extend: forumExtend } });
  app.boot();
});

afterEach(() => {
  jest.restoreAllMocks();
  app.forum.pushAttributes({ scavengerHuntCanManage: false });
});

type Tree = ReturnType<typeof mq>;

interface PlaceFixture {
  spot: string;
  route: string | null;
  contextType: string | null;
  contextId: number | null;
  label: string;
  url: string;
  fallback: boolean;
}

interface Body {
  parts: unknown[];
  exclude?: { spot: string; contextType: string | null; contextId: number | null }[];
}

let nextId = 100;

function place(contextId: number, overrides: Partial<PlaceFixture> = {}): PlaceFixture {
  return {
    spot: 'post.footer',
    route: null,
    contextType: 'post',
    contextId,
    label: `Post footer · post ${contextId}`,
    url: `https://example.com/d/1/${contextId}`,
    fallback: false,
    ...overrides,
  };
}

const ref = (p: { spot: string; contextType: string | null; contextId: number | null }) => ({
  spot: p.spot,
  contextType: p.contextType,
  contextId: p.contextId,
});

function spots(): HidingSpotDefinition[] {
  return app.store.pushPayload<HidingSpotDefinition[]>({
    data: [
      { type: 'scavenger-hunt-spots', id: 'post.footer', attributes: { label: 'Post footer', routes: ['discussion'], contextType: 'post' } },
      { type: 'scavenger-hunt-spots', id: 'page.footer', attributes: { label: 'Page footer', routes: null, contextType: null } },
    ],
  }) as unknown as HidingSpotDefinition[];
}

interface PartFixture {
  name: string;
  mode: string;
  locations?: { spot: string; contextType: string | null; contextId: number | null }[];
}

function setup(fixtures: PartFixture[]) {
  const huntId = String(++nextId);
  const included: object[] = [];
  const partRefs = fixtures.map((fixture, index) => {
    const id = String(++nextId);
    const locationRefs = (fixture.locations ?? []).map((location) => {
      const locationId = String(++nextId);
      included.push({ type: 'scavenger-hunt-locations', id: locationId, attributes: { route: null, isAvailable: true, ...location } });

      return { type: 'scavenger-hunt-locations', id: locationId };
    });

    included.push({
      type: 'scavenger-hunt-parts',
      id,
      attributes: { name: fixture.name, mode: fixture.mode, sort: index, imageUrl: `https://example.com/${fixture.name}.svg` },
      relationships: { locations: { data: locationRefs } },
    });

    return { type: 'scavenger-hunt-parts', id };
  });

  const hunt = app.store.pushPayload<Hunt>({
    data: { type: 'scavenger-hunts', id: huntId, attributes: { title: 'Snowman' }, relationships: { parts: { data: partRefs } } },
    included,
  });
  const parts = partRefs.map((partRef) => app.store.getById<Part>('scavenger-hunt-parts', partRef.id)!);

  return { hunt, parts };
}

type Handler = (body: Body) => unknown;

// Each endpoint answers from its queue in turn; the last answer repeats.
function mockApi({ suggestions = [], places }: { suggestions?: Handler[]; places?: Handler }) {
  let call = 0;

  return jest.spyOn(app, 'request').mockImplementation(((options: { url: string; body: Body }) => {
    if (options.url.endsWith('/hiding-suggestions')) {
      const answer = suggestions[Math.min(call++, suggestions.length - 1)](options.body);

      return answer === 'error' ? Promise.reject(new Error('500')) : Promise.resolve(answer);
    }

    if (options.url.endsWith('/hiding-places') && places) {
      const answer = places(options.body);

      return answer === 'error' ? Promise.reject(Object.assign(new Error('422'), { alert: { type: 'error', content: 'Invalid' } })) : Promise.resolve(answer);
    }

    return Promise.reject(new Error(`Unexpected request to ${options.url}`));
  }) as never);
}

function calls(request: ReturnType<typeof mockApi>, endpoint: string): Body[] {
  return request.mock.calls
    .map(([options]) => options as unknown as { url: string; body: Body })
    .filter((options) => options.url.endsWith(endpoint))
    .map((options) => options.body);
}

async function settle(tree: { redraw: () => void }) {
  await new Promise((resolve) => setTimeout(resolve, 0));
  tree.redraw();
}

async function render(parts: Part[], hunt: Hunt, extra: Record<string, unknown> = {}) {
  const animateHide = jest.fn();
  const onsaved = jest.fn();
  const modal = mq(HidePartsModal, { state: app.modal, animateShow: () => {}, animateHide, hunt, parts, spots: spots(), routes: [], onsaved, ...extra });

  await settle(modal);

  return { modal, animateHide, onsaved };
}

const row = (part: Part) => `.HidePartsModal-row[data-part-id="${part.id()}"]`;

function labels(modal: Tree, part: Part): string[] {
  return modal.find(`${row(part)} .HidePartsModal-placeLabel`).map((el) => el.textContent ?? '');
}

describe('HidePartsModal', () => {
  it('opens with proposals for Parts without places and keeps Parts with places', async () => {
    const { hunt, parts } = setup([
      { name: 'Hat', mode: 'fixed' },
      { name: 'Nose', mode: 'assigned' },
      { name: 'Scarf', mode: 'wandering', locations: [{ spot: 'post.footer', contextType: 'post', contextId: 7 }] },
    ]);
    const [hat, nose, scarf] = parts;
    const request = mockApi({
      suggestions: [() => ({ data: [{ partId: hat.id(), places: [place(1)] }, { partId: nose.id(), places: [place(2), place(3), place(4)] }] })],
    });

    const { modal } = await render(parts, hunt);

    expect(calls(request, '/hiding-suggestions')).toEqual([{ parts: [hat.id(), nose.id()], exclude: [{ spot: 'post.footer', contextType: 'post', contextId: 7 }] }]);
    expect(labels(modal, hat)).toEqual(['Post footer · post 1']);
    expect(labels(modal, nose)).toEqual(['Post footer · post 2', 'Post footer · post 3', 'Post footer · post 4']);
    expect(modal.first(`${row(nose)} .HidePartsModal-name`).textContent).toBe('Nose');
    expect(modal.first(`${row(nose)} .HidePartsModal-mode`).textContent).toBe('fof-scavenger-hunt.forum.dashboard.parts.modes.assigned');
    expect(modal).toHaveElementAttr(`${row(nose)} .HidePartsModal-image`, 'src', 'https://example.com/Nose.svg');

    expect(modal).toHaveElement(`${row(scarf)} .HidePartsModal-keep`);
    expect(modal).toHaveElement(`${row(scarf)} .HidePartsModal-pickForMe`);
    expect(modal).not.toHaveElement(`${row(scarf)} .HidePartsModal-place`);
    expect(modal).not.toHaveElement(`${row(hat)} .HidePartsModal-keep`);
  });

  it('links each place to its page in a new tab', async () => {
    const { hunt, parts } = setup([{ name: 'Hat', mode: 'fixed' }]);
    mockApi({ suggestions: [() => ({ data: [{ partId: parts[0].id(), places: [place(1)] }] })] });

    const { modal } = await render(parts, hunt);
    const open = modal.first('.HidePartsModal-open');

    expect(open.getAttribute('href')).toBe('https://example.com/d/1/1');
    expect(open.getAttribute('target')).toBe('_blank');
    expect(open.getAttribute('rel')).toBe('noopener');
  });

  it('shows a loading indicator, then an error with Retry', async () => {
    const { hunt, parts } = setup([{ name: 'Hat', mode: 'fixed' }]);
    const request = mockApi({ suggestions: [() => 'error', () => ({ data: [{ partId: parts[0].id(), places: [place(1)] }] })] });

    const animateHide = jest.fn();
    const modal = mq(HidePartsModal, { state: app.modal, animateShow: () => {}, animateHide, hunt, parts, spots: spots(), routes: [], onsaved: () => {} });

    expect(modal).toHaveElement(`${row(parts[0])} .LoadingIndicator`);

    await settle(modal);

    expect(modal).toHaveElement('.HidePartsModal-error');
    expect(modal).not.toHaveElement('.HidePartsModal-place');
    expect(modal).not.toHaveElement('.HidePartsModal-nothing');
    expect(modal).toHaveElement('.HidePartsModal-submit[disabled]');

    modal.click('.HidePartsModal-retry');
    await settle(modal);

    expect(calls(request, '/hiding-suggestions')).toHaveLength(2);
    expect(modal).not.toHaveElement('.HidePartsModal-error');
    expect(labels(modal, parts[0])).toEqual(['Post footer · post 1']);
  });

  it('Pick another replaces only that place and excludes every other place', async () => {
    const { hunt, parts } = setup([
      { name: 'Hat', mode: 'fixed' },
      { name: 'Nose', mode: 'assigned' },
      { name: 'Scarf', mode: 'fixed', locations: [{ spot: 'post.footer', contextType: 'post', contextId: 7 }] },
    ]);
    const [hat, nose] = parts;
    const request = mockApi({
      suggestions: [
        () => ({ data: [{ partId: hat.id(), places: [place(1)] }, { partId: nose.id(), places: [place(2), place(3), place(4)] }] }),
        () => ({ data: [{ partId: nose.id(), places: [place(9), place(10), place(11)] }] }),
      ],
    });

    const { modal } = await render(parts, hunt);

    modal.click(`${row(nose)} .HidePartsModal-place:nth-child(2) .HidePartsModal-pickAnother`);
    await settle(modal);

    const reroll = calls(request, '/hiding-suggestions')[1];

    expect(reroll.parts).toEqual([nose.id()]);
    expect(reroll.exclude).toHaveLength(5);
    expect(reroll.exclude).toEqual(
      expect.arrayContaining([ref(place(1)), ref(place(2)), ref(place(3)), ref(place(4)), { spot: 'post.footer', contextType: 'post', contextId: 7 }])
    );
    expect(labels(modal, nose)).toEqual(['Post footer · post 2', 'Post footer · post 9', 'Post footer · post 4']);
    expect(labels(modal, hat)).toEqual(['Post footer · post 1']);
  });

  it('Pick for me fetches places for a kept Part and switches it to proposed', async () => {
    const { hunt, parts } = setup([
      { name: 'Hat', mode: 'fixed' },
      { name: 'Scarf', mode: 'fixed', locations: [{ spot: 'post.footer', contextType: 'post', contextId: 7 }] },
    ]);
    const [hat, scarf] = parts;
    const request = mockApi({
      suggestions: [() => ({ data: [{ partId: hat.id(), places: [place(1)] }] }), () => ({ data: [{ partId: scarf.id(), places: [place(5)] }] })],
    });

    const { modal } = await render(parts, hunt);

    modal.click(`${row(scarf)} .HidePartsModal-pickForMe`);
    await settle(modal);

    expect(calls(request, '/hiding-suggestions')[1]).toEqual({ parts: [scarf.id()], exclude: [ref(place(1))] });
    expect(modal).not.toHaveElement(`${row(scarf)} .HidePartsModal-keep`);
    expect(labels(modal, scarf)).toEqual(['Post footer · post 5']);
  });

  it('Choose myself edits a local place', async () => {
    const { hunt, parts } = setup([{ name: 'Hat', mode: 'fixed' }]);
    const request = mockApi({
      suggestions: [() => ({ data: [{ partId: parts[0].id(), places: [place(1)] }] })],
      places: () => ({ data: { type: 'scavenger-hunts', id: hunt.id(), attributes: {} } }),
    });

    const { modal } = await render(parts, hunt);

    modal.click('.HidePartsModal-chooseMyself');

    expect(modal).toHaveElement('.HidePartsModal-fields select');
    expect(modal).not.toHaveElement('.HidePartsModal-open');
    expect(modal).not.toHaveElement('.HidePartsModal-chooseMyself');
    expect((modal.first('.HidePartsModal-fields input') as HTMLInputElement).value).toBe('1');

    modal.setValue('.HidePartsModal-fields input', '42');
    modal.click('.HidePartsModal-submit');
    await settle(modal);

    expect(calls(request, '/hiding-places')).toEqual([
      { parts: [{ id: parts[0].id(), locations: [{ spot: 'post.footer', route: null, contextType: 'post', contextId: 42 }] }] },
    ]);
  });

  it('Choose myself drops the item for a spot that takes none', async () => {
    const { hunt, parts } = setup([{ name: 'Hat', mode: 'fixed' }]);
    const request = mockApi({
      suggestions: [() => ({ data: [{ partId: parts[0].id(), places: [place(1)] }] })],
      places: () => ({ data: { type: 'scavenger-hunts', id: hunt.id(), attributes: {} } }),
    });

    const { modal } = await render(parts, hunt);

    modal.click('.HidePartsModal-chooseMyself');

    const select = modal.first('.HidePartsModal-fields select') as HTMLSelectElement;
    select.removeAttribute('value');
    select.value = 'page.footer';
    modal.trigger('.HidePartsModal-fields select', 'change', {} as Event);

    expect(modal).not.toHaveElement('.HidePartsModal-fields input');

    modal.click('.HidePartsModal-submit');
    await settle(modal);

    expect(calls(request, '/hiding-places')[0].parts).toEqual([{ id: parts[0].id(), locations: [{ spot: 'page.footer', route: null, contextType: null, contextId: null }] }]);
  });

  it('Use these places sends only proposed Parts, updates the store and closes', async () => {
    const { hunt, parts } = setup([
      { name: 'Hat', mode: 'fixed' },
      { name: 'Nose', mode: 'assigned' },
      { name: 'Scarf', mode: 'fixed', locations: [{ spot: 'post.footer', contextType: 'post', contextId: 7 }] },
    ]);
    const [hat, nose, scarf] = parts;
    const saved = {
      data: {
        type: 'scavenger-hunts',
        id: hunt.id(),
        attributes: { title: 'Snowman' },
        relationships: { parts: { data: parts.map((part) => ({ type: 'scavenger-hunt-parts', id: part.id() })) } },
      },
      included: [
        { type: 'scavenger-hunt-locations', id: '9001', attributes: { spot: 'post.footer', route: null, contextType: 'post', contextId: 1, isAvailable: true } },
        { type: 'scavenger-hunt-parts', id: hat.id(), attributes: { name: 'Hat' }, relationships: { locations: { data: [{ type: 'scavenger-hunt-locations', id: '9001' }] } } },
      ],
    };
    const request = mockApi({
      suggestions: [() => ({ data: [{ partId: hat.id(), places: [place(1)] }, { partId: nose.id(), places: [place(2), place(3, { route: 'index', fallback: true })] }] })],
      places: () => saved,
    });
    const alerts = jest.spyOn(app.alerts, 'show');

    const { modal, animateHide, onsaved } = await render(parts, hunt);

    modal.click('.HidePartsModal-submit');
    await settle(modal);

    expect(calls(request, '/hiding-places')).toEqual([
      {
        parts: [
          { id: hat.id(), locations: [{ spot: 'post.footer', route: null, contextType: 'post', contextId: 1 }] },
          {
            id: nose.id(),
            locations: [
              { spot: 'post.footer', route: null, contextType: 'post', contextId: 2 },
              { spot: 'post.footer', route: 'index', contextType: 'post', contextId: 3 },
            ],
          },
        ],
      },
    ]);
    expect(hat.locations() && (hat.locations() as { id: () => string }[]).map((location) => location.id())).toEqual(['9001']);
    expect(onsaved).toHaveBeenCalledWith([hat.id(), nose.id()]);
    expect(onsaved.mock.calls[0][0]).not.toContain(scarf.id());
    expect(animateHide).toHaveBeenCalled();
    expect(alerts).toHaveBeenCalledWith({ type: 'success' }, 'fof-scavenger-hunt.forum.dashboard.saved');
  });

  it('stays open with the error when saving fails', async () => {
    const { hunt, parts } = setup([{ name: 'Hat', mode: 'fixed' }]);
    mockApi({ suggestions: [() => ({ data: [{ partId: parts[0].id(), places: [place(1)] }] })], places: () => 'error' });

    const { modal, animateHide, onsaved } = await render(parts, hunt);

    modal.click('.HidePartsModal-submit');
    await settle(modal);

    expect(animateHide).not.toHaveBeenCalled();
    expect(onsaved).not.toHaveBeenCalled();
    expect(modal).toHaveElement('.HidePartsModal-submit:not([disabled])');
  });

  it('notes general places when any place is a fallback', async () => {
    const { hunt, parts } = setup([{ name: 'Hat', mode: 'fixed' }]);
    mockApi({ suggestions: [() => ({ data: [{ partId: parts[0].id(), places: [place(1, { spot: 'page.footer', route: 'index', contextType: null, contextId: null, fallback: true })] }] })] });

    const { modal } = await render(parts, hunt);

    expect(modal).toHaveElement('.HidePartsModal-fallback');
  });

  it('has no fallback note for specific places', async () => {
    const { hunt, parts } = setup([{ name: 'Hat', mode: 'fixed' }]);
    mockApi({ suggestions: [() => ({ data: [{ partId: parts[0].id(), places: [place(1)] }] })] });

    const { modal } = await render(parts, hunt);

    expect(modal).not.toHaveElement('.HidePartsModal-fallback');
  });

  it('has nothing to hide when every Part is kept', async () => {
    const { hunt, parts } = setup([{ name: 'Scarf', mode: 'fixed', locations: [{ spot: 'post.footer', contextType: 'post', contextId: 7 }] }]);
    const request = mockApi({});

    const { modal } = await render(parts, hunt);

    expect(request).not.toHaveBeenCalled();
    expect(modal).toHaveElement('.HidePartsModal-nothing');
    expect(modal).toHaveElement('.HidePartsModal-submit[disabled]');
  });

  it('Cancel closes without saving', async () => {
    const { hunt, parts } = setup([{ name: 'Hat', mode: 'fixed' }]);
    const request = mockApi({ suggestions: [() => ({ data: [{ partId: parts[0].id(), places: [place(1)] }] })] });

    const { modal, animateHide } = await render(parts, hunt);

    modal.click('.HidePartsModal-cancel');

    expect(animateHide).toHaveBeenCalled();
    expect(calls(request, '/hiding-places')).toHaveLength(0);
  });
});

function routeParams(params: Record<string, string>) {
  return jest.spyOn(m.route, 'param').mockImplementation(((key?: string) => (key === undefined ? params : params[key])) as never);
}

function loadHunt() {
  const { hunt, parts } = setup([{ name: 'Hat', mode: 'fixed' }]);
  const spotList = spots();
  (spotList as unknown as { payload: object }).payload = { meta: { routes: [{ name: 'index', path: '/' }] } };

  jest.spyOn(app.store, 'find').mockImplementation(((type: string) => Promise.resolve(type === 'scavenger-hunts' ? hunt : spotList)) as never);

  return { hunt, parts };
}

describe('Hide the parts for me entry points', () => {
  it('the Parts & locations tab opens the review', async () => {
    app.forum.pushAttributes({ scavengerHuntCanManage: true });
    routeParams({ id: '1', tab: 'parts' });
    const { hunt, parts } = loadHunt();
    const show = jest.spyOn(app.modal, 'show').mockResolvedValue(undefined as never);

    const page = mq({ view: () => <HuntDashboardPage routeName="fof-scavenger-hunt.dashboard.hunt" /> });
    await settle(page);

    expect(show).not.toHaveBeenCalled();

    page.click('.ScavengerDashboard-hideParts');

    expect(show).toHaveBeenCalledTimes(1);
    expect(typeof show.mock.calls[0][0]).toBe('function');
    expect(show.mock.calls[0][1]).toMatchObject({ hunt, parts, routes: [{ name: 'index', path: '/' }] });
  });

  it('the dashboard opens the review once when asked to, and drops the flag from the URL', async () => {
    app.forum.pushAttributes({ scavengerHuntCanManage: true });
    routeParams({ id: '1', tab: 'parts', hide: '1' });
    const { hunt } = loadHunt();
    const show = jest.spyOn(app.modal, 'show').mockResolvedValue(undefined as never);
    const replaceState = jest.spyOn(window.history, 'replaceState').mockImplementation(() => {});

    const page = mq({ view: () => <HuntDashboardPage routeName="fof-scavenger-hunt.dashboard.hunt" /> });
    await settle(page);
    page.redraw();

    expect(show).toHaveBeenCalledTimes(1);
    expect(show.mock.calls[0][1]).toMatchObject({ hunt });
    expect(replaceState).toHaveBeenCalledTimes(1);
    expect(String(replaceState.mock.calls[0][2])).not.toContain('hide');
    expect(String(replaceState.mock.calls[0][2])).toContain('tab=parts');
  });

  it('keeps the flag out of the URL when the tab changes afterwards', async () => {
    app.forum.pushAttributes({ scavengerHuntCanManage: true });
    routeParams({ id: '1', tab: 'parts', hide: '1' });
    loadHunt();
    jest.spyOn(app.modal, 'show').mockResolvedValue(undefined as never);
    const replaceState = jest.spyOn(window.history, 'replaceState').mockImplementation(() => {});

    const page = mq({ view: () => <HuntDashboardPage routeName="fof-scavenger-hunt.dashboard.hunt" /> });
    await settle(page);

    page.click('.ScavengerDashboard-nav .Dropdown-menu .item-overview button');

    expect(String(replaceState.mock.calls[1][2])).toContain('tab=overview');
    expect(String(replaceState.mock.calls[1][2])).not.toContain('hide');
  });

  it('shows the saved places in the Part editors once the review saves', async () => {
    app.forum.pushAttributes({ scavengerHuntCanManage: true });
    routeParams({ id: '1', tab: 'parts' });
    const { parts } = loadHunt();
    const show = jest.spyOn(app.modal, 'show').mockResolvedValue(undefined as never);

    const page = mq({ view: () => <HuntDashboardPage routeName="fof-scavenger-hunt.dashboard.hunt" /> });
    await settle(page);

    expect(page).not.toHaveElement('.PartEditor .LocationRow');

    page.click('.ScavengerDashboard-hideParts');
    app.store.pushPayload({
      data: {
        type: 'scavenger-hunt-parts',
        id: parts[0].id(),
        attributes: {},
        relationships: { locations: { data: [{ type: 'scavenger-hunt-locations', id: '9100' }] } },
      },
      included: [{ type: 'scavenger-hunt-locations', id: '9100', attributes: { spot: 'post.footer', route: null, contextType: 'post', contextId: 3, isAvailable: true } }],
    });
    (show.mock.calls[0][1] as { onsaved: (ids: string[]) => void }).onsaved([parts[0].id()!]);
    page.redraw();

    expect(page.find('.PartEditor .LocationRow')).toHaveLength(1);
  });

  const galleryHide = jest.fn();

  function gallery() {
    galleryHide.mockClear();

    jest.spyOn(app, 'request').mockImplementation(((options: { method?: string; url: string }) =>
      Promise.resolve(
        options.method === 'POST'
          ? { data: { type: 'scavenger-hunts', id: '77', attributes: { title: 'Snowman' } } }
          : {
              data: [
                {
                  type: 'scavenger-hunt-templates',
                  id: 'winter',
                  attributes: { title: 'Build a Snowman', occasion: 'Winter', imageUrl: null, imageReference: null, completedImageReference: null, parts: [] },
                },
              ],
            }
      )) as never);

    return mq(TemplateGalleryModal, { state: app.modal, animateShow: () => {}, animateHide: galleryHide });
  }

  async function create(tree: Tree) {
    tree.trigger('form', 'submit', {} as Event);
    await settle(tree);
  }

  it('the gallery checkbox opens the review after creating the Hunt', async () => {
    const tree = gallery();
    await settle(tree);
    const routeSet = jest.spyOn(m.route, 'set').mockImplementation(() => {});

    expect(tree).not.toHaveElement('.TemplateGallery-hideParts input');

    tree.click('.TemplateGallery-item:nth-child(2)');

    const checkbox = tree.first('.TemplateGallery-hideParts input') as HTMLInputElement;
    checkbox.checked = true;
    tree.trigger('.TemplateGallery-hideParts input', 'change', {} as Event);

    await create(tree);

    expect(routeSet).toHaveBeenCalledTimes(1);
    expect(String(routeSet.mock.calls[0][0])).toMatch(/\/scavenger-hunt\/dashboard\/77\?.*tab=parts/);
    expect(String(routeSet.mock.calls[0][0])).toContain('hide=1');
    // Animating the gallery out while the route changes would leave core unable to close the review.
    expect(galleryHide).not.toHaveBeenCalled();
  });

  it('the gallery leaves the review closed when the checkbox is not ticked', async () => {
    const tree = gallery();
    await settle(tree);
    const routeSet = jest.spyOn(m.route, 'set').mockImplementation(() => {});

    tree.click('.TemplateGallery-item:nth-child(2)');
    await create(tree);

    expect(String(routeSet.mock.calls[0][0])).not.toContain('hide');
  });
});
