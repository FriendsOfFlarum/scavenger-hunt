import Component, { type ComponentAttrs } from 'flarum/common/Component';
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
export declare const ANY = "";
export declare function locationValue(fields: LocationFieldStreams, spots: HidingSpotDefinition[]): LocationValue;
/**
 * The spot, page and item pickers for a Location.
 */
export default class LocationFields extends Component<LocationFieldsAttrs> {
    view(): ("" | JSX.Element | null | undefined)[];
}
