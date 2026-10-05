import app from 'flarum/forum/app';

/**
 * The public page of a Hunt, or null where the route isn't registered (e.g. in isolated tests).
 */
export default function huntUrl(slug: string): string | null {
  return app.routes['fof-scavenger-hunt.hunt'] ? app.route('fof-scavenger-hunt.hunt', { slug }) : null;
}
