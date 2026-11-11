import Component, { type ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
import type Hunt from '../../common/models/Hunt';
import type Part from '../../common/models/Part';
import type { PartLayer } from '../../common/models/Part';
import { type CanvasSize } from './layerMaths';
export interface ILayerEditorAttrs extends ComponentAttrs {
    hunt: Hunt;
    parts: Part[];
}
/** A local change and the saved layer it was made from. */
interface Change {
    layer: PartLayer;
    base: PartLayer | null;
}
interface Drag {
    partId: string;
    mode: 'move' | 'resize';
    pointerId: number;
    startX: number;
    startY: number;
    start: PartLayer;
    canvas: CanvasSize;
}
type RedrawableEvent<E extends Event> = E & {
    redraw?: boolean;
};
/**
 * Arranges the Parts on the Collectable canvas. Changes stay local until "Save layout".
 * The canvas is always laid out left to right (see CollectableCanvas), so pointer and
 * arrow-key movements map to x and y as they are, in RTL too.
 */
export default class LayerEditor<CustomAttrs extends ILayerEditorAttrs = ILayerEditorAttrs> extends Component<CustomAttrs> {
    protected changes: Record<string, Change>;
    protected aspects: Record<string, number>;
    protected solid: Record<string, boolean | null | undefined>;
    protected selected: string | null;
    protected drag: Drag | null;
    protected saving: boolean;
    protected announcement: string;
    protected focusAfterDraw: string | null;
    view(): JSX.Element;
    onupdate(vnode: Mithril.VnodeDOM<CustomAttrs, this>): void;
    protected layer(part: Part, helpId: string): Mithril.Children;
    protected selection(part: Part): Mithril.Children;
    /** Whether the Part's image covers what's underneath it. Checked once per image, in the background. */
    protected hasSolidBackground(part: Part): boolean;
    /** Parts drawn on the canvas. */
    protected placed(): Part[];
    protected otherLayers(part: Part): PartLayer[];
    protected layerOf(part: Part): PartLayer | null;
    protected update(part: Part, layer: PartLayer): void;
    /** A Part saved elsewhere since it was changed here (e.g. in the Part editor) shows what was saved. */
    protected dropStaleChanges(): void;
    protected changed(): Part[];
    protected restack(part: Part, layer: PartLayer): void;
    protected announce(part: Part): void;
    protected measure(part: Part, e: Event): void;
    protected place(part: Part): void;
    protected deselect(e: RedrawableEvent<PointerEvent>): void;
    protected nudge(e: RedrawableEvent<KeyboardEvent>, part: Part): void;
    protected startDrag(e: PointerEvent, part: Part): void;
    protected moveDrag(e: RedrawableEvent<PointerEvent>, part: Part): void;
    protected endDrag(e: PointerEvent, part: Part): void;
    protected reset(): void;
    protected save(): void;
}
export {};
