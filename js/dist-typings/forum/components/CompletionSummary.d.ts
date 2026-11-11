import Component, { type ComponentAttrs } from 'flarum/common/Component';
import type Hunt from '../../common/models/Hunt';
export interface CompletionSummaryAttrs extends ComponentAttrs {
    hunt: Hunt;
}
/**
 * The finished Collectable, the member's finishing place and the Reward message.
 */
export default class CompletionSummary extends Component<CompletionSummaryAttrs> {
    view(): JSX.Element;
}
