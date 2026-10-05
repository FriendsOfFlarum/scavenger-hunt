import app from 'flarum/forum/app';
import Component, { type ComponentAttrs } from 'flarum/common/Component';
import Icon from 'flarum/common/components/Icon';
import classList from 'flarum/common/utils/classList';
import ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';
import type Hunt from '../../common/models/Hunt';
import type Part from '../../common/models/Part';
import CollectableCanvas from './CollectableCanvas';
import Link from 'flarum/common/components/Link';
import huntUrl from '../utils/huntUrl';

export interface CollectionViewAttrs extends ComponentAttrs {
  hunt: Hunt;
}

/**
 * The Collectable assembling as its Parts are found: found Parts on their layers, silhouettes for the rest.
 */
export default class CollectionView extends Component<CollectionViewAttrs> {
  view() {
    return <div className="CollectionView">{this.viewItems().toArray()}</div>;
  }

  viewItems(): ItemList<Mithril.Children> {
    const items = new ItemList<Mithril.Children>();
    const hunt = this.attrs.hunt;
    const parts = this.parts();
    const found = parts.filter((part) => part.isFound()).length;

    items.add(
      'header',
      <h3 className="CollectionView-title">
        {huntUrl(hunt.slug()) ? <Link href={huntUrl(hunt.slug())!}>{hunt.title()}</Link> : hunt.title()}
        <span className="CollectionView-progress">
          {app.translator.trans('fof-scavenger-hunt.forum.collection.progress', { found, total: parts.length })}
        </span>
      </h3>,
      100
    );

    items.add('canvas', <CollectableCanvas hunt={hunt} parts={parts} />, 80);

    items.add('parts', <ul className="CollectionView-parts">{parts.map((part) => this.partItem(part))}</ul>, 60);

    return items;
  }

  protected parts(): Part[] {
    return ((this.attrs.hunt.parts() || []).filter(Boolean) as Part[]).sort((a, b) => a.sort() - b.sort());
  }

  protected partItem(part: Part): Mithril.Children {
    const found = !!part.isFound();
    let detail: Mithril.Children;

    if (found) {
      detail = app.translator.trans('fof-scavenger-hunt.forum.collection.found');
    } else if (part.isRandom()) {
      detail = app.translator.trans('fof-scavenger-hunt.forum.collection.random_part');
    } else if (part.hint()) {
      detail = part.hint();
    } else {
      detail = app.translator.trans('fof-scavenger-hunt.forum.collection.no_hint');
    }

    return (
      <li className={classList('CollectionView-part', { 'CollectionView-part--found': found })}>
        <Icon name={found ? 'fas fa-check-circle' : 'far fa-circle'} className="CollectionView-partIcon" />
        <span className="CollectionView-partName">{part.name()}</span>
        <span className="CollectionView-partDetail">{detail}</span>
      </li>
    );
  }
}
