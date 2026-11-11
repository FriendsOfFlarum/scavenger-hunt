import Component, { type ComponentAttrs } from 'flarum/common/Component';
import type Completion from '../../common/models/Completion';
export interface TrophyCardAttrs extends ComponentAttrs {
    completion: Completion;
}
/**
 * A Completion on a profile: the finished Collectable, when, and in what place.
 */
export default class TrophyCard extends Component<TrophyCardAttrs> {
    protected showReward: boolean;
    view(): JSX.Element | null;
}
