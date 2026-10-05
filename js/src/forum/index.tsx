import app from 'flarum/forum/app';
import { extend, override } from 'flarum/common/extend';
import Page from 'flarum/common/components/Page';
import LinkButton from 'flarum/common/components/LinkButton';
import Footer from 'flarum/forum/components/Footer';
import HeaderSecondary from 'flarum/forum/components/HeaderSecondary';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import SessionDropdown from 'flarum/forum/components/SessionDropdown';
import type ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';
import HidingSpot from './components/HidingSpot';
import HuntIndicator from './components/HuntIndicator';
import { encounters } from './states/EncounterState';
import { activeHunts, canManageHunts } from './utils/activeHunts';

export { default as extend } from './extend';

app.initializers.add('fof-scavenger-hunt', () => {
  // Every page change starts a fresh page view: spots are reported again and old Encounters dropped.
  extend(Page.prototype, 'oninit', function () {
    encounters.pageChanged(app.current.get('routeName') ?? '', m.route.param() ?? {});
  });

  extend(HeaderSecondary.prototype, 'items', function (items: ItemList<Mithril.Children>) {
    if (activeHunts().length && app.forum.attribute('fof-scavenger-hunt.showHeaderIndicator')) {
      items.add('scavengerHuntIndicator', <HuntIndicator />, 15);
    }

    items.add('scavengerHuntSpot', <HidingSpot spot="page.header" />, 14);
  });

  extend(IndexSidebar.prototype, 'items', function (items: ItemList<Mithril.Children>) {
    items.add('scavengerHuntSpot', <HidingSpot spot="page.sidebar" />, -100);
  });

  extend(IndexSidebar.prototype, 'navItems', function (items: ItemList<Mithril.Children>) {
    if (canManageHunts()) {
      items.add(
        'scavengerHuntDashboard',
        <LinkButton icon="fas fa-binoculars" href={app.route('fof-scavenger-hunt.dashboard')}>
          {app.translator.trans('fof-scavenger-hunt.forum.dashboard.nav')}
        </LinkButton>,
        -50
      );
    }
  });

  extend(SessionDropdown.prototype, 'items', function (items: ItemList<Mithril.Children>) {
    if (canManageHunts()) {
      items.add(
        'scavengerHuntDashboard',
        <LinkButton icon="fas fa-binoculars" href={app.route('fof-scavenger-hunt.dashboard')}>
          {app.translator.trans('fof-scavenger-hunt.forum.dashboard.nav')}
        </LinkButton>,
        75
      );
    }
  });

  // Core's Footer renders nothing; it is mounted on every page, so it hosts the footer and corner spots.
  override(Footer.prototype, 'view', function (original: () => Mithril.Children) {
    return [
      original(),
      <HidingSpot spot="page.footer" className="HidingSpot-footer" />,
      <div className="ScavengerCorner">
        <HidingSpot spot="page.corner" />
      </div>,
    ];
  });

  if (activeHunts().length) {
    app.prefetch.add('fof-scavenger-hunt.collection', () => import('./components/CollectionModal'));
  }
});
