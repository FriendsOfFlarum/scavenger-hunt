import Component, { type ComponentAttrs } from 'flarum/common/Component';
import type { HidingSpotAttrs } from '../components/HidingSpot';
import type { SpotReport } from '../states/EncounterState';
import type { PlacementController } from './PlacementController';
export interface PlacementTargetButtonAttrs extends ComponentAttrs {
    attrs: HidingSpotAttrs;
    report: SpotReport;
    controller: PlacementController;
}
export default class PlacementTargetButton extends Component<PlacementTargetButtonAttrs> {
    view(): JSX.Element;
}
