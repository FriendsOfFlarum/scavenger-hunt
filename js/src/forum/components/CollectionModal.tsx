import app from 'flarum/forum/app';
import Modal from 'flarum/common/components/Modal';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import type Mithril from 'mithril';
import type Hunt from '../../common/models/Hunt';
import { activeHunts } from '../utils/activeHunts';
import CollectionView from './CollectionView';

export default class CollectionModal extends Modal {
  protected hunts: Hunt[] | null = null;

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);

    Promise.all(activeHunts().map((hunt) => app.store.find<Hunt>('scavenger-hunts', String(hunt.id), { include: 'parts' }))).then((hunts) => {
      this.hunts = hunts;
      m.redraw();
    });
  }

  className() {
    return 'CollectionModal Modal--medium';
  }

  title() {
    return app.translator.trans('fof-scavenger-hunt.forum.collection.title');
  }

  content() {
    if (!this.hunts) {
      return (
        <div className="Modal-body">
          <LoadingIndicator />
        </div>
      );
    }

    return (
      <div className="Modal-body">
        {this.hunts.length ? (
          this.hunts.map((hunt) => <CollectionView hunt={hunt} />)
        ) : (
          <p className="CollectionModal-empty">{app.translator.trans('fof-scavenger-hunt.forum.collection.empty')}</p>
        )}
      </div>
    );
  }
}
