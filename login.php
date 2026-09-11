<?php
require_once __DIR__ . '/includes/functions.php';

// Only one safe redirect target is supported (a small whitelist, never a
// raw user-supplied path) — used so "Log in" from the cart returns you
// straight to checkout instead of the homepage.
$next = ($_GET['next'] ?? $_POST['next'] ?? '') === 'checkout' ? 'checkout' : '';

if (is_logged_in()) {
    redirect($next === 'checkout' ? 'checkout.php' : 'index.php');
}

$errors = [];
$old    = ['email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors['form'] = 'Your session expired. Please try again.';
    } else {
        $old['email'] = clean($_POST['email'] ?? '');
        $password     = (string)($_POST['password'] ?? '');

        if ($old['email'] === '' || $password === '') {
            $errors['form'] = 'Please enter both your email and password.';
        } else {
            $stmt = $pdo->prepare('SELECT id, full_name, email, password_hash, role FROM users WHERE email = ?');
            $stmt->execute([$old['email']]);
            $user = $stmt->fetch();

            // Deliberately the SAME error for "no such user" and "wrong password" —
            // this stops an attacker using the login form to discover which
            // emails are registered (a common security/privacy requirement).
            if (!$user || !password_verify($password, $user['password_hash'])) {
                $errors['form'] = 'Incorrect email or password.';
            } else {
                $_SESSION['user'] = [
                    'id'        => (int)$user['id'],
                    'full_name' => $user['full_name'],
                    'email'     => $user['email'],
                    'role'      => $user['role'],
                ];
                session_regenerate_id(true); // prevent session fixation on privilege change

                flash_set('success', 'Welcome back, ' . $user['full_name'] . '!');
                if ($next === 'checkout' && $user['role'] !== 'admin') {
                    redirect('checkout.php');
                }
                redirect($user['role'] === 'admin' ? 'admin/dashboard.php' : 'index.php');
            }
        }
    }
}

$page_title       = 'Log In | Nordic Nest';
$meta_description = 'Log in to your Nordic Nest account to leave reviews and manage your details.';
$active           = 'login';
$base             = '';
require __DIR__ . '/includes/header.php';
?>

<section class="page-header">
  <div class="container">
    <p class="breadcrumb"><a href="index.php">Home</a> / Log in</p>
    <h1>Welcome back</h1>
    <p class="page-subheading">Log in to leave a review or manage your account.</p>
  </div>
</section>

<section class="container narrow-form">
  <?php if (!empty($errors['form'])): ?>
    <div class="alert alert-error" role="alert"><?= h($errors['form']) ?></div>
  <?php endif; ?>

  <form class="contact-form" method="post" action="login.php<?= $next ? '?next=' . h($next) : '' ?>" novalidate>
    <?= csrf_field() ?>
    <?php if ($next): ?><input type="hidden" name="next" value="<?= h($next) ?>"><?php endif; ?>

    <div class="form-row">
      <label for="email">Email address <span class="required">*</span></label>
      <input type="email" id="email" name="email" value="<?= h($old['email']) ?>" required>
    </div>

    <div class="form-row">
      <label for="password">Password <span class="required">*</span></label>
      <input type="password" id="password" name="password" required>
    </div>

    <button type="submit" class="btn btn-primary">Log in</button>
    <p class="form-footnote">
      New to Nordic Nest? <a href="register.php<?= $next ? '?next=' . h($next) : '' ?>">Create an account</a>.<br>
      <!-- ENHANCEMENT: Forgot password link -->
      <a href="forgot_password.php" style="font-size: 12px;">Forgot your password?</a>
    </p>
  </form>

  <p class="form-footnote muted">Demo admin login — see README.md for how to create one via <code>setup/create_admin.php</code>.</p>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
