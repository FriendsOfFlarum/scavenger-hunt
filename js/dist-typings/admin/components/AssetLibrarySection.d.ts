import Component, { type ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
export interface IAssetLibrarySectionAttrs extends ComponentAttrs {
    /** Rendered in a tab of the extension page, which names it already. */
    inTab?: boolean;
}
/**
 * The extension page's Asset library. The library itself is a lazy chunk, loaded the first time this renders.
 */
export default class AssetLibrarySection<CustomAttrs extends IAssetLibrarySectionAttrs = IAssetLibrarySectionAttrs> extends Component<CustomAttrs> {
    protected failed: boolean;
    oninit(vnode: Mithril.Vnode<CustomAttrs, this>): void;
    view(): JSX.Element;
    protected library(): Mithril.Children;
    protected load(): void;
}
