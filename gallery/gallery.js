(() => {
  const photos = [...document.querySelectorAll('.photo')];
  const viewer = document.querySelector('.lightbox');
  if (!viewer || typeof viewer.showModal !== 'function') return;
  const image = viewer.querySelector('.viewer-image');
  const caption = viewer.querySelector('figcaption');
  const status = viewer.querySelector('.viewer-status');
  let current = 0;
  let opener;

  function showPhoto(index) {
    current = (index + photos.length) % photos.length;
    const photo = photos[current];
    status.textContent = `Loading · ${current + 1} / ${photos.length}`;
    image.alt = photo.querySelector('img').alt;
    caption.textContent = photo.dataset.caption;
    image.src = photo.href;
  }

  image.addEventListener('load', () => {
    status.textContent = `${current + 1} / ${photos.length}`;
  });
  image.addEventListener('error', () => {
    status.textContent = 'This photograph could not be loaded. Please try again.';
  });
  photos.forEach((photo, index) => {
    photo.addEventListener('click', event => {
      if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
      event.preventDefault();
      opener = photo;
      showPhoto(index);
      viewer.showModal();
      document.body.classList.add('viewer-open');
    });
  });
  viewer.querySelector('.viewer-close').addEventListener('click', () => viewer.close());
  viewer.addEventListener('click', event => {
    if (event.target === viewer) viewer.close();
  });
  viewer.addEventListener('close', () => {
    document.body.classList.remove('viewer-open');
    image.removeAttribute('src');
    opener?.focus({preventScroll: true});
  });
  viewer.querySelector('.viewer-previous').addEventListener('click', () => showPhoto(current - 1));
  viewer.querySelector('.viewer-next').addEventListener('click', () => showPhoto(current + 1));
  viewer.querySelectorAll('.viewer-previous, .viewer-next').forEach(button => {
    button.hidden = photos.length < 2;
  });
  viewer.addEventListener('keydown', event => {
    if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
      event.preventDefault();
      showPhoto(current + (event.key === 'ArrowLeft' ? -1 : 1));
    }
  });
})();
