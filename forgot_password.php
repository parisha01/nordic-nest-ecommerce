<?php
/**
 * forgot_password.php
 * ENHANCEMENT: Allow users to reset their password via email link
 */
require_once __DIR__ . '/includes/functions.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $email = clean($_POST['email'] ?? '');

        if (empty($email)) {
            $errors[] = 'Please enter your email address.';
        } elseif (!is_valid_email($email)) {
            $errors[] = 'Please enter a valid email address.';
        } else {
            // Find user by email
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // Generate reset token (valid for 1 hour)
                $token = generate_reset_token();
                $expires = date('Y-m-d H:i:s', time() + 3600);

                $stmt = $pdo->prepare('UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?');
                $stmt->execute([$token, $expires, $user['id']]);

                // Log email notification (in production, would send real email)
                $reset_link = 'https://localhost/nordic-nest-dynamic/reset_password.php?token=' . urlencode($token);
                $email_body = "Hi there,\n\n"
                    . "Click the link below to reset your password:\n"
                    . $reset_link . "\n\n"
                    . "This link expires in 1 hour.\n\n"
                    . "If you didn't request this, ignore this email.\n";

                log_email_notification($email, 'Reset your Nordic Nest password', $email_body);

                $success = 'If an account exists with that email, you\'ll receive password reset instructions. Check your email!';
            } else {
                // Don't reveal whether email exists (security best practice)
                $success = 'If an account exists with that email, you\'ll receive password reset instructions. Check your email!';
            }
        }
    }
}

$page_title = 'Forgot Password | Nordic Nest';
$meta_description = 'Reset your Nordic Nest password.';
$active = 'account';
$base = '';
require __DIR__ . '/includes/header.php';
?>

<section class="page-header">
  <div class="container">
    <p class="breadcrumb"><a href="index.php">Home</a> / Forgot Password</p>
    <h1>Forgot your password?</h1>
    <p class="page-subheading">Enter your email and we'll send you a link to reset it.</p>
  </div>
</section>

<section class="container form-columns">
  <div></div>
  <form method="post" class="form-card">
    <?= csrf_field() ?>

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
      </div>
    <?php endif; ?>

    <div class="form-group">
      <label for="email">Email address *</label>
      <input type="email" id="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required>
    </div>

    <button type="submit" class="btn btn-primary">Send Reset Link</button>
    <p style="margin-top: 20px; text-align: center;">
      Remembered your password? <a href="login.php">Log in here</a>
    </p>
  </form>
  <div></div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
