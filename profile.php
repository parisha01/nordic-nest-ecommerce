<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$user = current_user();
$errors = [];
$profile_success = null;
$password_success = null;

// ---- Handle "update profile" form ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    if (!csrf_check()) {
        $errors['profile_form'] = 'Your session expired. Please try again.';
    } else {
        $full_name = clean($_POST['full_name'] ?? '');
        $email     = clean($_POST['email'] ?? '');

        if ($full_name === '') {
            $errors['full_name'] = 'Please enter your full name.';
        }
        if ($email === '' || !is_valid_email($email)) {
            $errors['email'] = 'Please enter a valid email address.';
        } else {
            // Make sure another account hasn't already taken this email.
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
            $stmt->execute([$email, $user['id']]);
            if ($stmt->fetch()) {
                $errors['email'] = 'That email is already used by another account.';
            }
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare('UPDATE users SET full_name = ?, email = ? WHERE id = ?');
            $stmt->execute([$full_name, $email, $user['id']]);

            $_SESSION['user']['full_name'] = $full_name;
            $_SESSION['user']['email']     = $email;
            $user = current_user();
            $profile_success = 'Your details have been updated.';
        }
    }
}

// ---- Handle "change password" form ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    if (!csrf_check()) {
        $errors['password_form'] = 'Your session expired. Please try again.';
    } else {
        $current  = (string)($_POST['current_password'] ?? '');
        $new_pass = (string)($_POST['new_password'] ?? '');
        $confirm  = (string)($_POST['confirm_new_password'] ?? '');

        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($current, $row['password_hash'])) {
            $errors['current_password'] = 'Current password is incorrect.';
        }
        if (!is_valid_password($new_pass)) {
            $errors['new_password'] = 'New password must be at least 8 characters and include a letter and a number.';
        }
        if ($new_pass !== $confirm) {
            $errors['confirm_new_password'] = 'New passwords do not match.';
        }

        if (empty($errors)) {
            $hash = password_hash($new_pass, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $stmt->execute([$hash, $user['id']]);
            $password_success = 'Your password has been changed.';
        }
    }
}

