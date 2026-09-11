<?php
require_once __DIR__ . '/includes/functions.php';
require_login(); // guests can browse and build a cart, but must log in to order

$user  = current_user();
$items = cart_items($pdo);

if (empty($items)) {
    flash_set('error', 'Your cart is empty — add something before checking out.');
    redirect('cart.php');
}

$subtotal = cart_total($items);

// NEW: Coupon — applied via its own small form, stored in the session so
// it survives the page reload that happens when the address form is
// submitted (and is always re-validated server-side, never trusted).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apply_coupon') {
    if (!csrf_check()) {
        flash_set('error', 'Your session expired. Please try again.');
    } else {
        $result = coupon_validate($pdo, (string)($_POST['coupon_code'] ?? ''), $subtotal);
        if ($result['ok']) {
            $_SESSION['coupon_code'] = $result['coupon']['code'];
            flash_set('success', 'Coupon "' . $result['coupon']['code'] . '" applied.');
        } else {
            unset($_SESSION['coupon_code']);
            flash_set('error', $result['error']);
        }
    }
    redirect('checkout.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove_coupon') {
    if (csrf_check()) {
        unset($_SESSION['coupon_code']);
        flash_set('success', 'Coupon removed.');
    }
    redirect('checkout.php');
}

$appliedCoupon = null;
$discount      = 0.0;
if (!empty($_SESSION['coupon_code'])) {
    $check = coupon_validate($pdo, $_SESSION['coupon_code'], $subtotal);
    if ($check['ok']) {
        $appliedCoupon = $check['coupon'];
        $discount      = coupon_discount_amount($appliedCoupon, $subtotal);
    } else {
        unset($_SESSION['coupon_code']);
    }
}
$total = round($subtotal - $discount, 2);

$errors = [];
$old = [
    'full_name'    => $user['full_name'],
    'email'        => $user['email'],
    'phone'        => '',
    'address_line' => '',
    'suburb'       => '',
    'state'        => '',
    'postcode'     => '',
    'notes'        => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors['form'] = 'Your session expired. Please try again.';
    } else {
        foreach (array_keys($old) as $field) {
            $old[$field] = clean($_POST[$field] ?? '');
        }

        if ($old['full_name'] === '') {
            $errors['full_name'] = 'Please enter the name for this order.';
        }
        if ($old['email'] === '' || !is_valid_email($old['email'])) {
            $errors['email'] = 'Please enter a valid email address.';
        }
        if ($old['phone'] === '') {
            $errors['phone'] = 'Please enter a contact phone number.';
        }
        if ($old['address_line'] === '') {
            $errors['address_line'] = 'Please enter a street address.';
        }
        if ($old['suburb'] === '') {
            $errors['suburb'] = 'Please enter a suburb/city.';
        }
        if ($old['state'] === '') {
            $errors['state'] = 'Please enter a state/territory.';
        }
        if ($old['postcode'] === '') {
            $errors['postcode'] = 'Please enter a postcode.';
        }
        if (mb_strlen($old['notes']) > 500) {
            $errors['notes'] = 'Delivery notes are limited to 500 characters.';
        }

        // Re-check the cart server-side right before we commit — prices and
        // stock could have changed since the page was loaded.
        $items = cart_items($pdo);
        if (empty($items)) {
            $errors['form'] = 'Your cart is empty — add something before checking out.';
        }

        if (empty($errors)) {
            // Recompute everything fresh right before we commit — prices,
            // stock and the coupon's validity can all have changed since
            // the page was first loaded.
            $subtotal = cart_total($items);
            $discount = 0.0;
            $couponCodeToStore = null;
            if (!empty($_SESSION['coupon_code'])) {
                $recheck = coupon_validate($pdo, $_SESSION['coupon_code'], $subtotal);
                if ($recheck['ok']) {
                    $discount = coupon_discount_amount($recheck['coupon'], $subtotal);
                    $couponCodeToStore = $recheck['coupon']['code'];
                } else {
                    unset($_SESSION['coupon_code']);
                }
            }
            $total = round($subtotal - $discount, 2);

            try {
                $pdo->beginTransaction();

                // Lock and decrement stock one product at a time. The WHERE
                // clause makes this atomic: if two customers race for the
                // last unit, only one UPDATE affects a row — the loser's
                // rowCount() is 0 and we roll the whole order back rather
                // than oversell.
                $stockStmt = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');
                foreach ($items as $item) {
                    $p = $item['product'];
                    $stockStmt->execute([$item['qty'], (int)$p['id'], $item['qty']]);
                    if ($stockStmt->rowCount() === 0) {
                        throw new RuntimeException('OUT_OF_STOCK:' . $p['name']);
                    }
                }

                $stmt = $pdo->prepare(
                    'INSERT INTO orders (user_id, status, subtotal, coupon_code, discount, total, full_name, email, phone, address_line, suburb, state, postcode, notes)
                     VALUES (?, "pending", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $user['id'], $subtotal, $couponCodeToStore, $discount, $total,
                    $old['full_name'], $old['email'], $old['phone'],
                    $old['address_line'], $old['suburb'], $old['state'], $old['postcode'],
                    $old['notes'] !== '' ? $old['notes'] : null,
                ]);
                $orderId = (int)$pdo->lastInsertId();

                $itemStmt = $pdo->prepare(
                    'INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, line_total)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                foreach ($items as $item) {
                    $p = $item['product'];
                    $itemStmt->execute([
                        $orderId, (int)$p['id'], $p['name'], (float)$p['price'], $item['qty'], $item['line_total'],
                    ]);
                }

                if ($couponCodeToStore !== null) {
                    $pdo->prepare('UPDATE coupons SET times_used = times_used + 1 WHERE code = ?')->execute([$couponCodeToStore]);
                }

                $pdo->commit();
                cart_clear();
                unset($_SESSION['coupon_code']);

                flash_set('success', 'Thank you — your order has been placed!');
                redirect('order.php?id=' . $orderId);
            } catch (RuntimeException $e) {
                $pdo->rollBack();
                $productName = str_starts_with($e->getMessage(), 'OUT_OF_STOCK:') ? substr($e->getMessage(), 13) : 'an item in your cart';
                $errors['form'] = 'Sorry — ' . $productName . ' sold out while you were checking out. Please update your cart and try again.';
            } catch (Throwable $e) {
                $pdo->rollBack();
                error_log('Order placement failed: ' . $e->getMessage());
                $errors['form'] = 'Sorry — something went wrong placing your order. Please try again.';
            }
        }
    }
}

