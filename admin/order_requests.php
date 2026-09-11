<?php
/**
 * admin/order_requests.php
 * -----------------------------------------------------------
 * Admin-only queue for member-submitted order cancellation and
 * refund requests. Approving a CANCELLATION also restocks the
 * items (they never left the warehouse) and sets the order's
 * status to 'cancelled'. Approving a REFUND sets the order's
 * status to 'refunded' but does NOT restock automatically —
 * whether a refunded item is resaleable depends on its condition
 * on return, which needs a human decision, not an automatic rule.
 * -----------------------------------------------------------
 */
require_once __DIR__ . '/../includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        flash_set('error', 'Your session expired. Please try again.');
    } else {
        $requestId = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
        $action    = $_POST['action'] ?? '';
        $adminNote = clean($_POST['admin_note'] ?? '');

        if ($requestId && in_array($action, ['approve', 'reject'], true)) {
            $stmt = $pdo->prepare('SELECT * FROM order_requests WHERE id = ?');
            $stmt->execute([$requestId]);
            $req = $stmt->fetch();

            if (!$req) {
                flash_set('error', 'That request could not be found.');
            } elseif ($req['status'] !== 'pending') {
                flash_set('error', 'That request has already been resolved.');
            } else {
                try {
                    $pdo->beginTransaction();

                    $newStatus = $action === 'approve' ? 'approved' : 'rejected';
                    $pdo->prepare('UPDATE order_requests SET status = ?, admin_note = ?, resolved_at = NOW() WHERE id = ?')
                        ->execute([$newStatus, $adminNote !== '' ? $adminNote : null, $requestId]);

                    if ($action === 'approve') {
                        if ($req['request_type'] === 'cancellation') {
                            // Return the ordered quantities to stock — they never shipped.
                            $itemStmt = $pdo->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ? AND product_id IS NOT NULL');
                            $itemStmt->execute([$req['order_id']]);
                            $restock = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
                            foreach ($itemStmt->fetchAll() as $item) {
                                $restock->execute([(int)$item['quantity'], (int)$item['product_id']]);
                            }
                            $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?")->execute([$req['order_id']]);
                        } else { // refund
                            $pdo->prepare("UPDATE orders SET status = 'refunded' WHERE id = ?")->execute([$req['order_id']]);
                        }
                    }

                    $pdo->commit();
                    flash_set('success', 'Request ' . $newStatus . '.');
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    error_log('Order request resolution failed: ' . $e->getMessage());
                    flash_set('error', 'Something went wrong resolving that request. Please try again.');
                }
            }
        }
    }
    redirect('order_requests.php');
}

$stmt = $pdo->query(
    "SELECT r.*, o.total, o.status AS order_status, u.full_name, u.email
     FROM order_requests r
     JOIN orders o ON o.id = r.order_id
     JOIN users u ON u.id = r.user_id
     ORDER BY (r.status = 'pending') DESC, r.created_at DESC"
);
$requests = $stmt->fetchAll();

$page_title = 'Order Requests | Nordic Nest Admin';
$active     = 'admin';
$base       = '../';
require __DIR__ . '/../includes/header.php';
?>

<section class="page-header">
  <div class="container">
    <p class="breadcrumb"><a href="dashboard.php">Admin</a> / Order requests</p>
    <h1>Cancellation &amp; refund requests</h1>
    <p class="page-subheading">Pending requests are listed first. Approving a cancellation automatically returns the items to stock.</p>
  </div>
</section>

<section class="container section">
  <?php if (empty($requests)): ?>
    <p>No cancellation or refund requests have been submitted yet.</p>
  <?php else: ?>
    <table class="data-table">
      <thead>
        <tr><th scope="col">Order</th><th scope="col">Customer</th><th scope="col">Type</th><th scope="col">Reason</th><th scope="col">Status</th><th scope="col">Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($requests as $r): ?>
          <tr>
            <td><a href="order_detail.php?id=<?= (int)$r['order_id'] ?>">#<?= (int)$r['order_id'] ?></a> — $<?= number_format((float)$r['total'], 2) ?><br><span class="muted">(<?= h($r['order_status']) ?>)</span></td>
            <td><?= h($r['full_name']) ?><br><span class="muted"><?= h($r['email']) ?></span></td>
            <td><?= h(ucfirst($r['request_type'])) ?></td>
            <td><?= h($r['reason']) ?></td>
            <td>
              <span class="badge badge-<?= h($r['status']) ?>"><?= h(ucfirst($r['status'])) ?></span>
              <?php if ($r['admin_note']): ?><br><span class="muted">Note: <?= h($r['admin_note']) ?></span><?php endif; ?>
            </td>
            <td class="actions">
              <?php if ($r['status'] === 'pending'): ?>
                <form method="post" action="order_requests.php" class="inline-note-form">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <label class="visually-hidden" for="note-<?= (int)$r['id'] ?>">Note to customer (optional)</label>
                  <input type="text" id="note-<?= (int)$r['id'] ?>" name="admin_note" placeholder="Note to customer (optional)" maxlength="500">
                  <button type="submit" name="action" value="approve" class="link-button">Approve</button>
                  <button type="submit" name="action" value="reject" class="link-button danger">Reject</button>
                </form>
              <?php else: ?>
                <span class="muted">Resolved <?= h(date('d M Y', strtotime($r['resolved_at']))) ?></span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
