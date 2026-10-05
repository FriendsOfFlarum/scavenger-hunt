import Model from 'flarum/common/Model';
import type User from 'flarum/common/models/User';
import type Hunt from './Hunt';

export default class Completion extends Model {
  completedAt() {
    return Model.attribute<Date, string>('completedAt', Model.transformDate).call(this);
  }

  position() {
    return Model.attribute<number>('position').call(this);
  }

  user() {
    return Model.hasOne<User>('user').call(this);
  }

  hunt() {
    return Model.hasOne<Hunt>('hunt').call(this);
  }
}
