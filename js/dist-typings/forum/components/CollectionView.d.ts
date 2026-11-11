import Component, { type ComponentAttrs } from 'flarum/common/Component';
import ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';
import type Hunt from '../../common/models/Hunt';
import type Part from '../../common/models/Part';
export interface CollectionViewAttrs extends ComponentAttrs {
    hunt: Hunt;
}
/**
 * The Collectable assembling as its Parts are found: found Parts on their layers, silhouettes for the rest.
 */
export default class CollectionView extends Component<CollectionViewAttrs> {
    view(): JSX.Element;
    viewItems(): ItemList<Mithril.Children>;
    protected parts(): Part[];
    protected partItem(part: Part): Mithril.Children;
}
