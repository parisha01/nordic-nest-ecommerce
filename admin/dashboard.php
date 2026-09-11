<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$product_count    = (int)$pdo->query('SELECT COUNT(*) AS c FROM products')->fetch()['c'];
$pending_reviews  = (int)$pdo->query("SELECT COUNT(*) AS c FROM testimonials WHERE status = 'pending'")->fetch()['c'];
$new_messages     = (int)$pdo->query("SELECT COUNT(*) AS c FROM contact_messages WHERE status = 'new'")->fetch()['c'];
$member_count     = (int)$pdo->query("SELECT COUNT(*) AS c FROM users WHERE role = 'member'")->fetch()['c'];
$subscriber_count = (int)$pdo->query('SELECT COUNT(*) AS c FROM newsletter_subscribers')->fetch()['c'];
$order_count      = (int)$pdo->query('SELECT COUNT(*) AS c FROM orders')->fetch()['c'];
$pending_orders   = (int)$pdo->query("SELECT COUNT(*) AS c FROM orders WHERE status = 'pending'")->fetch()['c'];
$revenue          = (float)$pdo->query("SELECT COALESCE(SUM(total), 0) AS r FROM orders WHERE status NOT IN ('cancelled', 'refunded')")->fetch()['r'];
$active_coupons   = (int)$pdo->query("SELECT COUNT(*) AS c FROM coupons WHERE active = 1 AND (expires_at IS NULL OR expires_at >= CURDATE())")->fetch()['c'];
$low_stock_count  = (int)$pdo->query('SELECT COUNT(*) AS c FROM products WHERE stock <= 5')->fetch()['c'];
$pending_requests = (int)$pdo->query("SELECT COUNT(*) AS c FROM order_requests WHERE status = 'pending'")->fetch()['c'];

$page_title       = 'Admin Dashboard | Nordic Nest';
$meta_description = 'Nordic Nest admin dashboard.';
$active           = 'admin';
$base             = '../';
require __DIR__ . '/../includes/header.php';
?>

<section class="page-header">
  <div class="container">
    <p class="breadcrumb"><a href="../index.php">Home</a> / Admin</p>
    <h1>Admin dashboard</h1>
    <p class="page-subheading">Signed in as <?= h(current_user()['full_name']) ?> — role: admin</p>
  </div>
</section>

<section class="container section">
  <div class="stat-grid">
    <div class="stat-card">
      <p class="stat-number"><?= $order_count ?></p>
      <p class="stat-label">Orders (<?= $pending_orders ?> pending)</p>
      <a href="orders.php">Manage orders →</a>
    </div>
    <div class="stat-card">
      <p class="stat-number">$<?= number_format($revenue, 2) ?></p>
      <p class="stat-label">Revenue (non-cancelled)</p>
      <a href="orders.php">View orders →</a>
    </div>
    <div class="stat-card">
      <p class="stat-number"><?= $product_count ?></p>
      <p class="stat-label">Products<?= $low_stock_count > 0 ? ' (' . $low_stock_count . ' low/out of stock)' : '' ?></p>
      <a href="products.php">Manage products →</a>
    </div>
    <div class="stat-card">
      <p class="stat-number"><?= $active_coupons ?></p>
      <p class="stat-label">Active coupons</p>
      <a href="coupons.php">Manage coupons →</a>
    </div>
    <div class="stat-card">
      <p class="stat-number"><?= $pending_requests ?></p>
      <p class="stat-label">Pending cancellation/refund requests</p>
      <a href="order_requests.php">Review requests →</a>
    </div>
    <div class="stat-card">
      <p class="stat-number"><?= $pending_reviews ?></p>
      <p class="stat-label">Pending reviews</p>
      <a href="testimonials.php">Moderate reviews →</a>
    </div>
    <div class="stat-card">
      <p class="stat-number"><?= $new_messages ?></p>
      <p class="stat-label">New enquiries</p>
      <a href="messages.php">View messages →</a>
    </div>
    <div class="stat-card">
      <p class="stat-number"><?= $member_count ?></p>
      <p class="stat-label">Registered members</p>
    </div>
    <div class="stat-card">
      <p class="stat-number"><?= $subscriber_count ?></p>
      <p class="stat-label">Newsletter subscribers</p>
    </div>
  </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
