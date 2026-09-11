<?php
/**
 * setup/create_admin.php
 * -----------------------------------------------------------
 * Run this ONCE in your browser after importing schema.sql to
 * create your first admin account, then DELETE this file (or
 * at least rename/move it out of the web root) — leaving a
 * page that can create admin accounts publicly reachable is a
 * security risk on a real deployment.
 *
 * Why this exists: passwords must be hashed with PHP's
 * password_hash() function, which can't be produced from a
 * plain .sql seed file. This form calls password_hash() for
 * you so the schema file never has to contain a real password.
 * -----------------------------------------------------------
 */
require_once __DIR__ . '/../includes/functions.php';

$errors = [];
$success = false;
$old = ['full_name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors['form'] = 'Your session expired. Please reload this page and try again.';
    } else {
        $old['full_name'] = clean($_POST['full_name'] ?? '');
        $old['email']     = clean($_POST['email'] ?? '');
        $password         = (string)($_POST['password'] ?? '');

        if ($old['full_name'] === '') {
            $errors['full_name'] = 'Please enter a name.';
        }
        if (!is_valid_email($old['email'])) {
            $errors['email'] = 'Please enter a valid email address.';
        }
        if (!is_valid_password($password)) {
            $errors['password'] = 'Password must be at least 8 characters and include a letter and a number.';
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$old['email']]);
            $existing = $stmt->fetch();

            $hash = password_hash($password, PASSWORD_BCRYPT);

            if ($existing) {
                // Already exists — promote/reset it instead of erroring, so this
                // script is also useful if you ever need to reset the admin password.
                $stmt = $pdo->prepare('UPDATE users SET full_name = ?, password_hash = ?, role = ? WHERE id = ?');
                $stmt->execute([$old['full_name'], $hash, 'admin', $existing['id']]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)'
                );
                $stmt->execute([$old['full_name'], $old['email'], $hash, 'admin']);
            }
            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Create Admin Account — Setup</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="stylesheet" href="../css/style.css">
</head>
<body>
  <section class="container narrow-form" style="padding-top:3rem;">
    <h1>One-time setup: create admin account</h1>
    <p class="alert alert-error" role="alert">
      <strong>Delete this file after use.</strong> Leaving <code>setup/create_admin.php</code> publicly
      reachable would let anyone create an admin account on your live site.
    </p>

    <?php if ($success): ?>
      <div class="alert alert-success" role="status">
        Admin account ready for <strong><?= h($old['email']) ?></strong>. You can now
        <a href="../login.php">log in</a>. Please delete this file now.
      </div>
    <?php else: ?>
      <?php if (!empty($errors['form'])): ?><div class="alert alert-error"><?= h($errors['form']) ?></div><?php endif; ?>
      <form class="contact-form" method="post" action="create_admin.php" novalidate>
        <?= csrf_field() ?>
        <div class="form-row <?= isset($errors['full_name']) ? 'has-error' : '' ?>">
          <label for="full_name">Full name</label>
          <input type="text" id="full_name" name="full_name" value="<?= h($old['full_name']) ?>" required>
          <?php if (isset($errors['full_name'])): ?><p class="field-error"><?= h($errors['full_name']) ?></p><?php endif; ?>
        </div>
        <div class="form-row <?= isset($errors['email']) ? 'has-error' : '' ?>">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" value="<?= h($old['email']) ?>" required>
          <?php if (isset($errors['email'])): ?><p class="field-error"><?= h($errors['email']) ?></p><?php endif; ?>
        </div>
        <div class="form-row <?= isset($errors['password']) ? 'has-error' : '' ?>">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required minlength="8">
          <p class="field-hint">At least 8 characters, including a letter and a number.</p>
          <?php if (isset($errors['password'])): ?><p class="field-error"><?= h($errors['password']) ?></p><?php endif; ?>
        </div>
        <button type="submit" class="btn btn-primary">Create admin account</button>
      </form>
    <?php endif; ?>
  </section>
</body>
</html>
