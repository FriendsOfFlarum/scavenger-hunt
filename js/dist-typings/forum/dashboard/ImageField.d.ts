import Component, { type ComponentAttrs } from 'flarum/common/Component';
import type Stream from 'flarum/common/utils/Stream';
import type { ArtworkPurpose } from '../../common/utils/artworkFit';
export interface IImageFieldAttrs extends ComponentAttrs {
    /** The image reference the parent form saves. */
    stream: Stream<string | null>;
    /** The URL of the saved reference. */
    url: string | null | undefined;
    /** Space-separated ids, as for aria-labelledby. */
    labelledby?: string;
    /** The key of the Hunt's Template, which the picker lists first. */
    template?: string | null;
    /** What the image is for, which the picker's hint and warnings follow. */
    purpose: ArtworkPurpose;
}
export default class ImageField extends Component<IImageFieldAttrs> {
    protected picked: {
        reference: string | null;
        url: string | null;
    } | null;
    view(): JSX.Element;
    protected choose(): void;
    protected select(reference: string | null, url: string | null): void;
}
