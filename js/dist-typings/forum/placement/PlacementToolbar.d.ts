import Component, { type ComponentAttrs } from 'flarum/common/Component';
import type { PlacementController } from './PlacementController';
export interface PlacementToolbarAttrs extends ComponentAttrs {
    controller: PlacementController;
}
export default class PlacementToolbar extends Component<PlacementToolbarAttrs> {
    view(): JSX.Element;
}
