import Model from 'flarum/common/Model';
import type Part from './Part';

export default class Location extends Model {
  route() {
    return Model.attribute<string | null>('route').call(this);
  }

  spot() {
    return Model.attribute<string>('spot').call(this);
  }

  contextType() {
    return Model.attribute<string | null>('contextType').call(this);
  }

  contextId() {
    return Model.attribute<number | null>('contextId').call(this);
  }

  isAvailable() {
    return Model.attribute<boolean>('isAvailable').call(this);
  }

  part() {
    return Model.hasOne<Part>('part').call(this);
  }
}
