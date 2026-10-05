import app from 'flarum/forum/app';
import Component, { type ComponentAttrs } from 'flarum/common/Component';
import LinkButton from 'flarum/common/components/LinkButton';
import type Hunt from '../../common/models/Hunt';
import type Part from '../../common/models/Part';
import huntUrl from '../utils/huntUrl';
import CollectableCanvas from './CollectableCanvas';

export interface CompletionSummaryAttrs extends ComponentAttrs {
  hunt: Hunt;
}

/**
 * The finished Collectable, the member's finishing place and the Reward message.
 */
export default class CompletionSummary extends Component<CompletionSummaryAttrs> {
  view() {
    const hunt = this.attrs.hunt;
    const parts = ((hunt.parts() || []).filter(Boolean) as Part[]).sort((a, b) => a.sort() - b.sort());
    const position = hunt.finishingPosition();
    const rewardHtml = hunt.rewardMessageHtml();
    const url = huntUrl(hunt.slug());

    return (
      <div className="CompletionSummary">
        <CollectableCanvas hunt={hunt} parts={parts} complete />

        {position ? (
          <p className="CompletionSummary-position">{app.translator.trans('fof-scavenger-hunt.forum.completion_modal.position', { position })}</p>
        ) : null}

        {rewardHtml ? (
          <div className="CompletionSummary-reward">
            <h4>{app.translator.trans('fof-scavenger-hunt.forum.completion_modal.reward')}</h4>
            <div className="CompletionSummary-rewardMessage">{m.trust(rewardHtml)}</div>
          </div>
        ) : null}

        {url ? (
          <LinkButton className="Button" icon="fas fa-trophy" href={url}>
            {app.translator.trans('fof-scavenger-hunt.forum.completion_modal.view_hunt')}
          </LinkButton>
        ) : null}
      </div>
    );
  }
}
