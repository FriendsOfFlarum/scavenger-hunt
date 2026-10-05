import app from 'flarum/forum/app';
import { extend, override } from 'flarum/common/extend';
import Page from 'flarum/common/components/Page';
import LinkButton from 'flarum/common/components/LinkButton';
import Footer from 'flarum/forum/components/Footer';
import HeaderSecondary from 'flarum/forum/components/HeaderSecondary';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import SessionDropdown from 'flarum/forum/components/SessionDropdown';
import Button from 'flarum/common/components/Button';
import type ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';
import HidingSpot from './components/HidingSpot';
import HuntIndicator from './components/HuntIndicator';
import { encounters } from './states/EncounterState';
import { placement } from './states/placement';
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

      items.add(
        'scavengerHuntPlace',
        placement.active ? (
          <Button icon="fas fa-times" onclick={() => placement.stop()}>
            {app.translator.trans('fof-scavenger-hunt.forum.placement_mode.stop')}
          </Button>
        ) : (
          <Button icon="fas fa-map-pin" onclick={() => placement.start()}>
            {app.translator.trans('fof-scavenger-hunt.forum.placement_mode.start')}
          </Button>
        ),
        74
      );
    }
  });

  // Precise spots. String-path extends also cover modules a future core moves into a lazy chunk.
  extend('flarum/forum/components/DiscussionHero', 'items', function (this: any, items: ItemList<Mithril.Children>) {
    items.add('scavengerHuntSpot', <HidingSpot spot="discussion.hero" context={['discussion', Number(this.attrs.discussion.id())]} />, -100);
  });

  extend('flarum/forum/components/CommentPost', 'content', function (this: any, content: Mithril.Children[]) {
    content.push(<HidingSpot spot="post.body-end" className="HidingSpot-postBody" context={['post', Number(this.attrs.post.id())]} />);
  });

  extend('flarum/forum/components/Post', 'footerItems', function (this: any, items: ItemList<Mithril.Children>) {
    items.add('scavengerHuntSpot', <HidingSpot spot="post.footer" context={['post', Number(this.attrs.post.id())]} />, -100);
  });

  extend('flarum/forum/components/DiscussionListItem', 'contentItems', function (this: any, items: ItemList<Mithril.Children>) {
    items.add('scavengerHuntSpot', <HidingSpot spot="discussionList.item" context={['discussion', Number(this.attrs.discussion.id())]} />, 60);
  });

  extend('flarum/forum/components/UserCard', 'infoItems', function (this: any, items: ItemList<Mithril.Children>) {
    items.add('scavengerHuntSpot', <HidingSpot spot="user.card" context={['user', Number(this.attrs.user.id())]} />, -100);
  });

  extend('flarum/forum/components/UserPage', 'sidebarItems', function (this: any, items: ItemList<Mithril.Children>) {
    if (this.user) {
      items.add('scavengerHuntSpot', <HidingSpot spot="user.sidebar" context={['user', Number(this.user.id())]} />, -100);
    }
  });

  if (app.initializers.has('flarum-tags')) {
    // TagsPage renders each tile directly, with no ItemList to hook into, so the spot joins the tile's children.
    extend('ext:flarum/tags/forum/components/TagsPage', 'tagTileView', function (vnode: Mithril.Vnode, tag: any) {
      if (vnode && Array.isArray(vnode.children)) {
        vnode.children.push(<HidingSpot spot="tags.tile" className="HidingSpot-tagTile" context={['tag', Number(tag.id())]} />);
      }
    });
  }

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

  // "Hide it here" survives reloads for the manager who left it on.
  const saved = placement.saved();

  if (saved && canManageHunts()) {
    placement.start(saved);
  }
});
