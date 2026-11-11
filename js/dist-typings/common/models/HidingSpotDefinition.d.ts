import Model from 'flarum/common/Model';
export default class HidingSpotDefinition extends Model {
    label(): string;
    routes(): string[] | null;
    contextType(): string | null;
    allowsRoute(route: string): boolean;
}
