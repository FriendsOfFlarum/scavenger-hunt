import Model from 'flarum/common/Model';
export interface PartStats {
    id: number;
    name: string;
    shown: number;
    found: number;
    averageClaimMs: number | null;
}
export default class HuntStats extends Model {
    participants(): number;
    finds(): number;
    completions(): number;
    shown(): number;
    parts(): PartStats[];
    completionsPerDay(): {
        date: string;
        count: number;
    }[];
}
