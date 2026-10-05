import app from 'flarum/forum/app';
import Component, { type ComponentAttrs } from 'flarum/common/Component';
import Pill from 'flarum/common/components/Pill';
import type Hunt from '../../common/models/Hunt';
import huntStatus from './huntStatus';

export default class HuntStatusPill extends Component<ComponentAttrs & { hunt: Hunt }> {
  view() {
    const status = huntStatus(this.attrs.hunt);

    return (
      <Pill className={`HuntStatusPill HuntStatusPill--${status}`}>
        {app.translator.trans(`fof-scavenger-hunt.forum.dashboard.status.${status}`)}
      </Pill>
    );
  }
}
