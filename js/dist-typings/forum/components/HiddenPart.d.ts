import Component, { type ComponentAttrs } from 'flarum/common/Component';
import type Encounter from '../../common/models/Encounter';
export interface HiddenPartAttrs extends ComponentAttrs {
    encounter: Encounter;
}
export default class HiddenPart extends Component<HiddenPartAttrs> {
    protected claiming: boolean;
    view(): JSX.Element | null;
    protected collect(): Promise<void>;
}
