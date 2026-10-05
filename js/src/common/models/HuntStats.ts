import Model from 'flarum/common/Model';

export interface PartStats {
  id: number;
  name: string;
  shown: number;
  found: number;
  averageClaimMs: number | null;
}

export default class HuntStats extends Model {
  participants() {
    return Model.attribute<number>('participants').call(this);
  }

  finds() {
    return Model.attribute<number>('finds').call(this);
  }

  completions() {
    return Model.attribute<number>('completions').call(this);
  }

  shown() {
    return Model.attribute<number>('shown').call(this);
  }

  parts() {
    return Model.attribute<PartStats[]>('parts').call(this);
  }

  completionsPerDay() {
    return Model.attribute<{ date: string; count: number }[]>('completionsPerDay').call(this);
  }
}
