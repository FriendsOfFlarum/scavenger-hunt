import app from 'flarum/forum/app';
import type { ApiPayloadPlural } from 'flarum/common/Store';
import type Encounter from '../../common/models/Encounter';
import { activeHunts } from '../utils/activeHunts';

export interface SpotReport {
  spot: string;
  contextType?: string;
  contextId?: number;
}

export function reportKey(report: SpotReport): string {
  return [report.spot, report.contextType ?? '', report.contextId ?? ''].join('|');
}

/**
 * Collects the Hiding spots drawn on the current page and asks the server, once per page view,
 * which Parts appear in them. Spots that mount later (more posts while scrolling) get their own
 * lookup; spots already reported for this page are never reported again.
 */
export default class EncounterState {
  static readonly DEBOUNCE_MS = 500;

  /**
   * While true (e.g. in "hide it here" mode) nothing is looked up.
   */
  paused = false;

  protected mounted = new Map<string, { report: SpotReport; count: number }>();
  protected reported = new Set<string>();
  protected encounters = new Map<string, Encounter>();
  protected page = '';
  protected route = '';
  protected params: Record<string, unknown> = {};
  protected timer: ReturnType<typeof setTimeout> | null = null;

  isActive(): boolean {
    return !this.paused && activeHunts().length > 0;
  }

  register(report: SpotReport): void {
    const key = reportKey(report);
    const entry = this.mounted.get(key);

    if (entry) {
      entry.count++;
    } else {
      this.mounted.set(key, { report, count: 1 });
    }

    this.schedule();
  }

  unregister(report: SpotReport): void {
    const key = reportKey(report);
    const entry = this.mounted.get(key);

    if (entry && --entry.count <= 0) {
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
    return [...this.mounted.entries()].filter(([key]) => !this.reported.has(key)).map(([, entry]) => entry.report);
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
