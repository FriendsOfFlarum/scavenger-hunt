import app from 'flarum/forum/app';
import Modal, { type IInternalModalAttrs } from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import classList from 'flarum/common/utils/classList';
import type Mithril from 'mithril';
import type Asset from '../../common/models/Asset';
import type HuntTemplate from '../../common/models/HuntTemplate';

export interface IImagePickerModalAttrs extends IInternalModalAttrs {
  selected: string | null;
  onselect: (reference: string | null, url: string | null) => void;
}

type Tab = 'templates' | 'assets';

/**
 * Picks an image reference: a Template image (`template:<key>/<file>`) or an uploaded Asset (`asset:<id>`).
 */
export default class ImagePickerModal extends Modal<IImagePickerModalAttrs> {
  protected tab: Tab = 'templates';
  protected templates: HuntTemplate[] | null = null;
  protected templatesFailed = false;
  protected assets: Asset[] | null = null;
  protected assetsFailed = false;
  protected loadingAssets = false;
  protected moreAssets = false;

  oninit(vnode: Mithril.Vnode<IImagePickerModalAttrs, this>) {
    super.oninit(vnode);

    this.loadTemplates();

    if (this.attrs.selected?.startsWith('asset:')) {
      this.showTab('assets');
    }
  }

  className() {
    return 'ImagePickerModal Modal--large';
  }

  title() {
    return app.translator.trans('fof-scavenger-hunt.forum.dashboard.images.title');
  }

  content() {
    return (
      <div className="Modal-body">
        <div className="ImagePicker-toolbar">
          <div className="ImagePicker-tabs">
            {(['templates', 'assets'] as const).map((tab) => (
              <Button
                className={classList('Button Button--link ImagePicker-tab', `ImagePicker-tab--${tab}`, { active: this.tab === tab })}
                aria-pressed={this.tab === tab ? 'true' : 'false'}
                onclick={() => this.showTab(tab)}
              >
                {app.translator.trans(`fof-scavenger-hunt.forum.dashboard.images.tabs.${tab}`)}
              </Button>
            ))}
          </div>

          <Button
            className={classList('Button ImagePicker-none', { active: this.attrs.selected === null })}
            icon="fas fa-ban"
            aria-pressed={this.attrs.selected === null ? 'true' : 'false'}
            onclick={() => this.select(null, null)}
          >
            {app.translator.trans('fof-scavenger-hunt.forum.dashboard.images.none')}
          </Button>
        </div>

        <div className="ImagePicker-panel">{this.tab === 'templates' ? this.templatesPanel() : this.assetsPanel()}</div>
      </div>
    );
  }

  protected templatesPanel(): Mithril.Children {
    if (this.templatesFailed) return this.loadError(() => this.loadTemplates());
    if (!this.templates) return <LoadingIndicator />;

    return this.templates.map((template) => (
      <section className="ImagePicker-group">
        <h4 className="ImagePicker-groupTitle">{template.title()}</h4>
        <ul className="ImagePicker-grid">
          {template.imageReference() &&
            this.item(
              template.imageReference()!,
              template.imageUrl(),
              app.translator.trans('fof-scavenger-hunt.forum.dashboard.images.base_image', {}, true)
            )}
          {template.parts().map((part) => this.item(part.reference, part.imageUrl, part.name))}
        </ul>
      </section>
    ));
  }

  protected assetsPanel(): Mithril.Children {
    if (this.assetsFailed) return this.loadError(() => this.loadAssets());
    if (!this.assets) return <LoadingIndicator />;

    if (!this.assets.length) {
      return <p className="ImagePicker-empty helpText">{app.translator.trans('fof-scavenger-hunt.forum.dashboard.images.empty')}</p>;
    }

    return [
      <ul className="ImagePicker-grid">{this.assets.map((asset) => this.item(`asset:${asset.id()}`, asset.url(), asset.name()))}</ul>,
      this.moreAssets && (
        <Button className="Button ImagePicker-more" loading={this.loadingAssets} onclick={() => this.loadAssets()}>
          {app.translator.trans('fof-scavenger-hunt.forum.dashboard.images.load_more')}
        </Button>
      ),
    ];
  }

  protected item(reference: string, url: string | null, label: string): Mithril.Children {
    const selected = this.attrs.selected === reference;

    return (
      <li>
        <button
          type="button"
          className={classList('ImagePicker-item', { 'ImagePicker-item--selected': selected })}
          aria-pressed={selected ? 'true' : 'false'}
          data-reference={reference}
          onclick={() => this.select(reference, url)}
        >
          <span className="ImagePicker-thumbnail">{url && <img src={url} alt="" loading="lazy" />}</span>
          <span className="ImagePicker-label">{label}</span>
        </button>
      </li>
    );
  }

  protected loadError(retry: () => void): Mithril.Children {
    return (
      <div className="ImagePicker-error">
        <p>{app.translator.trans('fof-scavenger-hunt.forum.dashboard.images.load_failed')}</p>
        <Button className="Button ImagePicker-retry" icon="fas fa-redo" onclick={retry}>
          {app.translator.trans('fof-scavenger-hunt.forum.dashboard.images.retry')}
        </Button>
      </div>
    );
  }

  protected loadTemplates(): void {
    this.templatesFailed = false;

    app.store
      .find<HuntTemplate[]>('scavenger-hunt-templates')
      .then((templates) => (this.templates = templates))
      .catch(() => (this.templatesFailed = true))
      .finally(() => m.redraw());
  }

  protected showTab(tab: Tab): void {
    this.tab = tab;

    if (tab === 'assets' && !this.assets && !this.loadingAssets) {
      this.loadAssets();
    }
  }

  protected loadAssets(): void {
    const known = this.assets ?? [];

    this.loadingAssets = true;
    this.assetsFailed = false;

    app.store
      .find<Asset[]>('scavenger-hunt-assets', { page: { offset: known.length } })
      .then((assets) => {
        this.assets = [...known, ...assets.filter((asset) => !known.includes(asset))];
        this.moreAssets = !!assets.payload?.links?.next;
      })
      // A failed first page offers a retry; a failed later page leaves "Load more" to try again.
      .catch(() => (this.assetsFailed = !this.assets))
      .finally(() => {
        this.loadingAssets = false;
        m.redraw();
      });
  }

  protected select(reference: string | null, url: string | null): void {
    this.attrs.onselect(reference, url);
    this.hide();
  }
}
