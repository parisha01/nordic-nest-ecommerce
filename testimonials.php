<?php
require_once __DIR__ . '/includes/functions.php';

$errors = [];
$old = ['rating' => '', 'comment' => '', 'product_id' => ''];

// ---- Handle new review submission (members only) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_review') {
    require_login(); // redirects to login.php if not authenticated

    if (!csrf_check()) {
        $errors['form'] = 'Your session expired. Please try again.';
    } else {
        $old['rating']     = clean($_POST['rating'] ?? '');
        $old['comment']    = clean($_POST['comment'] ?? '');
        $old['product_id'] = clean($_POST['product_id'] ?? '');

        $rating = filter_var($old['rating'], FILTER_VALIDATE_INT);
        if ($rating === false || $rating < 1 || $rating > 5) {
            $errors['rating'] = 'Please choose a rating between 1 and 5.';
        }

        if ($old['comment'] === '') {
            $errors['comment'] = 'Please write a short review.';
        } elseif (mb_strlen($old['comment']) < 10) {
            $errors['comment'] = 'Reviews must be at least 10 characters.';
        } elseif (mb_strlen($old['comment']) > 600) {
            $errors['comment'] = 'Reviews must be under 600 characters.';
        }

        $product_id = $old['product_id'] !== '' ? filter_var($old['product_id'], FILTER_VALIDATE_INT) : null;
        if ($old['product_id'] !== '' && $product_id === false) {
            $errors['product_id'] = 'Please choose a valid product.';
        }

        if (empty($errors)) {
            $user = current_user();
            // NEW: Determined here, server-side, from actual order history —
            // never trusted from the submission form.
            $isVerified = $product_id ? (int)user_has_purchased_product($pdo, (int)$user['id'], $product_id) : 0;
            $stmt = $pdo->prepare(
                'INSERT INTO testimonials (user_id, product_id, is_verified_purchase, rating, comment, status) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$user['id'], $product_id ?: null, $isVerified, $rating, $old['comment'], 'pending']);

            flash_set('success', 'Thanks for your review! It will appear once our team approves it.');
            redirect('testimonials.php');
        }
    }
}

// ---- Approved testimonials for public display ----
$stmt = $pdo->query(
    "SELECT t.rating, t.comment, t.created_at, t.is_verified_purchase, u.full_name, p.name AS product_name
     FROM testimonials t
     JOIN users u ON u.id = t.user_id
     LEFT JOIN products p ON p.id = t.product_id
     WHERE t.status = 'approved'
     ORDER BY t.created_at DESC"
);
$approved_reviews = $stmt->fetchAll();

// Product list for the "which product" dropdown.
$products_list = $pdo->query('SELECT id, name FROM products ORDER BY name')->fetchAll();

$page_title       = 'Customer Reviews & Testimonials | Nordic Nest';
$meta_description = 'Read real customer reviews of Nordic Nest\'s sustainably made furniture, lighting, textiles and kitchenware.';
$active           = 'testimonials';
$base             = '';
require __DIR__ . '/includes/header.php';
?>

<section class="page-header">
  <div class="container">
    <p class="breadcrumb"><a href="index.php">Home</a> / Testimonials</p>
    <h1>Stories from our customers</h1>
    <p class="page-subheading">A few words from people who've made Nordic Nest part of their everyday.</p>
  </div>
</section>

<section class="container section">
  <?php if (empty($approved_reviews)): ?>
    <p>No reviews yet — be the first to share your experience.</p>
  <?php else: ?>
    <div class="testimonial-grid">
      <?php foreach ($approved_reviews as $r): ?>
        <article class="testimonial-card">
          <p aria-label="Rated <?= (int)$r['rating'] ?> out of 5">
            <?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?>
          </p>
          <p>"<?= h($r['comment']) ?>"</p>
          <cite>— <?= h($r['full_name']) ?><?= $r['product_name'] ? ', on the ' . h($r['product_name']) : '' ?></cite>
          <?php if ($r['is_verified_purchase']): ?>
            <p class="badge badge-approved" title="This reviewer bought this exact product through this store.">✓ Verified Purchase</p>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<section class="container section narrow-form">
  <h2>Leave a review</h2>

  <?php if (!is_logged_in()): ?>
    <p>Please <a href="login.php">log in</a> or <a href="register.php">create a free account</a> to leave a review.</p>
  <?php else: ?>
    <?php if (!empty($errors['form'])): ?>
      <div class="alert alert-error" role="alert"><?= h($errors['form']) ?></div>
    <?php endif; ?>

    <form class="contact-form" method="post" action="testimonials.php#leave-a-review" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="submit_review">

      <div class="form-row <?= isset($errors['product_id']) ? 'has-error' : '' ?>">
        <label for="product_id">Which product? (optional)</label>
        <select id="product_id" name="product_id">
          <option value="">General feedback</option>
          <?php foreach ($products_list as $p): ?>
            <option value="<?= (int)$p['id'] ?>" <?= $old['product_id'] == $p['id'] ? 'selected' : '' ?>><?= h($p['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-row <?= isset($errors['rating']) ? 'has-error' : '' ?>">
        <label for="rating">Rating <span class="required">*</span></label>
        <select id="rating" name="rating" required>
          <option value="">Please select...</option>
          <?php for ($i = 5; $i >= 1; $i--): ?>
            <option value="<?= $i ?>" <?= $old['rating'] === (string)$i ? 'selected' : '' ?>><?= $i ?> star<?= $i === 1 ? '' : 's' ?></option>
          <?php endfor; ?>
        </select>
        <?php if (isset($errors['rating'])): ?><p class="field-error"><?= h($errors['rating']) ?></p><?php endif; ?>
      </div>

      <div class="form-row <?= isset($errors['comment']) ? 'has-error' : '' ?>">
        <label for="comment">Your review <span class="required">*</span></label>
        <textarea id="comment" name="comment" required minlength="10" maxlength="600"><?= h($old['comment']) ?></textarea>
        <?php if (isset($errors['comment'])): ?><p class="field-error"><?= h($errors['comment']) ?></p><?php endif; ?>
      </div>

      <button type="submit" class="btn btn-primary">Submit review</button>
      <p class="form-footnote muted">Reviews are checked by our team before they appear publicly.</p>
    </form>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
