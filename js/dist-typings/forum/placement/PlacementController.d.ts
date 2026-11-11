import type HidingSpotDefinition from '../../common/models/HidingSpotDefinition';
import type Hunt from '../../common/models/Hunt';
import type Part from '../../common/models/Part';
import { type PlacementTarget } from '../states/placement';
/**
 * "Hide it here" mode: every Hiding spot on the forum becomes a target; clicking one hides the
 * chosen Part there. Lookups pause while it is on, and it survives navigation and reloads.
 */
export declare class PlacementController {
    hunts: Hunt[];
    spots: HidingSpotDefinition[];
    pageRoutes: string[];
    routeAliases: Record<string, string>;
    huntId: string | null;
    partId: string | null;
    protected toolbar: HTMLElement | null;
    protected onKeydown: (e: KeyboardEvent) => void;
    start(target: PlacementTarget): Promise<void>;
    stop(): void;
    select(huntId: string | null, partId: string | null): void;
    hunt(): Hunt | undefined;
    parts(): Part[];
    part(): Part | undefined;
    spot(key: string): HidingSpotDefinition | undefined;
    /**
     * The forum route for the current page, or null if it isn't a page Hunt managers can pick.
     */
    currentForumRoute(): string | null;
}
declare const controller: PlacementController;
export default controller;
