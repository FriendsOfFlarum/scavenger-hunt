import bootstrapAdmin from '@flarum/jest-config/src/bootstrap/admin';
import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import ItemList from 'flarum/common/utils/ItemList';
import AssetLibrarySection from '../../src/admin/components/AssetLibrarySection';

// Stand-ins for the tabbed extension page of core after 2.0.2.
const proto = ExtensionPage.prototype as any;
proto.usesPageTabs = function () {
  return !this.stacked;
};
proto.pageTabItems = function () {
  const items = new ItemList();
  items.add('general', { id: 'general', label: 'General' }, 10);
  items.add('permissions', { id: 'permissions', label: 'Permissions' }, -100);

  return items;
};
proto.pageTabContent = function (tab: string) {
  return `core:${tab}`;
};

beforeAll(async () => {
  bootstrapAdmin();
  await import('../../src/admin/index');
  app.initializers.get('fof-scavenger-hunt')(app);
});

function pageFor(id: string, stacked = false) {
  const page = new ExtensionPage() as any;
  page.extension = { id };
  page.stacked = stacked;
  page.content = () => <div className="settings" />;

  return page;
}

describe('the extension page on core with page tabs', () => {
  it('adds an Images tab before Permissions', () => {
    const tabs = pageFor('fof-scavenger-hunt').pageTabItems().toArray();

    expect(tabs.map((tab: { id: string }) => tab.id)).toEqual(['general', 'scavenger-hunt-assets', 'permissions']);
    expect(tabs[1]).toMatchObject({ icon: 'far fa-images', label: 'fof-scavenger-hunt.admin.assets.tab' });
  });

  it('renders the Asset library in its tab and leaves the other tabs to core', () => {
    const page = pageFor('fof-scavenger-hunt');
    const content = page.pageTabContent('scavenger-hunt-assets', {});

    expect(content.tag).toBe(AssetLibrarySection);
    expect(content.attrs.inTab).toBe(true);
    expect(page.pageTabContent('general', {})).toBe('core:general');
  });

  it('adds no section below the tabs', () => {
    expect(pageFor('fof-scavenger-hunt').sections({}).has('scavengerHuntAssets')).toBe(false);
  });

  it('still adds the section when the page is stacked', () => {
    expect(pageFor('fof-scavenger-hunt', true).sections({}).has('scavengerHuntAssets')).toBe(true);
  });

  it('leaves other extension pages alone', () => {
    const page = pageFor('flarum-tags');

    expect(page.pageTabItems().has('scavenger-hunt-assets')).toBe(false);
    expect(page.pageTabContent('scavenger-hunt-assets', {})).toBe('core:scavenger-hunt-assets');
  });
});
