import Model from 'flarum/common/Model';
import type Part from './Part';
export default class Encounter extends Model {
    spot(): string;
    contextType(): string | null;
    contextId(): number | null;
    /**
     * Null for actors who may see the Part but not claim it: guests and, by default, Hunt managers.
     */
    token(): string | null;
    expiresAt(): Date | null;
    part(): false | Part;
}
