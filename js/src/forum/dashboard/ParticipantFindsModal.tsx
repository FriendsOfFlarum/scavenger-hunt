import app from 'flarum/forum/app';
import Modal, { type IInternalModalAttrs } from 'flarum/common/components/Modal';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import fullTime from 'flarum/common/helpers/fullTime';
import username from 'flarum/common/helpers/username';
import type User from 'flarum/common/models/User';
import type Mithril from 'mithril';
import type Find from '../../common/models/Find';

export interface ParticipantFindsModalAttrs extends IInternalModalAttrs {
  huntId: string;
  user: User;
}

export default class ParticipantFindsModal extends Modal<ParticipantFindsModalAttrs> {
  protected finds: Find[] | null = null;

  oninit(vnode: Mithril.Vnode<ParticipantFindsModalAttrs, this>) {
    super.oninit(vnode);

    app.store
      .find<Find[]>('scavenger-hunt-finds', {
        filter: { hunt: this.attrs.huntId, user: this.attrs.user.id() },
        include: 'part',
        page: { limit: 100 },
      })
      .then((finds) => {
        this.finds = finds;
        m.redraw();
      });
  }

  className() {
    return 'ParticipantFindsModal Modal--small';
  }

  title() {
    return app.translator.trans('fof-scavenger-hunt.forum.dashboard.participants.finds_title', { user: username(this.attrs.user) });
  }

  content() {
    if (!this.finds) {
      return (
        <div className="Modal-body">
          <LoadingIndicator />
        </div>
      );
    }

    return (
      <div className="Modal-body">
        <ul className="ParticipantFindsModal-list">
          {this.finds.map((find) => {
            const part = find.part();

            return (
              <li>
                <strong>{part ? part.name() : '?'}</strong> — {fullTime(find.foundAt())}
              </li>
            );
          })}
        </ul>
      </div>
    );
  }
}
