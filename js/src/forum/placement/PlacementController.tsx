import app from 'flarum/forum/app';
import type HidingSpotDefinition from '../../common/models/HidingSpotDefinition';
import type Hunt from '../../common/models/Hunt';
import type Part from '../../common/models/Part';
import type { HidingSpotAttrs } from '../components/HidingSpot';
import { encounters, type SpotReport } from '../states/EncounterState';
import { PLACEMENT_STORAGE_KEY, placement, type PlacementTarget } from '../states/placement';
import PlacementTargetButton from './PlacementTargetButton';
import PlacementToolbar from './PlacementToolbar';

/**
 * "Hide it here" mode: every Hiding spot on the forum becomes a target; clicking one hides the
 * chosen Part there. Lookups pause while it is on, and it survives navigation and reloads.
 */
export class PlacementController {
  hunts: Hunt[] = [];
  spots: HidingSpotDefinition[] = [];
  pageRoutes: string[] = [];
  routeAliases: Record<string, string> = {};
  huntId: string | null = null;
  partId: string | null = null;

  protected toolbar: HTMLElement | null = null;

  async start(target: PlacementTarget): Promise<void> {
    if (!this.hunts.length) {
      const [hunts, spots] = await Promise.all([
        app.store.find<Hunt[]>('scavenger-hunts', { include: 'parts', sort: '-createdAt', page: { limit: 50 } }),
        app.store.find<HidingSpotDefinition[]>('scavenger-hunt-spots'),
      ]);

      const meta = (spots.payload as { meta?: { routes?: { name: string }[]; routeAliases?: Record<string, string> } }).meta ?? {};

      this.hunts = hunts;
      this.spots = spots;
      this.pageRoutes = (meta.routes ?? []).map((route) => route.name);
      this.routeAliases = meta.routeAliases ?? {};
    }

    this.select(target.huntId ?? this.huntId ?? this.hunts[0]?.id() ?? null, target.partId ?? this.partId);

    encounters.paused = true;
    placement.renderer = (attrs: HidingSpotAttrs, report: SpotReport) => <PlacementTargetButton attrs={attrs} report={report} controller={this} />;

    if (!this.toolbar) {
      this.toolbar = document.createElement('div');
      this.toolbar.className = 'PlacementToolbar-mount';
      document.body.append(this.toolbar);
      m.mount(this.toolbar, { view: () => <PlacementToolbar controller={this} /> });
    }

    m.redraw();
  }

  stop(): void {
    placement.renderer = null;
    encounters.paused = false;

    if (this.toolbar) {
      m.mount(this.toolbar, null);
      this.toolbar.remove();
      this.toolbar = null;
    }

    try {
      window.sessionStorage.removeItem(PLACEMENT_STORAGE_KEY);
    } catch {
      // Storage is a convenience; without it the mode simply doesn't survive a reload.
    }

    encounters.schedule();
    m.redraw();
  }

  select(huntId: string | null, partId: string | null): void {
    this.huntId = huntId;

    const parts = this.parts();
    this.partId = parts.some((part) => part.id() === partId) ? partId : (parts[0]?.id() ?? null);

    try {
      window.sessionStorage.setItem(PLACEMENT_STORAGE_KEY, JSON.stringify({ huntId: this.huntId, partId: this.partId }));
    } catch {
      // See stop().
    }
  }

  hunt(): Hunt | undefined {
    return this.hunts.find((hunt) => hunt.id() === this.huntId);
  }

  parts(): Part[] {
    return ((this.hunt()?.parts() || []).filter(Boolean) as Part[]).sort((a, b) => a.sort() - b.sort());
  }

  part(): Part | undefined {
    return this.parts().find((part) => part.id() === this.partId);
  }

  spot(key: string): HidingSpotDefinition | undefined {
    return this.spots.find((spot) => spot.id() === key);
  }

  /**
   * The forum route for the current page, or null if it isn't a page Hunt managers can pick.
   */
  currentForumRoute(): string | null {
    const route = encounters.currentRoute();
    const canonical = this.routeAliases[route] ?? route;

    return this.pageRoutes.includes(canonical) ? canonical : null;
  }
}

// Assigned to a const first: flarum-webpack-config's autoExportLoader reads a default-exported
// `new` expression as an identifier named "new" and emits invalid code.
const controller = new PlacementController();

export default controller;
