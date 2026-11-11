import app from 'flarum/forum/app';
import routeLabel from '../utils/routeLabel';
import Component, { type ComponentAttrs } from 'flarum/common/Component';
import Select from 'flarum/common/components/Select';
import type Stream from 'flarum/common/utils/Stream';
import type HidingSpotDefinition from '../../common/models/HidingSpotDefinition';

export interface PageRoute {
  name: string;
  path: string;
}

/** A Location's saved attributes. */
export interface LocationValue {
  spot: string;
  route: string | null;
  contextType: string | null;
  contextId: number | null;
}

/** The field values: an empty route means any page, an empty item any item. */
export interface LocationFieldStreams {
  spot: Stream<string>;
  route: Stream<string>;
  contextId: Stream<string>;
}

export interface LocationFieldsAttrs extends ComponentAttrs {
  fields: LocationFieldStreams;
  spots: HidingSpotDefinition[];
  routes: PageRoute[];
}

export const ANY = '';

export function locationValue(fields: LocationFieldStreams, spots: HidingSpotDefinition[]): LocationValue {
  const contextType = spots.find((candidate) => candidate.id() === fields.spot())?.contextType() ?? null;

  return {
    spot: fields.spot(),
    route: fields.route() || null,
    contextType,
    contextId: contextType && fields.contextId() ? Number(fields.contextId()) : null,
  };
}

/**
 * The spot, page and item pickers for a Location.
 */
export default class LocationFields extends Component<LocationFieldsAttrs> {
  view() {
    const { fields, spots, routes } = this.attrs;
    const spot = spots.find((candidate) => candidate.id() === fields.spot());

    const spotOptions: Record<string, string> = {};
    spots.forEach((candidate) => (spotOptions[candidate.id()!] = candidate.label()));

    const routeOptions: Record<string, string> = { [ANY]: app.translator.trans('fof-scavenger-hunt.forum.dashboard.locations.any_page', {}, true) };
    routes.filter((route) => !spot || spot.allowsRoute(route.name)).forEach((route) => (routeOptions[route.name] = routeLabel(route)));

    return [
      <div className="LocationRow-field">
        <label>{app.translator.trans('fof-scavenger-hunt.forum.dashboard.locations.spot')}</label>
        <Select options={spotOptions} value={fields.spot()} onchange={(value: string) => fields.spot(value)} />
      </div>,
      <div className="LocationRow-field">
        <label>{app.translator.trans('fof-scavenger-hunt.forum.dashboard.locations.page')}</label>
        <Select options={routeOptions} value={fields.route()} onchange={(value: string) => fields.route(value)} />
      </div>,
      spot?.contextType() && (
        <div className="LocationRow-field">
          <label>{app.translator.trans('fof-scavenger-hunt.forum.dashboard.locations.item', { type: spot.contextType() })}</label>
          <input
            className="FormControl"
            type="number"
            min="1"
            value={fields.contextId()}
            oninput={(e: InputEvent) => fields.contextId((e.target as HTMLInputElement).value)}
            placeholder={app.translator.trans('fof-scavenger-hunt.forum.dashboard.locations.any_item', {}, true)}
          />
        </div>
      ),
    ];
  }
}
