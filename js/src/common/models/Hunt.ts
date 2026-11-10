import Model from 'flarum/common/Model';
import type Group from 'flarum/common/models/Group';
import type Part from './Part';

export default class Hunt extends Model {
  title() {
    return Model.attribute<string>('title').call(this);
  }

  slug() {
    return Model.attribute<string>('slug').call(this);
  }

  description() {
    return Model.attribute<string | null>('description').call(this);
  }

  startsAt() {
    return Model.attribute<Date | null, string | null>('startsAt', Model.transformDate).call(this);
  }

  endsAt() {
    return Model.attribute<Date | null, string | null>('endsAt', Model.transformDate).call(this);
  }

  isActive() {
    return Model.attribute<boolean>('isActive').call(this);
  }

  imageUrl() {
    return Model.attribute<string | null>('imageUrl').call(this);
  }

  completedImageUrl() {
    return Model.attribute<string | null>('completedImageUrl').call(this);
  }

  partCount() {
    return Model.attribute<number>('partCount').call(this);
  }

  finisherCount() {
    return Model.attribute<number>('finisherCount').call(this);
  }

  /**
   * Only present for Hunt managers.
   */
  participantCount() {
    return Model.attribute<number | undefined>('participantCount').call(this);
  }

  foundCount() {
    return Model.attribute<number | undefined>('foundCount').call(this);
  }

  completedAt() {
    return Model.attribute<Date | null | undefined, string | null | undefined>('completedAt', Model.transformDate).call(this);
  }

  isEnabled() {
    return Model.attribute<boolean | undefined>('isEnabled').call(this);
  }

  guestsCanSee() {
    return Model.attribute<boolean | undefined>('guestsCanSee').call(this);
  }

  image() {
    return Model.attribute<string | null | undefined>('image').call(this);
  }

  completedImage() {
    return Model.attribute<string | null | undefined>('completedImage').call(this);
  }

  template() {
    return Model.attribute<string | null | undefined>('template').call(this);
  }

  finishingPosition() {
    return Model.attribute<number | null | undefined>('finishingPosition').call(this);
  }

  rewardMessage() {
    return Model.attribute<string | null | undefined>('rewardMessage').call(this);
  }

  /**
   * Only present for Hunt managers and for members who have earned the Completion.
   */
  rewardMessageHtml() {
    return Model.attribute<string | null | undefined>('rewardMessageHtml').call(this);
  }

  rewardGroup() {
    return Model.hasOne<Group | null>('rewardGroup').call(this);
  }

  parts() {
    return Model.hasMany<Part>('parts').call(this);
  }
}
