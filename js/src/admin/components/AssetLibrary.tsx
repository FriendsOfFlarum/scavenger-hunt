import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import type Mithril from 'mithril';
import type Asset from '../../common/models/Asset';
import uploadAsset, { ACCEPTED_IMAGE_TYPES } from '../../common/utils/uploadAsset';
import AssetCard from './AssetCard';
import showArtworkGuide from '../../common/utils/showArtworkGuide';

export default class AssetLibrary extends Component {
  protected assets: Asset[] = [];
  protected loading = false;
  protected uploading = false;
  protected more = false;
  protected failed = false;

  oninit(vnode: Mithril.Vnode<{}, this>) {
    super.oninit(vnode);

    this.load(true);
  }

  view() {
    return (
      <div className="AssetLibrary">
        <p className="helpText">{app.translator.trans('fof-scavenger-hunt.admin.assets.help')}</p>
        <div className="AssetLibrary-guide">
          <p className="helpText">{app.translator.trans('fof-scavenger-hunt.admin.assets.summary')}</p>
          <Button className="Button Button--link AssetLibrary-guideLink" icon="fas fa-palette" onclick={() => showArtworkGuide()}>
            {app.translator.trans('fof-scavenger-hunt.lib.artwork.guide_link')}
          </Button>
        </div>

        <div className="AssetLibrary-toolbar">
          <input
            className="AssetLibrary-fileInput"
            type="file"
            accept={ACCEPTED_IMAGE_TYPES}
            hidden
            onchange={(e: Event) => this.upload(e.target as HTMLInputElement)}
          />
          <Button className="Button Button--primary" icon="fas fa-upload" loading={this.uploading} onclick={() => this.chooseFile()}>
            {app.translator.trans('fof-scavenger-hunt.admin.assets.upload')}
          </Button>
          <span className="helpText">{app.translator.trans('fof-scavenger-hunt.admin.assets.upload_help')}</span>
        </div>

        {this.failed ? (
          <div className="AssetLibrary-error">
            <p>{app.translator.trans('fof-scavenger-hunt.admin.assets.load_failed')}</p>
            <Button className="Button AssetLibrary-retry" icon="fas fa-redo" onclick={() => this.load(true)}>
              {app.translator.trans('fof-scavenger-hunt.admin.assets.retry')}
            </Button>
          </div>
        ) : this.loading && !this.assets.length ? (
          <LoadingIndicator />
        ) : this.assets.length ? (
          <ul className="AssetLibrary-grid">
            {this.assets.map((asset) => (
              <AssetCard key={asset.id()} asset={asset} ondeleted={() => this.remove(asset)} />
            ))}
          </ul>
        ) : (
          <p className="AssetLibrary-empty helpText">{app.translator.trans('fof-scavenger-hunt.admin.assets.empty')}</p>
        )}

        {this.more && (
          <Button className="Button" loading={this.loading} onclick={() => this.load(false)}>
            {app.translator.trans('fof-scavenger-hunt.admin.assets.load_more')}
          </Button>
        )}
      </div>
    );
  }

  protected load(fresh: boolean): void {
    this.loading = true;
    this.failed = false;

    app.store
      .find<Asset[]>('scavenger-hunt-assets', { page: { offset: fresh ? 0 : this.assets.length } })
      .then((assets) => {
        const known = fresh ? [] : this.assets;

        this.assets = [...known, ...assets.filter((asset) => !known.includes(asset))];
        this.more = !!assets.payload?.links?.next;
      })
      // A failed later page leaves "Load more" to try again.
      .catch(() => (this.failed = fresh))
      .finally(() => {
        this.loading = false;
        m.redraw();
      });
  }

  protected chooseFile(): void {
    this.element.querySelector<HTMLInputElement>('.AssetLibrary-fileInput')?.click();
  }

  protected upload(input: HTMLInputElement): void {
    const file = input.files?.[0];

    if (!file || this.uploading) return;

    input.value = '';

    this.uploading = true;
    m.redraw();

    uploadAsset(file)
      .then((asset) => {
        this.assets = [asset, ...this.assets];
        this.confirm(asset);
      })
      .catch(() => {})
      .finally(() => {
        this.uploading = false;
        m.redraw();
      });
  }

  /** Names the size, the first thing to check against the artwork guide. */
  protected confirm(asset: Asset): void {
    const width = asset.width();
    const height = asset.height();

    app.alerts.show(
      { type: 'success' },
      width && height
        ? app.translator.trans('fof-scavenger-hunt.admin.assets.uploaded_size', { name: asset.name(), width, height })
        : app.translator.trans('fof-scavenger-hunt.admin.assets.uploaded', { name: asset.name() })
    );
  }

  protected remove(asset: Asset): void {
    this.assets = this.assets.filter((candidate) => candidate !== asset);
  }
}
