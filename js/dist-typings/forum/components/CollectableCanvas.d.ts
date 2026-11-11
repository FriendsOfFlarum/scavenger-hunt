import Component, { type ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
import type Hunt from '../../common/models/Hunt';
import type Part from '../../common/models/Part';
export interface CollectableCanvasAttrs extends ComponentAttrs {
    hunt: Hunt;
    parts: Part[];
    /** Draw every Part as found, e.g. for a Trophy. */
    complete?: boolean;
}
/**
 * The Collectable on its canvas: found Parts on their layers, silhouettes for the rest.
 */
export default class CollectableCanvas extends Component<CollectableCanvasAttrs> {
    view(): JSX.Element;
    protected layer(part: Part): Mithril.Children;
}
