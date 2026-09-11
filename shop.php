<?php
require_once __DIR__ . '/includes/functions.php';

$valid_categories = ['Furniture', 'Lighting', 'Textiles', 'Kitchen'];
$category = clean($_GET['category'] ?? '');
if ($category !== '' && !in_array($category, $valid_categories, true)) {
    $category = '';
}

// ENHANCEMENT: Price range filters
$min_price = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (float)$_GET['min_price'] : 0;
$max_price = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float)$_GET['max_price'] : 99999;

// ENHANCEMENT: Rating filter
$min_rating = isset($_GET['min_rating']) ? (int)$_GET['min_rating'] : 0;

// ENHANCEMENT: Search
$search = clean($_GET['search'] ?? '');

// Build query with all filters
$where_parts = ['p.price BETWEEN ? AND ?'];
$params = [$min_price, $max_price];

if ($category !== '') {
    $where_parts[] = 'p.category = ?';
    $params[] = $category;
}

if ($search !== '') {
    $where_parts[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $search_term = '%' . $search . '%';
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_parts);

// Get products with average ratings
$query = "SELECT p.id, p.name, p.category, p.price, p.description, p.image_url, p.stock,
                 COALESCE(AVG(t.rating), 0) as avg_rating,
                 COUNT(DISTINCT CASE WHEN t.status = 'approved' THEN t.id END) as review_count
          FROM products p
          LEFT JOIN testimonials t ON p.id = t.product_id
          $where_clause
          GROUP BY p.id";

if ($min_rating > 0) {
    $query .= " HAVING COALESCE(AVG(t.rating), 0) >= ?";
    $params[] = $min_rating;
}

$query .= " ORDER BY p.category, p.name";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

// NEW: so the shop can show a filled heart for products the logged-in
// member has already saved.
$wishlisted = is_logged_in() ? wishlist_product_ids($pdo, current_user()['id']) : [];

$page_title       = ($category !== '' ? $category . ' | ' : '') . 'Shop the Collection | Nordic Nest';
$meta_description = 'Browse sustainably made furniture, lighting, textiles and kitchenware from Nordic Nest — solid oak, undyed wool and hand-glazed stoneware.';
$active           = 'shop';
$base             = '';
require __DIR__ . '/includes/header.php';
?>

<section class="page-header">
  <div class="container">
    <p class="breadcrumb"><a href="index.php">Home</a> / Shop</p>
    <h1>Shop the collection</h1>
    <p class="page-subheading">Furniture, lighting, textiles and kitchenware — filter by category below.</p>
  </div>
</section>

<section class="container section">
  
  <!-- ENHANCEMENT: Search Bar -->
  <div class="search-section" style="margin-bottom: 30px;">
    <form method="GET" action="shop.php" class="search-form">
      <input type="text" name="search" placeholder="Search products..." value="<?= h($search) ?>" style="padding: 12px; border: 1px solid #ccc; border-radius: 4px; width: 100%; max-width: 400px; font-size: 16px;">
      <button type="submit" class="btn btn-primary" style="margin-left: 10px;">Search</button>
      <?php if ($search !== ''): ?>
        <a href="shop.php" class="btn btn-outline" style="margin-left: 10px;">Clear Search</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- Original category filter -->
  <div class="filter-bar" role="group" aria-label="Filter products by category">
    <a class="filter-btn <?= $category === '' ? 'is-active' : '' ?>" href="shop.php?<?php echo http_build_query(array_filter(['search' => $search, 'min_price' => $min_price > 0 ? $min_price : '', 'max_price' => $max_price < 99999 ? $max_price : '', 'min_rating' => $min_rating > 0 ? $min_rating : ''])); ?>">All</a>
    <?php foreach ($valid_categories as $cat): ?>
      <a class="filter-btn <?= $category === $cat ? 'is-active' : '' ?>" href="shop.php?category=<?= urlencode($cat) ?>&<?php echo http_build_query(array_filter(['search' => $search, 'min_price' => $min_price > 0 ? $min_price : '', 'max_price' => $max_price < 99999 ? $max_price : '', 'min_rating' => $min_rating > 0 ? $min_rating : ''])); ?>"><?= h($cat) ?></a>
    <?php endforeach; ?>
  </div>

  <!-- ENHANCEMENT: Advanced Filters (Price & Rating) -->
  <div class="advanced-filters" style="background: #f9f9f9; padding: 20px; margin: 30px 0; border-radius: 8px; display: flex; gap: 20px; flex-wrap: wrap; align-items: flex-end;">
    <form method="GET" action="shop.php" class="filters-form" style="display: flex; gap: 20px; flex-wrap: wrap; align-items: flex-end; width: 100%;">
      
      <!-- Price Range -->
      <div style="display: flex; gap: 10px; align-items: flex-end;">
        <div>
          <label for="min_price" style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px;">Min Price</label>
          <input type="number" name="min_price" id="min_price" placeholder="0" value="<?= $min_price > 0 ? $min_price : '' ?>" min="0" step="0.01" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px; width: 100px;">
        </div>
        <span style="color: #999;">–</span>
        <div>
          <label for="max_price" style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px;">Max Price</label>
          <input type="number" name="max_price" id="max_price" placeholder="9999" value="<?= $max_price < 99999 ? $max_price : '' ?>" min="0" step="0.01" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px; width: 100px;">
        </div>
      </div>

      <!-- Rating Filter -->
      <div>
        <label for="min_rating" style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px;">Min Rating</label>
        <select name="min_rating" id="min_rating" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
          <option value="0" <?= $min_rating == 0 ? 'selected' : '' ?>>Any</option>
          <option value="1" <?= $min_rating == 1 ? 'selected' : '' ?>>⭐ 1+</option>
          <option value="2" <?= $min_rating == 2 ? 'selected' : '' ?>>⭐⭐ 2+</option>
          <option value="3" <?= $min_rating == 3 ? 'selected' : '' ?>>⭐⭐⭐ 3+</option>
          <option value="4" <?= $min_rating == 4 ? 'selected' : '' ?>>⭐⭐⭐⭐ 4+</option>
          <option value="5" <?= $min_rating == 5 ? 'selected' : '' ?>>⭐⭐⭐⭐⭐ 5</option>
        </select>
      </div>

      <!-- Preserve other filters -->
      <input type="hidden" name="category" value="<?= h($category) ?>">
      <input type="hidden" name="search" value="<?= h($search) ?>">

      <button type="submit" class="btn btn-primary">Apply Filters</button>
      <a href="shop.php" class="btn btn-outline">Clear All</a>
    </form>
  </div>

  <?php if (empty($products)): ?>
    <p>No products found matching your filters. Try adjusting your search or filters.</p>
  <?php else: ?>
    <div class="shelf-row">
      <?php foreach ($products as $p): $stock = (int)$p['stock']; ?>
        <article class="card">
          <img src="<?= h($p['image_url']) ?>" alt="<?= h($p['name']) ?>" loading="lazy">
          <div class="card-body">
            <span class="card-tag"><?= h($p['category']) ?></span>
            <h3><a href="product.php?id=<?= (int)$p['id'] ?>"><?= h($p['name']) ?></a></h3>
            
            <!-- ENHANCEMENT: Display rating -->
            <?php if ($p['review_count'] > 0): ?>
              <p style="margin: 8px 0; font-size: 14px;">
                <span title="<?= round($p['avg_rating'], 1) ?> out of 5 stars">
                  <?= str_repeat('★', (int)round($p['avg_rating'])) . str_repeat('☆', 5 - (int)round($p['avg_rating'])) ?>
                </span>
                <span style="color: #999; font-size: 12px;">(<?= $p['review_count'] ?> review<?= $p['review_count'] === 1 ? '' : 's' ?>)</span>
              </p>
            <?php endif; ?>
            
            <p><?= h($p['description']) ?></p>
            <p class="card-price">$<?= number_format((float)$p['price'], 2) ?></p>
            <?php if ($stock === 0): ?>
              <p class="stock-note out">Out of stock</p>
            <?php elseif ($stock <= 5): ?>
              <p class="stock-note low">Only <?= $stock ?> left</p>
            <?php endif; ?>
            <div class="card-actions">
              <form method="post" action="cart.php" class="add-to-cart-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                <input type="hidden" name="qty" value="1">
                <button type="submit" class="btn btn-outline" <?= $stock === 0 ? 'disabled' : '' ?>><?= $stock === 0 ? 'Out of stock' : 'Add to cart' ?></button>
              </form>
              <?php if (is_logged_in()): ?>
                <form method="post" action="wishlist.php" class="wishlist-form">
                  <?= csrf_field() ?>
                  <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                  <input type="hidden" name="redirect" value="shop.php">
                  <button type="submit" class="wishlist-btn" aria-pressed="<?= in_array((int)$p['id'], $wishlisted, true) ? 'true' : 'false' ?>" aria-label="<?= in_array((int)$p['id'], $wishlisted, true) ? 'Remove from wishlist' : 'Save to wishlist' ?>">
                    <?= in_array((int)$p['id'], $wishlisted, true) ? '♥ Saved' : '♡ Save' ?>
                  </button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<section class="band-dark">
  <div class="container center">
    <p class="eyebrow">A note on this shop</p>
    <h2>This is a coursework prototype</h2>
    <p>Products, carts and orders are all stored in and served from a real MySQL database. Payments themselves are simulated — placing an order records it for the shop to follow up on, rather than charging a real card.</p>
    <a class="btn btn-primary" href="contact.php">Ask about a piece</a>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
