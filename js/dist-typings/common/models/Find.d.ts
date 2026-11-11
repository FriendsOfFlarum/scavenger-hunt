import Model from 'flarum/common/Model';
import type Part from './Part';
export default class Find extends Model {
    foundAt(): Date;
    part(): false | Part;
}
