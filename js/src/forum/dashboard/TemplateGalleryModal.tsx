import app from 'flarum/forum/app';
import FormModal from 'flarum/common/components/FormModal';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import classList from 'flarum/common/utils/classList';
import Stream from 'flarum/common/utils/Stream';
import type Mithril from 'mithril';
import type Hunt from '../../common/models/Hunt';
import type HuntTemplate from '../../common/models/HuntTemplate';

/**
 * Starts a new Hunt, from a Template or blank.
 */
export default class TemplateGalleryModal extends FormModal {
  protected templates: HuntTemplate[] | null = null;
  protected failed = false;
  protected chosen: HuntTemplate | null = null;
  protected huntTitle!: Stream<string>;

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);

    this.huntTitle = Stream('');
    this.load();
  }

  protected load(): void {
    this.failed = false;

    app.store
      .find<HuntTemplate[]>('scavenger-hunt-templates')
      .then((templates) => {
        this.templates = templates;
      })
      .catch(() => {
        this.failed = true;
      })
      .finally(() => m.redraw());
  }

  className() {
    return 'TemplateGalleryModal Modal--large';
  }

  title() {
    return app.translator.trans('fof-scavenger-hunt.forum.dashboard.gallery.title');
  }

  content() {
    return (
      <div className="Modal-body">
        {this.templates ? this.gallery() : this.failed ? this.loadError() : <LoadingIndicator />}

        <div className="Form-group">
          <label for="scavenger-new-hunt-title">{app.translator.trans('fof-scavenger-hunt.forum.dashboard.gallery.hunt_title')}</label>
          <input id="scavenger-new-hunt-title" className="FormControl" bidi={this.huntTitle} required />
        </div>

        <div className="Form-group">
          <Button type="submit" className="Button Button--primary" loading={this.loading} disabled={!this.huntTitle().trim()}>
            {app.translator.trans('fof-scavenger-hunt.forum.dashboard.gallery.create')}
          </Button>
        </div>
      </div>
    );
  }

  protected loadError(): Mithril.Children {
    return (
      <div className="TemplateGallery-error">
        <p>{app.translator.trans('fof-scavenger-hunt.forum.dashboard.gallery.load_failed')}</p>
        <Button className="Button TemplateGallery-retry" icon="fas fa-redo" onclick={() => this.load()}>
          {app.translator.trans('fof-scavenger-hunt.forum.dashboard.gallery.retry')}
        </Button>
      </div>
    );
  }

  protected gallery(): Mithril.Children {
    const choose = (template: HuntTemplate | null) => {
      this.chosen = template;

      if (template && !this.huntTitle()) {
        this.huntTitle(template.title());
      }
    };

    return (
      <div
        className="TemplateGallery"
        role="radiogroup"
        aria-label={app.translator.trans('fof-scavenger-hunt.forum.dashboard.gallery.title', {}, true)}
      >
        <button
          type="button"
          role="radio"
          aria-checked={this.chosen === null ? 'true' : 'false'}
          className={classList('TemplateGallery-item', { 'TemplateGallery-item--chosen': this.chosen === null })}
          onclick={() => choose(null)}
        >
          <span className="TemplateGallery-preview TemplateGallery-preview--blank" />
          <span className="TemplateGallery-name">{app.translator.trans('fof-scavenger-hunt.forum.dashboard.gallery.blank')}</span>
        </button>

        {this.templates!.map((template) => (
          <button
            type="button"
            role="radio"
            aria-checked={this.chosen === template ? 'true' : 'false'}
            className={classList('TemplateGallery-item', { 'TemplateGallery-item--chosen': this.chosen === template })}
            onclick={() => choose(template)}
          >
            <span className="TemplateGallery-preview">
              {template.imageUrl() && <img src={template.imageUrl()!} alt="" loading="lazy" />}
              {template
                .parts()
                .map((part) =>
                  part.imageUrl ? (
                    <img
                      className="TemplateGallery-layer"
                      src={part.imageUrl}
                      alt=""
                      loading="lazy"
                      style={{ left: `${part.layer.x}%`, top: `${part.layer.y}%`, width: `${part.layer.width}%`, zIndex: String(part.layer.z) }}
                    />
                  ) : null
                )}
            </span>
            <span className="TemplateGallery-name">{template.title()}</span>
            <span className="TemplateGallery-occasion">{template.occasion()}</span>
          </button>
        ))}
      </div>
    );
  }

  onsubmit(e: SubmitEvent) {
    e.preventDefault();

    this.loading = true;

    const attributes: Record<string, unknown> = { title: this.huntTitle().trim() };

    if (this.chosen) {
      attributes.template = this.chosen.id();
    }

    app.store
      .createRecord<Hunt>('scavenger-hunts')
      .save(attributes, { errorHandler: this.onerror.bind(this) })
      .then((hunt) => {
        this.hide();
        m.route.set(app.route('fof-scavenger-hunt.dashboard.hunt', { id: hunt.id(), tab: 'parts' }));
      })
      .catch(() => {
        this.loading = false;
        m.redraw();
      });
  }
}
