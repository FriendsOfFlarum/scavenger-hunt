import Component, { type ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
import type Hunt from '../../common/models/Hunt';
import type Part from '../../common/models/Part';

export interface CollectableCanvasAttrs extends ComponentAttrs {
  hunt: Hunt;
  parts: Part[];
  /** Draw every Part as found, e.g. for a Trophy. */
  complete?: boolean;
}

/**
 * The Collectable on its canvas: found Parts on their layers, silhouettes for the rest.
 */
export default class CollectableCanvas extends Component<CollectableCanvasAttrs> {
  view() {
    const { hunt, parts } = this.attrs;

    return (
      <div className="CollectionView-canvas">
        {hunt.imageUrl() && <img className="CollectionView-base" src={hunt.imageUrl()!} alt="" decoding="async" />}
        {parts.filter((part) => part.layer()).map((part) => this.layer(part))}
      </div>
    );
  }

  protected layer(part: Part): Mithril.Children {
    const layer = part.layer()!;
    const imageUrl = part.imageUrl();
    const position = { left: `${layer.x}%`, top: `${layer.y}%`, width: `${layer.width}%`, zIndex: String(layer.z) };

    if (!imageUrl) {
      return null;
    }

    if (this.attrs.complete || part.isFound()) {
      return <img className="CollectionView-layer" src={imageUrl} alt={part.name()} style={position} decoding="async" />;
    }

    // The hidden image sizes the box; the mask paints its shape in the theme's silhouette colour.
    return (
      <span
        className="CollectionView-layer CollectionView-layer--silhouette"
        aria-hidden="true"
        style={{ ...position, maskImage: `url("${imageUrl}")`, WebkitMaskImage: `url("${imageUrl}")` }}
      >
        <img src={imageUrl} alt="" decoding="async" />
      </span>
    );
  }
}
