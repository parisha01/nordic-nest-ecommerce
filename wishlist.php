<?php
/**
 * wishlist.php
 * -----------------------------------------------------------
 * Member-only "save for later" list. Unlike the cart (which
 * lives in the session so guests can use it), the wishlist is
 * tied to the user's account in the database, so it's still
 * there next time they log in from any device.
 * -----------------------------------------------------------
 */
require_once __DIR__ . '/includes/functions.php';
require_login();

$user = current_user();

// ---- Handle "toggle" (posted from shop.php / product.php / this page) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        flash_set('error', 'Your session expired. Please try again.');
    } else {
        $productId = filter_var($_POST['product_id'] ?? '', FILTER_VALIDATE_INT);
        if ($productId) {
            $stmt = $pdo->prepare('SELECT id FROM products WHERE id = ?');
            $stmt->execute([$productId]);
            if ($stmt->fetch()) {
                $nowSaved = wishlist_toggle($pdo, $user['id'], $productId);
                flash_set('success', $nowSaved ? 'Saved to your wishlist.' : 'Removed from your wishlist.');
            } else {
                flash_set('error', 'That product could not be found.');
            }
        }
    }
    // Only redirect to a same-site relative path from a small whitelist of
    // known pages — never trust a raw redirect target from POST data.
    $redirect = (string)($_POST['redirect'] ?? 'wishlist.php');
    $allowed  = ['wishlist.php', 'shop.php'];
    $isProductPage = (bool)preg_match('/^product\.php\?id=\d+$/', $redirect);
    if (!in_array($redirect, $allowed, true) && !$isProductPage) {
        $redirect = 'wishlist.php';
    }
    redirect($redirect);
}

$stmt = $pdo->prepare(
    'SELECT p.id, p.name, p.category, p.price, p.description, p.image_url, p.stock, w.created_at AS saved_at
     FROM wishlists w JOIN products p ON p.id = w.product_id
     WHERE w.user_id = ?
     ORDER BY w.created_at DESC'
);
$stmt->execute([$user['id']]);
$items = $stmt->fetchAll();

$page_title       = 'My Wishlist | Nordic Nest';
$meta_description = 'Products you\'ve saved for later at Nordic Nest.';
$active           = 'wishlist';
$base             = '';
require __DIR__ . '/includes/header.php';
?>

<section class="page-header">
  <div class="container">
    <p class="breadcrumb"><a href="index.php">Home</a> / <a href="profile.php">My account</a> / Wishlist</p>
    <h1>Your wishlist</h1>
    <p class="page-subheading">Products you've saved to come back to later.</p>
  </div>
</section>

<section class="container section">
  <?php if (empty($items)): ?>
    <p>You haven't saved anything yet. <a href="shop.php">Browse the shop</a> and tap "Save" on a product to add it here.</p>
  <?php else: ?>
    <div class="shelf-row">
      <?php foreach ($items as $p): $stock = (int)$p['stock']; ?>
        <article class="card">
          <img src="<?= h($p['image_url']) ?>" alt="<?= h($p['name']) ?>" loading="lazy">
          <div class="card-body">
            <span class="card-tag"><?= h($p['category']) ?></span>
            <h3><a href="product.php?id=<?= (int)$p['id'] ?>"><?= h($p['name']) ?></a></h3>
            <p class="card-price">$<?= number_format((float)$p['price'], 2) ?></p>
            <?php if ($stock === 0): ?>
              <p class="stock-note out">Out of stock</p>
            <?php endif; ?>
            <div class="card-actions">
              <form method="post" action="cart.php" class="add-to-cart-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                <input type="hidden" name="qty" value="1">
                <button type="submit" class="btn btn-outline" <?= $stock === 0 ? 'disabled' : '' ?>><?= $stock === 0 ? 'Out of stock' : 'Add to cart' ?></button>
              </form>
              <form method="post" action="wishlist.php" class="wishlist-form">
                <?= csrf_field() ?>
                <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                <input type="hidden" name="redirect" value="wishlist.php">
                <button type="submit" class="wishlist-btn" aria-pressed="true" aria-label="Remove from wishlist">✕ Remove</button>
              </form>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
