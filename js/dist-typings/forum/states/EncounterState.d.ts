import type Encounter from '../../common/models/Encounter';
export interface SpotReport {
    spot: string;
    contextType?: string;
    contextId?: number;
}
/** Whether a mounted spot is currently out of sight (e.g. in the closed phone drawer). */
export type HiddenCheck = () => boolean;
export declare function reportKey(report: SpotReport): string;
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
    paused: boolean;
    protected mounted: Map<string, {
        report: SpotReport;
        checks: HiddenCheck[];
    }>;
    protected reported: Set<string>;
    protected encounters: Map<string, Encounter>;
    protected page: string;
    protected route: string;
    protected params: Record<string, unknown>;
    protected timer: ReturnType<typeof setTimeout> | null;
    isActive(): boolean;
    register(report: SpotReport, isHidden?: HiddenCheck): void;
    unregister(report: SpotReport, isHidden?: HiddenCheck): void;
    pageChanged(route: string, params: Record<string, unknown>): void;
    currentRoute(): string;
    currentParams(): Record<string, unknown>;
    encounterAt(report: SpotReport): Encounter | undefined;
    forget(encounter: Encounter): void;
    pendingReports(): SpotReport[];
    schedule(): void;
    lookup(): Promise<void>;
}
export declare const encounters: EncounterState;
/**
 * Spots held back in the closed phone drawer get their lookup when the drawer opens.
 */
export declare function lookUpWhenDrawerOpens(): void;
