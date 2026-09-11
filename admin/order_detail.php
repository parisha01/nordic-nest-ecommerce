<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$valid_statuses = ['pending', 'processing', 'shipped', 'completed', 'cancelled', 'refunded'];
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    redirect('orders.php');
}

// ---- Handle status update ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    if (csrf_check()) {
        $newStatus = $_POST['status'] ?? '';
        if (in_array($newStatus, $valid_statuses, true)) {
            $stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
            $stmt->execute([$newStatus, $id]);
            flash_set('success', 'Order status updated to "' . $newStatus . '".');
        }
    } else {
        flash_set('error', 'Your session expired. Please try again.');
    }
    redirect('order_detail.php?id=' . $id);
}

$stmt = $pdo->prepare(
    'SELECT o.*, u.email AS account_email, u.full_name AS account_name
     FROM orders o JOIN users u ON u.id = o.user_id
     WHERE o.id = ?'
);
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    flash_set('error', 'That order could not be found.');
    redirect('orders.php');
}

$itemStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id');
$itemStmt->execute([$id]);
$orderItems = $itemStmt->fetchAll();

$reqStmt = $pdo->prepare('SELECT * FROM order_requests WHERE order_id = ? ORDER BY created_at DESC');
$reqStmt->execute([$id]);
$orderRequests = $reqStmt->fetchAll();

$page_title = 'Order #' . $order['id'] . ' | Nordic Nest Admin';
$active     = 'admin';
$base       = '../';
require __DIR__ . '/../includes/header.php';
?>

<section class="page-header">
  <div class="container">
    <p class="breadcrumb"><a href="dashboard.php">Admin</a> / <a href="orders.php">Orders</a> / #<?= (int)$order['id'] ?></p>
    <h1>Order #<?= (int)$order['id'] ?></h1>
    <p class="page-subheading">Placed <?= h(date('d M Y, g:ia', strtotime($order['created_at']))) ?> by <?= h($order['account_name']) ?> (<?= h($order['account_email']) ?>)</p>
  </div>
</section>

<section class="container form-columns checkout-columns">
  <div>
    <h2>Items</h2>
    <table class="data-table cart-table">
      <thead>
        <tr><th scope="col">Product</th><th scope="col">Unit price</th><th scope="col">Qty</th><th scope="col">Subtotal</th></tr>
      </thead>
      <tbody>
        <?php foreach ($orderItems as $item): ?>
          <tr>
            <td><?= h($item['product_name']) ?></td>
            <td>$<?= number_format((float)$item['unit_price'], 2) ?></td>
            <td><?= (int)$item['quantity'] ?></td>
            <td>$<?= number_format((float)$item['line_total'], 2) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p class="cart-total">Total: <strong>$<?= number_format((float)$order['total'], 2) ?></strong></p>
    <table class="data-table order-totals">
      <tbody>
        <tr><td>Subtotal</td><td>$<?= number_format((float)($order['subtotal'] ?? $order['total']), 2) ?></td></tr>
        <?php if ((float)($order['discount'] ?? 0) > 0): ?>
          <tr><td>Discount<?= !empty($order['coupon_code']) ? ' (' . h($order['coupon_code']) . ')' : '' ?></td><td>-$<?= number_format((float)$order['discount'], 2) ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div>
    <h2>Delivery details</h2>
    <p>
      <?= h($order['full_name']) ?><br>
      <?= h($order['address_line']) ?><br>
      <?= h($order['suburb']) ?>, <?= h($order['state']) ?> <?= h($order['postcode']) ?><br>
      <?= h($order['phone']) ?> · <?= h($order['email']) ?>
    </p>
    <?php if (!empty($order['notes'])): ?>
      <p><strong>Delivery notes:</strong> <?= h($order['notes']) ?></p>
    <?php endif; ?>

    <?php if (!empty($orderRequests)): ?>
      <h2>Cancellation / refund requests</h2>
      <?php foreach ($orderRequests as $r): ?>
        <p>
          <strong><?= h(ucfirst($r['request_type'])) ?></strong> —
          <span class="badge badge-<?= h($r['status']) ?>"><?= h(ucfirst($r['status'])) ?></span><br>
          <span class="muted">"<?= h($r['reason']) ?>"</span>
          <?php if ($r['status'] === 'pending'): ?>
            — <a href="order_requests.php">Review →</a>
          <?php endif; ?>
        </p>
      <?php endforeach; ?>
    <?php endif; ?>

    <h2>Update status</h2>
    <form class="contact-form" method="post" action="order_detail.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update_status">
      <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">

      <div class="form-row">
        <label for="status">Order status</label>
        <select id="status" name="status">
          <?php foreach ($valid_statuses as $s): ?>
            <option value="<?= h($s) ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= h(ucfirst($s)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <button type="submit" class="btn btn-primary">Update status</button>
    </form>
  </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
