import Model from 'flarum/common/Model';
import type User from 'flarum/common/models/User';
export default class Participant extends Model {
    foundCount(): number;
    firstFoundAt(): Date;
    lastFoundAt(): Date;
    completedAt(): Date | null;
    position(): number | null;
    encounterCount(): number;
    averageClaimMs(): number | null;
    fastClaimFlag(): boolean;
    user(): false | User;
}
