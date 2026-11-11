import Component, { type ComponentAttrs } from 'flarum/common/Component';
import type Completion from '../../common/models/Completion';
export interface FirstFinishersAttrs extends ComponentAttrs {
    completions: Completion[];
}
export default class FirstFinishers extends Component<FirstFinishersAttrs> {
    view(): JSX.Element;
}
