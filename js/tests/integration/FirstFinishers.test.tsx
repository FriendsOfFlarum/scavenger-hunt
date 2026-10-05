import bootstrapForum from '@flarum/jest-config/src/bootstrap/forum';
import app from 'flarum/forum/app';
import mq from 'mithril-query';
import FirstFinishers from '../../src/forum/components/FirstFinishers';
import Completion from '../../src/common/models/Completion';

beforeAll(() => {
  bootstrapForum();
  app.boot();
});

function completion(id: number, position: number): Completion {
  return new Completion({ type: 'scavenger-hunt-completions', id: String(id), attributes: { position, completedAt: '2026-12-01T12:00:00+00:00' } });
}

describe('FirstFinishers', () => {
  it('lists finishers with their positions', () => {
    const list = mq(FirstFinishers, { completions: [completion(1, 1), completion(2, 2)] });

    expect(list.find('.FirstFinishers-item')).toHaveLength(2);
    expect(list.first('.FirstFinishers-position').textContent).toBe('1');
  });

  it('invites someone to be first when nobody has finished', () => {
    expect(mq(FirstFinishers, { completions: [] })).toHaveElement('.FirstFinishers-empty');
  });
});
