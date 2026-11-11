export interface ActiveHunt {
    id: number;
    slug: string;
    title: string;
    partCount: number;
    foundCount: number;
    canClaim: boolean;
}
/**
 * The actor's visible, running Hunts from the forum payload. It never says where a Part is.
 */
export declare function activeHunts(): ActiveHunt[];
export declare function canManageHunts(): boolean;
/**
 * Keeps the payload's progress in step after a claim, so the header indicator updates without a reload.
 */
export declare function updateProgress(huntId: number, foundCount: number): void;
