import app from 'flarum/forum/app';
import Modal, { type IInternalModalAttrs } from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import Icon from 'flarum/common/components/Icon';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Stream from 'flarum/common/utils/Stream';
import type { ApiPayloadSingle } from 'flarum/common/Store';
import type RequestError from 'flarum/common/utils/RequestError';
import type Mithril from 'mithril';
import type HidingSpotDefinition from '../../common/models/HidingSpotDefinition';
import type Hunt from '../../common/models/Hunt';
import type Location from '../../common/models/Location';
import type Part from '../../common/models/Part';
import toast from '../utils/toast';
import LocationFields, { ANY, locationValue, type LocationFieldStreams, type LocationValue, type PageRoute } from './LocationFields';

export interface IHidePartsModalAttrs extends IInternalModalAttrs {
  hunt: Hunt;
  parts: Part[];
  spots: HidingSpotDefinition[];
  routes: PageRoute[];
  /** Called with the ids of the Parts whose places were replaced. */
  onsaved?: (partIds: string[]) => void;
}

export interface SuggestedPlace extends LocationValue {
  label: string;
  url: string | null;
  fallback: boolean;
}

interface Slot {
  key: number;
  place: SuggestedPlace;
  /** Set once the manager chooses the place themselves. */
  fields: LocationFieldStreams | null;
  picking: boolean;
}

interface Row {
  part: Part;
  /** Kept rows leave the Part's saved Locations alone; proposed rows replace them. */
  kept: boolean;
  slots: Slot[];
  loading: boolean;
}

type PlaceRef = Pick<LocationValue, 'spot' | 'contextType' | 'contextId'>;

let nextKey = 0;

/**
 * Proposes hiding places for a Hunt's Parts, lets the manager swap or choose any of them, and saves the reviewed set.
 */
export default class HidePartsModal extends Modal<IHidePartsModalAttrs> {
  protected rows: Row[] = [];
  protected failed: Row[] = [];

  oninit(vnode: Mithril.Vnode<IHidePartsModalAttrs, this>) {
    super.oninit(vnode);

    this.rows = this.attrs.parts.map((part) => ({ part, kept: this.savedLocations(part).length > 0, slots: [], loading: false }));

    const unplaced = this.rows.filter((row) => !row.kept);

    if (unplaced.length) this.suggest(unplaced);
  }

  className() {
    return 'HidePartsModal Modal--large';
  }

  title() {
    return app.translator.trans('fof-scavenger-hunt.forum.dashboard.hide_for_me.title');
  }

  content() {
    const proposed = this.proposed();
    const busy = this.rows.some((row) => row.loading || row.slots.some((slot) => slot.picking));
    const fallback = this.rows.some((row) => !row.kept && row.slots.some((slot) => !slot.fields && slot.place.fallback));

    return [
      <div className="Modal-body">
        {this.failed.length > 0 && (
          <div className="HidePartsModal-error">
            <p>{app.translator.trans('fof-scavenger-hunt.forum.dashboard.hide_for_me.load_failed')}</p>
            <Button className="Button HidePartsModal-retry" icon="fas fa-redo" onclick={() => this.suggest(this.failed)}>
              {app.translator.trans('fof-scavenger-hunt.forum.dashboard.hide_for_me.retry')}
            </Button>
          </div>
        )}

        {fallback && (
          <p className="HidePartsModal-fallback helpText">{app.translator.trans('fof-scavenger-hunt.forum.dashboard.hide_for_me.fallback_note')}</p>
        )}

        {!busy && !this.failed.length && !proposed.length && (
          <p className="HidePartsModal-nothing helpText">{app.translator.trans('fof-scavenger-hunt.forum.dashboard.hide_for_me.nothing_to_hide')}</p>
        )}

        <ul className="HidePartsModal-rows">{this.rows.map((row) => this.rowView(row))}</ul>
      </div>,
      <div className="Modal-footer HidePartsModal-footer">
        <Button className="Button HidePartsModal-cancel" onclick={() => this.hide()}>
          {app.translator.trans('fof-scavenger-hunt.forum.dashboard.hide_for_me.cancel')}
        </Button>
        <Button
          className="Button Button--primary HidePartsModal-submit"
          loading={this.loading}
          disabled={busy || !proposed.length}
          onclick={() => this.submit()}
        >
          {app.translator.trans('fof-scavenger-hunt.forum.dashboard.hide_for_me.use_these_places')}
        </Button>
      </div>,
    ];
  }

