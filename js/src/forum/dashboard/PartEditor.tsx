import app from 'flarum/forum/app';
import toast from '../utils/toast';
import Component, { type ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Icon from 'flarum/common/components/Icon';
import Select from 'flarum/common/components/Select';
import Stream from 'flarum/common/utils/Stream';
import type Mithril from 'mithril';
import type HidingSpotDefinition from '../../common/models/HidingSpotDefinition';
import type Location from '../../common/models/Location';
import type Part from '../../common/models/Part';
import type { PlacementMode } from '../../common/models/Part';
import ImageField from './ImageField';
import LocationRow, { type PageRoute } from './LocationRow';
import { placement } from '../states/placement';

export interface PartEditorAttrs extends ComponentAttrs {
  part: Part;
  spots: HidingSpotDefinition[];
  routes: PageRoute[];
  ondeleted: (part: Part) => void;
}

let unsavedKeys = 0;
const keys = new WeakMap<Location, string>();

/** A stable vnode key for a Location, including ones not saved yet. */
function keyFor(location: Location): string {
  if (location.id()) return location.id()!;
  if (!keys.has(location)) keys.set(location, `new-${++unsavedKeys}`);

  return keys.get(location)!;
}

export default class PartEditor extends Component<PartEditorAttrs> {
  protected saving = false;
  protected name!: Stream<string>;
  protected mode!: Stream<PlacementMode>;
  protected hint!: Stream<string>;
  protected wanderChance!: Stream<string>;
  protected sort!: Stream<string>;
  protected image!: Stream<string | null>;
  protected layer!: { x: Stream<string>; y: Stream<string>; width: Stream<string>; z: Stream<string> };
  protected syncedLayer = '';
  protected locations: Location[] = [];

  oninit(vnode: Mithril.Vnode<PartEditorAttrs, this>) {
    super.oninit(vnode);

    const part = this.attrs.part;

    this.name = Stream(part.name());
    this.mode = Stream(part.mode() ?? 'fixed');
    this.hint = Stream(part.hint() ?? '');
    this.wanderChance = Stream(part.wanderChance() ? String(part.wanderChance()) : '');
    this.sort = Stream(String(part.sort() ?? 0));
    this.image = Stream(part.image() ?? null);
    this.layer = { x: Stream(''), y: Stream(''), width: Stream(''), z: Stream('') };
    this.syncLayer();
    this.locations = ((part.locations() || []).filter(Boolean) as Location[]).slice();
  }

  onbeforeupdate(vnode: Mithril.VnodeDOM<PartEditorAttrs, this>) {
    super.onbeforeupdate(vnode);

    // The layer editor saves layers too: show the saved values.
    if (JSON.stringify(this.attrs.part.layer() ?? null) !== this.syncedLayer) {
      this.syncLayer();
    }
  }

  protected syncLayer(): void {
    const layer = this.attrs.part.layer() ?? null;

    this.syncedLayer = JSON.stringify(layer);
    (['x', 'y', 'width', 'z'] as const).forEach((key) => this.layer[key](layer ? String(layer[key]) : ''));
  }

  view() {
    const part = this.attrs.part;
    const modeOptions = {
      fixed: app.translator.trans('fof-scavenger-hunt.forum.dashboard.parts.modes.fixed', {}, true),
      assigned: app.translator.trans('fof-scavenger-hunt.forum.dashboard.parts.modes.assigned', {}, true),
      wandering: app.translator.trans('fof-scavenger-hunt.forum.dashboard.parts.modes.wandering', {}, true),
    };

    return (
      <section className="PartEditor">
        <header className="PartEditor-header">
          {part.imageUrl() ? (
            <img className="PartEditor-image" src={part.imageUrl()!} alt="" />
          ) : (
            <Icon name="fas fa-puzzle-piece" className="PartEditor-image" />
          )}
          <h4 className="PartEditor-title" id={`part-${part.id()}-title`}>
            {part.name()}
          </h4>
          <div className="PartEditor-actions">
            <Button className="Button Button--primary" icon="fas fa-check" loading={this.saving} onclick={() => this.save()}>
              {app.translator.trans('fof-scavenger-hunt.forum.dashboard.save')}
            </Button>
            <Button
              className="Button Button--icon Button--danger"
              icon="fas fa-trash"
              aria-label={app.translator.trans('fof-scavenger-hunt.forum.dashboard.parts.delete', {}, true)}
              onclick={() => this.delete()}
            />
          </div>
        </header>

        <div className="PartEditor-fields">
          <div className="Form-group">
            <label>{app.translator.trans('fof-scavenger-hunt.forum.dashboard.parts.name')}</label>
            <input className="FormControl" bidi={this.name} maxlength={100} />
          </div>

          <div className="Form-group">
            <label>{app.translator.trans('fof-scavenger-hunt.forum.dashboard.parts.mode')}</label>
            <Select options={modeOptions} value={this.mode()} onchange={(value: PlacementMode) => this.mode(value)} />
          </div>

          {this.mode() === 'fixed' && (
            <div className="Form-group PartEditor-hint">
              <label>{app.translator.trans('fof-scavenger-hunt.forum.dashboard.parts.hint')}</label>
              <input className="FormControl" bidi={this.hint} maxlength={255} />
            </div>
          )}

          {this.mode() === 'wandering' && (
            <div className="Form-group">
              <label>{app.translator.trans('fof-scavenger-hunt.forum.dashboard.parts.wander_chance')}</label>
              <input className="FormControl" type="number" min="1" max="100" bidi={this.wanderChance} />
            </div>
          )}

          <div className="Form-group">
            <label>{app.translator.trans('fof-scavenger-hunt.forum.dashboard.parts.sort')}</label>
            <input className="FormControl" type="number" min="0" bidi={this.sort} />
          </div>

          <div className="helpText PartEditor-modeHelp">
            {this.mode() === 'fixed'
              ? app.translator.trans('fof-scavenger-hunt.forum.dashboard.parts.hint_help')
              : app.translator.trans('fof-scavenger-hunt.forum.dashboard.parts.random_recommended')}
          </div>
        </div>

        <details className="PartEditor-appearance">
          <summary>{app.translator.trans('fof-scavenger-hunt.forum.dashboard.parts.appearance')}</summary>

          <div className="PartEditor-fields">
            <div className="Form-group PartEditor-imageField">
              <label id={`part-${part.id()}-image`}>{app.translator.trans('fof-scavenger-hunt.forum.dashboard.parts.image')}</label>
              <ImageField stream={this.image} url={part.imageUrl()} labelledby={`part-${part.id()}-title part-${part.id()}-image`} />
            </div>

            {(['x', 'y', 'width', 'z'] as const).map((key) => (
              <div className="Form-group PartEditor-layerField">
                <label>{app.translator.trans(`fof-scavenger-hunt.forum.dashboard.parts.layer_${key}`)}</label>
                <input className="FormControl" type="number" step="any" min="0" bidi={this.layer[key]} />
              </div>
            ))}
          </div>

          <div className="helpText">{app.translator.trans('fof-scavenger-hunt.forum.dashboard.parts.layer_help')}</div>
        </details>

        <div className="PartEditor-locations">
          <h5>{app.translator.trans('fof-scavenger-hunt.forum.dashboard.locations.heading')}</h5>
          {this.locations.length === 0 && <p className="helpText">{app.translator.trans('fof-scavenger-hunt.forum.dashboard.locations.none')}</p>}
          {this.locations.map((location) => (
            <LocationRow
              key={keyFor(location)}
              location={location}
              part={part}
              spots={this.attrs.spots}
              routes={this.attrs.routes}
              ondeleted={(deleted: Location) => (this.locations = this.locations.filter((candidate) => candidate !== deleted))}
            />
          ))}
          <div className="PartEditor-locationActions">
            <Button
              className="Button"
              icon="fas fa-plus"
              onclick={() => this.locations.push(app.store.createRecord<Location>('scavenger-hunt-locations'))}
            >
              {app.translator.trans('fof-scavenger-hunt.forum.dashboard.locations.add')}
            </Button>
            <Button className="Button" icon="fas fa-map-pin" onclick={() => this.placeOnForum()}>
              {app.translator.trans('fof-scavenger-hunt.forum.dashboard.locations.place_on_forum')}
            </Button>
          </div>
        </div>
      </section>
    );
  }

  protected save(): void {
    const layer = this.layer;
    const hasLayer = [layer.x(), layer.y(), layer.width(), layer.z()].every((value) => value !== '');

    this.saving = true;

    this.attrs.part
      .save({
        name: this.name(),
        mode: this.mode(),
        hint: this.mode() === 'fixed' ? this.hint() || null : null,
        wanderChance: this.mode() === 'wandering' ? Number(this.wanderChance()) || null : null,
        image: this.image(),
        sort: Number(this.sort()) || 0,
        layer: hasLayer ? { x: Number(layer.x()), y: Number(layer.y()), width: Number(layer.width()), z: Number(layer.z()) } : null,
      })
      .then(() => toast(app.translator.trans('fof-scavenger-hunt.forum.dashboard.saved')))
      .catch(() => {})
      .finally(() => {
        this.saving = false;
        m.redraw();
      });
  }

  protected placeOnForum(): void {
    const part = this.attrs.part;
    const hunt = part.hunt();

    placement.start({ huntId: hunt ? hunt.id() : undefined, partId: part.id() }).then(() => m.route.set(app.route('index')));
  }

  protected delete(): void {
    if (!confirm(app.translator.trans('fof-scavenger-hunt.forum.dashboard.parts.delete_confirm', { part: this.attrs.part.name() }, true))) {
      return;
    }

    this.attrs.part.delete().then(() => {
      this.attrs.ondeleted(this.attrs.part);
      m.redraw();
    });
  }
}
