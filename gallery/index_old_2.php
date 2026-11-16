<?php
$photoDirectory = __DIR__ . '/photos';
$files = is_dir($photoDirectory) ? scandir($photoDirectory, SCANDIR_SORT_DESCENDING) : [];
$photos = [];
foreach ($files ?: [] as $file) {
    if (!is_file($photoDirectory . '/' . $file) || !preg_match('/\.(jpe?g|png|webp)$/i', $file)) {
        continue;
    }
    $captionPath = $photoDirectory . '/' . pathinfo($file, PATHINFO_FILENAME) . '.txt';
    $caption = is_readable($captionPath) ? trim(file_get_contents($captionPath)) : '';
    $thumbnail = 'thumbnails/' . rawurlencode($file) . '.webp';
    $photos[] = [
        'original' => 'photos/' . rawurlencode($file),
        'thumbnail' => is_file(__DIR__ . '/thumbnails/' . $file . '.webp')
            ? $thumbnail : 'photos/' . rawurlencode($file),
        'caption' => $caption,
        'label' => $caption !== '' ? $caption : pathinfo($file, PATHINFO_FILENAME),
    ];
}
function galleryEscape($text) {
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="A collection of photographs by Jonas Bojanovsky.">
  <title>Jonas Bojanovsky — Gallery</title>
  <link rel="icon" href="../favicon.ico">
  <link rel="stylesheet" href="../theme.css">
  <link rel="stylesheet" href="style.css">
  <script src="../theme.js" defer></script>
  <script src="gallery.js" defer></script>
</head>
<body>
  <button class="theme-toggle" type="button" hidden>Switch theme</button>
  <main class="container">
    <header class="gallery-header">
      <a class="home-link" href="../">← Jonas Bojanovsky</a>
      <h1>Gallery</h1>
      <p><?= count($photos) ?> photographs <span aria-hidden="true">/</span> Click to explore.</p>
    </header>
    <div class="gallery-grid">
      <?php foreach ($photos as $index => $photo): ?>
        <a class="photo" href="<?= galleryEscape($photo['original']) ?>" data-index="<?= $index ?>" data-caption="<?= galleryEscape($photo['caption']) ?>" aria-label="<?= galleryEscape('Enlarge photograph: ' . $photo['label']) ?>">
          <img src="<?= galleryEscape($photo['thumbnail']) ?>" alt="<?= galleryEscape($photo['label']) ?>" width="640" height="640" loading="<?= $index < 4 ? 'eager' : 'lazy' ?>" decoding="async">
          <?php if ($photo['caption'] !== ''): ?>
            <span class="photo-caption"><?= galleryEscape($photo['caption']) ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
    <?php if (!$photos): ?><p class="empty-state">No photographs yet. Check back soon.</p><?php endif; ?>
  </main>
  <dialog class="lightbox" aria-label="Photograph viewer" aria-describedby="viewer-caption">
    <button class="viewer-close viewer-button" type="button" aria-label="Close photograph" title="Close (Esc)" autofocus>×</button>
    <button class="viewer-previous viewer-button" type="button" aria-label="Previous photograph" title="Previous (←)">‹</button>
    <figure class="viewer-figure">
      <img class="viewer-image" alt="">
      <figcaption id="viewer-caption"></figcaption>
      <p class="viewer-status" role="status" aria-live="polite"></p>
    </figure>
    <button class="viewer-next viewer-button" type="button" aria-label="Next photograph" title="Next (→)">›</button>
  </dialog>
</body>
</html>
