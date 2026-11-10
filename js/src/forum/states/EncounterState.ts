import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import Drawer from 'flarum/common/utils/Drawer';
import type { ApiPayloadPlural } from 'flarum/common/Store';
import type Encounter from '../../common/models/Encounter';
import { activeHunts } from '../utils/activeHunts';

export interface SpotReport {
  spot: string;
  contextType?: string;
  contextId?: number;
}

/** Whether a mounted spot is currently out of sight (e.g. in the closed phone drawer). */
export type HiddenCheck = () => boolean;

const inSight: HiddenCheck = () => false;

export function reportKey(report: SpotReport): string {
  return [report.spot, report.contextType ?? '', report.contextId ?? ''].join('|');
}

/**
 * Collects the Hiding spots drawn on the current page and asks the server, once per page view,
 * which Parts appear in them. Spots that mount later (more posts while scrolling) get their own
 * lookup; spots already reported for this page are never reported again. A spot that is out of
 * sight is held back until a later lookup finds it in sight.
 */
export default class EncounterState {
  static readonly DEBOUNCE_MS = 500;

  /**
   * While true (e.g. in "hide it here" mode) nothing is looked up.
   */
  paused = false;

  protected mounted = new Map<string, { report: SpotReport; checks: HiddenCheck[] }>();
  protected reported = new Set<string>();
  protected encounters = new Map<string, Encounter>();
  protected page = '';
  protected route = '';
  protected params: Record<string, unknown> = {};
  protected timer: ReturnType<typeof setTimeout> | null = null;

  isActive(): boolean {
    // Hunt managers see every hiding place on the dashboard, so Parts there would only cost lookups.
    return !this.paused && activeHunts().length > 0 && !this.route.startsWith('fof-scavenger-hunt.dashboard');
  }

  register(report: SpotReport, isHidden: HiddenCheck = inSight): void {
    const key = reportKey(report);
    const entry = this.mounted.get(key);

    if (entry) {
      entry.checks.push(isHidden);
    } else {
      this.mounted.set(key, { report, checks: [isHidden] });
    }

    this.schedule();
  }

  unregister(report: SpotReport, isHidden: HiddenCheck = inSight): void {
    const key = reportKey(report);
    const entry = this.mounted.get(key);

    if (!entry) {
      return;
    }

    const index = entry.checks.indexOf(isHidden);
    entry.checks.splice(index === -1 ? 0 : index, 1);

    if (!entry.checks.length) {
      this.mounted.delete(key);
    }
  }

  pageChanged(route: string, params: Record<string, unknown>): void {
    const page = route + JSON.stringify(params);

    if (page === this.page) {
      return;
    }

    this.page = page;
    this.route = route;
    this.params = params;
    this.reported.clear();
    this.encounters.clear();
    this.schedule();
  }

  currentRoute(): string {
    return this.route;
  }

  currentParams(): Record<string, unknown> {
    return this.params;
  }

  encounterAt(report: SpotReport): Encounter | undefined {
    return this.encounters.get(reportKey(report));
  }

  forget(encounter: Encounter): void {
    for (const [key, candidate] of this.encounters) {
      if (candidate === encounter) {
        this.encounters.delete(key);
      }
    }
  }

  pendingReports(): SpotReport[] {
    return [...this.mounted.entries()]
      .filter(([key, entry]) => !this.reported.has(key) && !entry.checks.every((isHidden) => isHidden()))
      .map(([, entry]) => entry.report);
  }

  schedule(): void {
    if (!this.isActive()) {
      return;
    }

    if (this.timer) {
      clearTimeout(this.timer);
    }

    this.timer = setTimeout(() => {
      this.timer = null;
      this.lookup();
    }, EncounterState.DEBOUNCE_MS);
  }

  async lookup(): Promise<void> {
    const reports = this.pendingReports();

    if (!reports.length || !this.isActive()) {
      return;
    }

    reports.forEach((report) => this.reported.add(reportKey(report)));

    const page = this.page;

    const payload = await app
      .request<ApiPayloadPlural>({
        method: 'POST',
        url: `${app.forum.attribute('apiUrl')}/scavenger-hunt-encounters`,
        body: { data: { attributes: { route: this.route, routeParams: this.params, spots: reports } } },
        // A refused lookup (rate limit, network) just means nothing appears; never alert.
        errorHandler: () => {},
      })
      .catch(() => null);

    if (!payload || page !== this.page) {
      return;
    }

    const encounters = app.store.pushPayload<Encounter[]>(payload);

    encounters.forEach((encounter) => {
      this.encounters.set(
        reportKey({ spot: encounter.spot(), contextType: encounter.contextType() ?? undefined, contextId: encounter.contextId() ?? undefined }),
        encounter
      );
    });

    if (encounters.length) {
      m.redraw();
    }
  }
}

export const encounters = new EncounterState();

/**
 * Spots held back in the closed phone drawer get their lookup when the drawer opens.
 */
export function lookUpWhenDrawerOpens(): void {
  extend(Drawer.prototype, 'show', () => encounters.schedule());
}
