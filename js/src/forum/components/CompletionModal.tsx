import app from 'flarum/forum/app';
import Modal, { type IInternalModalAttrs } from 'flarum/common/components/Modal';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import type Mithril from 'mithril';
import type Hunt from '../../common/models/Hunt';
import CompletionSummary from './CompletionSummary';

export interface CompletionModalAttrs extends IInternalModalAttrs {
  huntId: string;
}

export default class CompletionModal extends Modal<CompletionModalAttrs> {
  protected hunt: Hunt | null = null;

  oninit(vnode: Mithril.Vnode<CompletionModalAttrs, this>) {
    super.oninit(vnode);

    app.store.find<Hunt>('scavenger-hunts', this.attrs.huntId, { include: 'parts' }).then((hunt) => {
      this.hunt = hunt;
      m.redraw();
    });
  }

  className() {
    return 'CompletionModal Modal--small';
  }

  title() {
    return this.hunt
      ? app.translator.trans('fof-scavenger-hunt.forum.completion_modal.title', { hunt: this.hunt.title() })
      : app.translator.trans('fof-scavenger-hunt.forum.completion_modal.loading_title');
  }

  content() {
    return <div className="Modal-body">{this.hunt ? <CompletionSummary hunt={this.hunt} /> : <LoadingIndicator />}</div>;
  }
}
