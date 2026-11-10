import Model from 'flarum/common/Model';

export default class Asset extends Model {
  name() {
    return Model.attribute<string>('name').call(this);
  }

  url() {
    return Model.attribute<string>('url').call(this);
  }

  mimeType() {
    return Model.attribute<string>('mimeType').call(this);
  }

  width() {
    return Model.attribute<number | null>('width').call(this);
  }

  height() {
    return Model.attribute<number | null>('height').call(this);
  }

  size() {
    return Model.attribute<number>('size').call(this);
  }

  createdAt() {
    return Model.attribute<Date, string>('createdAt', Model.transformDate).call(this);
  }

  isInUse() {
    return Model.attribute<boolean>('isInUse').call(this);
  }
}
