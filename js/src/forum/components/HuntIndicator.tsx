import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Tooltip from 'flarum/common/components/Tooltip';
import { activeHunts } from '../utils/activeHunts';

/**
 * The basket in the header: how many Parts the member has found across the running Hunts.
 */
export default class HuntIndicator extends Component {
  view() {
    const hunts = activeHunts();
    const found = hunts.reduce((sum, hunt) => sum + hunt.foundCount, 0);
    const total = hunts.reduce((sum, hunt) => sum + hunt.partCount, 0);
    const label = app.translator.trans('fof-scavenger-hunt.forum.indicator.label', { found, total }, true);

    return (
      <Tooltip text={label} position="bottom">
        <Button
          className="Button Button--flat HuntIndicator"
          icon="fas fa-basket-shopping"
          aria-label={label}
          onclick={() => app.modal.show(() => import('./CollectionModal'))}
        >
          <span className="HuntIndicator-count" aria-hidden="true">
            {found}/{total}
          </span>
        </Button>
      </Tooltip>
    );
  }
}
