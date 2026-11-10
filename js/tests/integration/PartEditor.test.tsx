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
  // The inputs are bound with bidi, which the test renderer leaves unpatched, so drive the streams.
  function editorWithFields() {
    const oninit = jest.spyOn(PartEditor.prototype, 'oninit');
    const editor = editorFor('fixed');
    const fields = oninit.mock.contexts[0] as unknown as Record<string, any>;
    oninit.mockRestore();

    return { editor, fields };
  }

  it('shows Save as primary only once something has changed', () => {
    const { editor, fields } = editorWithFields();

    expect(editor).toHaveElement('.PartEditor-save');
    expect(editor).not.toHaveElement('.PartEditor-save.Button--primary');

    fields.name('Top hat');
    editor.redraw();

    expect(editor).toHaveElement('.PartEditor-save.Button--primary');

    fields.name('Hat');
    editor.redraw();

    expect(editor).not.toHaveElement('.PartEditor-save.Button--primary');
  });

  it.each([
    ['mode', (fields: Record<string, any>) => fields.mode('assigned')],
    ['hint', (fields: Record<string, any>) => fields.hint('Look up')],
    ['sort', (fields: Record<string, any>) => fields.sort('3')],
    ['image', (fields: Record<string, any>) => fields.image('asset:1')],
    ['layer', (fields: Record<string, any>) => ['x', 'y', 'width', 'z'].forEach((key) => fields.layer[key]('10'))],
  ])('counts a changed %s', (_name, change) => {
    const { editor, fields } = editorWithFields();

    change(fields);
    editor.redraw();

    expect(editor).toHaveElement('.PartEditor-save.Button--primary');
  });

  it('counts a changed wander chance', () => {
    const part = new Part({
      type: 'scavenger-hunt-parts',
      id: '1',
      attributes: { name: 'Hat', mode: 'wandering', wanderChance: 20, sort: 0, layer: null },
    });
    jest.spyOn(part, 'locations').mockReturnValue([]);
    const oninit = jest.spyOn(PartEditor.prototype, 'oninit');
    const editor = mq(PartEditor, { part, spots: [], routes: [], ondeleted: () => {} });
    const fields = oninit.mock.contexts[0] as unknown as Record<string, any>;
    oninit.mockRestore();

    expect(editor).not.toHaveElement('.PartEditor-save.Button--primary');

    fields.wanderChance('30');
    editor.redraw();

    expect(editor).toHaveElement('.PartEditor-save.Button--primary');
  });

  it('keeps a plain Save clickable', () => {
    const { editor } = editorWithFields();
    const save = jest.spyOn(PartEditor.prototype as any, 'save').mockImplementation(() => {});

    editor.click('.PartEditor-save');

    expect(save).toHaveBeenCalled();
    save.mockRestore();
  });

  it('offers a Hint for Fixed Parts', () => {
    expect(editorFor('fixed')).toHaveElement('.PartEditor-hint');
  });

  it('never offers a Hint for Random Parts', () => {
    expect(editorFor('assigned')).not.toHaveElement('.PartEditor-hint');
    expect(editorFor('wandering')).not.toHaveElement('.PartEditor-hint');
  });

  it("names each Part's image group after the Part", () => {
    const editor = editorFor('fixed');
    const ids = editor.first('.PartEditor-imageField .ImageField').getAttribute('aria-labelledby')!.split(' ');
    const names = ids.map((id: string) => editor.first(`#${id}`).textContent);

    expect(names).toEqual(['Hat', 'fof-scavenger-hunt.forum.dashboard.parts.image']);
  });
});
