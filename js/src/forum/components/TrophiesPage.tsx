import app from 'flarum/forum/app';
import UserPage from 'flarum/forum/components/UserPage';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import type User from 'flarum/common/models/User';
import type Mithril from 'mithril';
import type Completion from '../../common/models/Completion';
import TrophyCard from './TrophyCard';

export default class TrophiesPage extends UserPage {
  protected completions: Completion[] | null = null;

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);

    this.loadUser(m.route.param('username'));
  }

  show(user: User): void {
    super.show(user);

    this.completions = null;

    app.store
      .find<Completion[]>('scavenger-hunt-completions', {
        filter: { user: user.id() },
        include: 'hunt,hunt.parts',
        sort: '-completedAt',
        page: { limit: 50 },
      })
      .then((completions) => {
        this.completions = completions;
        m.redraw();
      });
  }

  content() {
    if (!this.completions) {
      return <LoadingIndicator />;
    }

    return (
      <div className="TrophiesPage">
        {this.completions.length ? (
          <div className="TrophiesPage-grid">
            {this.completions.map((completion) => (
              <TrophyCard completion={completion} />
            ))}
          </div>
        ) : (
          <p className="TrophiesPage-empty">{app.translator.trans('fof-scavenger-hunt.forum.trophies.empty')}</p>
        )}
      </div>
    );
  }
}
