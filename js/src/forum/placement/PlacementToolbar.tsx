import app from 'flarum/forum/app';
import Component, { type ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Select from 'flarum/common/components/Select';
import type { PlacementController } from './PlacementController';

export interface PlacementToolbarAttrs extends ComponentAttrs {
  controller: PlacementController;
}

export default class PlacementToolbar extends Component<PlacementToolbarAttrs> {
  view() {
    const controller = this.attrs.controller;
    const huntOptions: Record<string, string> = {};
    const partOptions: Record<string, string> = {};

    controller.hunts.forEach((hunt) => (huntOptions[hunt.id()!] = hunt.title()));
    controller.parts().forEach((part) => (partOptions[part.id()!] = part.name()));

    return (
      <div
        className="PlacementToolbar"
        role="region"
        aria-label={app.translator.trans('fof-scavenger-hunt.forum.placement_mode.title', {}, true)}
        aria-describedby="PlacementToolbar-help"
      >
        <strong className="PlacementToolbar-title">{app.translator.trans('fof-scavenger-hunt.forum.placement_mode.title')}</strong>
        <div className="PlacementToolbar-selects">
          <Select options={huntOptions} value={controller.huntId ?? undefined} onchange={(value: string) => controller.select(value, null)} />
          <Select
            options={partOptions}
            value={controller.partId ?? undefined}
            onchange={(value: string) => controller.select(controller.huntId, value)}
          />
        </div>
        <span className="PlacementToolbar-help" id="PlacementToolbar-help">
          {app.translator.trans('fof-scavenger-hunt.forum.placement_mode.help')}
        </span>
        <div className="PlacementToolbar-actions">
          <Button className="Button Button--primary" icon="fas fa-check" onclick={() => controller.stop()}>
            {app.translator.trans('fof-scavenger-hunt.forum.placement_mode.done')}
          </Button>
          <Button
            className="Button Button--icon Button--flat"
            icon="fas fa-times"
            aria-label={app.translator.trans('fof-scavenger-hunt.forum.placement_mode.cancel', {}, true)}
            onclick={() => controller.stop()}
          />
        </div>
      </div>
    );
  }
}
