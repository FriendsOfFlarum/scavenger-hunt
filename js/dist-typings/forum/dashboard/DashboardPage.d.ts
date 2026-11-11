import Page from 'flarum/common/components/Page';
import ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';
import type Hunt from '../../common/models/Hunt';
/**
 * Where Hunt managers see every Hunt and start new ones.
 */
export default class DashboardPage extends Page {
    protected hunts: Hunt[] | null;
    oninit(vnode: Mithril.Vnode): void;
    view(): JSX.Element;
    protected hero(): Mithril.Children;
    protected sidebar(): Mithril.Children;
    sidebarItems(): ItemList<Mithril.Children>;
    navItems(): ItemList<Mithril.Children>;
    protected content(): Mithril.Children;
}
