import app from 'flarum/forum/app';
import Modal, { type IInternalModalAttrs } from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import classList from 'flarum/common/utils/classList';
import type Mithril from 'mithril';
import type Asset from '../../common/models/Asset';
import type HuntTemplate from '../../common/models/HuntTemplate';
import { artworkFit, backgroundCheck, type ArtworkPurpose } from '../../common/utils/artworkFit';
import showArtworkGuide from '../../common/utils/showArtworkGuide';
import uploadAsset, { ACCEPTED_IMAGE_TYPES } from '../../common/utils/uploadAsset';

export interface IImagePickerModalAttrs extends IInternalModalAttrs {
  selected: string | null;
  /** The key of the Template the Hunt was made from, listed first. */
  template?: string | null;
  /** What the image is for, which decides the hint and the warnings. */
  purpose?: ArtworkPurpose;
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
  protected uploading = false;
  /** The Asset uploaded from this picker, marked until it closes. */
  protected justUploaded: Asset | null = null;
  /** Whether each Part image has a solid background; `undefined` while it's being checked. */
  protected solid: Record<string, boolean | null | undefined> = {};

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
    const purpose = this.attrs.purpose;

    return (
      <div className="Modal-body">
        {purpose && (
          <p className="ImagePicker-hint">
            <span className="helpText">{app.translator.trans(`fof-scavenger-hunt.lib.artwork.hints.${purpose}`)}</span>{' '}
            <Button className="Button Button--link ImagePicker-guide" icon="fas fa-palette" onclick={() => showArtworkGuide()}>
              {app.translator.trans('fof-scavenger-hunt.lib.artwork.guide_link')}
            </Button>
          </p>
        )}

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

    const own = this.attrs.template;
    const templates = own ? [...this.templates].sort((a, b) => Number(b.id() === own) - Number(a.id() === own)) : this.templates;

    return templates.map((template) => (
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
    const canUpload = !!app.forum.attribute('scavengerHuntCanUploadImages');
    const upload = canUpload && (
      <div className="ImagePicker-upload">
        <input
          className="ImagePicker-fileInput"
          type="file"
          accept={ACCEPTED_IMAGE_TYPES}
          hidden
          onchange={(e: Event) => this.upload(e.target as HTMLInputElement)}
        />
        <Button className="Button ImagePicker-uploadButton" icon="fas fa-upload" loading={this.uploading} onclick={() => this.chooseFile()}>
          {app.translator.trans('fof-scavenger-hunt.forum.dashboard.images.upload')}
        </Button>
      </div>
    );

    if (this.assetsFailed) return [upload, this.loadError(() => this.loadAssets())];
    if (!this.assets) return <LoadingIndicator />;

    if (!this.assets.length) {
      return [
        upload,
        <p className="ImagePicker-empty helpText">
          {app.translator.trans(`fof-scavenger-hunt.forum.dashboard.images.${canUpload ? 'empty_can_upload' : 'empty_ask_admin'}`)}
        </p>,
      ];
    }

    return [
      upload,
      <ul className="ImagePicker-grid">{this.assets.map((asset) => this.item(`asset:${asset.id()}`, asset.url(), asset.name(), asset))}</ul>,
      this.moreAssets && (
        <Button className="Button ImagePicker-more" loading={this.loadingAssets} onclick={() => this.loadAssets()}>
          {app.translator.trans('fof-scavenger-hunt.forum.dashboard.images.load_more')}
        </Button>
      ),
    ];
  }

  protected item(reference: string, url: string | null, label: string, asset?: Asset): Mithril.Children {
    const selected = this.attrs.selected === reference;
    const warnings = asset ? this.warnings(asset) : [];

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
          <span className="ImagePicker-label" title={label}>
            {label}
          </span>
          {asset === this.justUploaded && asset && (
            <span className="ImagePicker-new">{app.translator.trans('fof-scavenger-hunt.forum.dashboard.images.just_uploaded')}</span>
          )}
          {warnings.length > 0 && (
            <span className="ImagePicker-warnings">
              {warnings.map((warning) => (
                <span className={`ArtworkWarning ArtworkWarning--${warning.key}`}>{warning.text}</span>
              ))}
            </span>
          )}
        </button>
      </li>
    );
  }

  /** Soft warnings for an uploaded image that doesn't fit what it's being chosen for. */
  protected warnings(asset: Asset): { key: string; text: Mithril.Children }[] {
    const purpose = this.attrs.purpose;

    if (!purpose) return [];

    const width = asset.width();
    const height = asset.height();
    const warnings: { key: string; text: Mithril.Children }[] = artworkFit(purpose, { width, height, mimeType: asset.mimeType() }).map((issue) => ({
      key: issue,
      text: app.translator.trans(`fof-scavenger-hunt.lib.artwork.warnings.${issue}`, { width, height }),
    }));

    if (purpose === 'part' && this.isSolid(asset.url())) {
      warnings.push({ key: 'solid_background', text: app.translator.trans('fof-scavenger-hunt.lib.artwork.warnings.solid_background') });
    }

    return warnings;
  }

  protected isSolid(url: string): boolean {
    if (!(url in this.solid)) {
      this.solid[url] = undefined;

      backgroundCheck.isSolid(url).then((solid) => {
        this.solid[url] = solid;
        if (solid) m.redraw();
      });
    }

    return this.solid[url] === true;
  }

  protected chooseFile(): void {
    this.element.querySelector<HTMLInputElement>('.ImagePicker-fileInput')?.click();
  }

  protected upload(input: HTMLInputElement): void {
    const file = input.files?.[0];

    if (!file || this.uploading) return;

    input.value = '';
    this.uploading = true;
    m.redraw();

    uploadAsset(file)
      .then((asset) => {
        // Not chosen straight away, so its warnings are seen first.
        this.assets = [asset, ...(this.assets ?? []).filter((known) => known !== asset)];
        this.justUploaded = asset;
      })
      .catch(() => {})
      .finally(() => {
        this.uploading = false;
        m.redraw();
      });
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
