import bootstrapForum from '@flarum/jest-config/src/bootstrap/forum';
import app from 'flarum/forum/app';
import SearchModal from 'flarum/common/components/SearchModal';
import HidingSpot from '../../src/forum/components/HidingSpot';

beforeAll(async () => {
  bootstrapForum();
  await import('../../src/forum/index');
  app.initializers.get('fof-scavenger-hunt')(app);

  // As in the browser: the search window's chunk loads after the initializers have run.
  flarum.reg.add('core', 'common/components/SearchModal', SearchModal);
});

/** Just enough of an open search window for its results list to be built. */
function openSearchWindow() {
  const modal = Object.create(SearchModal.prototype);

  Object.assign(modal, {
    loadingSources: [],
    query: () => '',
    gambits: () => [],
    activeSource: () => ({ resource: 'discussions', fullPage: () => null, view: () => [], customGrouping: () => false }),
  });

  return modal;
}

describe('the search window', () => {
  it('holds a hiding spot below its results', () => {
    const items = openSearchWindow().activeTabItems();

    expect(items.has('scavengerHuntSpot')).toBe(true);

    const spot = items.get('scavengerHuntSpot');
    expect(spot.tag).toBe(HidingSpot);
    expect(spot.attrs.spot).toBe('search.modal');

    const keys = items.toArray().map((item: any) => item.itemName);
    expect(keys[keys.length - 1]).toBe('scavengerHuntSpot');
  });
});
