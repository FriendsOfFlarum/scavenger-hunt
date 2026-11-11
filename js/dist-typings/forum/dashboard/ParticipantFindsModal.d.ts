import Modal, { type IInternalModalAttrs } from 'flarum/common/components/Modal';
import type User from 'flarum/common/models/User';
import type Mithril from 'mithril';
import type Find from '../../common/models/Find';
export interface ParticipantFindsModalAttrs extends IInternalModalAttrs {
    huntId: string;
    user: User;
}
export default class ParticipantFindsModal extends Modal<ParticipantFindsModalAttrs> {
    protected finds: Find[] | null;
    oninit(vnode: Mithril.Vnode<ParticipantFindsModalAttrs, this>): void;
    className(): string;
    title(): any[];
    content(): JSX.Element;
}
