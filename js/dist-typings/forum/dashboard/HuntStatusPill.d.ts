import Component, { type ComponentAttrs } from 'flarum/common/Component';
import type Hunt from '../../common/models/Hunt';
export default class HuntStatusPill extends Component<ComponentAttrs & {
    hunt: Hunt;
}> {
    view(): JSX.Element;
}