  protected rowView(row: Row): Mithril.Children {
    const part = row.part;
    const mode = part.mode() ?? 'fixed';

    return (
      <li className="HidePartsModal-row" key={part.id()} data-part-id={part.id()}>
        <div className="HidePartsModal-part">
          {part.imageUrl() ? (
            <img className="HidePartsModal-image" src={part.imageUrl()!} alt="" />
          ) : (
            <Icon name="fas fa-puzzle-piece" className="HidePartsModal-image" />
          )}
          <div className="HidePartsModal-partText">
            <strong className="HidePartsModal-name">{part.name()}</strong>
            <span className="HidePartsModal-mode">{app.translator.trans(`fof-scavenger-hunt.forum.dashboard.parts.modes.${mode}`)}</span>
          </div>
        </div>

        <div className="HidePartsModal-places">{this.placesView(row)}</div>
      </li>
    );
  }

  protected placesView(row: Row): Mithril.Children {
    if (row.loading) return <LoadingIndicator size="small" display="inline" />;

    if (row.kept) {
      return (
        <div className="HidePartsModal-keep">
          <span className="HidePartsModal-keepLabel">
            {app.translator.trans('fof-scavenger-hunt.forum.dashboard.hide_for_me.keep_current', { count: this.savedLocations(row.part).length })}
          </span>
          <Button className="Button HidePartsModal-pickForMe" icon="fas fa-wand-magic-sparkles" onclick={() => this.suggest([row])}>
            {app.translator.trans('fof-scavenger-hunt.forum.dashboard.hide_for_me.pick_for_me')}
          </Button>
        </div>
      );
    }

    return <ol className="HidePartsModal-placeList">{row.slots.map((slot) => this.slotView(row, slot))}</ol>;
  }

  protected slotView(row: Row, slot: Slot): Mithril.Children {
    const place = slot.place;

    return (
      <li className="HidePartsModal-place" key={slot.key}>
        {slot.fields ? (
          <div className="HidePartsModal-fields">
            <LocationFields fields={slot.fields} spots={this.attrs.spots} routes={this.attrs.routes} />
          </div>
        ) : (
          <span className="HidePartsModal-placeLabel">{place.label}</span>
        )}
        <div className="HidePartsModal-placeActions">
          {!slot.fields && place.url && (
            <a className="Button HidePartsModal-open" href={place.url} target="_blank" rel="noopener">
              <Icon name="fas fa-external-link-alt" className="Button-icon" />
              <span className="Button-label">{app.translator.trans('fof-scavenger-hunt.forum.dashboard.hide_for_me.open')}</span>
            </a>
          )}
          <Button className="Button HidePartsModal-pickAnother" icon="fas fa-dice" loading={slot.picking} onclick={() => this.pickAnother(row, slot)}>
            {app.translator.trans('fof-scavenger-hunt.forum.dashboard.hide_for_me.pick_another')}
          </Button>
          {!slot.fields && (
            <Button className="Button HidePartsModal-chooseMyself" icon="fas fa-pen" onclick={() => this.chooseMyself(slot)}>
              {app.translator.trans('fof-scavenger-hunt.forum.dashboard.hide_for_me.choose_myself')}
            </Button>
          )}
        </div>
      </li>
    );
  }

  protected savedLocations(part: Part): Location[] {
    return ((part.locations() || []).filter(Boolean) as Location[]).slice();
  }

