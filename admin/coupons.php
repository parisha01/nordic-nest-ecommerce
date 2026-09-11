<?php
/**
 * admin/coupons.php
 * -----------------------------------------------------------
 * Admin-only CRUD for discount coupons applied at checkout.
 * Follows the same single-page list+form pattern as
 * product_form.php/products.php, just combined into one file
 * since coupons have far fewer fields.
 * -----------------------------------------------------------
 */
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$errors = [];
$coupon = ['id' => null, 'code' => '', 'description' => '', 'discount_type' => 'percent', 'discount_value' => '', 'min_order' => '0', 'max_uses' => '', 'expires_at' => '', 'active' => '1'];
$is_edit = false;

// ---- Load existing coupon into the form if ?edit=ID is present ----
$edit_id = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
if ($edit_id) {
    $stmt = $pdo->prepare('SELECT * FROM coupons WHERE id = ?');
    $stmt->execute([$edit_id]);
    $found = $stmt->fetch();
    if ($found) {
        $coupon  = $found;
        $is_edit = true;
    }
}

// ---- Handle delete ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (csrf_check()) {
        $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
        if ($id) {
            $pdo->prepare('DELETE FROM coupons WHERE id = ?')->execute([$id]);
            flash_set('success', 'Coupon deleted.');
        }
    }
    redirect('coupons.php');
}

// ---- Handle quick enable/disable toggle from the list ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_active') {
    if (csrf_check()) {
        $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
        if ($id) {
            $pdo->prepare('UPDATE coupons SET active = NOT active WHERE id = ?')->execute([$id]);
            flash_set('success', 'Coupon status updated.');
        }
    }
    redirect('coupons.php');
}

// ---- Handle add / edit save ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    if (!csrf_check()) {
        $errors['form'] = 'Your session expired. Please try again.';
    } else {
        $posted_id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
        $is_edit   = (bool)$posted_id;

        $coupon['id']             = $posted_id ?: null;
        $coupon['code']           = strtoupper(clean($_POST['code'] ?? ''));
        $coupon['description']    = clean($_POST['description'] ?? '');
        $coupon['discount_type']  = clean($_POST['discount_type'] ?? '');
        $coupon['discount_value'] = clean($_POST['discount_value'] ?? '');
        $coupon['min_order']      = clean($_POST['min_order'] ?? '0');
        $coupon['max_uses']       = clean($_POST['max_uses'] ?? '');
        $coupon['expires_at']     = clean($_POST['expires_at'] ?? '');
        $coupon['active']         = isset($_POST['active']) ? '1' : '0';

        if ($coupon['code'] === '' || !preg_match('/^[A-Z0-9_-]{3,40}$/', $coupon['code'])) {
            $errors['code'] = 'Code must be 3-40 characters: letters, numbers, hyphens or underscores only.';
        } else {
            // Uniqueness check (excluding this coupon's own row if editing).
            $dupStmt = $pdo->prepare('SELECT id FROM coupons WHERE code = ? AND id != ?');
            $dupStmt->execute([$coupon['code'], $coupon['id'] ?? 0]);
            if ($dupStmt->fetch()) {
                $errors['code'] = 'A coupon with this code already exists.';
            }
        }
        if (!in_array($coupon['discount_type'], ['percent', 'fixed'], true)) {
            $errors['discount_type'] = 'Please choose a discount type.';
        }
        $discount_value = filter_var($coupon['discount_value'], FILTER_VALIDATE_FLOAT);
        if ($discount_value === false || $discount_value <= 0) {
            $errors['discount_value'] = 'Please enter a discount value greater than 0.';
        } elseif ($coupon['discount_type'] === 'percent' && $discount_value > 100) {
            $errors['discount_value'] = 'A percentage discount cannot exceed 100.';
        }
        $min_order = filter_var($coupon['min_order'], FILTER_VALIDATE_FLOAT);
        if ($min_order === false || $min_order < 0) {
            $errors['min_order'] = 'Minimum order must be 0 or more.';
        }
        $max_uses = null;
        if ($coupon['max_uses'] !== '') {
            $max_uses = filter_var($coupon['max_uses'], FILTER_VALIDATE_INT);
            if ($max_uses === false || $max_uses < 1) {
                $errors['max_uses'] = 'Usage limit must be a positive whole number, or left blank for unlimited.';
            }
        }
        $expires_at = null;
        if ($coupon['expires_at'] !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $coupon['expires_at']);
            if (!$d) {
                $errors['expires_at'] = 'Please enter a valid date.';
            } else {
                $expires_at = $coupon['expires_at'];
            }
        }

        if (empty($errors)) {
            if ($is_edit) {
                $stmt = $pdo->prepare(
                    'UPDATE coupons SET code=?, description=?, discount_type=?, discount_value=?, min_order=?, max_uses=?, expires_at=?, active=? WHERE id=?'
                );
                $stmt->execute([
                    $coupon['code'], $coupon['description'], $coupon['discount_type'], $discount_value,
                    $min_order, $max_uses, $expires_at, $coupon['active'], $coupon['id'],
                ]);
                flash_set('success', 'Coupon updated.');
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO coupons (code, description, discount_type, discount_value, min_order, max_uses, expires_at, active) VALUES (?,?,?,?,?,?,?,?)'
                );
                $stmt->execute([
                    $coupon['code'], $coupon['description'], $coupon['discount_type'], $discount_value,
                    $min_order, $max_uses, $expires_at, $coupon['active'],
                ]);
                flash_set('success', 'Coupon created.');
            }
            redirect('coupons.php');
        }
    }
}

