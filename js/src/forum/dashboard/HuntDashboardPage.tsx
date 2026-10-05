import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import PageStructure from 'flarum/forum/components/PageStructure';
import Button from 'flarum/common/components/Button';
import Link from 'flarum/common/components/Link';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import classList from 'flarum/common/utils/classList';
import type Mithril from 'mithril';
import type HidingSpotDefinition from '../../common/models/HidingSpotDefinition';
import type Hunt from '../../common/models/Hunt';
import type Part from '../../common/models/Part';
import { canManageHunts } from '../utils/activeHunts';
import HuntForm from './HuntForm';
import HuntRewardsForm from './HuntRewardsForm';
import HuntStatusPill from './HuntStatusPill';
import type { PageRoute } from './LocationRow';
import PartEditor from './PartEditor';

type Tab = 'details' | 'schedule' | 'rewards' | 'parts';

export default class HuntDashboardPage extends Page {
  protected hunt: Hunt | null = null;
  protected parts: Part[] = [];
  protected spots: HidingSpotDefinition[] = [];
  protected routes: PageRoute[] = [];
  protected tab: Tab = 'details';
  protected addingPart = false;

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);

    const tab = m.route.param('tab');
    this.tab = tab === 'schedule' || tab === 'rewards' || tab === 'parts' ? tab : 'details';

    if (!canManageHunts()) {
      return;
    }

    const id = m.route.param('id');

    Promise.all([
      app.store.find<Hunt>('scavenger-hunts', id, { include: 'parts,parts.locations,rewardGroup' }),
      app.store.find<HidingSpotDefinition[]>('scavenger-hunt-spots'),
    ]).then(([hunt, spots]) => {
      this.hunt = hunt;
      this.parts = ((hunt.parts() || []).filter(Boolean) as Part[]).sort((a, b) => a.sort() - b.sort());
      this.spots = spots;
      this.routes = ((spots.payload as { meta?: { routes?: PageRoute[] } }).meta?.routes ?? []) as PageRoute[];

      app.setTitle(hunt.title());
      m.redraw();
    });
  }

  view() {
    return (
      <PageStructure className="ScavengerDashboard ScavengerDashboard--hunt" hero={() => this.hero()} sidebar={() => this.sidebar()}>
        {this.content()}
      </PageStructure>
    );
  }

  protected hero(): Mithril.Children {
    return (
      <header className="Hero ScavengerDashboard-hero">
        <div className="container">
          <Link className="ScavengerDashboard-back" href={app.route('fof-scavenger-hunt.dashboard')}>
            {app.translator.trans('fof-scavenger-hunt.forum.dashboard.back')}
          </Link>
          <h1 className="Hero-title">
            {this.hunt ? this.hunt.title() : app.translator.trans('fof-scavenger-hunt.forum.dashboard.title')}{' '}
            {this.hunt && <HuntStatusPill hunt={this.hunt} />}
          </h1>
        </div>
      </header>
    );
  }

  protected sidebar(): Mithril.Children {
    const tabs: Tab[] = ['details', 'schedule', 'rewards', 'parts'];

    return (
      <nav className="sideNav ScavengerDashboard-nav">
        <ul>
          {tabs.map((tab) => (
            <li className={classList({ active: this.tab === tab })}>
              <Button className={classList('Button Button--link', { active: this.tab === tab })} onclick={() => this.setTab(tab)}>
                {app.translator.trans(`fof-scavenger-hunt.forum.dashboard.tabs.${tab}`)}
              </Button>
            </li>
          ))}
        </ul>
      </nav>
    );
  }

  protected content(): Mithril.Children {
    if (!canManageHunts()) {
      return <p className="ScavengerDashboard-denied">{app.translator.trans('fof-scavenger-hunt.forum.dashboard.denied')}</p>;
    }

    if (!this.hunt) {
      return <LoadingIndicator />;
    }

    if (this.tab === 'parts') {
      return (
        <div className="ScavengerDashboard-parts">
          {this.parts.map((part) => (
            <PartEditor
              key={part.id()}
              part={part}
              spots={this.spots}
              routes={this.routes}
              ondeleted={(deleted: Part) => (this.parts = this.parts.filter((candidate) => candidate !== deleted))}
            />
          ))}
          <Button className="Button Button--primary" icon="fas fa-plus" loading={this.addingPart} onclick={() => this.addPart()}>
            {app.translator.trans('fof-scavenger-hunt.forum.dashboard.parts.add')}
          </Button>
        </div>
      );
    }

    if (this.tab === 'rewards') {
      return <HuntRewardsForm hunt={this.hunt} />;
    }

    return <HuntForm key={this.tab} hunt={this.hunt} section={this.tab} />;
  }

  protected setTab(tab: Tab): void {
    this.tab = tab;

    const params = { ...m.route.param(), tab };
    window.history.replaceState(null, '', app.route('fof-scavenger-hunt.dashboard.hunt', params));
  }

  protected addPart(): void {
    this.addingPart = true;

    app.store
      .createRecord<Part>('scavenger-hunt-parts')
      .save({
        name: app.translator.trans('fof-scavenger-hunt.forum.dashboard.parts.new_name', {}, true),
        sort: this.parts.length,
        relationships: { hunt: this.hunt! },
      })
      .then((part) => this.parts.push(part))
      .catch(() => {})
      .finally(() => {
        this.addingPart = false;
        m.redraw();
      });
  }
}
