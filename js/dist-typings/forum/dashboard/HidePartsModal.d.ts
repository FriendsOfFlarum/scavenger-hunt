import Modal, { type IInternalModalAttrs } from 'flarum/common/components/Modal';
import type Mithril from 'mithril';
import type HidingSpotDefinition from '../../common/models/HidingSpotDefinition';
import type Hunt from '../../common/models/Hunt';
import type Location from '../../common/models/Location';
import type Part from '../../common/models/Part';
import { type LocationFieldStreams, type LocationValue, type PageRoute } from './LocationFields';
export interface IHidePartsModalAttrs extends IInternalModalAttrs {
    hunt: Hunt;
    parts: Part[];
    spots: HidingSpotDefinition[];
    routes: PageRoute[];
    /** Called with the ids of the Parts whose places were replaced. */
    onsaved?: (partIds: string[]) => void;
}
export interface SuggestedPlace extends LocationValue {
    label: string;
    url: string | null;
    fallback: boolean;
}
interface Slot {
    key: number;
    place: SuggestedPlace;
    /** Set once the manager chooses the place themselves. */
    fields: LocationFieldStreams | null;
    picking: boolean;
}
interface Row {
    part: Part;
    /** Kept rows leave the Part's saved Locations alone; proposed rows replace them. */
    kept: boolean;
    slots: Slot[];
    loading: boolean;
}
type PlaceRef = Pick<LocationValue, 'spot' | 'contextType' | 'contextId'>;
/**
 * Proposes hiding places for a Hunt's Parts, lets the manager swap or choose any of them, and saves the reviewed set.
 */
export default class HidePartsModal extends Modal<IHidePartsModalAttrs> {
    protected rows: Row[];
    protected failed: Row[];
    oninit(vnode: Mithril.Vnode<IHidePartsModalAttrs, this>): void;
    className(): string;
    title(): string | any[];
    content(): JSX.Element[];
    protected rowView(row: Row): Mithril.Children;
    protected placesView(row: Row): Mithril.Children;
    protected slotView(row: Row, slot: Slot): Mithril.Children;
    protected savedLocations(part: Part): Location[];
    protected proposed(): Row[];
    protected slotValue(slot: Slot): LocationValue;
    /** Every place the review would use or keep, apart from those of the given rows. */
    protected placesInUse(except?: Row[]): PlaceRef[];
    protected request(parts: Part[], exclude: PlaceRef[]): Promise<{
        data: {
            partId: string;
            places: SuggestedPlace[];
        }[];
    }>;
    protected suggest(rows: Row[]): void;
    protected pickAnother(row: Row, slot: Slot): void;
    protected chooseMyself(slot: Slot): void;
    protected submit(): void;
}
export {};
