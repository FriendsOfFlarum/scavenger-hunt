import Component, { type ComponentAttrs } from 'flarum/common/Component';
import Stream from 'flarum/common/utils/Stream';
import type Mithril from 'mithril';
import type Hunt from '../../common/models/Hunt';
export interface HuntFormAttrs extends ComponentAttrs {
    hunt: Hunt;
    section: 'details' | 'schedule';
}
export default class HuntForm extends Component<HuntFormAttrs> {
    protected saving: boolean;
    protected title: Stream<string>;
    protected slug: Stream<string>;
    protected description: Stream<string>;
    protected image: Stream<string | null>;
    protected completedImage: Stream<string | null>;
    protected isEnabled: Stream<boolean>;
    protected guestsCanSee: Stream<boolean>;
    protected startsAt: Stream<string>;
    protected endsAt: Stream<string>;
    oninit(vnode: Mithril.Vnode<HuntFormAttrs, this>): void;
    view(): JSX.Element;
    protected detailsFields(): Mithril.Children;
    protected scheduleFields(): Mithril.Children;
    protected save(e: SubmitEvent): void;
}
