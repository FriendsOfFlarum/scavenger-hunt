import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import type Mithril from 'mithril';
import type AssetLibraryComponent from './AssetLibrary';

let AssetLibrary: typeof AssetLibraryComponent | null = null;

/**
 * The extension page's Asset library section. The library itself is a lazy chunk, loaded the first time this renders.
 */
export default class AssetLibrarySection extends Component {
  oninit(vnode: Mithril.Vnode<{}, this>) {
    super.oninit(vnode);

    if (!AssetLibrary) {
      import('./AssetLibrary').then(({ default: component }) => {
        AssetLibrary = component;
        m.redraw();
      });
    }
  }

  view() {
    return (
      <div className="AssetLibrarySection">
        <div className="AssetLibrarySection-header">
          <div className="container">
            <h2 className="ExtensionTitle">{app.translator.trans('fof-scavenger-hunt.admin.assets.title')}</h2>
          </div>
        </div>
        <div className="container">{AssetLibrary ? <AssetLibrary /> : <LoadingIndicator />}</div>
      </div>
    );
  }
}
