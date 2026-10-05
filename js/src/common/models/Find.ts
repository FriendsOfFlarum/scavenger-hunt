import Model from 'flarum/common/Model';
import type Part from './Part';

export default class Find extends Model {
  foundAt() {
    return Model.attribute<Date, string>('foundAt', Model.transformDate).call(this);
  }

  part() {
    return Model.hasOne<Part>('part').call(this);
  }
}
