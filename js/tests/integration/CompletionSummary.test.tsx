import bootstrapForum from '@flarum/jest-config/src/bootstrap/forum';
import app from 'flarum/forum/app';
import mq from 'mithril-query';
import { jest } from '@jest/globals';
import CompletionSummary from '../../src/forum/components/CompletionSummary';
import Hunt from '../../src/common/models/Hunt';

beforeAll(() => {
  bootstrapForum();
  app.boot();
});

function hunt(attributes: Record<string, unknown>): Hunt {
  const model = new Hunt({ type: 'scavenger-hunts', id: '1', attributes: { title: 'Snowman', slug: 'snowman', imageUrl: null, ...attributes } });
  jest.spyOn(model, 'parts').mockReturnValue([]);

  return model;
}

describe('CompletionSummary', () => {
  it('shows the finishing place and the reward message', () => {
    const summary = mq(CompletionSummary, { hunt: hunt({ finishingPosition: 3, rewardMessageHtml: '<p>Use code <strong>SNOW26</strong></p>' }) });

    expect(summary).toHaveElement('.CompletionSummary-position');
    // mithril-query does not render m.trust() output, so check the block is drawn.
    expect(summary).toHaveElement('.CompletionSummary-reward .CompletionSummary-rewardMessage');
  });

  it('leaves the reward out when there is none', () => {
    expect(mq(CompletionSummary, { hunt: hunt({ finishingPosition: 1 }) })).not.toHaveElement('.CompletionSummary-reward');
  });
});
