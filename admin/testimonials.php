<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (csrf_check()) {
        $id     = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
        $action = $_POST['action'] ?? '';

        if ($id && in_array($action, ['approve', 'reject', 'delete'], true)) {
            if ($action === 'delete') {
                $stmt = $pdo->prepare('DELETE FROM testimonials WHERE id = ?');
                $stmt->execute([$id]);
                flash_set('success', 'Review deleted.');
            } else {
                $status = $action === 'approve' ? 'approved' : 'rejected';
                $stmt = $pdo->prepare('UPDATE testimonials SET status = ? WHERE id = ?');
                $stmt->execute([$status, $id]);
                flash_set('success', 'Review ' . $status . '.');
            }
        }
    }
    redirect('testimonials.php');
}

$stmt = $pdo->query(
    "SELECT t.id, t.rating, t.comment, t.status, t.created_at, t.is_verified_purchase, u.full_name, p.name AS product_name
     FROM testimonials t
     JOIN users u ON u.id = t.user_id
     LEFT JOIN products p ON p.id = t.product_id
     ORDER BY (t.status = 'pending') DESC, t.created_at DESC"
);
$reviews = $stmt->fetchAll();

$page_title = 'Moderate Reviews | Nordic Nest Admin';
$active     = 'admin';
$base       = '../';
require __DIR__ . '/../includes/header.php';
?>

<section class="page-header">
  <div class="container">
    <p class="breadcrumb"><a href="dashboard.php">Admin</a> / Reviews</p>
    <h1>Moderate reviews</h1>
    <p class="page-subheading">Pending reviews are listed first. Approving makes a review visible on the public Testimonials and Product pages.</p>
  </div>
</section>

<section class="container section">
  <?php if (empty($reviews)): ?>
    <p>No reviews have been submitted yet.</p>
  <?php else: ?>
    <table class="data-table">
      <thead>
        <tr><th scope="col">Customer</th><th scope="col">Product</th><th scope="col">Rating</th><th scope="col">Comment</th><th scope="col">Status</th><th scope="col">Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($reviews as $r): ?>
          <tr>
            <td><?= h($r['full_name']) ?><?php if ($r['is_verified_purchase']): ?><br><span class="badge badge-approved">✓ Verified</span><?php endif; ?></td>
            <td><?= h($r['product_name'] ?? 'General') ?></td>
            <td><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?></td>
            <td><?= h($r['comment']) ?></td>
            <td><span class="badge badge-<?= h($r['status']) ?>"><?= h(ucfirst($r['status'])) ?></span></td>
            <td class="actions">
              <?php if ($r['status'] !== 'approved'): ?>
                <form method="post" action="testimonials.php">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="action" value="approve">
                  <button type="submit" class="link-button">Approve</button>
                </form>
              <?php endif; ?>
              <?php if ($r['status'] !== 'rejected'): ?>
                <form method="post" action="testimonials.php">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="action" value="reject">
                  <button type="submit" class="link-button">Reject</button>
                </form>
              <?php endif; ?>
              <form method="post" action="testimonials.php" onsubmit="return confirm('Delete this review permanently?');">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <input type="hidden" name="action" value="delete">
                <button type="submit" class="link-button danger">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
