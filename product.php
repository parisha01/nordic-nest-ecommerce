<?php
require_once __DIR__ . '/includes/functions.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(404);
    $page_title = 'Product Not Found | Nordic Nest';
    $meta_description = 'The product you were looking for could not be found.';
    $active = 'shop';
    $base = '';
    require __DIR__ . '/includes/header.php';
    echo '<section class="container section"><h1>Product not found</h1><p><a href="shop.php">Back to the shop</a></p></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$stmt = $pdo->prepare('SELECT id, name, category, price, description, image_url, stock FROM products WHERE id = ?');
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    $page_title = 'Product Not Found | Nordic Nest';
    $meta_description = 'The product you were looking for could not be found.';
    $active = 'shop';
    $base = '';
    require __DIR__ . '/includes/header.php';
    echo '<section class="container section"><h1>Product not found</h1><p><a href="shop.php">Back to the shop</a></p></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

// Approved reviews for this specific product.
$stmt = $pdo->prepare(
    "SELECT t.rating, t.comment, t.created_at, t.is_verified_purchase, u.full_name
     FROM testimonials t JOIN users u ON u.id = t.user_id
     WHERE t.product_id = ? AND t.status = 'approved'
     ORDER BY t.created_at DESC"
);
$stmt->execute([$id]);
$reviews = $stmt->fetchAll();

$avg_rating = null;
if (!empty($reviews)) {
    $avg_rating = array_sum(array_column($reviews, 'rating')) / count($reviews);
}

$page_title       = $product['name'] . ' | Nordic Nest';
$meta_description = mb_substr($product['description'], 0, 155);
$active           = 'shop';
$base             = '';
require __DIR__ . '/includes/header.php';
?>

<!-- SEO: structured data so search engines can show price/rating rich results -->
<script type="application/ld+json">
<?= json_encode([
    '@context'    => 'https://schema.org',
    '@type'       => 'Product',
    'name'        => $product['name'],
    'description' => $product['description'],
    'image'       => $product['image_url'],
    'category'    => $product['category'],
    'offers'      => [
        '@type'         => 'Offer',
        'priceCurrency' => 'AUD',
        'price'         => number_format((float)$product['price'], 2, '.', ''),
        'availability'  => (int)$product['stock'] > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
    ],
    'aggregateRating' => $avg_rating ? [
        '@type'       => 'AggregateRating',
        'ratingValue' => round($avg_rating, 1),
        'reviewCount' => count($reviews),
    ] : null,
], JSON_UNESCAPED_SLASHES) ?>
</script>

<section class="page-header">
  <div class="container">
    <p class="breadcrumb"><a href="index.php">Home</a> / <a href="shop.php">Shop</a> / <?= h($product['name']) ?></p>
  </div>
</section>

<section class="split">
  <div class="product-media">
    <img src="<?= h($product['image_url']) ?>" alt="<?= h($product['name']) ?>" loading="lazy">
  </div>
  <div>
    <span class="card-tag"><?= h($product['category']) ?></span>
    <h1><?= h($product['name']) ?></h1>
    <?php if ($avg_rating): ?>
      <p aria-label="Average rating <?= round($avg_rating, 1) ?> out of 5">
        <?= str_repeat('★', (int)round($avg_rating)) . str_repeat('☆', 5 - (int)round($avg_rating)) ?>
        <span class="muted">(<?= count($reviews) ?> review<?= count($reviews) === 1 ? '' : 's' ?>)</span>
      </p>
    <?php endif; ?>
    <p class="card-price large"><?= '$' . number_format((float)$product['price'], 2) ?></p>
    <?php $stock = (int)$product['stock']; ?>
    <?php if ($stock === 0): ?>
      <p class="stock-note out">Out of stock — check back soon, or ask us about a restock date.</p>
    <?php elseif ($stock <= 5): ?>
      <p class="stock-note low">Only <?= $stock ?> left in stock.</p>
    <?php endif; ?>
    <p><?= h($product['description']) ?></p>

    <?php if ($stock > 0): ?>
      <form method="post" action="cart.php" class="add-to-cart-form add-to-cart-form-detail">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
        <label for="qty">Quantity</label>
        <input type="number" id="qty" name="qty" value="1" min="1" max="<?= min(CART_MAX_QTY_PER_ITEM, $stock) ?>">
        <button type="submit" class="btn btn-primary">Add to cart</button>
      </form>
    <?php else: ?>
      <button type="button" class="btn btn-primary" disabled>Out of stock</button>
    <?php endif; ?>

    <?php if (is_logged_in()): ?>
      <?php $is_wishlisted = in_array((int)$product['id'], wishlist_product_ids($pdo, current_user()['id']), true); ?>
      <form method="post" action="wishlist.php" class="wishlist-form">
        <?= csrf_field() ?>
        <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
        <input type="hidden" name="redirect" value="product.php?id=<?= (int)$product['id'] ?>">
        <button type="submit" class="wishlist-btn" aria-pressed="<?= $is_wishlisted ? 'true' : 'false' ?>">
          <?= $is_wishlisted ? '♥ Saved to wishlist' : '♡ Save to wishlist' ?>
        </button>
      </form>
    <?php else: ?>
      <p class="muted"><a href="login.php">Log in</a> to save this to your wishlist.</p>
    <?php endif; ?>

    <a class="btn btn-outline" href="contact.php">Ask about this piece</a>
  </div>
</section>

<section class="container section">
  <h2>Customer reviews</h2>
  <?php if (empty($reviews)): ?>
    <p>No reviews yet for this product. <?= is_logged_in() ? '<a href="testimonials.php">Be the first to leave one.</a>' : '<a href="login.php">Log in</a> to be the first to leave one.' ?></p>
  <?php else: ?>
    <div class="testimonial-grid">
      <?php foreach ($reviews as $r): ?>
        <article class="testimonial-card">
          <p aria-label="Rated <?= (int)$r['rating'] ?> out of 5">
            <?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?>
          </p>
          <p>"<?= h($r['comment']) ?>"</p>
          <cite>— <?= h($r['full_name']) ?>, <?= h(date('M Y', strtotime($r['created_at']))) ?></cite>
          <?php if ($r['is_verified_purchase']): ?>
            <p class="badge badge-approved" title="This reviewer bought this exact product through this store.">✓ Verified Purchase</p>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
