import Modal, { type IInternalModalAttrs } from 'flarum/common/components/Modal';
import type Mithril from 'mithril';
import type Hunt from '../../common/models/Hunt';
export interface CompletionModalAttrs extends IInternalModalAttrs {
    huntId: string;
}
export default class CompletionModal extends Modal<CompletionModalAttrs> {
    protected hunt: Hunt | null;
    oninit(vnode: Mithril.Vnode<CompletionModalAttrs, this>): void;
    className(): string;
    title(): string | any[];
    content(): JSX.Element;
}
