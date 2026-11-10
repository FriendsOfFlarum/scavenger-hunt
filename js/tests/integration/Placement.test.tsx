import bootstrapForum from '@flarum/jest-config/src/bootstrap/forum';
import app from 'flarum/forum/app';
import mq from 'mithril-query';
import HidingSpot from '../../src/forum/components/HidingSpot';
import { placement } from '../../src/forum/states/placement';
import locationAttributes from '../../src/forum/placement/locationAttributes';
import PlacementToolbar from '../../src/forum/placement/PlacementToolbar';
import type { PlacementController } from '../../src/forum/placement/PlacementController';

beforeAll(() => {
  bootstrapForum();
  app.boot();
});

afterEach(() => {
  placement.renderer = null;
});

describe('"Hide it here" mode', () => {
  it('turns every Hiding spot into a target while it is on', () => {
    placement.renderer = (attrs, report) => <button className="PlacementTarget">{`${report.spot}:${report.contextId ?? ''}`}</button>;

    const spot = mq(HidingSpot, { spot: 'post.footer', context: ['post', 12] });

    expect(spot).toHaveElement('button.PlacementTarget');
    expect(spot).toContainRaw('post.footer:12');
  });

  it('draws nothing in a spot once it is off', () => {
    expect(mq(HidingSpot, { spot: 'page.corner' })).not.toHaveElement('button.PlacementTarget');
  });
});

describe('locationAttributes', () => {
  const post = { spot: 'post.footer', contextType: 'post', contextId: 12 };

  it('hides a Part on exactly this item on this page', () => {
    expect(locationAttributes(post, { exactItem: true, thisPage: true }, 'discussion')).toEqual({
      spot: 'post.footer',
      route: 'discussion',
      contextType: 'post',
      contextId: 12,
    });
  });

  it('can widen to any item and any page', () => {
    expect(locationAttributes(post, { exactItem: false, thisPage: false }, 'discussion')).toEqual({
      spot: 'post.footer',
      route: null,
      contextType: 'post',
      contextId: null,
    });
  });

  it('falls back to any page when the current page is not one the forum offers', () => {
    expect(locationAttributes({ spot: 'page.corner' }, { exactItem: true, thisPage: true }, null)).toEqual({
      spot: 'page.corner',
      route: null,
      contextType: null,
      contextId: null,
    });
  });
});

describe('PlacementToolbar', () => {
  const controller = { hunts: [], parts: () => [], huntId: null, partId: null, select: () => {}, stop: () => {} } as unknown as PlacementController;

  it('groups the pickers and the actions so phones can lay each out on one row', () => {
    const toolbar = mq(PlacementToolbar, { controller });

    expect(toolbar).toHaveElement('.PlacementToolbar-selects > .Select + .Select');
    expect(toolbar).toHaveElement('.PlacementToolbar-actions > .Button--primary + .Button--icon');
  });

  it('keeps the help text as the region description when phones hide it', () => {
    const toolbar = mq(PlacementToolbar, { controller });

    expect(toolbar).toHaveElementAttr('.PlacementToolbar', 'aria-describedby', 'PlacementToolbar-help');
    expect(toolbar).toHaveElement('#PlacementToolbar-help.PlacementToolbar-help');
  });
});
