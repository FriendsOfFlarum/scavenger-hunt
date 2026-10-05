import type { SpotReport } from '../states/EncounterState';

export interface PlacementChoice {
  exactItem: boolean;
  thisPage: boolean;
}

/**
 * The Location a "hide it here" click creates. `route` must already be the forum's route name
 * (aliases resolved), or null when the page isn't one the forum offers.
 */
export default function locationAttributes(report: SpotReport, choice: PlacementChoice, route: string | null): Record<string, unknown> {
  return {
    spot: report.spot,
    route: choice.thisPage && route ? route : null,
    contextType: report.contextType ?? null,
    contextId: report.contextType && choice.exactItem ? (report.contextId ?? null) : null,
  };
}
