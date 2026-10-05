import Model from 'flarum/common/Model';
import type { PartLayer } from './Part';

export interface HuntTemplatePart {
  key: string;
  name: string;
  imageUrl: string | null;
  layer: PartLayer;
}

export default class HuntTemplate extends Model {
  title() {
    return Model.attribute<string>('title').call(this);
  }

  occasion() {
    return Model.attribute<string>('occasion').call(this);
  }

  imageUrl() {
    return Model.attribute<string | null>('imageUrl').call(this);
  }

  parts() {
    return Model.attribute<HuntTemplatePart[]>('parts').call(this);
  }
}
