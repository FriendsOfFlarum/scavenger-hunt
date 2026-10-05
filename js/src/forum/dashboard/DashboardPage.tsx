import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import PageStructure from 'flarum/forum/components/PageStructure';
import Button from 'flarum/common/components/Button';
import Link from 'flarum/common/components/Link';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import type Mithril from 'mithril';
import type Hunt from '../../common/models/Hunt';
import { canManageHunts } from '../utils/activeHunts';
import HuntStatusPill from './HuntStatusPill';

/**
 * Where Hunt managers see every Hunt and start new ones.
 */
export default class DashboardPage extends Page {
  protected hunts: Hunt[] | null = null;

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);

    app.setTitle(app.translator.trans('fof-scavenger-hunt.forum.dashboard.title', {}, true));

    if (canManageHunts()) {
      app.store.find<Hunt[]>('scavenger-hunts', { sort: '-createdAt', page: { limit: 50 } }).then((hunts) => {
        this.hunts = hunts;
        m.redraw();
      });
    }
  }

  view() {
    return (
      <PageStructure className="ScavengerDashboard" hero={() => this.hero()}>
        {this.content()}
      </PageStructure>
    );
  }

  protected hero(): Mithril.Children {
    return (
      <header className="Hero ScavengerDashboard-hero">
        <div className="container">
          <h1 className="Hero-title">{app.translator.trans('fof-scavenger-hunt.forum.dashboard.title')}</h1>
          <div className="Hero-subtitle">{app.translator.trans('fof-scavenger-hunt.forum.dashboard.subtitle')}</div>
        </div>
      </header>
    );
  }

  protected content(): Mithril.Children {
    if (!canManageHunts()) {
      return <p className="ScavengerDashboard-denied">{app.translator.trans('fof-scavenger-hunt.forum.dashboard.denied')}</p>;
    }

    if (!this.hunts) {
      return <LoadingIndicator />;
    }

    return (
      <div className="ScavengerDashboard-hunts">
        <div className="ScavengerDashboard-toolbar">
          <Button className="Button Button--primary" icon="fas fa-plus" onclick={() => app.modal.show(() => import('./TemplateGalleryModal'))}>
            {app.translator.trans('fof-scavenger-hunt.forum.dashboard.new_hunt')}
          </Button>
        </div>

        {this.hunts.length ? (
          <table className="Table ScavengerDashboard-table">
            <thead>
              <tr>
                <th>{app.translator.trans('fof-scavenger-hunt.forum.dashboard.columns.title')}</th>
                <th>{app.translator.trans('fof-scavenger-hunt.forum.dashboard.columns.status')}</th>
                <th>{app.translator.trans('fof-scavenger-hunt.forum.dashboard.columns.parts')}</th>
              </tr>
            </thead>
            <tbody>
              {this.hunts.map((hunt) => (
                <tr>
                  <td>
                    <Link href={app.route('fof-scavenger-hunt.dashboard.hunt', { id: hunt.id() })}>{hunt.title()}</Link>
                  </td>
                  <td>
                    <HuntStatusPill hunt={hunt} />
                  </td>
                  <td>{hunt.partCount()}</td>
                </tr>
              ))}
            </tbody>
          </table>
        ) : (
          <p className="ScavengerDashboard-empty">{app.translator.trans('fof-scavenger-hunt.forum.dashboard.empty')}</p>
        )}
      </div>
    );
  }
}
