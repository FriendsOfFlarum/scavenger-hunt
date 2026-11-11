import app from 'flarum/common/app';

/**
 * Opens the Artwork guide on top of whatever modal is open (the image picker, usually), so closing it goes back there.
 */
export default function showArtworkGuide(): void {
  app.modal.show(() => import('../components/ArtworkGuideModal'), {}, true);
}
