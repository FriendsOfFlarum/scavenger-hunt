import app from 'flarum/admin/app';
import { extend } from 'flarum/common/extend';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import type ItemList from 'flarum/common/utils/ItemList';
import AssetLibrarySection from './components/AssetLibrarySection';

export { default as extend } from './extend';

app.initializers.add('fof-scavenger-hunt', () => {
  extend(ExtensionPage.prototype, 'sections', function (this: ExtensionPage, items: ItemList<unknown>) {
    if (this.extension.id !== 'fof-scavenger-hunt') return;

    // Between the settings and the permission grid, whose section ends in a tall spacer.
    if (items.has('permissions')) items.setPriority('permissions', -20);
    items.add('scavengerHuntAssets', <AssetLibrarySection />, -10);
  });
});
