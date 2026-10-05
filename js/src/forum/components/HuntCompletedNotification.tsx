import app from 'flarum/forum/app';
import Notification from 'flarum/forum/components/Notification';
import type Hunt from '../../common/models/Hunt';
import huntUrl from '../utils/huntUrl';

export default class HuntCompletedNotification extends Notification {
  icon() {
    return 'fas fa-trophy';
  }

  href() {
    const hunt = this.attrs.notification.subject() as Hunt | false;

    return hunt ? (huntUrl(hunt.slug()) ?? '') : '';
  }

  content() {
    const hunt = this.attrs.notification.subject() as Hunt | false;

    return app.translator.trans('fof-scavenger-hunt.forum.notifications.hunt_completed', { hunt: hunt ? hunt.title() : '' });
  }

  excerpt() {
    return null;
  }
}
