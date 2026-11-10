import app from 'flarum/forum/app';
import Component, { type ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Icon from 'flarum/common/components/Icon';
import classList from 'flarum/common/utils/classList';
import type Mithril from 'mithril';
import type Hunt from '../../common/models/Hunt';
import type Part from '../../common/models/Part';
import type { PartLayer } from '../../common/models/Part';
import toast from '../utils/toast';
import { bringForward, defaultLayer, layerStyle, layersEqual, moveBy, pointerDelta, resizeBy, sendBackward, type CanvasSize } from './layerMaths';

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

type RedrawableEvent<E extends Event> = E & { redraw?: boolean };

const ARROWS: Record<string, [number, number]> = {
  ArrowLeft: [-1, 0],
  ArrowRight: [1, 0],
  ArrowUp: [0, -1],
  ArrowDown: [0, 1],
};

/**
 * Arranges the Parts on the Collectable canvas. Changes stay local until "Save layout".
 * The canvas is always laid out left to right (see CollectableCanvas), so pointer and
 * arrow-key movements map to x and y as they are, in RTL too.
 */
export default class LayerEditor<CustomAttrs extends ILayerEditorAttrs = ILayerEditorAttrs> extends Component<CustomAttrs> {
  protected changes: Record<string, Change> = {};
  protected aspects: Record<string, number> = {};
  protected selected: string | null = null;
  protected drag: Drag | null = null;
  protected saving = false;
  protected announcement = '';
  protected focusAfterDraw: string | null = null;

  view() {
    // Model#save sets the layer before the request settles, so wait for the save.
    if (!this.saving) this.dropStaleChanges();

    const placed = this.placed();
    const unplaced = this.attrs.parts.filter((part) => !part.imageUrl() || !this.layerOf(part));
    const selected = placed.find((part) => part.id() === this.selected);
    const dirty = this.changed().length > 0;
    const helpId = `LayerEditor-help-${this.attrs.hunt.id()}`;

    return (
      <section className="LayerEditor">
        <h4 className="LayerEditor-title">{app.translator.trans('fof-scavenger-hunt.forum.dashboard.layout.heading')}</h4>
        <p className="helpText" id={helpId}>
          {app.translator.trans('fof-scavenger-hunt.forum.dashboard.layout.help')}
        </p>

        <div className="LayerEditor-body">
          <div className="LayerEditor-canvas" onpointerdown={(e: PointerEvent) => this.deselect(e)}>
            {this.attrs.hunt.imageUrl() && <img className="LayerEditor-base" src={this.attrs.hunt.imageUrl()!} alt="" draggable={false} />}
            {placed.map((part) => this.layer(part, helpId))}
          </div>
          <div className="LayerEditor-status visually-hidden" role="status" aria-live="polite">
            {this.announcement}
          </div>

          <div className="LayerEditor-sidebar">
            {selected && this.selection(selected)}

            {unplaced.length > 0 && (
              <div className="LayerEditor-unplaced">
                <h5>{app.translator.trans('fof-scavenger-hunt.forum.dashboard.layout.unplaced')}</h5>
                <ul className="LayerEditor-unplacedList">
                  {unplaced.map((part) =>
                    part.imageUrl() ? (
                      <li key={part.id()} className="LayerEditor-unplacedItem">
                        <img className="LayerEditor-thumbnail" src={part.imageUrl()!} alt="" onload={(e: Event) => this.measure(part, e)} />
                        <span className="LayerEditor-unplacedName">{part.name()}</span>
                        <Button className="Button Button--text LayerEditor-place" icon="fas fa-plus" onclick={() => this.place(part)}>
                          {app.translator.trans('fof-scavenger-hunt.forum.dashboard.layout.place')}
                        </Button>
                      </li>
                    ) : (
                      <li key={part.id()} className="LayerEditor-unplacedItem LayerEditor-unplacedItem--noImage">
                        <Icon name="far fa-image" className="LayerEditor-thumbnail" />
                        <span className="LayerEditor-unplacedName">{part.name()}</span>
                        <span className="LayerEditor-noImage helpText">
                          {app.translator.trans('fof-scavenger-hunt.forum.dashboard.layout.no_image')}
                        </span>
                      </li>
                    )
                  )}
                </ul>
              </div>
            )}
          </div>
        </div>

        <div className="LayerEditor-actions">
          <Button
            className="Button Button--primary LayerEditor-save"
            icon="fas fa-check"
            disabled={!dirty}
            loading={this.saving}
            onclick={() => this.save()}
          >
            {app.translator.trans('fof-scavenger-hunt.forum.dashboard.layout.save')}
          </Button>
          <Button className="Button LayerEditor-reset" disabled={!dirty || this.saving} onclick={() => this.reset()}>
            {app.translator.trans('fof-scavenger-hunt.forum.dashboard.layout.reset')}
          </Button>
        </div>
      </section>
    );
  }

  onupdate(vnode: Mithril.VnodeDOM<CustomAttrs, this>) {
    super.onupdate(vnode);

    if (this.focusAfterDraw) {
      this.element.querySelector<HTMLElement>(`.LayerEditor-layer[data-part-id="${this.focusAfterDraw}"]`)?.focus();
      this.focusAfterDraw = null;
    }
  }

  protected layer(part: Part, helpId: string): Mithril.Children {
    const id = part.id()!;
    const layer = this.layerOf(part)!;
    const isSelected = this.selected === id;

    return (
      <div
        key={id}
        className={classList('LayerEditor-layer', { 'LayerEditor-layer--selected': isSelected })}
        data-part-id={id}
        role="group"
        tabindex="0"
        aria-roledescription={app.translator.trans('fof-scavenger-hunt.forum.dashboard.layout.movable_part', {}, true)}
        aria-label={part.name()}
        aria-describedby={helpId}
        style={layerStyle(layer)}
        onfocus={() => (this.selected = id)}
        onkeydown={(e: KeyboardEvent) => this.nudge(e, part)}
        onpointerdown={(e: PointerEvent) => this.startDrag(e, part)}
        onpointermove={(e: PointerEvent) => this.moveDrag(e, part)}
        onpointerup={(e: PointerEvent) => this.endDrag(e, part)}
        onpointercancel={(e: PointerEvent) => this.endDrag(e, part)}
      >
        <img src={part.imageUrl()!} alt="" draggable={false} onload={(e: Event) => this.measure(part, e)} />
        {isSelected && <span className="LayerEditor-handle" aria-hidden="true" />}
      </div>
    );
  }

  protected selection(part: Part): Mithril.Children {
    const layer = this.layerOf(part)!;
    const others = this.otherLayers(part);
    const forward = bringForward(layer, others);
    const backward = sendBackward(layer, others);

    return (
      <div className="LayerEditor-selection">
        <h5 className="LayerEditor-selectionName">{part.name()}</h5>
        <div className="LayerEditor-zButtons">
          <Button
            className="Button LayerEditor-forward"
            icon="fas fa-arrow-up"
            disabled={this.saving || layersEqual(forward, layer)}
            onclick={() => this.restack(part, forward)}
          >
            {app.translator.trans('fof-scavenger-hunt.forum.dashboard.layout.forward')}
          </Button>
          <Button
            className="Button LayerEditor-backward"
            icon="fas fa-arrow-down"
            disabled={this.saving || layersEqual(backward, layer)}
            onclick={() => this.restack(part, backward)}
          >
            {app.translator.trans('fof-scavenger-hunt.forum.dashboard.layout.backward')}
          </Button>
        </div>
      </div>
    );
  }

  /** Parts drawn on the canvas. */
  protected placed(): Part[] {
    return this.attrs.parts.filter((part) => part.imageUrl() && this.layerOf(part));
  }

  protected otherLayers(part: Part): PartLayer[] {
    return this.placed()
      .filter((other) => other !== part)
      .map((other) => this.layerOf(other)!);
  }

  protected layerOf(part: Part): PartLayer | null {
    return this.changes[part.id()!]?.layer ?? part.layer() ?? null;
  }

  protected update(part: Part, layer: PartLayer): void {
    const id = part.id()!;
    const base = part.layer() ?? null;

    if (layersEqual(layer, base)) {
      delete this.changes[id];
    } else {
      this.changes[id] = { layer, base };
    }
  }

  /** A Part saved elsewhere since it was changed here (e.g. in the Part editor) shows what was saved. */
  protected dropStaleChanges(): void {
    this.attrs.parts.forEach((part) => {
      const change = this.changes[part.id()!];

      if (change && !layersEqual(change.base, part.layer() ?? null)) delete this.changes[part.id()!];
    });
  }

  protected changed(): Part[] {
    return this.attrs.parts.filter((part) => this.changes[part.id()!]);
  }

  protected restack(part: Part, layer: PartLayer): void {
    if (this.saving) return;

    this.update(part, layer);
    this.announce(part);
  }

  protected announce(part: Part): void {
    this.announcement = app.translator.trans(
      'fof-scavenger-hunt.forum.dashboard.layout.position',
      { name: part.name(), ...this.layerOf(part)! },
      true
    );
  }

  protected measure(part: Part, e: Event): void {
    const image = e.currentTarget as HTMLImageElement;

    if (image.naturalWidth > 0) {
      this.aspects[part.id()!] = image.naturalHeight / image.naturalWidth;
    }
  }

  protected place(part: Part): void {
    const existing = this.attrs.parts.map((other) => this.layerOf(other)).filter((layer): layer is PartLayer => layer !== null);

    this.update(part, defaultLayer(existing, this.aspects[part.id()!]));
    this.selected = part.id()!;
    // The Place button is gone after this redraw.
    this.focusAfterDraw = part.id()!;
  }

  protected deselect(e: RedrawableEvent<PointerEvent>): void {
    if ((e.target as Element).closest('.LayerEditor-layer')) {
      e.redraw = false;
      return;
    }

    this.selected = null;
  }

  protected nudge(e: RedrawableEvent<KeyboardEvent>, part: Part): void {
    const arrow = ARROWS[e.key];

    if (!arrow || e.altKey || e.ctrlKey || e.metaKey || this.saving) {
      e.redraw = false;
      return;
    }

    e.preventDefault();

    const step = e.shiftKey ? 5 : 1;

    this.selected = part.id()!;
    this.update(part, moveBy(this.layerOf(part)!, arrow[0] * step, arrow[1] * step, this.aspects[part.id()!]));
    this.announce(part);
  }

  protected startDrag(e: PointerEvent, part: Part): void {
    if (e.button > 0 || this.saving) return;

    const element = e.currentTarget as HTMLElement;
    const canvas = element.parentElement!.getBoundingClientRect();

    e.preventDefault();
    element.focus?.();
    element.setPointerCapture?.(e.pointerId);

    this.selected = part.id()!;
    this.drag = {
      partId: part.id()!,
      mode: (e.target as Element).closest('.LayerEditor-handle') ? 'resize' : 'move',
      pointerId: e.pointerId,
      startX: e.clientX,
      startY: e.clientY,
      start: this.layerOf(part)!,
      canvas: { width: canvas.width, height: canvas.height },
    };
  }

  protected moveDrag(e: RedrawableEvent<PointerEvent>, part: Part): void {
    const drag = this.drag;

    if (!drag || drag.pointerId !== e.pointerId || drag.partId !== part.id()) {
      e.redraw = false;
      return;
    }

    const { dx, dy } = pointerDelta(e.clientX - drag.startX, e.clientY - drag.startY, drag.canvas);
    const aspect = this.aspects[drag.partId];

    this.update(part, drag.mode === 'move' ? moveBy(drag.start, dx, dy, aspect) : resizeBy(drag.start, dx, aspect));
  }

  protected endDrag(e: PointerEvent, part: Part): void {
    if (!this.drag || this.drag.pointerId !== e.pointerId) return;

    (e.currentTarget as HTMLElement).releasePointerCapture?.(e.pointerId);

    if (!layersEqual(this.drag.start, this.layerOf(part))) this.announce(part);
    this.drag = null;
  }

  protected reset(): void {
    this.changes = {};
    this.drag = null;
  }

  protected save(): void {
    const parts = this.changed();

    this.saving = true;

    Promise.allSettled(parts.map((part) => part.save({ layer: this.changes[part.id()!].layer })))
      .then((results) => {
        // Failed ones keep their local change.
        results.forEach((result, index) => {
          if (result.status === 'fulfilled') delete this.changes[parts[index].id()!];
        });

        if (results.every((result) => result.status === 'fulfilled')) {
          toast(app.translator.trans('fof-scavenger-hunt.forum.dashboard.layout.saved'));
        }
      })
      .finally(() => {
        this.saving = false;
        m.redraw();
      });
  }
}
