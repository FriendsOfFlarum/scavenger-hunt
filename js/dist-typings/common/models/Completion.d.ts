import Model from 'flarum/common/Model';
import type User from 'flarum/common/models/User';
import type Hunt from './Hunt';
export default class Completion extends Model {
    completedAt(): Date;
    position(): number;
    user(): false | User;
    hunt(): false | Hunt;
}
