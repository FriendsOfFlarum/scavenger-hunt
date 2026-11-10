import app from 'flarum/forum/app';
import toast from '../utils/toast';
import Component, { type ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Switch from 'flarum/common/components/Switch';
import Stream from 'flarum/common/utils/Stream';
import type Mithril from 'mithril';
import type Hunt from '../../common/models/Hunt';
import ImageField from './ImageField';

export interface HuntFormAttrs extends ComponentAttrs {
  hunt: Hunt;
  section: 'details' | 'schedule';
}

function toLocalInput(date: Date | null): string {
  if (!date) return '';

  const offset = date.getTimezoneOffset() * 60000;

  return new Date(date.getTime() - offset).toISOString().slice(0, 16);
}

function fromLocalInput(value: string): string | null {
  return value ? new Date(value).toISOString() : null;
}

export default class HuntForm extends Component<HuntFormAttrs> {
  protected saving = false;
  protected title!: Stream<string>;
  protected slug!: Stream<string>;
  protected description!: Stream<string>;
  protected image!: Stream<string | null>;
  protected completedImage!: Stream<string | null>;
  protected isEnabled!: Stream<boolean>;
  protected guestsCanSee!: Stream<boolean>;
  protected startsAt!: Stream<string>;
  protected endsAt!: Stream<string>;

  oninit(vnode: Mithril.Vnode<HuntFormAttrs, this>) {
    super.oninit(vnode);

    const hunt = this.attrs.hunt;

    this.title = Stream(hunt.title());
    this.slug = Stream(hunt.slug());
    this.description = Stream(hunt.description() ?? '');
    this.image = Stream(hunt.image() ?? null);
    this.completedImage = Stream(hunt.completedImage() ?? null);
    this.isEnabled = Stream(!!hunt.isEnabled());
    this.guestsCanSee = Stream(!!hunt.guestsCanSee());
    this.startsAt = Stream(toLocalInput(hunt.startsAt()));
    this.endsAt = Stream(toLocalInput(hunt.endsAt()));
  }

  view() {
    return (
      <form className="HuntForm Form" onsubmit={(e: SubmitEvent) => this.save(e)}>
        {this.attrs.section === 'details' ? this.detailsFields() : this.scheduleFields()}

        <div className="Form-group">
          <Button type="submit" className="Button Button--primary" loading={this.saving}>
            {app.translator.trans('fof-scavenger-hunt.forum.dashboard.save')}
          </Button>
        </div>
      </form>
    );
  }

  protected detailsFields(): Mithril.Children {
    return [
      <div className="Form-group">
        <label for="hunt-title">{app.translator.trans('fof-scavenger-hunt.forum.dashboard.details.title')}</label>
        <input id="hunt-title" className="FormControl" bidi={this.title} maxlength={200} required />
      </div>,
      <div className="Form-group">
        <label for="hunt-slug">{app.translator.trans('fof-scavenger-hunt.forum.dashboard.details.slug')}</label>
        <input
          id="hunt-slug"
          className="FormControl"
          bidi={this.slug}
          maxlength={100}
          pattern="(?=[a-z0-9\-]*[a-z])[a-z0-9]+(-[a-z0-9]+)*"
          required
        />
      </div>,
      <div className="Form-group">
        <label for="hunt-description">{app.translator.trans('fof-scavenger-hunt.forum.dashboard.details.description')}</label>
        <textarea id="hunt-description" className="FormControl" bidi={this.description} rows={4} maxlength={5000} />
      </div>,
      <div className="Form-group HuntForm-image">
        <label id="hunt-image">{app.translator.trans('fof-scavenger-hunt.forum.dashboard.details.image')}</label>
        <ImageField stream={this.image} url={this.attrs.hunt.imageUrl()} labelledby="hunt-image" template={this.attrs.hunt.template()} />
      </div>,
      <div className="Form-group HuntForm-completedImage">
        <label id="hunt-completed-image">{app.translator.trans('fof-scavenger-hunt.forum.dashboard.details.completed_image')}</label>
        <ImageField
          stream={this.completedImage}
          url={this.attrs.hunt.completedImageUrl()}
          labelledby="hunt-completed-image"
          template={this.attrs.hunt.template()}
        />
        <div className="helpText">{app.translator.trans('fof-scavenger-hunt.forum.dashboard.details.completed_image_help')}</div>
      </div>,
    ];
  }

  protected scheduleFields(): Mithril.Children {
    return [
      <div className="Form-group">
        <Switch state={this.isEnabled()} onchange={this.isEnabled}>
          {app.translator.trans('fof-scavenger-hunt.forum.dashboard.schedule.enabled')}
        </Switch>
      </div>,
      <div className="Form-group">
        <label for="hunt-starts">{app.translator.trans('fof-scavenger-hunt.forum.dashboard.schedule.starts_at')}</label>
        <input id="hunt-starts" type="datetime-local" className="FormControl" bidi={this.startsAt} />
        <div className="helpText">{app.translator.trans('fof-scavenger-hunt.forum.dashboard.schedule.starts_at_help')}</div>
      </div>,
      <div className="Form-group">
        <label for="hunt-ends">{app.translator.trans('fof-scavenger-hunt.forum.dashboard.schedule.ends_at')}</label>
        <input id="hunt-ends" type="datetime-local" className="FormControl" bidi={this.endsAt} />
        <div className="helpText">{app.translator.trans('fof-scavenger-hunt.forum.dashboard.schedule.ends_at_help')}</div>
      </div>,
      <div className="Form-group">
        <Switch state={this.guestsCanSee()} onchange={this.guestsCanSee}>
          {app.translator.trans('fof-scavenger-hunt.forum.dashboard.schedule.guests_can_see')}
        </Switch>
        <div className="helpText">{app.translator.trans('fof-scavenger-hunt.forum.dashboard.schedule.guests_can_see_help')}</div>
      </div>,
    ];
  }

  protected save(e: SubmitEvent): void {
    e.preventDefault();

    const attributes =
      this.attrs.section === 'details'
        ? {
            title: this.title(),
            slug: this.slug(),
            description: this.description() || null,
            image: this.image(),
            completedImage: this.completedImage(),
          }
        : {
            isEnabled: this.isEnabled(),
            guestsCanSee: this.guestsCanSee(),
            startsAt: fromLocalInput(this.startsAt()),
            endsAt: fromLocalInput(this.endsAt()),
          };

    this.saving = true;

    this.attrs.hunt
      .save(attributes)
      .then(() => toast(app.translator.trans('fof-scavenger-hunt.forum.dashboard.saved')))
      .catch(() => {})
      .finally(() => {
        this.saving = false;
        m.redraw();
      });
  }
}
