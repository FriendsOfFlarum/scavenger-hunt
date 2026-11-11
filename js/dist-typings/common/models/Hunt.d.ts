import Model from 'flarum/common/Model';
import type Group from 'flarum/common/models/Group';
import type Part from './Part';
export default class Hunt extends Model {
    title(): string;
    slug(): string;
    description(): string | null;
    startsAt(): Date | null;
    endsAt(): Date | null;
    isActive(): boolean;
    imageUrl(): string | null;
    completedImageUrl(): string | null;
    partCount(): number;
    finisherCount(): number;
    /**
     * Only present for Hunt managers.
     */
    participantCount(): number | undefined;
    foundCount(): number | undefined;
    completedAt(): Date | null | undefined;
    isEnabled(): boolean | undefined;
    guestsCanSee(): boolean | undefined;
    image(): string | null | undefined;
    completedImage(): string | null | undefined;
    template(): string | null | undefined;
    finishingPosition(): number | null | undefined;
    rewardMessage(): string | null | undefined;
    /**
     * Only present for Hunt managers and for members who have earned the Completion.
     */
    rewardMessageHtml(): string | null | undefined;
    rewardGroup(): false | Group | null;
    parts(): false | (Part | undefined)[];
}
