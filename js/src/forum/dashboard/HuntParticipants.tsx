import app from 'flarum/forum/app';
import Component, { type ComponentAttrs } from 'flarum/common/Component';
import Avatar from 'flarum/common/components/Avatar';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Pill from 'flarum/common/components/Pill';
import Select from 'flarum/common/components/Select';
import Tooltip from 'flarum/common/components/Tooltip';
import humanTime from 'flarum/common/helpers/humanTime';
import username from 'flarum/common/helpers/username';
import type Mithril from 'mithril';
import type Hunt from '../../common/models/Hunt';
import type Participant from '../../common/models/Participant';
import toast from '../utils/toast';

export interface HuntParticipantsAttrs extends ComponentAttrs {
  hunt: Hunt;
}

export default class HuntParticipants extends Component<HuntParticipantsAttrs> {
  protected participants: Participant[] = [];
  protected loading = false;
  protected more = false;
  protected search = '';
  protected sort = '-foundCount';
  protected searchTimer: ReturnType<typeof setTimeout> | null = null;

  oninit(vnode: Mithril.Vnode<HuntParticipantsAttrs, this>) {
    super.oninit(vnode);

    this.load(true);
  }

  view() {
    const t = (key: string, params = {}) => app.translator.trans(`fof-scavenger-hunt.forum.dashboard.participants.${key}`, params);
    const sorts = {
      '-foundCount': app.translator.trans('fof-scavenger-hunt.forum.dashboard.participants.sort_found', {}, true),
      completedAt: app.translator.trans('fof-scavenger-hunt.forum.dashboard.participants.sort_completed', {}, true),
      '-lastFoundAt': app.translator.trans('fof-scavenger-hunt.forum.dashboard.participants.sort_recent', {}, true),
    };

    return (
      <div className="HuntParticipants">
        <div className="HuntParticipants-toolbar">
          <input
            className="FormControl"
            type="search"
            placeholder={app.translator.trans('fof-scavenger-hunt.forum.dashboard.participants.search', {}, true)}
            value={this.search}
            oninput={(e: InputEvent) => this.searchFor((e.target as HTMLInputElement).value)}
          />
          <Select options={sorts} value={this.sort} onchange={(value: string) => this.sortBy(value)} />
        </div>

        {this.loading && !this.participants.length ? (
          <LoadingIndicator />
        ) : this.participants.length ? (
          <div className="ScavengerDashboard-tableScroll">
            <table className="ScavengerDashboard-table HuntParticipants-table">
              <thead>
                <tr>
                  <th>{t('member')}</th>
                  <th>{t('found')}</th>
                  <th>{t('completed')}</th>
                  <th>{t('average_claim')}</th>
                  <th />
                </tr>
              </thead>
              <tbody>{this.participants.map((participant) => this.row(participant))}</tbody>
            </table>
          </div>
        ) : (
          <p className="helpText">{t('none')}</p>
        )}

        {this.more && (
          <Button className="Button" loading={this.loading} onclick={() => this.load(false)}>
            {app.translator.trans('fof-scavenger-hunt.forum.hunt_page.load_more')}
          </Button>
        )}
      </div>
    );
  }

  protected row(participant: Participant): Mithril.Children {
    const user = participant.user() || null;
    const completedAt = participant.completedAt();
    const ms = participant.averageClaimMs();

    return (
      <tr>
        <td>
          <div className="HuntParticipants-member">
            <Avatar user={user} /> {username(user)}
            {participant.fastClaimFlag() && (
              <Tooltip text={app.translator.trans('fof-scavenger-hunt.forum.dashboard.participants.fast_claims_help', {}, true)}>
                <Pill className="HuntParticipants-flag">{app.translator.trans('fof-scavenger-hunt.forum.dashboard.participants.fast_claims')}</Pill>
              </Tooltip>
            )}
          </div>
        </td>
        <td>
          {participant.foundCount()}/{this.attrs.hunt.partCount()}
        </td>
        <td>{completedAt ? [`#${participant.position()} · `, humanTime(completedAt)] : '—'}</td>
        <td>{ms === null ? '—' : `${(ms / 1000).toFixed(1)} s`}</td>
        <td className="HuntParticipants-actions">
          {user && (
            <Button
              className="Button Button--icon"
              icon="fas fa-list"
              aria-label={app.translator.trans('fof-scavenger-hunt.forum.dashboard.participants.view_finds', {}, true)}
              onclick={() => app.modal.show(() => import('./ParticipantFindsModal'), { huntId: this.attrs.hunt.id(), user })}
            />
          )}
          {user && (
            <Button
              className="Button Button--icon Button--danger"
              icon="fas fa-undo"
              aria-label={app.translator.trans('fof-scavenger-hunt.forum.dashboard.participants.reset', {}, true)}
              onclick={() => this.reset(participant)}
            />
          )}
        </td>
      </tr>
    );
  }

  protected searchFor(value: string): void {
    this.search = value;

    if (this.searchTimer) clearTimeout(this.searchTimer);

    this.searchTimer = setTimeout(() => this.load(true), 300);
  }

  protected sortBy(value: string): void {
    this.sort = value;
    this.load(true);
  }

  protected load(fresh: boolean): void {
    this.loading = true;

    const filter: Record<string, string> = { hunt: this.attrs.hunt.id()! };
    if (this.search.trim()) filter.q = this.search.trim();

    app.store
      .find<Participant[]>('scavenger-hunt-participants', {
        filter,
        sort: this.sort,
        include: 'user',
        page: { offset: fresh ? 0 : this.participants.length, limit: 20 },
      })
      .then((participants) => {
        this.participants = fresh ? participants : [...this.participants, ...participants];
        this.more = !!participants.payload?.links?.next;
      })
      .finally(() => {
        this.loading = false;
        m.redraw();
      });
  }

  protected reset(participant: Participant): void {
    const user = participant.user();

    if (!user || !confirm(app.translator.trans('fof-scavenger-hunt.forum.dashboard.participants.reset_confirm', { user: username(user) }, true))) {
      return;
    }

    app
      .request({
        method: 'POST',
        url: `${app.forum.attribute('apiUrl')}/scavenger-hunts/${this.attrs.hunt.id()}/reset-progress`,
        body: { data: { attributes: { userId: user.id() } } },
      })
      .then(() => {
        this.participants = this.participants.filter((candidate) => candidate !== participant);
        toast(app.translator.trans('fof-scavenger-hunt.forum.dashboard.participants.reset_done', { user: username(user) }));
        m.redraw();
      });
  }
}
