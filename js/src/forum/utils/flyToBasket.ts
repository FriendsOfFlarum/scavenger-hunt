/**
 * Sends a copy of a claimed Part flying into the header basket.
 */
export default function flyToBasket(from: Element | null): void {
  const target = document.querySelector('.HuntIndicator');

  if (!from || !target || window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('animate' in from)) {
    return;
  }

  const start = from.getBoundingClientRect();
  const end = target.getBoundingClientRect();
  const ghost = from.cloneNode(true) as HTMLElement;

  ghost.classList.add('HiddenPart--ghost');
  ghost.removeAttribute('aria-label');
  ghost.setAttribute('aria-hidden', 'true');
  Object.assign(ghost.style, {
    left: `${start.left}px`,
    top: `${start.top}px`,
    width: `${start.width}px`,
    height: `${start.height}px`,
  });
  document.body.append(ghost);

  const dx = end.left + end.width / 2 - (start.left + start.width / 2);
  const dy = end.top + end.height / 2 - (start.top + start.height / 2);

  ghost
    .animate(
      [
        { transform: 'translate(0, 0) scale(1)', opacity: 1 },
        { transform: `translate(${dx}px, ${dy}px) scale(0.35)`, opacity: 0.3 },
      ],
      { duration: 700, easing: 'cubic-bezier(0.5, -0.3, 0.7, 1)' }
    )
    .finished.finally(() => ghost.remove());
}
