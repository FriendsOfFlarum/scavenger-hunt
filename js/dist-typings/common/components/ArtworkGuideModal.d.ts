import Modal from 'flarum/common/components/Modal';
import type Mithril from 'mithril';
export interface ExampleLayer {
    key: string;
    file: string;
    x: number;
    y: number;
    width: number;
    z: number;
}
/** Three Parts of the winter Template, as its template.json places them (a test keeps the two in step). */
export declare const EXAMPLE_PARTS: ExampleLayer[];
/**
 * What artwork a Hunt needs, shown with the winter Template taken apart.
 */
export default class ArtworkGuideModal extends Modal {
    className(): string;
    title(): string | any[];
    content(): JSX.Element;
    protected example(): Mithril.Children;
}
