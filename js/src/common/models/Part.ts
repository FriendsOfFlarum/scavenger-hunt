import Model from 'flarum/common/Model';
import type Hunt from './Hunt';
import type Location from './Location';

export interface PartLayer {
  x: number;
  y: number;
  width: number;
  z: number;
}

export type PlacementMode = 'fixed' | 'assigned' | 'wandering';

export default class Part extends Model {
  name() {
    return Model.attribute<string>('name').call(this);
  }

  hint() {
    return Model.attribute<string | null | undefined>('hint').call(this);
  }

  imageUrl() {
    return Model.attribute<string | null>('imageUrl').call(this);
  }

  layer() {
    return Model.attribute<PartLayer | null>('layer').call(this);
  }

  sort() {
    return Model.attribute<number>('sort').call(this);
  }

  isRandom() {
    return Model.attribute<boolean>('isRandom').call(this);
  }

  isFound() {
    return Model.attribute<boolean | undefined>('isFound').call(this);
  }

  foundAt() {
    return Model.attribute<Date | null | undefined, string | null | undefined>('foundAt', Model.transformDate).call(this);
  }

  mode() {
    return Model.attribute<PlacementMode | undefined>('mode').call(this);
  }

  wanderChance() {
    return Model.attribute<number | null | undefined>('wanderChance').call(this);
  }

  image() {
    return Model.attribute<string | null | undefined>('image').call(this);
  }

  hunt() {
    return Model.hasOne<Hunt>('hunt').call(this);
  }

  locations() {
    return Model.hasMany<Location>('locations').call(this);
  }
}
