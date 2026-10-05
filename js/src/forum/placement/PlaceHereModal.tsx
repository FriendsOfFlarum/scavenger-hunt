import app from 'flarum/forum/app';
import toast from '../utils/toast';
import FormModal, { type IFormModalAttrs } from 'flarum/common/components/FormModal';
import Button from 'flarum/common/components/Button';
import type Mithril from 'mithril';
import type Location from '../../common/models/Location';
import type { SpotReport } from '../states/EncounterState';
import locationAttributes from './locationAttributes';
import type { PlacementController } from './PlacementController';

export interface PlaceHereModalAttrs extends IFormModalAttrs {
  report: SpotReport;
  controller: PlacementController;
}

export default class PlaceHereModal extends FormModal<PlaceHereModalAttrs> {
  protected exactItem = true;
  protected thisPage = true;

  className() {
    return 'PlaceHereModal Modal--small';
  }

  title() {
    return app.translator.trans('fof-scavenger-hunt.forum.placement_mode.modal_title', { part: this.attrs.controller.part()?.name() ?? '' });
  }

  content(): Mithril.Children {
    const { report, controller } = this.attrs;
    const route = controller.currentForumRoute();
    const spot = controller.spot(report.spot);

    return (
      <div className="Modal-body">
        <p>{app.translator.trans('fof-scavenger-hunt.forum.placement_mode.spot', { spot: spot ? spot.label() : report.spot })}</p>

        {report.contextType && (
          <fieldset className="Form-group">
            <legend>{app.translator.trans('fof-scavenger-hunt.forum.placement_mode.which_item')}</legend>
            <label className="checkbox">
              <input type="radio" name="item" checked={this.exactItem} onchange={() => (this.exactItem = true)} />
              {app.translator.trans('fof-scavenger-hunt.forum.placement_mode.exact_item', { type: report.contextType, id: report.contextId })}
            </label>
            <label className="checkbox">
              <input type="radio" name="item" checked={!this.exactItem} onchange={() => (this.exactItem = false)} />
              {app.translator.trans('fof-scavenger-hunt.forum.placement_mode.any_item', { type: report.contextType })}
            </label>
          </fieldset>
        )}

        <fieldset className="Form-group">
          <legend>{app.translator.trans('fof-scavenger-hunt.forum.placement_mode.which_page')}</legend>
          <label className="checkbox">
            <input type="radio" name="page" checked={this.thisPage && !!route} disabled={!route} onchange={() => (this.thisPage = true)} />
            {app.translator.trans('fof-scavenger-hunt.forum.placement_mode.this_page', { route: route ?? '—' })}
          </label>
          <label className="checkbox">
            <input type="radio" name="page" checked={!this.thisPage || !route} onchange={() => (this.thisPage = false)} />
            {app.translator.trans('fof-scavenger-hunt.forum.placement_mode.any_page')}
          </label>
        </fieldset>

        <div className="Form-group">
          <Button type="submit" className="Button Button--primary" loading={this.loading}>
            {app.translator.trans('fof-scavenger-hunt.forum.placement_mode.hide_here')}
          </Button>{' '}
          <Button className="Button" onclick={() => this.hide()}>
            {app.translator.trans('fof-scavenger-hunt.forum.placement_mode.cancel')}
          </Button>
        </div>
      </div>
    );
  }

  onsubmit(e: SubmitEvent) {
    e.preventDefault();

    const { report, controller } = this.attrs;
    const part = controller.part();

    if (!part) return;

    this.loading = true;

    app.store
      .createRecord<Location>('scavenger-hunt-locations')
      .save(
        {
          ...locationAttributes(report, { exactItem: this.exactItem, thisPage: this.thisPage }, controller.currentForumRoute()),
          relationships: { part },
        },
        { errorHandler: this.onerror.bind(this) }
      )
      .then(() => {
        this.hide();
        toast(app.translator.trans('fof-scavenger-hunt.forum.placement_mode.hidden', { part: part.name() }));
      })
      .catch(() => {
        this.loading = false;
        m.redraw();
      });
  }
}
