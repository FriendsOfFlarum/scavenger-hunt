import FormModal from 'flarum/common/components/FormModal';
import Stream from 'flarum/common/utils/Stream';
import type Mithril from 'mithril';
import type HuntTemplate from '../../common/models/HuntTemplate';
/**
 * Starts a new Hunt, from a Template or blank.
 */
export default class TemplateGalleryModal extends FormModal {
    protected templates: HuntTemplate[] | null;
    protected failed: boolean;
    protected chosen: HuntTemplate | null;
    protected huntTitle: Stream<string>;
    protected hideParts: boolean;
    oninit(vnode: Mithril.Vnode): void;
    protected load(): void;
    className(): string;
    title(): string | any[];
    content(): JSX.Element;
    protected loadError(): Mithril.Children;
    protected gallery(): Mithril.Children;
    onsubmit(e: SubmitEvent): void;
}
