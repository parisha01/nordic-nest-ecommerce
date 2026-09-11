<?php
require_once __DIR__ . '/includes/functions.php';

$valid_categories = ['Furniture', 'Lighting', 'Textiles', 'Kitchen'];

// ---- Handle "add to cart" (posted from shop.php / product.php) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    if (csrf_check()) {
        $productId = filter_var($_POST['product_id'] ?? '', FILTER_VALIDATE_INT);
        $qty       = filter_var($_POST['qty'] ?? 1, FILTER_VALIDATE_INT) ?: 1;

        if ($productId) {
            // Confirm the product actually exists — and has stock — before trusting the id.
            $stmt = $pdo->prepare('SELECT id, stock, name FROM products WHERE id = ?');
            $stmt->execute([$productId]);
            $product = $stmt->fetch();
            if (!$product) {
                flash_set('error', 'That product could not be found.');
            } elseif ((int)$product['stock'] <= 0) {
                flash_set('error', $product['name'] . ' is currently out of stock.');
            } else {
                $existingQty = cart_get()[$productId] ?? 0;
                $qty = min($qty, (int)$product['stock'] - $existingQty);
                if ($qty <= 0) {
                    flash_set('error', 'You already have all the available stock of ' . $product['name'] . ' in your cart.');
                } else {
                    cart_add($productId, $qty);
                    flash_set('success', 'Added to your cart.');
                }
            }
        }
    } else {
        flash_set('error', 'Your session expired. Please try again.');
    }
    redirect('cart.php');
}

// ---- Handle "update quantities" (from the cart table itself) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    if (csrf_check()) {
        $capped = false;
        foreach ((array)($_POST['qty'] ?? []) as $productId => $qty) {
            $productId = filter_var($productId, FILTER_VALIDATE_INT);
            $qty       = filter_var($qty, FILTER_VALIDATE_INT);
            if ($productId !== false && $qty !== false) {
                if ($qty > 0) {
                    $stmt = $pdo->prepare('SELECT stock FROM products WHERE id = ?');
                    $stmt->execute([$productId]);
                    $row = $stmt->fetch();
                    $stockAvailable = $row ? (int)$row['stock'] : 0;
                    if ($qty > $stockAvailable) {
                        $qty = $stockAvailable;
                        $capped = true;
                    }
                }
                cart_set_qty($productId, $qty);
            }
        }
        flash_set('success', $capped ? 'Cart updated — some quantities were reduced to match available stock.' : 'Cart updated.');
    } else {
        flash_set('error', 'Your session expired. Please try again.');
    }
    redirect('cart.php');
}

// ---- Handle "remove item" ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove') {
    if (csrf_check()) {
        $productId = filter_var($_POST['product_id'] ?? '', FILTER_VALIDATE_INT);
        if ($productId) {
            cart_remove($productId);
            flash_set('success', 'Item removed from your cart.');
        }
    }
    redirect('cart.php');
}

// ---- Handle "empty cart" ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear') {
    if (csrf_check()) {
        cart_clear();
        flash_set('success', 'Your cart has been emptied.');
    }
    redirect('cart.php');
}

$items = cart_items($pdo);
$total = cart_total($items);

$page_title       = 'Your Cart | Nordic Nest';
$meta_description = 'Review the items in your Nordic Nest cart before checking out.';
$active           = 'cart';
$base             = '';
require __DIR__ . '/includes/header.php';
?>

<section class="page-header">
  <div class="container">
    <p class="breadcrumb"><a href="index.php">Home</a> / Cart</p>
    <h1>Your cart</h1>
  </div>
</section>

<section class="container section">
  <?php if (empty($items)): ?>
    <p>Your cart is empty. <a href="shop.php">Continue shopping</a>.</p>
  <?php else: ?>
    <form method="post" action="cart.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update">
      <table class="data-table cart-table">
        <thead>
          <tr>
            <th scope="col">Product</th>
            <th scope="col">Price</th>
            <th scope="col">Quantity</th>
            <th scope="col">Subtotal</th>
            <th scope="col">Remove</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item): $p = $item['product']; $lineStock = (int)($p['stock'] ?? 0); ?>
            <tr>
              <td class="cart-product">
                <img src="<?= h($p['image_url']) ?>" alt="" class="thumb">
                <a href="product.php?id=<?= (int)$p['id'] ?>"><?= h($p['name']) ?></a>
                <?php if ($lineStock <= 5): ?><p class="stock-note low">Only <?= $lineStock ?> left</p><?php endif; ?>
              </td>
              <td>$<?= number_format((float)$p['price'], 2) ?></td>
              <td>
                <input type="number" name="qty[<?= (int)$p['id'] ?>]" value="<?= (int)$item['qty'] ?>"
                       min="1" max="<?= min(CART_MAX_QTY_PER_ITEM, $lineStock) ?>" class="qty-input" aria-label="Quantity for <?= h($p['name']) ?>">
              </td>
              <td>$<?= number_format($item['line_total'], 2) ?></td>
              <td>
                <button type="submit" form="remove-<?= (int)$p['id'] ?>" class="link-button danger">Remove</button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <button type="submit" class="btn btn-outline">Update cart</button>
    </form>

    <?php foreach ($items as $item): $p = $item['product']; ?>
      <form id="remove-<?= (int)$p['id'] ?>" method="post" action="cart.php" class="hidden-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="remove">
        <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
      </form>
    <?php endforeach; ?>

    <div class="cart-summary">
      <p class="cart-total">Total: <strong>$<?= number_format($total, 2) ?></strong></p>

      <?php if (is_logged_in()): ?>
        <a class="btn btn-primary" href="checkout.php">Proceed to checkout</a>
      <?php else: ?>
        <div class="alert alert-error" role="alert">
          You'll need to log in or create an account to complete your order.
        </div>
        <div class="cart-auth-actions">
          <a class="btn btn-primary" href="login.php?next=checkout">Log in</a>
          <a class="btn btn-outline" href="register.php?next=checkout">Create an account</a>
        </div>
      <?php endif; ?>

      <form method="post" action="cart.php" class="cart-clear-form" onsubmit="return confirm('Empty your whole cart?');">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="clear">
        <button type="submit" class="link-button danger">Empty cart</button>
      </form>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
