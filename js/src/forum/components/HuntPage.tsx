import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import PageStructure from 'flarum/forum/components/PageStructure';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import fullTime from 'flarum/common/helpers/fullTime';
import type Mithril from 'mithril';
import type Completion from '../../common/models/Completion';
import type Hunt from '../../common/models/Hunt';
import CollectionView from './CollectionView';
import FirstFinishers from './FirstFinishers';

/**
 * A Hunt's public page: what it is, how far the member has got, and who finished first.
 */
export default class HuntPage extends Page {
  protected hunt: Hunt | null = null;
  protected notFound = false;
  protected finishers: Completion[] = [];
  protected moreFinishers = false;
  protected loadingFinishers = false;

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);

    app.store
      .find<Hunt>('scavenger-hunts', m.route.param('slug'), { include: 'parts' })
      .then((hunt) => {
        this.hunt = hunt;
        app.setTitle(hunt.title());
        this.loadFinishers();
      })
      .catch(() => {
        this.notFound = true;
        m.redraw();
      });
  }

  view() {
    return (
      <PageStructure className="HuntPage" hero={() => this.hero()} sidebar={() => <IndexSidebar />} loading={!this.hunt && !this.notFound}>
        {this.content()}
      </PageStructure>
    );
  }

  protected hero(): Mithril.Children {
    const hunt = this.hunt;

    if (!hunt) return null;

    return (
      <header className="Hero HuntPage-hero">
        <div className="container">
          <h1 className="Hero-title">{hunt.title()}</h1>
          {hunt.description() && <div className="Hero-subtitle">{hunt.description()}</div>}
          <div className="HuntPage-schedule">
            {hunt.startsAt() && (
              <span>{app.translator.trans('fof-scavenger-hunt.forum.hunt_page.starts', { time: fullTime(hunt.startsAt()!) })}</span>
            )}
            {hunt.endsAt() && <span>{app.translator.trans('fof-scavenger-hunt.forum.hunt_page.ends', { time: fullTime(hunt.endsAt()!) })}</span>}
            {!hunt.isActive() && <span className="HuntPage-ended">{app.translator.trans('fof-scavenger-hunt.forum.hunt_page.not_running')}</span>}
          </div>
        </div>
      </header>
    );
  }

  protected content(): Mithril.Children {
    if (this.notFound) {
      return <p className="HuntPage-notFound">{app.translator.trans('fof-scavenger-hunt.forum.hunt_page.not_found')}</p>;
    }

    if (!this.hunt) return null;

    return (
      <div className="HuntPage-content">
        <CollectionView hunt={this.hunt} />

        <section className="HuntPage-finishers">
          <h3>{app.translator.trans('fof-scavenger-hunt.forum.hunt_page.first_finishers')}</h3>
          {this.loadingFinishers && !this.finishers.length ? <LoadingIndicator /> : <FirstFinishers completions={this.finishers} />}
          {this.moreFinishers && (
            <Button className="Button" loading={this.loadingFinishers} onclick={() => this.loadFinishers()}>
              {app.translator.trans('fof-scavenger-hunt.forum.hunt_page.load_more')}
            </Button>
          )}
        </section>
      </div>
    );
  }

  protected loadFinishers(): void {
    this.loadingFinishers = true;

    app.store
      .find<Completion[]>('scavenger-hunt-completions', {
        filter: { hunt: this.hunt!.id() },
        include: 'user',
        page: { offset: this.finishers.length, limit: 20 },
      })
      .then((completions) => {
        this.finishers = [...this.finishers, ...completions];
        this.moreFinishers = !!completions.payload?.links?.next;
      })
      .finally(() => {
        this.loadingFinishers = false;
        m.redraw();
      });
  }
}
