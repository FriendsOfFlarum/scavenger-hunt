import Model from 'flarum/common/Model';
import type Hunt from './Hunt';
import type Location from './Location';
export interface PartLayer {
    x: number;
    y: number;
    width: number;
    z: number;
}
export type PlacementMode = 'fixed' | 'assigned' | 'wandering';
export default class Part extends Model {
    name(): string;
    hint(): string | null | undefined;
    imageUrl(): string | null;
    layer(): PartLayer | null;
    sort(): number;
    isRandom(): boolean;
    isFound(): boolean | undefined;
    foundAt(): Date | null | undefined;
    mode(): PlacementMode | undefined;
    wanderChance(): number | null | undefined;
    image(): string | null | undefined;
    hunt(): false | Hunt;
    locations(): false | (Location | undefined)[];
}
