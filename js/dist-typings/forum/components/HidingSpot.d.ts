import Component, { type ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
import { type SpotReport } from '../states/EncounterState';
export interface HidingSpotAttrs extends ComponentAttrs {
    /** A Hiding spot key registered in PHP through `FoF\ScavengerHunt\Extend\HidingSpots`. */
    spot: string;
    /** `[contextType, id]` for spots drawn once per item, e.g. `['post', 12]`. */
    context?: [string, number];
    className?: string;
}
/**
 * A place a Part can appear. Renders nothing until the server says a Part is here.
 *
 *     items.add('scavengerHunt', <HidingSpot spot="acme.article-footer" context={['article', article.id()]} />);
 */
export default class HidingSpot extends Component<HidingSpotAttrs> {
    protected report: SpotReport;
    protected inDrawer: boolean;
    /** On phones the drawer (core's off-canvas menu) is out of sight until it is opened. */
    protected isHidden: () => boolean;
    oninit(vnode: Mithril.Vnode<HidingSpotAttrs, this>): void;
    oncreate(vnode: Mithril.VnodeDOM<HidingSpotAttrs, this>): void;
    onbeforeupdate(vnode: Mithril.VnodeDOM<HidingSpotAttrs, this>): void;
    onremove(vnode: Mithril.VnodeDOM<HidingSpotAttrs, this>): void;
    view(): JSX.Element | Mithril.Children;
    protected reportFor(attrs: HidingSpotAttrs): SpotReport;
}
