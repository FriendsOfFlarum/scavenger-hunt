import app from 'flarum/forum/app';
import Component, { type ComponentAttrs } from 'flarum/common/Component';
import Avatar from 'flarum/common/components/Avatar';
import Link from 'flarum/common/components/Link';
import humanTime from 'flarum/common/helpers/humanTime';
import username from 'flarum/common/helpers/username';
import type Completion from '../../common/models/Completion';

export interface FirstFinishersAttrs extends ComponentAttrs {
  completions: Completion[];
}

export default class FirstFinishers extends Component<FirstFinishersAttrs> {
  view() {
    const completions = this.attrs.completions;

    if (!completions.length) {
      return <p className="FirstFinishers-empty">{app.translator.trans('fof-scavenger-hunt.forum.hunt_page.no_finishers')}</p>;
    }

    return (
      <ol className="FirstFinishers">
        {completions.map((completion) => {
          const user = completion.user() || null;

          return (
            <li className="FirstFinishers-item">
              <span className="FirstFinishers-position">{completion.position()}</span>
              {user && app.routes['user'] ? (
                <Link className="FirstFinishers-user" href={app.route.user(user)}>
                  <Avatar user={user} /> {username(user)}
                </Link>
              ) : (
                <span className="FirstFinishers-user">
                  <Avatar user={user} /> {username(user)}
                </span>
              )}
              <span className="FirstFinishers-time">{humanTime(completion.completedAt())}</span>
            </li>
          );
        })}
      </ol>
    );
  }
}
