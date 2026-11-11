import app from 'flarum/common/app';
import Modal from 'flarum/common/components/Modal';
import type Mithril from 'mithril';

export interface ExampleLayer {
  key: string;
  file: string;
  x: number;
  y: number;
  width: number;
  z: number;
}

/** Three Parts of the winter Template, as its template.json places them (a test keeps the two in step). */
export const EXAMPLE_PARTS: ExampleLayer[] = [
  { key: 'body', file: 'body.svg', x: 28.75, y: 53.75, width: 42.5, z: 2 },
  { key: 'head', file: 'head.svg', x: 35.5, y: 28, width: 29, z: 3 },
  { key: 'hat', file: 'hat.svg', x: 35, y: 12.5, width: 30, z: 6 },
];

const SECTIONS = ['collectable', 'parts', 'completed', 'files'] as const;

function templateUrl(file: string): string {
  return `${app.forum.attribute('assetsBaseUrl')}/extensions/fof-scavenger-hunt/templates/winter/${file}`;
}

/**
 * What artwork a Hunt needs, shown with the winter Template taken apart.
 */
export default class ArtworkGuideModal extends Modal {
  className() {
    return 'ArtworkGuideModal Modal--large';
  }

  title() {
    return app.translator.trans('fof-scavenger-hunt.lib.artwork.guide_title');
  }

  content() {
    const t = (key: string) => app.translator.trans(`fof-scavenger-hunt.lib.artwork.${key}`);

    return (
      <div className="Modal-body ArtworkGuide">
        <p>{t('intro')}</p>

        {this.example()}

        {SECTIONS.map((section) => (
          <section className={`ArtworkGuide-section ArtworkGuide-section--${section}`}>
            <h4>{t(`${section}_heading`)}</h4>
            <p>{t(`${section}_body`)}</p>
          </section>
        ))}

        <section className="ArtworkGuide-section ArtworkGuide-section--tips">
          <h4>{t('tips_heading')}</h4>
          <ul>
            <li>{t('tip_template')}</li>
            <li>{t('tip_scale')}</li>
          </ul>
        </section>
      </div>
    );
  }

  protected example(): Mithril.Children {
    const partName = (key: string) => app.translator.trans(`fof-scavenger-hunt.lib.templates.winter.parts.${key}`);

    return (
      <figure className="ArtworkGuide-example">
        <div className="ArtworkGuide-steps">
          <div className="ArtworkGuide-step">
            <span className="ArtworkGuide-tile">
              <img src={templateUrl('base.svg')} alt="" />
            </span>
            <span className="ArtworkGuide-label">{app.translator.trans('fof-scavenger-hunt.lib.artwork.example_picture')}</span>
          </div>

          <span className="ArtworkGuide-operator" aria-hidden="true">
            +
          </span>

          {EXAMPLE_PARTS.map((part) => (
            <div className="ArtworkGuide-step">
              <span className="ArtworkGuide-tile ArtworkGuide-tile--transparent">
                <img src={templateUrl(part.file)} alt="" />
              </span>
              <span className="ArtworkGuide-label">{partName(part.key)}</span>
            </div>
          ))}

          <span className="ArtworkGuide-operator" aria-hidden="true">
            =
          </span>

          <div className="ArtworkGuide-step">
            <span className="ArtworkGuide-tile ArtworkGuide-canvas">
              <img src={templateUrl('base.svg')} alt="" />
              {EXAMPLE_PARTS.map((part) => (
                <img
                  className="ArtworkGuide-layer"
                  src={templateUrl(part.file)}
                  alt=""
                  style={{ left: `${part.x}%`, top: `${part.y}%`, width: `${part.width}%`, zIndex: String(part.z) }}
                />
              ))}
            </span>
            <span className="ArtworkGuide-label">{app.translator.trans('fof-scavenger-hunt.lib.artwork.example_finished')}</span>
          </div>
        </div>
        <figcaption className="helpText">{app.translator.trans('fof-scavenger-hunt.lib.artwork.example_caption')}</figcaption>
      </figure>
    );
  }
}
