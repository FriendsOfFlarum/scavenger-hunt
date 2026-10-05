import app from 'flarum/forum/app';
import Component, { type ComponentAttrs } from 'flarum/common/Component';
import Icon from 'flarum/common/components/Icon';
import Tooltip from 'flarum/common/components/Tooltip';
import classList from 'flarum/common/utils/classList';
import type { ApiPayloadSingle } from 'flarum/common/Store';
import type Encounter from '../../common/models/Encounter';
import type Find from '../../common/models/Find';
import { encounters } from '../states/EncounterState';
import { updateProgress } from '../utils/activeHunts';
import flyToBasket from '../utils/flyToBasket';

export interface HiddenPartAttrs extends ComponentAttrs {
  encounter: Encounter;
}

export default class HiddenPart extends Component<HiddenPartAttrs> {
  protected claiming = false;

  view() {
    const encounter = this.attrs.encounter;
    const part = encounter.part();

    if (!part) {
      return null;
    }

    const imageUrl = part.imageUrl();
    const label = app.translator.trans('fof-scavenger-hunt.forum.part.collect', { part: part.name() }, true);

    const button = (
      <button
        type="button"
        className={classList('HiddenPart', { 'HiddenPart--claiming': this.claiming, 'HiddenPart--preview': !encounter.token() })}
        aria-label={label}
        disabled={this.claiming}
        onclick={() => this.collect()}
      >
        {imageUrl ? (
          <img className="HiddenPart-image" src={imageUrl} alt="" loading="lazy" decoding="async" />
        ) : (
          <Icon name="fas fa-puzzle-piece" className="HiddenPart-placeholder" />
        )}
      </button>
    );

    let tooltip = label;

    if (!app.session.user) {
      tooltip = app.translator.trans('fof-scavenger-hunt.forum.part.sign_up_prompt', { part: part.name() }, true);
    } else if (!encounter.token()) {
      tooltip = app.translator.trans('fof-scavenger-hunt.forum.part.manager_preview', {}, true);
    }

    return <Tooltip text={tooltip}>{button}</Tooltip>;
  }

  protected async collect(): Promise<void> {
    const encounter = this.attrs.encounter;
    const token = encounter.token();

    if (!app.session.user) {
      app.modal.show(() => import('flarum/forum/components/LogInModal'));

      return;
    }

    if (!token || this.claiming) {
      return;
    }

    this.claiming = true;

    try {
      const payload = await app.request<ApiPayloadSingle>({
        method: 'POST',
        url: `${app.forum.attribute('apiUrl')}/scavenger-hunt-finds`,
        body: { data: { type: 'scavenger-hunt-finds', attributes: { token } } },
      });

      const find = app.store.pushPayload<Find>(payload);
      const part = find.part() || null;
      const hunt = part ? part.hunt() || null : null;

      flyToBasket(this.element);

      if (part && hunt) {
        updateProgress(Number(hunt.id()), hunt.foundCount() ?? 0);

        app.alerts.show(
          { type: 'success' },
          hunt.completedAt()
            ? app.translator.trans('fof-scavenger-hunt.forum.part.hunt_completed', { hunt: hunt.title() })
            : app.translator.trans('fof-scavenger-hunt.forum.part.found', {
                part: part.name(),
                found: hunt.foundCount() ?? 0,
                total: hunt.partCount(),
              })
        );
      }
    } catch {
      // The API's own message ("This part has slipped away") is shown by the default handler.
    } finally {
      this.claiming = false;
      encounters.forget(encounter);
      m.redraw();
    }
  }
}
