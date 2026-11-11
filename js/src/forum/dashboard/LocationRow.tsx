import app from 'flarum/forum/app';
import toast from '../utils/toast';
import Component, { type ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Stream from 'flarum/common/utils/Stream';
import type Mithril from 'mithril';
import type HidingSpotDefinition from '../../common/models/HidingSpotDefinition';
import type Location from '../../common/models/Location';
import type Part from '../../common/models/Part';
import LocationFields, { ANY, locationValue, type LocationFieldStreams, type PageRoute } from './LocationFields';

export type { PageRoute };

export interface LocationRowAttrs extends ComponentAttrs {
  location: Location;
  part: Part;
  spots: HidingSpotDefinition[];
  routes: PageRoute[];
  ondeleted: (location: Location) => void;
}

export default class LocationRow extends Component<LocationRowAttrs> {
  protected saving = false;
  protected fields!: LocationFieldStreams;

  oninit(vnode: Mithril.Vnode<LocationRowAttrs, this>) {
    super.oninit(vnode);

    const location = this.attrs.location;

    this.fields = {
      spot: Stream(location.exists ? location.spot() : (this.attrs.spots[0]?.id() ?? '')),
      route: Stream(location.exists ? (location.route() ?? ANY) : ANY),
      contextId: Stream(location.exists && location.contextId() ? String(location.contextId()) : ''),
    };
  }

  view() {
    const location = this.attrs.location;

    return (
      <div className="LocationRow">
        <LocationFields fields={this.fields} spots={this.attrs.spots} routes={this.attrs.routes} />
        {location.exists && !location.isAvailable() && (
          <span className="LocationRow-warning">{app.translator.trans('fof-scavenger-hunt.forum.dashboard.locations.unavailable')}</span>
        )}
        <div className="LocationRow-actions">
          <Button className="Button" icon="fas fa-check" loading={this.saving} onclick={() => this.save()}>
            {app.translator.trans('fof-scavenger-hunt.forum.dashboard.save')}
          </Button>
          <Button
            className="Button Button--icon Button--danger"
            icon="fas fa-trash"
            aria-label={app.translator.trans('fof-scavenger-hunt.forum.dashboard.locations.delete', {}, true)}
            onclick={() => this.delete()}
          />
        </div>
      </div>
    );
  }

  protected save(): void {
    const location = this.attrs.location;
    const attributes: Record<string, unknown> = { ...locationValue(this.fields, this.attrs.spots) };

    if (!location.exists) {
      attributes.relationships = { part: this.attrs.part };
    }

    this.saving = true;

    location
      .save(attributes)
      .then(() => toast(app.translator.trans('fof-scavenger-hunt.forum.dashboard.saved')))
      .catch(() => {})
      .finally(() => {
        this.saving = false;
        m.redraw();
      });
  }

  protected delete(): void {
    const location = this.attrs.location;

    if (!location.exists) {
      this.attrs.ondeleted(location);

      return;
    }

    location.delete().then(() => {
      this.attrs.ondeleted(location);
      m.redraw();
    });
  }
}
