import app from 'flarum/forum/app';

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
export function activeHunts(): ActiveHunt[] {
  return app.forum?.attribute<ActiveHunt[] | undefined>('scavengerHunts') ?? [];
}

export function canManageHunts(): boolean {
  return !!app.forum?.attribute<boolean | undefined>('scavengerHuntCanManage');
}

/**
 * Keeps the payload's progress in step after a claim, so the header indicator updates without a reload.
 */
export function updateProgress(huntId: number, foundCount: number): void {
  const hunt = activeHunts().find((candidate) => candidate.id === huntId);

  if (hunt) {
    hunt.foundCount = foundCount;
  }
}
