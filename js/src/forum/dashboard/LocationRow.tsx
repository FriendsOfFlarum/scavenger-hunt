import app from 'flarum/forum/app';
import Component, { type ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Select from 'flarum/common/components/Select';
import Stream from 'flarum/common/utils/Stream';
import type Mithril from 'mithril';
import type HidingSpotDefinition from '../../common/models/HidingSpotDefinition';
import type Location from '../../common/models/Location';
import type Part from '../../common/models/Part';

export interface PageRoute {
  name: string;
  path: string;
}

export interface LocationRowAttrs extends ComponentAttrs {
  location: Location;
  part: Part;
  spots: HidingSpotDefinition[];
  routes: PageRoute[];
  ondeleted: (location: Location) => void;
}

const ANY = '';

export default class LocationRow extends Component<LocationRowAttrs> {
  protected saving = false;
  protected route!: Stream<string>;
  protected spot!: Stream<string>;
  protected contextId!: Stream<string>;

  oninit(vnode: Mithril.Vnode<LocationRowAttrs, this>) {
    super.oninit(vnode);

    const location = this.attrs.location;

    this.spot = Stream(location.exists ? location.spot() : (this.attrs.spots[0]?.id() ?? ''));
    this.route = Stream(location.exists ? (location.route() ?? ANY) : ANY);
    this.contextId = Stream(location.exists && location.contextId() ? String(location.contextId()) : '');
  }

  view() {
    const spot = this.attrs.spots.find((candidate) => candidate.id() === this.spot());
    const location = this.attrs.location;

    const spotOptions: Record<string, string> = {};
    this.attrs.spots.forEach((candidate) => (spotOptions[candidate.id()!] = candidate.label()));

    const routeOptions: Record<string, string> = { [ANY]: app.translator.trans('fof-scavenger-hunt.forum.dashboard.locations.any_page', {}, true) };
    this.attrs.routes
      .filter((route) => !spot || spot.allowsRoute(route.name))
      .forEach((route) => (routeOptions[route.name] = `${route.name} — ${route.path}`));

    return (
      <div className="LocationRow">
        <div className="LocationRow-field">
          <label>{app.translator.trans('fof-scavenger-hunt.forum.dashboard.locations.spot')}</label>
          <Select options={spotOptions} value={this.spot()} onchange={(value: string) => this.spot(value)} />
        </div>
        <div className="LocationRow-field">
          <label>{app.translator.trans('fof-scavenger-hunt.forum.dashboard.locations.page')}</label>
          <Select options={routeOptions} value={this.route()} onchange={(value: string) => this.route(value)} />
        </div>
        {spot?.contextType() && (
          <div className="LocationRow-field">
            <label>{app.translator.trans('fof-scavenger-hunt.forum.dashboard.locations.item', { type: spot.contextType() })}</label>
            <input
              className="FormControl"
              type="number"
              min="1"
              bidi={this.contextId}
              placeholder={app.translator.trans('fof-scavenger-hunt.forum.dashboard.locations.any_item', {}, true)}
            />
          </div>
        )}
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
    const spot = this.attrs.spots.find((candidate) => candidate.id() === this.spot());
    const location = this.attrs.location;
    const attributes: Record<string, unknown> = {
      spot: this.spot(),
      route: this.route() || null,
      contextType: spot?.contextType() ?? null,
      contextId: spot?.contextType() && this.contextId() ? Number(this.contextId()) : null,
    };

    if (!location.exists) {
      attributes.relationships = { part: this.attrs.part };
    }

    this.saving = true;

    location
      .save(attributes)
      .then(() => app.alerts.show({ type: 'success' }, app.translator.trans('fof-scavenger-hunt.forum.dashboard.saved')))
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
