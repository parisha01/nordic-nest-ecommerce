<?php
require_once __DIR__ . '/includes/functions.php';

$page_title       = 'About Us | Nordic Nest';
$meta_description = "Learn about Nordic Nest's story, values and the makers behind our Scandinavian-inspired home goods.";
$active           = 'about';
$base             = '';
require __DIR__ . '/includes/header.php';
?>

<section class="page-header">
  <div class="container">
    <p class="breadcrumb"><a href="index.php">Home</a> / About</p>
    <h1>Our story</h1>
    <p class="page-subheading">Slow-made goods for a calmer everyday, since 2021.</p>
  </div>
</section>

<section class="split band">
  <div class="container" style="display:grid; grid-template-columns:1fr 1fr; gap:3rem; align-items:center;">
    <div>
      <p class="eyebrow">How we started</p>
      <h2>Founded on a small kitchen table</h2>
      <p>Nordic Nest began in 2021 when our founder, Elin Sørensen, started importing pieces from a handful of Danish and Swedish workshops she'd grown up visiting with her grandmother. What started as gifts for friends became a small online shop — and then a home for makers who share our belief that everyday objects deserve real care.</p>
      <p>Today we work with 18 independent studios across Scandinavia and Australia, each chosen for craft, not scale.</p>
    </div>
    <div>
      <img src="https://picsum.photos/seed/nordic-founder/620/460" alt="Founder Elin Sørensen arranging ceramics on a wooden shelf in the studio" loading="lazy">
    </div>
  </div>
</section>

<section class="split reverse">
  <div class="container" style="display:grid; grid-template-columns:1fr 1fr; gap:3rem; align-items:center;">
    <div class="split-media">
      <img src="https://picsum.photos/seed/nordic-material/620/460" alt="Raw materials including oak offcuts, undyed wool and clay on a workbench" loading="lazy">
    </div>
    <div>
      <p class="eyebrow">What guides us</p>
      <h2>Materials first, trends never</h2>
      <p>We choose oak over veneer, wool over synthetic blends, and stoneware over mass-cast ceramic — even when it costs more or takes longer to restock. It's a slower way to run a shop, but it's the only way these pieces last decades instead of seasons.</p>
    </div>
  </div>
</section>

<section class="band-dark">
  <div class="container">
    <p class="eyebrow center gold">Our values</p>
    <h2 class="center white">Three things we don't compromise on</h2>
    <div class="value-grid">
      <div class="value-item">
        <h3>Sustainable sourcing</h3>
        <p>FSC-certified timber, undyed natural fibres, and low-fire glazes wherever possible.</p>
      </div>
      <div class="value-item">
        <h3>Fair partnerships</h3>
        <p>Every studio we work with sets its own prices — we don't squeeze makers for margin.</p>
      </div>
      <div class="value-item">
        <h3>Built to last</h3>
        <p>We test every product for everyday durability before it's allowed on the shelf.</p>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
