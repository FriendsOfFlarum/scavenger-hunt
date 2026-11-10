import app from 'flarum/forum/app';
import Component, { type ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Icon from 'flarum/common/components/Icon';
import type Stream from 'flarum/common/utils/Stream';

export interface IImageFieldAttrs extends ComponentAttrs {
  /** The image reference the parent form saves. */
  stream: Stream<string | null>;
  /** The URL of the saved reference. */
  url: string | null | undefined;
  /** Space-separated ids, as for aria-labelledby. */
  labelledby?: string;
}

export default class ImageField extends Component<IImageFieldAttrs> {
  protected picked: { reference: string | null; url: string | null } | null = null;

  view() {
    const reference = this.attrs.stream();
    const url = reference === null ? null : this.picked && this.picked.reference === reference ? this.picked.url : this.attrs.url;

    return (
      <div className="ImageField" role="group" aria-labelledby={this.attrs.labelledby}>
        <div className="ImageField-preview">{url ? <img src={url} alt="" /> : <Icon name="far fa-image" />}</div>
        <div className="ImageField-actions">
          <Button className="Button ImageField-choose" icon="fas fa-images" onclick={() => this.choose()}>
            {app.translator.trans('fof-scavenger-hunt.forum.dashboard.images.choose')}
          </Button>
          {reference !== null && (
            <Button className="Button Button--link ImageField-remove" icon="fas fa-times" onclick={() => this.select(null, null)}>
              {app.translator.trans('fof-scavenger-hunt.forum.dashboard.images.remove')}
            </Button>
          )}
        </div>
      </div>
    );
  }

  protected choose(): void {
    app.modal.show(() => import('./ImagePickerModal'), {
      selected: this.attrs.stream(),
      onselect: (reference: string | null, url: string | null) => this.select(reference, url),
    });
  }

  protected select(reference: string | null, url: string | null): void {
    this.picked = { reference, url };
    this.attrs.stream(reference);
  }
}
