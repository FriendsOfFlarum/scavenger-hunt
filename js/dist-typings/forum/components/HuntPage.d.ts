import Page from 'flarum/common/components/Page';
import type Mithril from 'mithril';
import type Completion from '../../common/models/Completion';
import type Hunt from '../../common/models/Hunt';
/**
 * A Hunt's public page: what it is, how far the member has got, and who finished first.
 */
export default class HuntPage extends Page {
    protected hunt: Hunt | null;
    protected notFound: boolean;
    protected finishers: Completion[];
    protected moreFinishers: boolean;
    protected loadingFinishers: boolean;
    oninit(vnode: Mithril.Vnode): void;
    view(): JSX.Element;
    protected hero(): Mithril.Children;
    protected content(): Mithril.Children;
    protected loadFinishers(): void;
}
