import app from 'flarum/forum/app';
import Component, { type ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Select from 'flarum/common/components/Select';
import Group from 'flarum/common/models/Group';
import Stream from 'flarum/common/utils/Stream';
import type Mithril from 'mithril';
import type Hunt from '../../common/models/Hunt';
import toast from '../utils/toast';

export interface HuntRewardsFormAttrs extends ComponentAttrs {
  hunt: Hunt;
}

const NONE = '';

export default class HuntRewardsForm extends Component<HuntRewardsFormAttrs> {
  protected saving = false;
  protected groupId!: Stream<string>;
  protected message!: Stream<string>;

  oninit(vnode: Mithril.Vnode<HuntRewardsFormAttrs, this>) {
    super.oninit(vnode);

    const group = this.attrs.hunt.rewardGroup();

    this.groupId = Stream(group ? group.id()! : NONE);
    this.message = Stream(this.attrs.hunt.rewardMessage() ?? '');
  }

  view() {
    const options: Record<string, string> = { [NONE]: app.translator.trans('fof-scavenger-hunt.forum.dashboard.rewards.no_group', {}, true) };

    app.store
      .all<Group>('groups')
      .filter((group) => ![Group.ADMINISTRATOR_ID, Group.GUEST_ID, Group.MEMBER_ID].includes(group.id()!))
      .forEach((group) => (options[group.id()!] = group.namePlural()));

    return (
      <form className="HuntForm Form" onsubmit={(e: SubmitEvent) => this.save(e)}>
        <div className="Form-group">
          <label>{app.translator.trans('fof-scavenger-hunt.forum.dashboard.rewards.group')}</label>
          <Select options={options} value={this.groupId()} onchange={(value: string) => this.groupId(value)} />
          <div className="helpText">{app.translator.trans('fof-scavenger-hunt.forum.dashboard.rewards.group_help')}</div>
        </div>

        <div className="Form-group">
          <label for="hunt-reward-message">{app.translator.trans('fof-scavenger-hunt.forum.dashboard.rewards.message')}</label>
          <textarea id="hunt-reward-message" className="FormControl" rows={5} maxlength={10000} bidi={this.message} />
          <div className="helpText">{app.translator.trans('fof-scavenger-hunt.forum.dashboard.rewards.message_help')}</div>
        </div>

        <div className="Form-group">
          <Button type="submit" className="Button Button--primary" loading={this.saving}>
            {app.translator.trans('fof-scavenger-hunt.forum.dashboard.save')}
          </Button>
        </div>
      </form>
    );
  }

  protected save(e: SubmitEvent): void {
    e.preventDefault();

    const groupId = this.groupId();

    this.saving = true;

    this.attrs.hunt
      .save({
        rewardMessage: this.message() || null,
        relationships: { rewardGroup: groupId ? (app.store.getById<Group>('groups', groupId) ?? null) : null },
      })
      .then(() => toast(app.translator.trans('fof-scavenger-hunt.forum.dashboard.saved')))
      .catch(() => {})
      .finally(() => {
        this.saving = false;
        m.redraw();
      });
  }
}
