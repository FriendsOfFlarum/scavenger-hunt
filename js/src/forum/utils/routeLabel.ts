export interface PageRouteLike {
  name: string;
  path: string;
}

/**
 * A readable label for a forum route: FastRoute patterns like `/d/{id:\d+(?:-[^/]*)?}[/{near:[^/]*}]`
 * become `/d/:id`.
 */
export default function routeLabel(route: PageRouteLike): string {
  const path = route.path.replace(/\{(\w+)(?::[^{}]*)?\}/g, ':$1').replace(/\[[^[\]]*\]/g, '');

  return `${route.name} — ${path || '/'}`;
}
