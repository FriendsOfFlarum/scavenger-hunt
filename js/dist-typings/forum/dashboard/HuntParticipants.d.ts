import Component, { type ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
import type Hunt from '../../common/models/Hunt';
import type Participant from '../../common/models/Participant';
export interface HuntParticipantsAttrs extends ComponentAttrs {
    hunt: Hunt;
}
export default class HuntParticipants extends Component<HuntParticipantsAttrs> {
    protected participants: Participant[];
    protected loading: boolean;
    protected more: boolean;
    protected search: string;
    protected sort: string;
    protected searchTimer: ReturnType<typeof setTimeout> | null;
    oninit(vnode: Mithril.Vnode<HuntParticipantsAttrs, this>): void;
    view(): JSX.Element;
    protected row(participant: Participant): Mithril.Children;
    protected searchFor(value: string): void;
    protected sortBy(value: string): void;
    protected load(fresh: boolean): void;
    protected reset(participant: Participant): void;
}
