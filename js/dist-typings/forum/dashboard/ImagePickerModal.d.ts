import Modal, { type IInternalModalAttrs } from 'flarum/common/components/Modal';
import type Mithril from 'mithril';
import type Asset from '../../common/models/Asset';
import type HuntTemplate from '../../common/models/HuntTemplate';
import { type ArtworkPurpose } from '../../common/utils/artworkFit';
export interface IImagePickerModalAttrs extends IInternalModalAttrs {
    selected: string | null;
    /** The key of the Template the Hunt was made from, listed first. */
    template?: string | null;
    /** What the image is for, which decides the hint and the warnings. */
    purpose?: ArtworkPurpose;
    onselect: (reference: string | null, url: string | null) => void;
}
type Tab = 'templates' | 'assets';
/**
 * Picks an image reference: a Template image (`template:<key>/<file>`) or an uploaded Asset (`asset:<id>`).
 */
export default class ImagePickerModal extends Modal<IImagePickerModalAttrs> {
    protected tab: Tab;
    protected templates: HuntTemplate[] | null;
    protected templatesFailed: boolean;
    protected assets: Asset[] | null;
    protected assetsFailed: boolean;
    protected loadingAssets: boolean;
    protected moreAssets: boolean;
    protected uploading: boolean;
    /** The Asset uploaded from this picker, marked until it closes. */
    protected justUploaded: Asset | null;
    /** Whether each Part image has a solid background; `undefined` while it's being checked. */
    protected solid: Record<string, boolean | null | undefined>;
    oninit(vnode: Mithril.Vnode<IImagePickerModalAttrs, this>): void;
    className(): string;
    title(): string | any[];
    content(): JSX.Element;
    protected templatesPanel(): Mithril.Children;
    protected assetsPanel(): Mithril.Children;
    protected item(reference: string, url: string | null, label: string, asset?: Asset): Mithril.Children;
    /** Soft warnings for an uploaded image that doesn't fit what it's being chosen for. */
    protected warnings(asset: Asset): {
        key: string;
        text: Mithril.Children;
    }[];
    protected isSolid(url: string): boolean;
    protected chooseFile(): void;
    protected upload(input: HTMLInputElement): void;
    protected loadError(retry: () => void): Mithril.Children;
    protected loadTemplates(): void;
    protected showTab(tab: Tab): void;
    protected loadAssets(): void;
    protected select(reference: string | null, url: string | null): void;
}
export {};
