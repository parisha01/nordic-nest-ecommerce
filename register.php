<?php
require_once __DIR__ . '/includes/functions.php';

// Only one safe redirect target is supported (a small whitelist, never a
// raw user-supplied path) — used so registering from the cart returns you
// straight to checkout instead of the homepage.
$next = ($_GET['next'] ?? $_POST['next'] ?? '') === 'checkout' ? 'checkout' : '';

// Already logged in? No need to register again.
if (is_logged_in()) {
    redirect($next === 'checkout' ? 'checkout.php' : 'index.php');
}

$errors = [];
$old    = ['full_name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors['form'] = 'Your session expired. Please try again.';
    } else {
        $old['full_name'] = clean($_POST['full_name'] ?? '');
        $old['email']     = clean($_POST['email'] ?? '');
        $password         = (string)($_POST['password'] ?? '');
        $confirm          = (string)($_POST['confirm_password'] ?? '');

        // ---- Server-side validation (never trust client-side alone) ----
        if ($old['full_name'] === '') {
            $errors['full_name'] = 'Please enter your full name.';
        } elseif (mb_strlen($old['full_name']) > 120) {
            $errors['full_name'] = 'Name is too long (max 120 characters).';
        }

        if ($old['email'] === '') {
            $errors['email'] = 'Please enter your email address.';
        } elseif (!is_valid_email($old['email'])) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        if ($password === '') {
            $errors['password'] = 'Please choose a password.';
        } elseif (!is_valid_password($password)) {
            $errors['password'] = 'Password must be at least 8 characters and include a letter and a number.';
        }

        if ($confirm === '' || $confirm !== $password) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }

        // Check email isn't already registered (only if no earlier errors, to save a query).
        if (empty($errors) ) {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$old['email']]);
            if ($stmt->fetch()) {
                $errors['email'] = 'An account with that email already exists. Try logging in instead.';
            }
        }

        if (empty($errors)) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare(
                'INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$old['full_name'], $old['email'], $hash, 'member']);

            // Log the new member straight in.
            $_SESSION['user'] = [
                'id'        => (int)$pdo->lastInsertId(),
                'full_name' => $old['full_name'],
                'email'     => $old['email'],
                'role'      => 'member',
            ];
            session_regenerate_id(true);

            flash_set('success', 'Welcome to Nordic Nest, ' . $old['full_name'] . '! Your account has been created.');
            redirect($next === 'checkout' ? 'checkout.php' : 'index.php');
        }
    }
}

$page_title       = 'Create an Account | Nordic Nest';
$meta_description = 'Register a free Nordic Nest account to leave product reviews, save your details and track your enquiries.';
$active           = 'register';
$base             = '';
require __DIR__ . '/includes/header.php';
?>

<section class="page-header">
  <div class="container">
    <p class="breadcrumb"><a href="index.php">Home</a> / Register</p>
    <h1>Create your account</h1>
    <p class="page-subheading">Join Nordic Nest to leave reviews and manage your details.</p>
  </div>
</section>

<section class="container narrow-form">
  <?php if (!empty($errors['form'])): ?>
    <div class="alert alert-error" role="alert"><?= h($errors['form']) ?></div>
  <?php endif; ?>

  <form class="contact-form" method="post" action="register.php<?= $next ? '?next=' . h($next) : '' ?>" novalidate>
    <?= csrf_field() ?>
    <?php if ($next): ?><input type="hidden" name="next" value="<?= h($next) ?>"><?php endif; ?>

    <div class="form-row <?= isset($errors['full_name']) ? 'has-error' : '' ?>">
      <label for="full_name">Full name <span class="required">*</span></label>
      <input type="text" id="full_name" name="full_name" value="<?= h($old['full_name']) ?>" required maxlength="120" aria-describedby="full_name_error">
      <?php if (isset($errors['full_name'])): ?>
        <p class="field-error" id="full_name_error"><?= h($errors['full_name']) ?></p>
      <?php endif; ?>
    </div>

    <div class="form-row <?= isset($errors['email']) ? 'has-error' : '' ?>">
      <label for="email">Email address <span class="required">*</span></label>
      <input type="email" id="email" name="email" value="<?= h($old['email']) ?>" required aria-describedby="email_error">
      <?php if (isset($errors['email'])): ?>
        <p class="field-error" id="email_error"><?= h($errors['email']) ?></p>
      <?php endif; ?>
    </div>

    <div class="form-row <?= isset($errors['password']) ? 'has-error' : '' ?>">
      <label for="password">Password <span class="required">*</span></label>
      <input type="password" id="password" name="password" required minlength="8" aria-describedby="password_error password_hint">
      <p class="field-hint" id="password_hint">At least 8 characters, including a letter and a number.</p>
      <?php if (isset($errors['password'])): ?>
        <p class="field-error" id="password_error"><?= h($errors['password']) ?></p>
      <?php endif; ?>
    </div>

    <div class="form-row <?= isset($errors['confirm_password']) ? 'has-error' : '' ?>">
      <label for="confirm_password">Confirm password <span class="required">*</span></label>
      <input type="password" id="confirm_password" name="confirm_password" required minlength="8" aria-describedby="confirm_password_error">
      <?php if (isset($errors['confirm_password'])): ?>
        <p class="field-error" id="confirm_password_error"><?= h($errors['confirm_password']) ?></p>
      <?php endif; ?>
    </div>

    <button type="submit" class="btn btn-primary">Create account</button>
    <p class="form-footnote">Already have an account? <a href="login.php<?= $next ? '?next=' . h($next) : '' ?>">Log in</a>.</p>
  </form>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
