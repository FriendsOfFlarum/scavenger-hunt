import FormModal, { type IFormModalAttrs } from 'flarum/common/components/FormModal';
import type Mithril from 'mithril';
import type { SpotReport } from '../states/EncounterState';
import type { PlacementController } from './PlacementController';
export interface PlaceHereModalAttrs extends IFormModalAttrs {
    report: SpotReport;
    controller: PlacementController;
}
export default class PlaceHereModal extends FormModal<PlaceHereModalAttrs> {
    protected exactItem: boolean;
    protected thisPage: boolean;
    className(): string;
    title(): any[];
    content(): Mithril.Children;
    onsubmit(e: SubmitEvent): void;
}