  protected proposed(): Row[] {
    return this.rows.filter((row) => !row.kept && !row.loading && row.slots.length > 0);
  }

  protected slotValue(slot: Slot): LocationValue {
    if (slot.fields) return locationValue(slot.fields, this.attrs.spots);

    const { spot, route, contextType, contextId } = slot.place;

    return { spot, route, contextType, contextId };
  }

  /** Every place the review would use or keep, apart from those of the given rows. */
  protected placesInUse(except: Row[] = []): PlaceRef[] {
    return this.rows
      .filter((row) => !except.includes(row))
      .flatMap((row) =>
        row.kept
          ? this.savedLocations(row.part).map((location) => ({
              spot: location.spot(),
              contextType: location.contextType(),
              contextId: location.contextId(),
            }))
          : row.slots.map((slot) => this.slotValue(slot))
      )
      .map(({ spot, contextType, contextId }) => ({ spot, contextType: contextType ?? null, contextId: contextId ?? null }));
  }

  protected request(parts: Part[], exclude: PlaceRef[]): Promise<{ data: { partId: string; places: SuggestedPlace[] }[] }> {
    return app.request({
      method: 'POST',
      url: `${app.forum.attribute('apiUrl')}/scavenger-hunts/${this.attrs.hunt.id()}/hiding-suggestions`,
      body: { parts: parts.map((part) => part.id()), exclude },
    });
  }

  protected suggest(rows: Row[]): void {
    const exclude = this.placesInUse(rows);

    this.failed = this.failed.filter((row) => !rows.includes(row));
    rows.forEach((row) => (row.loading = true));

    this.request(
      rows.map((row) => row.part),
      exclude
    )
      .then((response) => {
        rows.forEach((row) => {
          const places = response.data.find((entry) => String(entry.partId) === row.part.id())?.places ?? [];

          row.kept = false;
          row.slots = places.map((place) => ({ key: ++nextKey, place, fields: null, picking: false }));
        });
      })
      .catch(() => (this.failed = [...this.failed, ...rows]))
      .finally(() => {
        rows.forEach((row) => (row.loading = false));
        m.redraw();
      });
  }

  protected pickAnother(row: Row, slot: Slot): void {
    // The place being replaced is excluded too, so the new pick is a different one.
    const exclude = this.placesInUse();

    slot.picking = true;

    this.request([row.part], exclude)
      .then((response) => {
        const place = response.data[0]?.places[0];

        if (!place) return;

        row.slots = row.slots.map((candidate) => (candidate === slot ? { key: ++nextKey, place, fields: null, picking: false } : candidate));
      })
      .catch(() => {})
      .finally(() => {
        slot.picking = false;
        m.redraw();
      });
  }

  protected chooseMyself(slot: Slot): void {
    const place = slot.place;

    slot.fields = {
      spot: Stream(place.spot),
      route: Stream(place.route ?? ANY),
      contextId: Stream(place.contextId ? String(place.contextId) : ''),
    };
  }

  protected submit(): void {
    const proposed = this.proposed();

    this.loading = true;
    this.alertAttrs = null;

    app
      .request<ApiPayloadSingle>({
        method: 'POST',
        url: `${app.forum.attribute('apiUrl')}/scavenger-hunts/${this.attrs.hunt.id()}/hiding-places`,
        body: { parts: proposed.map((row) => ({ id: row.part.id(), locations: row.slots.map((slot) => this.slotValue(slot)) })) },
        errorHandler: (error: RequestError) => {
          this.alertAttrs = error.alert;
        },
      })
      .then((payload) => {
        app.store.pushPayload(payload);
        this.attrs.onsaved?.(proposed.map((row) => row.part.id()!));
        this.hide();
        toast(app.translator.trans('fof-scavenger-hunt.forum.dashboard.saved'));
      })
      .catch(() => {})
      .finally(() => {
        this.loading = false;
        m.redraw();
      });
  }
}
