<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

// ---- Handle delete ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_product') {
    if (csrf_check()) {
        $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
        if ($id) {
            $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
            $stmt->execute([$id]);
            flash_set('success', 'Product deleted.');
        }
    }
    redirect('products.php');
}

$products = $pdo->query('SELECT id, name, category, price, image_url, stock FROM products ORDER BY category, name')->fetchAll();
$low_stock_threshold = 5;

$page_title = 'Manage Products | Nordic Nest Admin';
$active     = 'admin';
$base       = '../';
require __DIR__ . '/../includes/header.php';
?>

<section class="page-header">
  <div class="container">
    <p class="breadcrumb"><a href="dashboard.php">Admin</a> / Products</p>
    <h1>Manage products</h1>
  </div>
</section>

<section class="container section">
  <p><a class="btn btn-primary" href="product_form.php">+ Add new product</a></p>

  <table class="data-table">
    <thead>
      <tr><th scope="col">Image</th><th scope="col">Name</th><th scope="col">Category</th><th scope="col">Price</th><th scope="col">Stock</th><th scope="col">Actions</th></tr>
    </thead>
    <tbody>
      <?php foreach ($products as $p): $stock = (int)$p['stock']; ?>
        <tr>
          <td><img src="<?= h($p['image_url']) ?>" alt="" class="thumb"></td>
          <td><?= h($p['name']) ?></td>
          <td><?= h($p['category']) ?></td>
          <td>$<?= number_format((float)$p['price'], 2) ?></td>
          <td>
            <?php if ($stock === 0): ?>
              <span class="badge badge-danger">Out of stock</span>
            <?php elseif ($stock <= $low_stock_threshold): ?>
              <span class="badge badge-warning"><?= $stock ?> left — low</span>
            <?php else: ?>
              <?= $stock ?>
            <?php endif; ?>
          </td>
          <td class="actions">
            <a href="product_form.php?id=<?= (int)$p['id'] ?>">Edit</a>
            <form method="post" action="products.php" onsubmit="return confirm('Delete this product? This cannot be undone.');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete_product">
              <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
              <button type="submit" class="link-button">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
