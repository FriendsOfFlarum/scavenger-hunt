import app from 'flarum/admin/app';
import { extend, override } from 'flarum/common/extend';
import ExtensionPage, { type ExtensionPageAttrs } from 'flarum/admin/components/ExtensionPage';
import AssetLibrarySection from './components/AssetLibrarySection';

import type ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';

export { default as extend } from './extend';

const ASSETS_TAB = 'scavenger-hunt-assets';

/** Core's `TabItem`, as far as this extension uses it. */
interface PageTab {
  id: string;
  label: Mithril.Children;
  icon?: string;
}

/** The tabbed extension page of core releases after 2.0.2. */
interface TabbedExtensionPage extends ExtensionPage {
  usesPageTabs(): boolean;
  pageTabItems(): ItemList<PageTab>;
  pageTabContent(tab: string, vnode: Mithril.VnodeDOM<ExtensionPageAttrs, ExtensionPage>): Mithril.Children;
}

function hasPageTabs(page: ExtensionPage): page is TabbedExtensionPage {
  return typeof (page as Partial<TabbedExtensionPage>).usesPageTabs === 'function';
}

app.initializers.add('fof-scavenger-hunt', () => {
  const proto = ExtensionPage.prototype;

  // On a tabbed page an extra section would show below the tabs, on every tab.
  extend(proto, 'sections', function (this: ExtensionPage, items: ItemList<unknown>) {
    if (this.extension.id !== 'fof-scavenger-hunt' || (hasPageTabs(this) && this.usesPageTabs())) return;

    // Between the settings and the permission grid, whose section ends in a tall spacer.
    if (items.has('permissions')) items.setPriority('permissions', -20);
    items.add('scavengerHuntAssets', <AssetLibrarySection />, -10);
  });

  if (!hasPageTabs(proto)) return;

  extend(proto, 'pageTabItems', function (this: TabbedExtensionPage, items: ItemList<PageTab>) {
    if (this.extension.id !== 'fof-scavenger-hunt') return;

    items.add(ASSETS_TAB, { id: ASSETS_TAB, label: app.translator.trans('fof-scavenger-hunt.admin.assets.tab'), icon: 'far fa-images' }, -50);
  });

  override(proto, 'pageTabContent', function (this: TabbedExtensionPage, original: TabbedExtensionPage['pageTabContent'], tab: string, vnode) {
    if (this.extension.id === 'fof-scavenger-hunt' && tab === ASSETS_TAB) return <AssetLibrarySection inTab />;

    return original(tab, vnode);
  });
});
