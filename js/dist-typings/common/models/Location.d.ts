import Model from 'flarum/common/Model';
import type Part from './Part';
export default class Location extends Model {
    route(): string | null;
    spot(): string;
    contextType(): string | null;
    contextId(): number | null;
    isAvailable(): boolean;
    part(): false | Part;
}
