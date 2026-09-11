<?php
/**
 * reset_password.php
 * ENHANCEMENT: Handle password reset with token
 */
require_once __DIR__ . '/includes/functions.php';

$errors = [];
$success = '';
$token = clean($_GET['token'] ?? '');
$user = null;

// Validate token if GET request
if (!empty($token)) {
    $stmt = $pdo->prepare('SELECT id, email FROM users WHERE reset_token = ? AND reset_token_expires > NOW()');
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        $errors[] = 'Invalid or expired reset link. Please request a new one.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $token = clean($_POST['token'] ?? '');
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($token)) {
            $errors[] = 'Invalid request.';
        } else {
            // Verify token again
            $stmt = $pdo->prepare('SELECT id, email FROM users WHERE reset_token = ? AND reset_token_expires > NOW()');
            $stmt->execute([$token]);
            $user = $stmt->fetch();

            if (!$user) {
                $errors[] = 'Invalid or expired reset link.';
            } elseif (empty($new_password)) {
                $errors[] = 'Please enter a new password.';
            } elseif (!is_valid_password($new_password)) {
                $errors[] = 'Password must be at least 8 characters and contain a letter and a number.';
            } elseif ($new_password !== $confirm_password) {
                $errors[] = 'Passwords do not match.';
            } else {
                // Reset the password
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?');
                $stmt->execute([$hashed, $user['id']]);

                log_email_notification($user['email'], 'Your password has been reset', 'Your Nordic Nest account password was successfully reset.');

                $success = 'Your password has been reset! You can now log in with your new password.';
                $user = null;
            }
        }
    }
}

$page_title = 'Reset Password | Nordic Nest';
$meta_description = 'Reset your Nordic Nest account password.';
$active = 'account';
$base = '';
require __DIR__ . '/includes/header.php';
?>

<section class="page-header">
  <div class="container">
    <p class="breadcrumb"><a href="index.php">Home</a> / Reset Password</p>
    <h1>Reset your password</h1>
  </div>
</section>

<section class="container form-columns">
  <div></div>
  <form method="post" class="form-card">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= h($token) ?>">

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error" role="alert">
        <ul>
          <?php foreach ($errors as $e): ?>
            <li><?= h($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert alert-success" role="alert">
        <?= h($success) ?>
        <p style="margin-top: 15px;">
          <a href="login.php" class="btn btn-primary">Go to login</a>
        </p>
      </div>
    <?php elseif ($user): ?>
      <p>Resetting password for: <strong><?= h($user['email']) ?></strong></p>

      <div class="form-group">
        <label for="new_password">New password *</label>
        <input type="password" id="new_password" name="new_password" required>
        <small>At least 8 characters, with a letter and a number.</small>
      </div>

      <div class="form-group">
        <label for="confirm_password">Confirm password *</label>
        <input type="password" id="confirm_password" name="confirm_password" required>
      </div>

      <button type="submit" class="btn btn-primary">Reset Password</button>
    <?php else: ?>
      <p>Unable to process reset request. <a href="forgot_password.php">Try again</a></p>
    <?php endif; ?>
  </form>
  <div></div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
