import Component, { type ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
import type Hunt from '../../common/models/Hunt';
import type HuntStats from '../../common/models/HuntStats';
export interface HuntOverviewAttrs extends ComponentAttrs {
    hunt: Hunt;
}
export default class HuntOverview extends Component<HuntOverviewAttrs> {
    protected stats: HuntStats | null;
    oninit(vnode: Mithril.Vnode<HuntOverviewAttrs, this>): void;
    view(): JSX.Element;
}
