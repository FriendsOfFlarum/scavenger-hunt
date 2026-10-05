import Component, { type ComponentAttrs } from 'flarum/common/Component';
import classList from 'flarum/common/utils/classList';
import type Mithril from 'mithril';
import { encounters, reportKey, type SpotReport } from '../states/EncounterState';
import HiddenPart from './HiddenPart';

export interface HidingSpotAttrs extends ComponentAttrs {
  /** A Hiding spot key registered in PHP through `FoF\ScavengerHunt\Extend\HidingSpots`. */
  spot: string;
  /** `[contextType, id]` for spots drawn once per item, e.g. `['post', 12]`. */
  context?: [string, number];
  className?: string;
}

/**
 * A place a Part can appear. Renders nothing until the server says a Part is here.
 *
 *     items.add('scavengerHunt', <HidingSpot spot="acme.article-footer" context={['article', article.id()]} />);
 */
export default class HidingSpot extends Component<HidingSpotAttrs> {
  protected report!: SpotReport;

  oninit(vnode: Mithril.Vnode<HidingSpotAttrs, this>) {
    super.oninit(vnode);

    this.report = this.reportFor(this.attrs);
    encounters.register(this.report);
  }

  onbeforeupdate(vnode: Mithril.VnodeDOM<HidingSpotAttrs, this>) {
    super.onbeforeupdate(vnode);

    const report = this.reportFor(vnode.attrs);

    // A reused component can move to another item (lists re-render in place).
    if (reportKey(report) !== reportKey(this.report)) {
      encounters.unregister(this.report);
      this.report = report;
      encounters.register(report);
    }
  }

  onremove(vnode: Mithril.VnodeDOM<HidingSpotAttrs, this>) {
    super.onremove(vnode);

    encounters.unregister(this.report);
  }

  view() {
    const encounter = encounters.encounterAt(this.report);

    if (!encounter) {
      return null;
    }

    return (
      <span className={classList('HidingSpot', `HidingSpot--${this.attrs.spot.replace(/\./g, '-')}`, this.attrs.className)}>
        <HiddenPart encounter={encounter} />
      </span>
    );
  }

  protected reportFor(attrs: HidingSpotAttrs): SpotReport {
    return attrs.context ? { spot: attrs.spot, contextType: attrs.context[0], contextId: attrs.context[1] } : { spot: attrs.spot };
  }
}
