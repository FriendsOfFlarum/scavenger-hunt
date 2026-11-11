import type Mithril from 'mithril';
import type { HidingSpotAttrs } from '../components/HidingSpot';
import type { SpotReport } from './EncounterState';
export type PlacementRenderer = (attrs: HidingSpotAttrs, report: SpotReport) => Mithril.Children;
export interface PlacementTarget {
    huntId?: string;
    partId?: string;
}
export declare const PLACEMENT_STORAGE_KEY = "fof-scavenger-hunt.placement";
/**
 * The always-loaded half of "hide it here" mode. The mode itself lives in a lazy chunk, which
 * installs a renderer here so every HidingSpot draws a target while it is on.
 */
declare class Placement {
    renderer: PlacementRenderer | null;
    get active(): boolean;
    start(target?: PlacementTarget): Promise<void>;
    stop(): Promise<void>;
    saved(): PlacementTarget | null;
}
export declare const placement: Placement;
export {};
