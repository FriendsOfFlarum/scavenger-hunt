import Component, { type ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
import type Asset from '../../common/models/Asset';
export interface IAssetCardAttrs extends ComponentAttrs {
    asset: Asset;
    ondeleted: () => void;
}
export default class AssetCard<CustomAttrs extends IAssetCardAttrs = IAssetCardAttrs> extends Component<CustomAttrs> {
    protected name: string;
    protected saving: boolean;
    protected deleting: boolean;
    oninit(vnode: Mithril.Vnode<CustomAttrs, this>): void;
    view(): JSX.Element;
    protected idPrefix(): string;
    protected deleteButton(): Mithril.Children;
    protected meta(): string;
    protected formatSize(bytes: number): string;
    protected rename(): void;
    protected delete(): void;
}
