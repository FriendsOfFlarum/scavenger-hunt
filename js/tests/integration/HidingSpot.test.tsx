import bootstrapForum from '@flarum/jest-config/src/bootstrap/forum';
import app from 'flarum/forum/app';
import mq from 'mithril-query';
import { jest } from '@jest/globals';
import HidingSpot from '../../src/forum/components/HidingSpot';
import { encounters } from '../../src/forum/states/EncounterState';
import Encounter from '../../src/common/models/Encounter';
import Part from '../../src/common/models/Part';

beforeAll(() => {
  bootstrapForum();
  app.boot();
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
