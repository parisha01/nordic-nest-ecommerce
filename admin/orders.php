<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$valid_statuses = ['pending', 'processing', 'shipped', 'completed', 'cancelled', 'refunded'];
$status = clean($_GET['status'] ?? '');
if ($status !== '' && !in_array($status, $valid_statuses, true)) {
    $status = '';
}

if ($status === '') {
    $stmt = $pdo->query(
        'SELECT o.id, o.status, o.total, o.full_name, o.created_at, u.email AS account_email
         FROM orders o JOIN users u ON u.id = o.user_id
         ORDER BY o.created_at DESC'
    );
} else {
    $stmt = $pdo->prepare(
        'SELECT o.id, o.status, o.total, o.full_name, o.created_at, u.email AS account_email
         FROM orders o JOIN users u ON u.id = o.user_id
         WHERE o.status = ?
         ORDER BY o.created_at DESC'
    );
    $stmt->execute([$status]);
}
$orders = $stmt->fetchAll();

$revenue = (float)$pdo->query("SELECT COALESCE(SUM(total), 0) AS r FROM orders WHERE status NOT IN ('cancelled', 'refunded')")->fetch()['r'];

$page_title = 'Manage Orders | Nordic Nest Admin';
$active     = 'admin';
$base       = '../';
require __DIR__ . '/../includes/header.php';
?>

<section class="page-header">
  <div class="container">
    <p class="breadcrumb"><a href="dashboard.php">Admin</a> / Orders</p>
    <h1>Manage orders</h1>
    <p class="page-subheading">Total revenue across all non-cancelled, non-refunded orders: <strong>$<?= number_format($revenue, 2) ?></strong></p>
  </div>
</section>

<section class="container section">
  <div class="filter-bar" role="group" aria-label="Filter orders by status">
    <a class="filter-btn <?= $status === '' ? 'is-active' : '' ?>" href="orders.php">All</a>
    <?php foreach ($valid_statuses as $s): ?>
      <a class="filter-btn <?= $status === $s ? 'is-active' : '' ?>" href="orders.php?status=<?= urlencode($s) ?>"><?= h(ucfirst($s)) ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (empty($orders)): ?>
    <p>No orders found<?= $status !== '' ? ' with that status' : '' ?>.</p>
  <?php else: ?>
    <table class="data-table">
      <thead>
        <tr><th scope="col">Order</th><th scope="col">Customer</th><th scope="col">Placed</th><th scope="col">Status</th><th scope="col">Total</th><th scope="col">Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($orders as $o): ?>
          <tr>
            <td>#<?= (int)$o['id'] ?></td>
            <td><?= h($o['full_name']) ?><br><span class="muted"><?= h($o['account_email']) ?></span></td>
            <td><?= h(date('d M Y', strtotime($o['created_at']))) ?></td>
            <td><span class="badge badge-order-<?= h($o['status']) ?>"><?= h(ucfirst($o['status'])) ?></span></td>
            <td>$<?= number_format((float)$o['total'], 2) ?></td>
            <td class="actions"><a href="order_detail.php?id=<?= (int)$o['id'] ?>">View / update</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
