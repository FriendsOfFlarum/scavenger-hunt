import Model from 'flarum/common/Model';
import type User from 'flarum/common/models/User';

export default class Participant extends Model {
  foundCount() {
    return Model.attribute<number>('foundCount').call(this);
  }

  firstFoundAt() {
    return Model.attribute<Date, string>('firstFoundAt', Model.transformDate).call(this);
  }

  lastFoundAt() {
    return Model.attribute<Date, string>('lastFoundAt', Model.transformDate).call(this);
  }

  completedAt() {
    return Model.attribute<Date | null, string | null>('completedAt', Model.transformDate).call(this);
  }

  position() {
    return Model.attribute<number | null>('position').call(this);
  }

  encounterCount() {
    return Model.attribute<number>('encounterCount').call(this);
  }

  averageClaimMs() {
    return Model.attribute<number | null>('averageClaimMs').call(this);
  }

  fastClaimFlag() {
    return Model.attribute<boolean>('fastClaimFlag').call(this);
  }

  user() {
    return Model.hasOne<User>('user').call(this);
  }
}
