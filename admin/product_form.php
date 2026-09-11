<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$categories = ['Furniture', 'Lighting', 'Textiles', 'Kitchen'];
$errors = [];
$product = ['id' => null, 'name' => '', 'category' => '', 'price' => '', 'description' => '', 'image_url' => '', 'stock' => '0'];
$is_edit = false;

// ---- Load existing product if editing ----
$edit_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($edit_id) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$edit_id]);
    $found = $stmt->fetch();
    if ($found) {
        $product = $found;
        $is_edit = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors['form'] = 'Your session expired. Please try again.';
    } else {
        $posted_id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
        $is_edit = (bool)$posted_id;

        $product['id']          = $posted_id ?: null;
        $product['name']        = clean($_POST['name'] ?? '');
        $product['category']    = clean($_POST['category'] ?? '');
        $product['price']       = clean($_POST['price'] ?? '');
        $product['description'] = clean($_POST['description'] ?? '');
        $product['image_url']   = clean($_POST['image_url'] ?? '');
        $product['stock']       = clean($_POST['stock'] ?? '');

        if ($product['name'] === '' || mb_strlen($product['name']) > 150) {
            $errors['name'] = 'Please enter a product name (max 150 characters).';
        }
        if (!in_array($product['category'], $categories, true)) {
            $errors['category'] = 'Please choose a valid category.';
        }
        $price_value = filter_var($product['price'], FILTER_VALIDATE_FLOAT);
        if ($price_value === false || $price_value <= 0) {
            $errors['price'] = 'Please enter a valid price greater than 0.';
        }
        if ($product['description'] === '') {
            $errors['description'] = 'Please enter a short description.';
        }
        if ($product['image_url'] === '' || !filter_var($product['image_url'], FILTER_VALIDATE_URL)) {
            $errors['image_url'] = 'Please enter a valid image URL.';
        }
        $stock_value = filter_var($product['stock'], FILTER_VALIDATE_INT);
        if ($stock_value === false || $stock_value < 0) {
            $errors['stock'] = 'Please enter a stock quantity of 0 or more.';
        }

        if (empty($errors)) {
            if ($is_edit) {
                $stmt = $pdo->prepare(
                    'UPDATE products SET name = ?, category = ?, price = ?, description = ?, image_url = ?, stock = ? WHERE id = ?'
                );
                $stmt->execute([
                    $product['name'], $product['category'], $price_value,
                    $product['description'], $product['image_url'], $stock_value, $product['id'],
                ]);
                flash_set('success', 'Product updated.');
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO products (name, category, price, description, image_url, stock) VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $product['name'], $product['category'], $price_value,
                    $product['description'], $product['image_url'], $stock_value,
                ]);
                flash_set('success', 'Product added.');
            }
            redirect('products.php');
        }
    }
}

$page_title = ($is_edit ? 'Edit Product' : 'Add Product') . ' | Nordic Nest Admin';
$active     = 'admin';
$base       = '../';
require __DIR__ . '/../includes/header.php';
?>

<section class="page-header">
  <div class="container">
    <p class="breadcrumb"><a href="dashboard.php">Admin</a> / <a href="products.php">Products</a> / <?= $is_edit ? 'Edit' : 'Add' ?></p>
    <h1><?= $is_edit ? 'Edit product' : 'Add a new product' ?></h1>
  </div>
</section>

<section class="container section narrow-form">
  <?php if (!empty($errors['form'])): ?>
    <div class="alert alert-error" role="alert"><?= h($errors['form']) ?></div>
  <?php endif; ?>

  <form class="contact-form" method="post" action="product_form.php" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= h((string)$product['id']) ?>">

    <div class="form-row <?= isset($errors['name']) ? 'has-error' : '' ?>">
      <label for="name">Product name <span class="required">*</span></label>
      <input type="text" id="name" name="name" value="<?= h($product['name']) ?>" required maxlength="150">
      <?php if (isset($errors['name'])): ?><p class="field-error"><?= h($errors['name']) ?></p><?php endif; ?>
    </div>

    <div class="form-row <?= isset($errors['category']) ? 'has-error' : '' ?>">
      <label for="category">Category <span class="required">*</span></label>
      <select id="category" name="category" required>
        <option value="">Please select...</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= h($c) ?>" <?= $product['category'] === $c ? 'selected' : '' ?>><?= h($c) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($errors['category'])): ?><p class="field-error"><?= h($errors['category']) ?></p><?php endif; ?>
    </div>

    <div class="form-row <?= isset($errors['price']) ? 'has-error' : '' ?>">
      <label for="price">Price (AUD) <span class="required">*</span></label>
      <input type="number" id="price" name="price" value="<?= h((string)$product['price']) ?>" required min="0.01" step="0.01">
      <?php if (isset($errors['price'])): ?><p class="field-error"><?= h($errors['price']) ?></p><?php endif; ?>
    </div>

    <div class="form-row <?= isset($errors['description']) ? 'has-error' : '' ?>">
      <label for="description">Description <span class="required">*</span></label>
      <textarea id="description" name="description" required maxlength="500"><?= h($product['description']) ?></textarea>
      <?php if (isset($errors['description'])): ?><p class="field-error"><?= h($errors['description']) ?></p><?php endif; ?>
    </div>

    <div class="form-row <?= isset($errors['image_url']) ? 'has-error' : '' ?>">
      <label for="image_url">Image URL <span class="required">*</span></label>
      <input type="url" id="image_url" name="image_url" value="<?= h($product['image_url']) ?>" required placeholder="https://...">
      <p class="field-hint">ENHANCEMENT: Enter a URL to an image. You can upload images to free services like:</p>
      <ul style="font-size: 12px; margin: 8px 0; padding-left: 20px;">
        <li><a href="https://imgur.com" target="_blank">Imgur.com</a> - Free image hosting</li>
        <li><a href="https://picsum.photos" target="_blank">Picsum.photos</a> - Free placeholder images (e.g., https://picsum.photos/seed/sofa/600/450)</li>
        <li><a href="https://cloudinary.com" target="_blank">Cloudinary</a> - Free media hosting</li>
      </ul>
      <?php if (isset($errors['image_url'])): ?><p class="field-error"><?= h($errors['image_url']) ?></p><?php endif; ?>
    </div>

    <div class="form-row <?= isset($errors['stock']) ? 'has-error' : '' ?>">
      <label for="stock">Stock on hand <span class="required">*</span></label>
      <input type="number" id="stock" name="stock" value="<?= h((string)$product['stock']) ?>" required min="0" step="1">
      <p class="field-hint">Set to 0 to mark this product as out of stock — it stays visible in the shop but customers can't add it to their cart.</p>
      <?php if (isset($errors['stock'])): ?><p class="field-error"><?= h($errors['stock']) ?></p><?php endif; ?>
    </div>

    <button type="submit" class="btn btn-primary"><?= $is_edit ? 'Save changes' : 'Add product' ?></button>
    <a class="btn btn-outline" href="products.php">Cancel</a>
  </form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
