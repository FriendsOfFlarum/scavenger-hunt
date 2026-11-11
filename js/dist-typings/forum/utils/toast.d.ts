import type Mithril from 'mithril';
/**
 * Core alerts stay until dismissed; confirmations shouldn't, so these clear themselves.
 */
export default function toast(message: Mithril.Children, ms?: number): void;
