<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$user = current_user();

if (!$id) {
    http_response_code(404);
    $page_title = 'Order Not Found | Nordic Nest';
    $active = 'profile';
    $base = '';
    require __DIR__ . '/includes/header.php';
    echo '<section class="container section"><h1>Order not found</h1><p><a href="profile.php">Back to my account</a></p></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
$stmt->execute([$id]);
$order = $stmt->fetch();

// A member may only view their own orders; an admin may view any order.
if (!$order || ((int)$order['user_id'] !== (int)$user['id'] && !is_admin())) {
    http_response_code(404);
    $page_title = 'Order Not Found | Nordic Nest';
    $active = 'profile';
    $base = '';
    require __DIR__ . '/includes/header.php';
    echo '<section class="container section"><h1>Order not found</h1><p><a href="profile.php">Back to my account</a></p></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$itemStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id');
$itemStmt->execute([$id]);
$orderItems = $itemStmt->fetchAll();

$errors = [];

// ---- NEW: Handle a member submitting a cancellation/refund request ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_request') {
    if ((int)$order['user_id'] !== (int)$user['id']) {
        $errors['form'] = 'You can only request changes on your own orders.';
    } elseif (!csrf_check()) {
        $errors['form'] = 'Your session expired. Please try again.';
    } else {
        $eligibleType = order_request_eligible_type($order);
        $reason       = clean($_POST['reason'] ?? '');

        if ($eligibleType === null) {
            $errors['form'] = 'This order is no longer eligible for a cancellation or refund request.';
        } elseif (order_has_open_request($pdo, (int)$order['id'])) {
            $errors['form'] = 'You already have a pending request on this order — please wait for it to be reviewed.';
        } elseif ($reason === '' || mb_strlen($reason) < 10) {
            $errors['reason'] = 'Please give a short reason (at least 10 characters) so our team can review your request.';
        } elseif (mb_strlen($reason) > 500) {
            $errors['reason'] = 'Please keep your reason under 500 characters.';
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO order_requests (order_id, user_id, request_type, reason) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$order['id'], $user['id'], $eligibleType, $reason]);
            flash_set('success', ucfirst($eligibleType) . ' request submitted — our team will review it shortly.');
            redirect('order.php?id=' . $order['id']);
        }
    }
}

$latestRequest = order_latest_request($pdo, (int)$order['id']);
$eligibleType  = order_request_eligible_type($order);

$page_title       = 'Order #' . $order['id'] . ' | Nordic Nest';
$meta_description = 'Details for your Nordic Nest order.';
$active           = 'profile';
$base             = '';
require __DIR__ . '/includes/header.php';
?>

<section class="page-header">
  <div class="container">
    <p class="breadcrumb"><a href="index.php">Home</a> / <a href="profile.php">My account</a> / Order #<?= (int)$order['id'] ?></p>
    <h1>Order #<?= (int)$order['id'] ?></h1>
    <p class="page-subheading">
      Placed <?= h(date('d M Y, g:ia', strtotime($order['created_at']))) ?> —
      <span class="badge badge-order-<?= h($order['status']) ?>"><?= h(ucfirst($order['status'])) ?></span>
    </p>
  </div>
</section>

<!-- ENHANCEMENT: Order Tracking Timeline -->
<?php if (in_array($order['status'], ['cancelled', 'refunded'], true)): ?>
  <!-- NEW: the friend's 4-step timeline (Pending → Processing → Shipped →
       Delivered) doesn't have a slot for these two terminal states that our
       cancellation/refund feature introduces, so show a simple status note
       instead of a misleading "in progress" bar. -->
  <section class="container section" style="background: #f9f9f9; margin: 30px 0;">
    <h2>Order Status</h2>
    <p style="text-align: center; padding: 20px 0;">
      <span class="badge badge-order-<?= h($order['status']) ?>" style="font-size: 1.1rem; padding: 8px 16px;"><?= h(ucfirst($order['status'])) ?></span>
    </p>
  </section>
