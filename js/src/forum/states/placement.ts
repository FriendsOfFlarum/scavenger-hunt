import type Mithril from 'mithril';
import type { HidingSpotAttrs } from '../components/HidingSpot';
import type { SpotReport } from './EncounterState';

export type PlacementRenderer = (attrs: HidingSpotAttrs, report: SpotReport) => Mithril.Children;

export interface PlacementTarget {
  huntId?: string;
  partId?: string;
}

export const PLACEMENT_STORAGE_KEY = 'fof-scavenger-hunt.placement';

/**
 * The always-loaded half of "hide it here" mode. The mode itself lives in a lazy chunk, which
 * installs a renderer here so every HidingSpot draws a target while it is on.
 */
class Placement {
  renderer: PlacementRenderer | null = null;

  get active(): boolean {
    return this.renderer !== null;
  }

  async start(target: PlacementTarget = {}): Promise<void> {
    const { default: controller } = await import('../placement/PlacementController');

    await controller.start(target);
  }

  async stop(): Promise<void> {
    const { default: controller } = await import('../placement/PlacementController');

    controller.stop();
  }

  saved(): PlacementTarget | null {
    try {
      const value = window.sessionStorage.getItem(PLACEMENT_STORAGE_KEY);

      return value ? (JSON.parse(value) as PlacementTarget) : null;
    } catch {
      return null;
    }
  }
}

export const placement = new Placement();