$coupons = $pdo->query('SELECT * FROM coupons ORDER BY created_at DESC')->fetchAll();

$page_title = 'Manage Coupons | Nordic Nest Admin';
$active     = 'admin';
$base       = '../';
require __DIR__ . '/../includes/header.php';
?>

<section class="page-header">
  <div class="container">
    <p class="breadcrumb"><a href="dashboard.php">Admin</a> / Coupons</p>
    <h1>Manage discount coupons</h1>
    <p class="page-subheading">Coupons are validated server-side at checkout — expiry, minimum order and usage limits are all enforced there, never trusted from the browser.</p>
  </div>
</section>

<section class="container section narrow-form">
  <h2><?= $is_edit ? 'Edit coupon' : 'Add a new coupon' ?></h2>
  <?php if (!empty($errors['form'])): ?>
    <div class="alert alert-error" role="alert"><?= h($errors['form']) ?></div>
  <?php endif; ?>

  <form class="contact-form" method="post" action="coupons.php" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= h((string)$coupon['id']) ?>">

    <div class="form-row <?= isset($errors['code']) ? 'has-error' : '' ?>">
      <label for="code">Coupon code <span class="required">*</span></label>
      <input type="text" id="code" name="code" value="<?= h($coupon['code']) ?>" required maxlength="40" placeholder="e.g. WELCOME10">
      <?php if (isset($errors['code'])): ?><p class="field-error"><?= h($errors['code']) ?></p><?php endif; ?>
    </div>

    <div class="form-row">
      <label for="description">Description</label>
      <input type="text" id="description" name="description" value="<?= h($coupon['description'] ?? '') ?>" maxlength="190" placeholder="Shown to admins only, e.g. \"Spring sale\"">
    </div>

    <div class="form-row <?= isset($errors['discount_type']) ? 'has-error' : '' ?>">
      <label for="discount_type">Discount type <span class="required">*</span></label>
      <select id="discount_type" name="discount_type" required>
        <option value="percent" <?= $coupon['discount_type'] === 'percent' ? 'selected' : '' ?>>Percentage off</option>
        <option value="fixed" <?= $coupon['discount_type'] === 'fixed' ? 'selected' : '' ?>>Fixed amount off</option>
      </select>
      <?php if (isset($errors['discount_type'])): ?><p class="field-error"><?= h($errors['discount_type']) ?></p><?php endif; ?>
    </div>

    <div class="form-row <?= isset($errors['discount_value']) ? 'has-error' : '' ?>">
      <label for="discount_value">Discount value <span class="required">*</span></label>
      <input type="number" id="discount_value" name="discount_value" value="<?= h((string)$coupon['discount_value']) ?>" required min="0.01" step="0.01">
      <p class="field-hint">Either a percentage (e.g. 10 = 10% off) or a dollar amount, depending on the type selected above.</p>
      <?php if (isset($errors['discount_value'])): ?><p class="field-error"><?= h($errors['discount_value']) ?></p><?php endif; ?>
    </div>

    <div class="form-row <?= isset($errors['min_order']) ? 'has-error' : '' ?>">
      <label for="min_order">Minimum order (AUD)</label>
      <input type="number" id="min_order" name="min_order" value="<?= h((string)$coupon['min_order']) ?>" min="0" step="0.01">
      <?php if (isset($errors['min_order'])): ?><p class="field-error"><?= h($errors['min_order']) ?></p><?php endif; ?>
    </div>

    <div class="form-row <?= isset($errors['max_uses']) ? 'has-error' : '' ?>">
      <label for="max_uses">Usage limit</label>
      <input type="number" id="max_uses" name="max_uses" value="<?= h((string)($coupon['max_uses'] ?? '')) ?>" min="1" step="1" placeholder="Leave blank for unlimited">
      <?php if (isset($errors['max_uses'])): ?><p class="field-error"><?= h($errors['max_uses']) ?></p><?php endif; ?>
    </div>

    <div class="form-row <?= isset($errors['expires_at']) ? 'has-error' : '' ?>">
      <label for="expires_at">Expiry date</label>
      <input type="date" id="expires_at" name="expires_at" value="<?= h((string)($coupon['expires_at'] ?? '')) ?>">
      <p class="field-hint">Leave blank for a coupon that never expires.</p>
      <?php if (isset($errors['expires_at'])): ?><p class="field-error"><?= h($errors['expires_at']) ?></p><?php endif; ?>
    </div>

    <div class="form-row form-row-checkbox">
      <label for="active">
        <input type="checkbox" id="active" name="active" value="1" <?= (string)$coupon['active'] === '1' ? 'checked' : '' ?>>
        Active (customers can use this code right now)
      </label>
    </div>

    <button type="submit" class="btn btn-primary"><?= $is_edit ? 'Save changes' : 'Add coupon' ?></button>
    <?php if ($is_edit): ?><a class="btn btn-outline" href="coupons.php">Cancel</a><?php endif; ?>
  </form>
