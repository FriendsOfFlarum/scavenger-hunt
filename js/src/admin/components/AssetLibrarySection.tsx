import app from 'flarum/admin/app';
import Component, { type ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import type Mithril from 'mithril';
import type AssetLibraryComponent from './AssetLibrary';

export interface IAssetLibrarySectionAttrs extends ComponentAttrs {
  /** Rendered in a tab of the extension page, which names it already. */
  inTab?: boolean;
}

let AssetLibrary: typeof AssetLibraryComponent | null = null;
let loading: Promise<void> | null = null;

function loadAssetLibrary(): Promise<void> {
  return (loading ??= import('./AssetLibrary')
    .then(({ default: component }) => {
      AssetLibrary = component;
    })
    .catch((error) => {
      loading = null;
      throw error;
    }));
}

/**
 * The extension page's Asset library. The library itself is a lazy chunk, loaded the first time this renders.
 */
export default class AssetLibrarySection<CustomAttrs extends IAssetLibrarySectionAttrs = IAssetLibrarySectionAttrs> extends Component<CustomAttrs> {
  protected failed = false;

  oninit(vnode: Mithril.Vnode<CustomAttrs, this>) {
    super.oninit(vnode);

    if (!AssetLibrary) this.load();
  }

  view() {
    if (this.attrs.inTab) {
      return <div className="AssetLibrarySection AssetLibrarySection--tab">{this.library()}</div>;
    }

    return (
      <div className="AssetLibrarySection">
        <div className="AssetLibrarySection-header">
          <div className="container">
            <h2 className="ExtensionTitle">{app.translator.trans('fof-scavenger-hunt.admin.assets.title')}</h2>
          </div>
        </div>
        <div className="container">{this.library()}</div>
      </div>
    );
  }

  protected library(): Mithril.Children {
    if (AssetLibrary) return <AssetLibrary />;

    if (this.failed) {
      return (
        <div className="AssetLibrary-error">
          <p>{app.translator.trans('fof-scavenger-hunt.admin.assets.load_failed')}</p>
          <Button className="Button AssetLibrarySection-retry" icon="fas fa-redo" onclick={() => this.load()}>
            {app.translator.trans('fof-scavenger-hunt.admin.assets.retry')}
          </Button>
        </div>
      );
    }

    return <LoadingIndicator />;
  }

  protected load(): void {
    this.failed = false;

    loadAssetLibrary()
      .catch(() => (this.failed = true))
      .finally(() => m.redraw());
  }
}
