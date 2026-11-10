import app from 'flarum/admin/app';
import Component, { type ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Tooltip from 'flarum/common/components/Tooltip';
import type Mithril from 'mithril';
import type Asset from '../../common/models/Asset';

export interface IAssetCardAttrs extends ComponentAttrs {
  asset: Asset;
  ondeleted: () => void;
}

export default class AssetCard<CustomAttrs extends IAssetCardAttrs = IAssetCardAttrs> extends Component<CustomAttrs> {
  protected name = '';
  protected saving = false;
  protected deleting = false;

  oninit(vnode: Mithril.Vnode<CustomAttrs, this>) {
    super.oninit(vnode);

    this.name = this.attrs.asset.name();
  }

  view() {
    const asset = this.attrs.asset;

    return (
      <li className={`AssetLibrary-item${asset.isInUse() ? ' AssetLibrary-item--inUse' : ''}`}>
        <div className="AssetLibrary-thumbnail">
          <img src={asset.url()} alt="" loading="lazy" />
        </div>
        <input
          className="FormControl AssetLibrary-name"
          value={this.name}
          maxlength={100}
          readonly={this.saving}
          aria-busy={this.saving ? 'true' : 'false'}
          aria-label={app.translator.trans('fof-scavenger-hunt.admin.assets.name_label', { name: asset.name() }, true)}
          oninput={(e: InputEvent) => (this.name = (e.target as HTMLInputElement).value)}
          onchange={() => this.rename()}
          onkeydown={(e: KeyboardEvent) => {
            if (e.key === 'Escape') this.name = asset.name();
          }}
        />
        <p className="AssetLibrary-meta">{this.meta()}</p>
        <div className="AssetLibrary-actions">
          {asset.isInUse() && [
            <span className="AssetLibrary-inUse" id={`${this.idPrefix()}-in-use`}>
              {app.translator.trans('fof-scavenger-hunt.admin.assets.in_use')}
            </span>,
            <span className="visually-hidden" id={`${this.idPrefix()}-in-use-help`}>
              {app.translator.trans('fof-scavenger-hunt.admin.assets.in_use_help')}
            </span>,
          ]}
          {this.deleteButton()}
        </div>
      </li>
    );
  }

  protected idPrefix(): string {
    return `scavenger-hunt-asset-${this.attrs.asset.id()}`;
  }

  protected deleteButton(): Mithril.Children {
    const inUse = this.attrs.asset.isInUse();
    const button = (
      <Button
        className="Button Button--icon Button--danger AssetLibrary-delete"
        icon="fas fa-trash-alt"
        disabled={inUse}
        loading={this.deleting}
        aria-label={app.translator.trans('fof-scavenger-hunt.admin.assets.delete', { name: this.attrs.asset.name() }, true)}
        aria-describedby={inUse ? `${this.idPrefix()}-in-use ${this.idPrefix()}-in-use-help` : undefined}
        onclick={() => this.delete()}
      />
    );

    if (!inUse) return button;

    // A disabled button gets no pointer events, so the tooltip sits on a wrapper.
    return (
      <Tooltip text={app.translator.trans('fof-scavenger-hunt.admin.assets.in_use_help', {}, true)}>
        <span className="AssetLibrary-deleteWrapper">{button}</span>
      </Tooltip>
    );
  }

  protected meta(): string {
    const asset = this.attrs.asset;
    const width = asset.width();
    const height = asset.height();
    const parts: string[] = [];

    if (width && height) {
      parts.push(app.translator.trans('fof-scavenger-hunt.admin.assets.dimensions', { width, height }, true));
    }

    parts.push(this.formatSize(asset.size()));

    return parts.join(' · ');
  }

  protected formatSize(bytes: number): string {
    const format = (value: number) => new Intl.NumberFormat(app.data.locale, { maximumFractionDigits: 1 }).format(value);

    return bytes >= 1024 * 1024
      ? app.translator.trans('fof-scavenger-hunt.admin.assets.size_mb', { size: format(bytes / (1024 * 1024)) }, true)
      : app.translator.trans('fof-scavenger-hunt.admin.assets.size_kb', { size: format(bytes / 1024) }, true);
  }

  protected rename(): void {
    const asset = this.attrs.asset;
    const name = this.name.trim();

    if (this.saving) return;

    if (!name || name === asset.name()) {
      this.name = asset.name();
      return;
    }

    this.saving = true;

    // A rejected name is shown by core's alert handling; the field then goes back to the saved name.
    asset
      .save({ name })
      .catch(() => {})
      .finally(() => {
        this.saving = false;
        this.name = asset.name();
        m.redraw();
      });
  }

  protected delete(): void {
    const asset = this.attrs.asset;

    if (asset.isInUse() || !confirm(app.translator.trans('fof-scavenger-hunt.admin.assets.delete_confirm', { name: asset.name() }, true))) {
      return;
    }

    this.deleting = true;

    asset
      .delete()
      .then(() => this.attrs.ondeleted())
      .catch(() => {})
      .finally(() => {
        this.deleting = false;
        m.redraw();
      });
  }
}
