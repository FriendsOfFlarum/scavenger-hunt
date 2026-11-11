import Component, { type ComponentAttrs } from 'flarum/common/Component';
import Stream from 'flarum/common/utils/Stream';
import type Mithril from 'mithril';
import type Hunt from '../../common/models/Hunt';
export interface HuntRewardsFormAttrs extends ComponentAttrs {
    hunt: Hunt;
}
export default class HuntRewardsForm extends Component<HuntRewardsFormAttrs> {
    protected saving: boolean;
    protected groupId: Stream<string>;
    protected message: Stream<string>;
    oninit(vnode: Mithril.Vnode<HuntRewardsFormAttrs, this>): void;
    view(): JSX.Element;
    static canAwardGroups(): boolean;
    protected save(e: SubmitEvent): void;
}
