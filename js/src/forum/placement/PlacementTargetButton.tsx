import app from 'flarum/forum/app';
import Component, { type ComponentAttrs } from 'flarum/common/Component';
import Icon from 'flarum/common/components/Icon';
import type { HidingSpotAttrs } from '../components/HidingSpot';
import type { SpotReport } from '../states/EncounterState';
import type { PlacementController } from './PlacementController';
import PlaceHereModal from './PlaceHereModal';

export interface PlacementTargetButtonAttrs extends ComponentAttrs {
  attrs: HidingSpotAttrs;
  report: SpotReport;
  controller: PlacementController;
}

export default class PlacementTargetButton extends Component<PlacementTargetButtonAttrs> {
  view() {
    const { report, controller } = this.attrs;
    const spot = controller.spot(report.spot);
    const label = spot ? spot.label() : report.spot;

    return (
      <button
        type="button"
        className="PlacementTarget"
        title={app.translator.trans('fof-scavenger-hunt.forum.placement_mode.target', { spot: label }, true)}
        disabled={!controller.part()}
        onclick={(e: MouseEvent) => {
          e.preventDefault();
          e.stopPropagation();
          app.modal.show(PlaceHereModal, { report, controller });
        }}
      >
        <Icon name="fas fa-map-pin" />
        <span className="PlacementTarget-label">
          {label}
          {report.contextId ? ` #${report.contextId}` : ''}
        </span>
      </button>
    );
  }
}
