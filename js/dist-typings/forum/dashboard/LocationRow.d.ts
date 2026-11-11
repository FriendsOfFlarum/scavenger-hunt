import Component, { type ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
import type HidingSpotDefinition from '../../common/models/HidingSpotDefinition';
import type Location from '../../common/models/Location';
import type Part from '../../common/models/Part';
import { type LocationFieldStreams, type PageRoute } from './LocationFields';
export type { PageRoute };
export interface LocationRowAttrs extends ComponentAttrs {
    location: Location;
    part: Part;
    spots: HidingSpotDefinition[];
    routes: PageRoute[];
    ondeleted: (location: Location) => void;
}
export default class LocationRow extends Component<LocationRowAttrs> {
    protected saving: boolean;
    protected fields: LocationFieldStreams;
    oninit(vnode: Mithril.Vnode<LocationRowAttrs, this>): void;
    view(): JSX.Element;
    protected save(): void;
    protected delete(): void;
}
