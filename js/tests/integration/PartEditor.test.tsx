import bootstrapForum from '@flarum/jest-config/src/bootstrap/forum';
import app from 'flarum/forum/app';
import mq from 'mithril-query';
import { jest } from '@jest/globals';
import PartEditor from '../../src/forum/dashboard/PartEditor';
import Part from '../../src/common/models/Part';

beforeAll(() => {
  bootstrapForum();
  app.boot();
});

function editorFor(mode: string) {
  const part = new Part({ type: 'scavenger-hunt-parts', id: '1', attributes: { name: 'Hat', mode, sort: 0, imageUrl: null, layer: null } });
  jest.spyOn(part, 'locations').mockReturnValue([]);

  return mq(PartEditor, { part, spots: [], routes: [], ondeleted: () => {} });
}

describe('PartEditor', () => {
  it('offers a Hint for Fixed Parts', () => {
    expect(editorFor('fixed')).toHaveElement('.PartEditor-hint');
  });

  it('never offers a Hint for Random Parts', () => {
    expect(editorFor('assigned')).not.toHaveElement('.PartEditor-hint');
    expect(editorFor('wandering')).not.toHaveElement('.PartEditor-hint');
  });
});
