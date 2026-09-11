<?php
require_once __DIR__ . '/includes/functions.php';

$page_title       = 'Gallery | Nordic Nest';
$meta_description = "A look inside Nordic Nest's studio, workshops and homes styled with our collection.";
$active           = 'gallery';
$base             = '';
require __DIR__ . '/includes/header.php';

$photos = [
    ['seed' => 'gallery-1', 'alt' => 'Woodworking studio with tools and oak offcuts, lit by morning light', 'caption' => 'The workshop at first light'],
    ['seed' => 'gallery-2', 'alt' => "Potter's hands glazing a stoneware bowl", 'caption' => 'Hand-glazing the Fjord bowls'],
    ['seed' => 'gallery-3', 'alt' => 'Row of oak lounge chairs drying after an oil finish', 'caption' => 'Vester chairs, drying'],
    ['seed' => 'gallery-4', 'alt' => 'Living room styled with a wool throw, side table and lamp', 'caption' => 'Styled in Melbourne'],
    ['seed' => 'gallery-5', 'alt' => 'Skeins of undyed wool on a wooden spinning wheel', 'caption' => 'Spinning the Birk wool'],
    ['seed' => 'gallery-6', 'alt' => 'Ceramic mugs wrapped in tissue paper inside a shipping box', 'caption' => 'Packing an order'],
    ['seed' => 'gallery-7', 'alt' => 'Woven rattan pendant lights hanging in a workshop', 'caption' => 'Weaving the Solvang pendant'],
    ['seed' => 'gallery-8', 'alt' => 'Styled corner with an oak chair, linen cushion and table lamp', 'caption' => 'Studio styling, autumn shoot'],
];
?>

<section class="page-header">
  <div class="container">
    <p class="breadcrumb"><a href="index.php">Home</a> / Gallery</p>
    <h1>Inside the studio</h1>
    <p class="page-subheading">A look at how our pieces are made, and how customers style them at home. Click any photo for a closer look.</p>
  </div>
</section>

<section class="container section">
  <div class="gallery-grid">
    <?php foreach ($photos as $p): ?>
      <button class="gallery-item"
              data-full="https://picsum.photos/seed/<?= h($p['seed']) ?>/1200/800"
              data-caption="<?= h($p['caption']) ?>"
              aria-label="View larger image: <?= h($p['caption']) ?>">
        <img src="https://picsum.photos/seed/<?= h($p['seed']) ?>/500/400" alt="<?= h($p['alt']) ?>" loading="lazy">
        <span class="gallery-caption"><?= h($p['caption']) ?></span>
      </button>
    <?php endforeach; ?>
  </div>
</section>

<!-- Lightbox (hidden by default, opened via JS) -->
<div class="lightbox" id="lightbox" role="dialog" aria-modal="true" aria-label="Image preview" aria-hidden="true">
  <div class="lightbox-content">
    <button class="lightbox-close" aria-label="Close image preview">&times;</button>
    <img src="" alt="">
    <p class="lightbox-caption"></p>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
