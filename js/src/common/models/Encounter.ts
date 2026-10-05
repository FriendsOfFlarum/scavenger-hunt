import Model from 'flarum/common/Model';
import type Part from './Part';

export default class Encounter extends Model {
  spot() {
    return Model.attribute<string>('spot').call(this);
  }

  contextType() {
    return Model.attribute<string | null>('contextType').call(this);
  }

  contextId() {
    return Model.attribute<number | null>('contextId').call(this);
  }

  /**
   * Null for actors who may see the Part but not claim it: guests and, by default, Hunt managers.
   */
  token() {
    return Model.attribute<string | null>('token').call(this);
  }

  expiresAt() {
    return Model.attribute<Date | null, string | null>('expiresAt', Model.transformDate).call(this);
  }

  part() {
    return Model.hasOne<Part>('part').call(this);
  }
}
