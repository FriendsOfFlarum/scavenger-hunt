import app from 'flarum/forum/app';
import type Mithril from 'mithril';

/**
 * Core alerts stay until dismissed; confirmations shouldn't, so these clear themselves.
 */
export default function toast(message: Mithril.Children, ms = 4000): void {
  const id = app.alerts.show({ type: 'success' }, message);

  setTimeout(() => app.alerts.dismiss(id), ms);
}
