import bootstrapForum from '@flarum/jest-config/src/bootstrap/forum';
import app from 'flarum/forum/app';
import mq from 'mithril-query';
import { jest } from '@jest/globals';
import CollectionView from '../../src/forum/components/CollectionView';
import Hunt from '../../src/common/models/Hunt';
import Part from '../../src/common/models/Part';

beforeAll(() => {
  bootstrapForum();
  app.boot();
});

function part(id: number, attributes: Record<string, unknown>): Part {
  return new Part({
    type: 'scavenger-hunt-parts',
    id: String(id),
    attributes: { name: `Part ${id}`, sort: id, imageUrl: `https://forum.test/${id}.svg`, layer: { x: 10, y: 10, width: 20, z: id }, isRandom: false, ...attributes },
  });
}

describe('CollectionView', () => {
  it('draws found Parts on the canvas and silhouettes for the rest, with the right hint', () => {
    const hunt = new Hunt({ type: 'scavenger-hunts', id: '1', attributes: { title: 'Snowman', imageUrl: null } });
    jest.spyOn(hunt, 'parts').mockReturnValue([
      part(1, { isFound: true }),
      part(2, { isFound: false, hint: 'Near the snow talk' }),
      part(3, { isFound: false, isRandom: true }),
    ]);

    const view = mq(CollectionView, { hunt });

    expect(view).toHaveElement('img.CollectionView-layer[alt="Part 1"]');
    expect(view.find('.CollectionView-layer--silhouette')).toHaveLength(2);
    expect(view).toContainRaw('Near the snow talk');
    expect(view).toContainRaw('fof-scavenger-hunt.forum.collection.random_part');
    expect(view.find('.CollectionView-part--found')).toHaveLength(1);
  });
});