// Fetch this member's own orders to show them their order history.
$stmt = $pdo->prepare('SELECT id, status, total, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$user['id']]);
$my_orders = $stmt->fetchAll();

// Fetch this member's own testimonials (any status) to show them their history.
$stmt = $pdo->prepare(
    'SELECT t.id, t.rating, t.comment, t.status, t.created_at, p.name AS product_name
     FROM testimonials t
     LEFT JOIN products p ON p.id = t.product_id
     WHERE t.user_id = ?
     ORDER BY t.created_at DESC'
);
$stmt->execute([$user['id']]);
$my_reviews = $stmt->fetchAll();

$page_title       = 'My Account | Nordic Nest';
$meta_description = 'Manage your Nordic Nest account details and view your submitted reviews.';
$active           = 'profile';
$base             = '';
require __DIR__ . '/includes/header.php';
?>

<section class="page-header">
  <div class="container">
    <p class="breadcrumb"><a href="index.php">Home</a> / My account</p>
    <h1>My account</h1>
    <p class="page-subheading">Signed in as <?= h($user['full_name']) ?> (<?= h(ucfirst($user['role'])) ?>)</p>
  </div>
</section>

<section class="container form-columns">
  <div>
    <h2>Your details</h2>
    <?php if ($profile_success): ?>
      <div class="alert alert-success" role="status"><?= h($profile_success) ?></div>
    <?php endif; ?>
    <?php if (!empty($errors['profile_form'])): ?>
      <div class="alert alert-error" role="alert"><?= h($errors['profile_form']) ?></div>
    <?php endif; ?>

    <form class="contact-form" method="post" action="profile.php" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update_profile">

      <div class="form-row <?= isset($errors['full_name']) ? 'has-error' : '' ?>">
        <label for="full_name">Full name <span class="required">*</span></label>
        <input type="text" id="full_name" name="full_name" value="<?= h($user['full_name']) ?>" required maxlength="120">
        <?php if (isset($errors['full_name'])): ?><p class="field-error"><?= h($errors['full_name']) ?></p><?php endif; ?>
      </div>

      <div class="form-row <?= isset($errors['email']) ? 'has-error' : '' ?>">
        <label for="email">Email address <span class="required">*</span></label>
        <input type="email" id="email" name="email" value="<?= h($user['email']) ?>" required>
        <?php if (isset($errors['email'])): ?><p class="field-error"><?= h($errors['email']) ?></p><?php endif; ?>
      </div>

      <button type="submit" class="btn btn-primary">Save details</button>
    </form>
  </div>

  <div>
    <h2>Change password</h2>
    <?php if ($password_success): ?>
      <div class="alert alert-success" role="status"><?= h($password_success) ?></div>
    <?php endif; ?>
    <?php if (!empty($errors['password_form'])): ?>
      <div class="alert alert-error" role="alert"><?= h($errors['password_form']) ?></div>
    <?php endif; ?>

    <form class="contact-form" method="post" action="profile.php" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="change_password">

      <div class="form-row <?= isset($errors['current_password']) ? 'has-error' : '' ?>">
        <label for="current_password">Current password <span class="required">*</span></label>
        <input type="password" id="current_password" name="current_password" required>
        <?php if (isset($errors['current_password'])): ?><p class="field-error"><?= h($errors['current_password']) ?></p><?php endif; ?>
      </div>

      <div class="form-row <?= isset($errors['new_password']) ? 'has-error' : '' ?>">
        <label for="new_password">New password <span class="required">*</span></label>
        <input type="password" id="new_password" name="new_password" required minlength="8">
        <p class="field-hint">At least 8 characters, including a letter and a number.</p>
        <?php if (isset($errors['new_password'])): ?><p class="field-error"><?= h($errors['new_password']) ?></p><?php endif; ?>
      </div>

      <div class="form-row <?= isset($errors['confirm_new_password']) ? 'has-error' : '' ?>">
        <label for="confirm_new_password">Confirm new password <span class="required">*</span></label>
        <input type="password" id="confirm_new_password" name="confirm_new_password" required minlength="8">
        <?php if (isset($errors['confirm_new_password'])): ?><p class="field-error"><?= h($errors['confirm_new_password']) ?></p><?php endif; ?>
      </div>

      <button type="submit" class="btn btn-primary">Update password</button>
    </form>
  </div>
</section>

<section class="container">
  <h2>Your orders</h2>
  <?php if (empty($my_orders)): ?>
    <p>You haven't placed any orders yet. <a href="shop.php">Browse the shop</a>.</p>
  <?php else: ?>
    <table class="data-table">
      <thead>
        <tr><th scope="col">Order</th><th scope="col">Placed</th><th scope="col">Status</th><th scope="col">Total</th><th scope="col"></th></tr>
      </thead>
      <tbody>
        <?php foreach ($my_orders as $o): ?>
          <tr>
            <td>#<?= (int)$o['id'] ?></td>
            <td><?= h(date('d M Y', strtotime($o['created_at']))) ?></td>
            <td><span class="badge badge-order-<?= h($o['status']) ?>"><?= h(ucfirst($o['status'])) ?></span></td>
            <td>$<?= number_format((float)$o['total'], 2) ?></td>
            <td><a href="order.php?id=<?= (int)$o['id'] ?>">View</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<section class="container">
  <h2>Your reviews</h2>
  <?php if (empty($my_reviews)): ?>
    <p>You haven't submitted any reviews yet. <a href="testimonials.php">Leave one here</a>.</p>
  <?php else: ?>
    <table class="data-table">
      <thead>
        <tr><th scope="col">Product</th><th scope="col">Rating</th><th scope="col">Comment</th><th scope="col">Status</th><th scope="col">Submitted</th></tr>
      </thead>
      <tbody>
        <?php foreach ($my_reviews as $r): ?>
          <tr>
            <td><?= h($r['product_name'] ?? 'General') ?></td>
            <td><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?></td>
            <td><?= h($r['comment']) ?></td>
            <td><span class="badge badge-<?= h($r['status']) ?>"><?= h(ucfirst($r['status'])) ?></span></td>
            <td><?= h(date('d M Y', strtotime($r['created_at']))) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
