import Component, { type ComponentAttrs } from 'flarum/common/Component';
import Stream from 'flarum/common/utils/Stream';
import type Mithril from 'mithril';
import type HidingSpotDefinition from '../../common/models/HidingSpotDefinition';
import type Location from '../../common/models/Location';
import type Part from '../../common/models/Part';
import type { PlacementMode } from '../../common/models/Part';
import { type PageRoute } from './LocationRow';
export interface PartEditorAttrs extends ComponentAttrs {
    part: Part;
    spots: HidingSpotDefinition[];
    routes: PageRoute[];
    /** The key of the Hunt's Template, which the image picker lists first. */
    template?: string | null;
    ondeleted: (part: Part) => void;
}
export default class PartEditor extends Component<PartEditorAttrs> {
    protected saving: boolean;
    protected name: Stream<string>;
    protected mode: Stream<PlacementMode>;
    protected hint: Stream<string>;
    protected wanderChance: Stream<string>;
    protected sort: Stream<string>;
    protected image: Stream<string | null>;
    protected layer: {
        x: Stream<string>;
        y: Stream<string>;
        width: Stream<string>;
        z: Stream<string>;
    };
    protected syncedLayer: string;
    protected locations: Location[];
    oninit(vnode: Mithril.Vnode<PartEditorAttrs, this>): void;
    onbeforeupdate(vnode: Mithril.VnodeDOM<PartEditorAttrs, this>): void;
    protected syncLayer(): void;
    view(): JSX.Element;
    protected data(): {
        name: any;
        mode: any;
        hint: any;
        wanderChance: number | null;
        image: any;
        sort: number;
        layer: {
            x: number;
            y: number;
            width: number;
            z: number;
        } | null;
    };
    /** Whether saving would change the Part. */
    protected isDirty(): boolean;
    protected save(): void;
    protected placeOnForum(): void;
    protected delete(): void;
}
