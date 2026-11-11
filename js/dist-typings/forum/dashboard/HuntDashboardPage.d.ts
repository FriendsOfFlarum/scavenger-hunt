import Page from 'flarum/common/components/Page';
import ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';
import type HidingSpotDefinition from '../../common/models/HidingSpotDefinition';
import type Hunt from '../../common/models/Hunt';
import type Part from '../../common/models/Part';
import type { PageRoute } from './LocationRow';
type Tab = 'overview' | 'details' | 'schedule' | 'rewards' | 'parts' | 'participants';
export default class HuntDashboardPage extends Page {
    protected hunt: Hunt | null;
    protected parts: Part[];
    protected spots: HidingSpotDefinition[];
    protected routes: PageRoute[];
    protected tab: Tab;
    protected addingPart: boolean;
    /** Bumped per Part when its Locations are replaced elsewhere, so its editor starts again from the saved ones. */
    protected revisions: Record<string, number>;
    oninit(vnode: Mithril.Vnode): void;
    view(): JSX.Element;
    protected hero(): Mithril.Children;
    protected sidebar(): Mithril.Children;
    sidebarItems(): ItemList<Mithril.Children>;
    navItems(): ItemList<Mithril.Children>;
    protected content(): Mithril.Children;
    protected setTab(tab: Tab): void;
    /** Mithril keeps the params it routed with, so the one-off `hide` flag is dropped here every time. */
    protected replaceUrl(changes: Record<string, string>): void;
    protected hideParts(): void;
    protected addPart(): void;
}
export {};
