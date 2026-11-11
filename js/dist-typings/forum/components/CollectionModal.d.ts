import Modal from 'flarum/common/components/Modal';
import type Mithril from 'mithril';
import type Hunt from '../../common/models/Hunt';
export default class CollectionModal extends Modal {
    protected hunts: Hunt[] | null;
    oninit(vnode: Mithril.Vnode): void;
    className(): string;
    title(): string | any[];
    content(): JSX.Element;
}
