import Component from 'flarum/common/Component';
import type Mithril from 'mithril';
import type Asset from '../../common/models/Asset';
export default class AssetLibrary extends Component {
    protected assets: Asset[];
    protected loading: boolean;
    protected uploading: boolean;
    protected more: boolean;
    protected failed: boolean;
    oninit(vnode: Mithril.Vnode<{}, this>): void;
    view(): JSX.Element;
    protected load(fresh: boolean): void;
    protected chooseFile(): void;
    protected upload(input: HTMLInputElement): void;
    /** Names the size, the first thing to check against the artwork guide. */
    protected confirm(asset: Asset): void;
    protected remove(asset: Asset): void;
}
