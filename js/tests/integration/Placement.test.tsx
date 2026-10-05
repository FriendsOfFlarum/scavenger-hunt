import bootstrapForum from '@flarum/jest-config/src/bootstrap/forum';
import app from 'flarum/forum/app';
import mq from 'mithril-query';
import HidingSpot from '../../src/forum/components/HidingSpot';
import { placement } from '../../src/forum/states/placement';
import locationAttributes from '../../src/forum/placement/locationAttributes';

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
