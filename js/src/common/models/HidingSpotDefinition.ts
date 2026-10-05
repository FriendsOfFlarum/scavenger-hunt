import Model from 'flarum/common/Model';

export default class HidingSpotDefinition extends Model {
  label() {
    return Model.attribute<string>('label').call(this);
  }

  routes() {
    return Model.attribute<string[] | null>('routes').call(this);
  }

  contextType() {
    return Model.attribute<string | null>('contextType').call(this);
  }

  allowsRoute(route: string): boolean {
    const routes = this.routes();

    return routes === null || routes.some((pattern) => pattern === route || (pattern.endsWith('*') && route.startsWith(pattern.slice(0, -1))));
  }
}
