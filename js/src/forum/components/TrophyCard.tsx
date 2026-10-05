import app from 'flarum/forum/app';
import Component, { type ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Link from 'flarum/common/components/Link';
import humanTime from 'flarum/common/helpers/humanTime';
import type Completion from '../../common/models/Completion';
import type Part from '../../common/models/Part';
import huntUrl from '../utils/huntUrl';
import CollectableCanvas from './CollectableCanvas';

export interface TrophyCardAttrs extends ComponentAttrs {
  completion: Completion;
}

/**
 * A Completion on a profile: the finished Collectable, when, and in what place.
 */
export default class TrophyCard extends Component<TrophyCardAttrs> {
  protected showReward = false;

  view() {
    const completion = this.attrs.completion;
    const hunt = completion.hunt();

    if (!hunt) return null;

    const parts = ((hunt.parts() || []).filter(Boolean) as Part[]).sort((a, b) => a.sort() - b.sort());
    const url = huntUrl(hunt.slug());
    const rewardHtml = hunt.rewardMessageHtml();

    return (
      <article className="TrophyCard">
        <CollectableCanvas hunt={hunt} parts={parts} complete />
        <h4 className="TrophyCard-title">{url ? <Link href={url}>{hunt.title()}</Link> : hunt.title()}</h4>
        <div className="TrophyCard-meta">
          {app.translator.trans('fof-scavenger-hunt.forum.trophies.finished', { position: completion.position() })} ·{' '}
          {humanTime(completion.completedAt())}
        </div>
        {rewardHtml ? (
          <div className="TrophyCard-reward">
            <Button className="Button Button--link" icon="fas fa-gift" onclick={() => (this.showReward = !this.showReward)}>
              {app.translator.trans('fof-scavenger-hunt.forum.trophies.show_reward')}
            </Button>
            {this.showReward && <div className="CompletionSummary-rewardMessage">{m.trust(rewardHtml)}</div>}
          </div>
        ) : null}
      </article>
    );
  }
}