$page_title       = 'Checkout | Nordic Nest';
$meta_description = 'Complete your Nordic Nest order.';
$active           = 'cart';
$base             = '';
require __DIR__ . '/includes/header.php';
?>

<section class="page-header">
  <div class="container">
    <p class="breadcrumb"><a href="index.php">Home</a> / <a href="cart.php">Cart</a> / Checkout</p>
    <h1>Checkout</h1>
  </div>
</section>

<section class="container form-columns checkout-columns">
  <div>
    <h2>Delivery details</h2>
    <?php if (!empty($errors['form'])): ?>
      <div class="alert alert-error" role="alert"><?= h($errors['form']) ?></div>
    <?php endif; ?>

    <form class="contact-form" method="post" action="checkout.php" novalidate>
      <?= csrf_field() ?>

      <div class="form-row <?= isset($errors['full_name']) ? 'has-error' : '' ?>">
        <label for="full_name">Full name <span class="required">*</span></label>
        <input type="text" id="full_name" name="full_name" value="<?= h($old['full_name']) ?>" required maxlength="120">
        <?php if (isset($errors['full_name'])): ?><p class="field-error"><?= h($errors['full_name']) ?></p><?php endif; ?>
      </div>

      <div class="form-row <?= isset($errors['email']) ? 'has-error' : '' ?>">
        <label for="email">Email address <span class="required">*</span></label>
        <input type="email" id="email" name="email" value="<?= h($old['email']) ?>" required>
        <?php if (isset($errors['email'])): ?><p class="field-error"><?= h($errors['email']) ?></p><?php endif; ?>
      </div>

      <div class="form-row <?= isset($errors['phone']) ? 'has-error' : '' ?>">
        <label for="phone">Phone number <span class="required">*</span></label>
        <input type="tel" id="phone" name="phone" value="<?= h($old['phone']) ?>" required maxlength="30">
        <?php if (isset($errors['phone'])): ?><p class="field-error"><?= h($errors['phone']) ?></p><?php endif; ?>
      </div>

      <div class="form-row <?= isset($errors['address_line']) ? 'has-error' : '' ?>">
        <label for="address_line">Street address <span class="required">*</span></label>
        <input type="text" id="address_line" name="address_line" value="<?= h($old['address_line']) ?>" required maxlength="190">
        <?php if (isset($errors['address_line'])): ?><p class="field-error"><?= h($errors['address_line']) ?></p><?php endif; ?>
      </div>

      <div class="form-row <?= isset($errors['suburb']) ? 'has-error' : '' ?>">
        <label for="suburb">Suburb / City <span class="required">*</span></label>
        <input type="text" id="suburb" name="suburb" value="<?= h($old['suburb']) ?>" required maxlength="120">
        <?php if (isset($errors['suburb'])): ?><p class="field-error"><?= h($errors['suburb']) ?></p><?php endif; ?>
      </div>

      <div class="form-row <?= isset($errors['state']) ? 'has-error' : '' ?>">
        <label for="state">State / Territory <span class="required">*</span></label>
        <input type="text" id="state" name="state" value="<?= h($old['state']) ?>" required maxlength="60">
        <?php if (isset($errors['state'])): ?><p class="field-error"><?= h($errors['state']) ?></p><?php endif; ?>
      </div>

      <div class="form-row <?= isset($errors['postcode']) ? 'has-error' : '' ?>">
        <label for="postcode">Postcode <span class="required">*</span></label>
        <input type="text" id="postcode" name="postcode" value="<?= h($old['postcode']) ?>" required maxlength="15">
        <?php if (isset($errors['postcode'])): ?><p class="field-error"><?= h($errors['postcode']) ?></p><?php endif; ?>
      </div>

      <div class="form-row <?= isset($errors['notes']) ? 'has-error' : '' ?>">
        <label for="notes">Delivery notes (optional)</label>
        <textarea id="notes" name="notes" maxlength="500"><?= h($old['notes']) ?></textarea>
        <?php if (isset($errors['notes'])): ?><p class="field-error"><?= h($errors['notes']) ?></p><?php endif; ?>
      </div>

      <p class="field-hint">This is a coursework prototype — no real payment is taken. Placing the order simply records it for follow-up.</p>

      <button type="submit" class="btn btn-primary">Place order</button>
    </form>
  </div>

  <div>
    <h2>Order summary</h2>
    <table class="data-table cart-table">
      <thead>
        <tr><th scope="col">Product</th><th scope="col">Qty</th><th scope="col">Subtotal</th></tr>
      </thead>
      <tbody>
        <?php foreach ($items as $item): $p = $item['product']; ?>
          <tr>
            <td><?= h($p['name']) ?></td>
            <td><?= (int)$item['qty'] ?></td>
            <td>$<?= number_format($item['line_total'], 2) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <form method="post" action="checkout.php" class="coupon-row">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $appliedCoupon ? 'remove_coupon' : 'apply_coupon' ?>">
      <?php if ($appliedCoupon): ?>
        <p>Coupon applied: <strong><?= h($appliedCoupon['code']) ?></strong> (<?= h($appliedCoupon['description'] ?? '') ?>)</p>
        <button type="submit" class="link-button">Remove</button>
      <?php else: ?>
        <div class="form-row">
          <label for="coupon_code">Coupon code</label>
          <input type="text" id="coupon_code" name="coupon_code" placeholder="e.g. WELCOME10">
        </div>
        <button type="submit" class="btn btn-outline">Apply</button>
      <?php endif; ?>
    </form>

    <table class="data-table order-totals">
      <tbody>
        <tr><td>Subtotal</td><td>$<?= number_format($subtotal, 2) ?></td></tr>
        <?php if ($discount > 0): ?>
          <tr><td>Discount<?= $appliedCoupon ? ' (' . h($appliedCoupon['code']) . ')' : '' ?></td><td>-$<?= number_format($discount, 2) ?></td></tr>
        <?php endif; ?>
        <tr><td><strong>Total</strong></td><td><strong>$<?= number_format($total, 2) ?></strong></td></tr>
      </tbody>
    </table>
    <p><a href="cart.php">Edit cart</a></p>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
