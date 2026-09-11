<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (csrf_check()) {
        $id     = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
        $action = $_POST['action'] ?? '';

        if ($id && $action === 'mark_read') {
            $stmt = $pdo->prepare("UPDATE contact_messages SET status = 'read' WHERE id = ?");
            $stmt->execute([$id]);
        } elseif ($id && $action === 'delete') {
            $stmt = $pdo->prepare('DELETE FROM contact_messages WHERE id = ?');
            $stmt->execute([$id]);
            flash_set('success', 'Message deleted.');
        }
    }
    redirect('messages.php');
}

$messages = $pdo->query(
    "SELECT id, name, email, subject, message, status, created_at
     FROM contact_messages ORDER BY (status = 'new') DESC, created_at DESC"
)->fetchAll();

$page_title = 'Contact Messages | Nordic Nest Admin';
$active     = 'admin';
$base       = '../';
require __DIR__ . '/../includes/header.php';
?>

<section class="page-header">
  <div class="container">
    <p class="breadcrumb"><a href="dashboard.php">Admin</a> / Messages</p>
    <h1>Contact enquiries</h1>
  </div>
</section>

<section class="container section">
  <?php if (empty($messages)): ?>
    <p>No messages yet.</p>
  <?php else: ?>
    <div class="message-list">
      <?php foreach ($messages as $m): ?>
        <article class="message-card <?= $m['status'] === 'new' ? 'is-new' : '' ?>">
          <header>
            <h3><?= h($m['subject']) ?> <?php if ($m['status'] === 'new'): ?><span class="badge badge-pending">New</span><?php endif; ?></h3>
            <p class="muted"><?= h($m['name']) ?> — <a href="mailto:<?= h($m['email']) ?>"><?= h($m['email']) ?></a> — <?= h(date('d M Y, g:ia', strtotime($m['created_at']))) ?></p>
          </header>
          <p><?= nl2br(h($m['message'])) ?></p>
          <div class="actions">
            <?php if ($m['status'] === 'new'): ?>
              <form method="post" action="messages.php">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                <input type="hidden" name="action" value="mark_read">
                <button type="submit" class="link-button">Mark as read</button>
              </form>
            <?php endif; ?>
            <form method="post" action="messages.php" onsubmit="return confirm('Delete this message?');">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
              <input type="hidden" name="action" value="delete">
              <button type="submit" class="link-button danger">Delete</button>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