<?php else: ?>
<section class="container section" style="background: #f9f9f9; margin: 30px 0;">
  <h2>Order Status Timeline</h2>
  <div class="order-timeline" style="display: flex; gap: 5px; justify-content: space-between; position: relative; padding: 30px 0;">
    <style>
      .timeline-step { position: relative; flex: 1; text-align: center; }
      .timeline-step::before { 
        content: '';
        position: absolute;
        left: 0;
        right: 0;
        top: -15px;
        height: 8px;
        background: #e0e0e0;
        z-index: 0;
      }
      .timeline-step:first-child::before { left: 50%; }
      .timeline-step:last-child::before { right: 50%; }
      .timeline-circle {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #e0e0e0;
        margin: 0 auto 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        position: relative;
        z-index: 1;
      }
      .timeline-step.active .timeline-circle {
        background: #b8860b;
        color: white;
      }
      .timeline-step.completed .timeline-circle {
        background: #4caf50;
        color: white;
      }
      .timeline-label { font-weight: 600; font-size: 14px; margin-bottom: 5px; }
      .timeline-date { font-size: 12px; color: #999; }
    </style>

    <div class="timeline-step <?= $order['status'] === 'pending' ? 'active' : ($order['status'] !== 'pending' ? 'completed' : '') ?>">
      <div class="timeline-circle">📋</div>
      <div class="timeline-label">Pending</div>
      <div class="timeline-date"><?= h(date('d M Y', strtotime($order['created_at']))) ?></div>
    </div>

    <div class="timeline-step <?= $order['status'] === 'processing' ? 'active' : (in_array($order['status'], ['processing', 'shipped', 'completed']) ? 'completed' : '') ?>">
      <div class="timeline-circle">🔄</div>
      <div class="timeline-label">Processing</div>
      <div class="timeline-date">Soon</div>
    </div>

    <div class="timeline-step <?= $order['status'] === 'shipped' ? 'active' : (in_array($order['status'], ['shipped', 'completed']) ? 'completed' : '') ?>">
      <div class="timeline-circle">🚚</div>
      <div class="timeline-label">Shipped</div>
      <div class="timeline-date">Soon</div>
    </div>

    <div class="timeline-step <?= $order['status'] === 'completed' ? 'active completed' : '' ?>">
      <div class="timeline-circle">✓</div>
      <div class="timeline-label">Delivered</div>
      <div class="timeline-date">Soon</div>
    </div>
  </div>
  <p style="text-align: center; color: #666; margin-top: 20px; font-size: 14px;">Current status: <strong><?= h(ucfirst($order['status'])) ?></strong></p>
</section>
<?php endif; ?>

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
            <td><?= h($item['product_name']) ?><?php if ($item['product_id']): ?> — <a href="product.php?id=<?= (int)$item['product_id'] ?>">view</a><?php endif; ?></td>
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

    <?php if (is_admin()): ?>
      <p><a href="admin/order_detail.php?id=<?= (int)$order['id'] ?>">Manage this order in admin →</a></p>
    <?php endif; ?>

    <?php if ((int)$order['user_id'] === (int)$user['id']): ?>
      <h2>Need to change this order?</h2>

      <?php if (!empty($errors['form'])): ?>
        <div class="alert alert-error" role="alert"><?= h($errors['form']) ?></div>
      <?php endif; ?>

      <?php if ($latestRequest): ?>
        <p>
          Latest request: <strong><?= h(ucfirst($latestRequest['request_type'])) ?></strong> —
          <span class="badge badge-<?= $latestRequest['status'] === 'approved' ? 'approved' : ($latestRequest['status'] === 'rejected' ? 'rejected' : 'pending') ?>"><?= h(ucfirst($latestRequest['status'])) ?></span>
        </p>
        <p class="muted">"<?= h($latestRequest['reason']) ?>"</p>
        <?php if ($latestRequest['admin_note']): ?>
          <p><strong>Our response:</strong> <?= h($latestRequest['admin_note']) ?></p>
        <?php endif; ?>
      <?php endif; ?>

      <?php if ($eligibleType === null): ?>
        <p class="muted">This order's current status (<?= h($order['status']) ?>) is no longer eligible for a cancellation or refund request.</p>
      <?php elseif ($latestRequest && $latestRequest['status'] === 'pending'): ?>
        <p class="muted">Your request is being reviewed — no need to submit another.</p>
      <?php else: ?>
        <form class="contact-form" method="post" action="order.php?id=<?= (int)$order['id'] ?>" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="submit_request">
          <div class="form-row <?= isset($errors['reason']) ? 'has-error' : '' ?>">
            <label for="reason">
              <?= $eligibleType === 'cancellation' ? 'Reason for cancelling' : 'Reason for requesting a refund' ?>
              <span class="required">*</span>
            </label>
            <textarea id="reason" name="reason" required minlength="10" maxlength="500" placeholder="e.g. Ordered the wrong colour, no longer needed, item arrived damaged..."></textarea>
            <?php if (isset($errors['reason'])): ?><p class="field-error"><?= h($errors['reason']) ?></p><?php endif; ?>
          </div>
          <button type="submit" class="btn btn-outline"><?= $eligibleType === 'cancellation' ? 'Request cancellation' : 'Request refund' ?></button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
