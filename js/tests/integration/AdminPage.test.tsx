import bootstrapAdmin from '@flarum/jest-config/src/bootstrap/admin';
import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import ItemList from 'flarum/common/utils/ItemList';
import AssetLibrarySection from '../../src/admin/components/AssetLibrarySection';

// Core 2.0.2 stacks the extension page's sections.
beforeAll(async () => {
  bootstrapAdmin();
  await import('../../src/admin/index');
  app.initializers.get('fof-scavenger-hunt')(app);
});

function pageFor(id: string) {
  const page = new ExtensionPage();
  page.extension = { id } as never;
  page.content = () => (<div className="settings" />) as never;

  return page;
}

describe('the extension page on core without page tabs', () => {
  it('adds the Asset library between the settings and the permissions', () => {
    const items = pageFor('fof-scavenger-hunt').sections({} as never) as ItemList<any>;

    expect(items.toArray(true).map((item: any) => item.itemName)).toEqual(['content', 'scavengerHuntAssets', 'permissions']);
    expect(items.get('scavengerHuntAssets').tag).toBe(AssetLibrarySection);
  });

  it('leaves other extension pages alone', () => {
    expect((pageFor('flarum-tags').sections({} as never) as ItemList<any>).has('scavengerHuntAssets')).toBe(false);
  });
});