</section>

<section class="container section">
  <h2>Existing coupons</h2>
  <?php if (empty($coupons)): ?>
    <p>No coupons yet.</p>
  <?php else: ?>
    <table class="data-table">
      <thead>
        <tr><th scope="col">Code</th><th scope="col">Discount</th><th scope="col">Min order</th><th scope="col">Uses</th><th scope="col">Expires</th><th scope="col">Status</th><th scope="col">Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($coupons as $c): ?>
          <?php
            $expired = $c['expires_at'] !== null && $c['expires_at'] < date('Y-m-d');
            $exhausted = $c['max_uses'] !== null && (int)$c['times_used'] >= (int)$c['max_uses'];
          ?>
          <tr>
            <td><strong><?= h($c['code']) ?></strong><?php if ($c['description']): ?><br><span class="muted"><?= h($c['description']) ?></span><?php endif; ?></td>
            <td><?= $c['discount_type'] === 'percent' ? h((string)(float)$c['discount_value']) . '%' : '$' . number_format((float)$c['discount_value'], 2) ?></td>
            <td><?= (float)$c['min_order'] > 0 ? '$' . number_format((float)$c['min_order'], 2) : '—' ?></td>
            <td><?= (int)$c['times_used'] ?><?= $c['max_uses'] !== null ? ' / ' . (int)$c['max_uses'] : '' ?></td>
            <td><?= $c['expires_at'] ? h(date('d M Y', strtotime($c['expires_at']))) : 'Never' ?></td>
            <td>
              <?php if (!$c['active']): ?>
                <span class="badge badge-danger">Disabled</span>
              <?php elseif ($expired): ?>
                <span class="badge badge-warning">Expired</span>
              <?php elseif ($exhausted): ?>
                <span class="badge badge-warning">Fully used</span>
              <?php else: ?>
                <span class="badge badge-approved">Active</span>
              <?php endif; ?>
            </td>
            <td class="actions">
              <a href="coupons.php?edit=<?= (int)$c['id'] ?>">Edit</a>
              <form method="post" action="coupons.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle_active">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <button type="submit" class="link-button"><?= $c['active'] ? 'Disable' : 'Enable' ?></button>
              </form>
              <form method="post" action="coupons.php" onsubmit="return confirm('Delete this coupon permanently?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
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
