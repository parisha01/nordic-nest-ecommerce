<?php
require_once __DIR__ . '/includes/functions.php';

$errors = [];
$old = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];
$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors['form'] = 'Your session expired. Please try again.';
    } else {
        $old['name']    = clean($_POST['name'] ?? '');
        $old['email']   = clean($_POST['email'] ?? '');
        $old['subject'] = clean($_POST['subject'] ?? '');
        $old['message'] = clean($_POST['message'] ?? '');

        if ($old['name'] === '') {
            $errors['name'] = 'Please enter your name.';
        }
        if ($old['email'] === '' || !is_valid_email($old['email'])) {
            $errors['email'] = 'Please enter a valid email address.';
        }
        $valid_subjects = ['General enquiry', 'Order question', 'Wholesale', 'Something else'];
        if (!in_array($old['subject'], $valid_subjects, true)) {
            $errors['subject'] = 'Please choose a subject.';
        }
        if ($old['message'] === '') {
            $errors['message'] = 'Please write a message.';
        } elseif (mb_strlen($old['message']) < 10) {
            $errors['message'] = 'Message must be at least 10 characters.';
        } elseif (mb_strlen($old['message']) > 1000) {
            $errors['message'] = 'Message must be under 1000 characters.';
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare(
                'INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$old['name'], $old['email'], $old['subject'], $old['message']]);
            $submitted = true;
            $old = ['name' => '', 'email' => '', 'subject' => '', 'message' => '']; // clear form on success
        }
    }
}

$page_title       = 'Contact Us | Nordic Nest';
$meta_description = 'Get in touch with Nordic Nest about an order, a product question, or a wholesale enquiry.';
$active           = 'contact';
$base             = '';
require __DIR__ . '/includes/header.php';
?>

<section class="page-header">
  <div class="container">
    <p class="breadcrumb"><a href="index.php">Home</a> / Contact</p>
    <h1>Get in touch</h1>
    <p class="page-subheading">Questions about a product, an order, or a wholesale enquiry — we'd love to hear from you.</p>
  </div>
</section>

<section class="container contact-layout">
  <div class="contact-info">
    <h2>Visit or write to us</h2>
    <p>Our studio and small showroom are open Tuesday–Saturday. Can't make it in? The form is the fastest way to reach the team.</p>
    <ul class="contact-list">
      <li>📍 14 Birchwood Lane, Surry Hills NSW 2010, Australia</li>
      <li>✉️ <a href="mailto:hello@nordicnest.example">hello@nordicnest.example</a></li>
      <li>📞 (02) 8000 1234</li>
      <li>🕒 Tue–Sat, 10am–5pm AEST</li>
    </ul>
  </div>

  <div>
    <h2>Send us a message</h2>

    <div aria-live="polite">
      <?php if ($submitted): ?>
        <div class="alert alert-success" role="status">Thanks — your message has been sent. We'll reply within 1–2 business days.</div>
      <?php elseif (!empty($errors['form'])): ?>
        <div class="alert alert-error" role="alert"><?= h($errors['form']) ?></div>
      <?php endif; ?>
    </div>

    <form class="contact-form" method="post" action="contact.php" novalidate>
      <?= csrf_field() ?>

      <div class="form-row <?= isset($errors['name']) ? 'has-error' : '' ?>">
        <label for="name">Full name <span class="required">*</span></label>
        <input type="text" id="name" name="name" value="<?= h($old['name']) ?>" required maxlength="120">
        <?php if (isset($errors['name'])): ?><p class="field-error"><?= h($errors['name']) ?></p><?php endif; ?>
      </div>

      <div class="form-row <?= isset($errors['email']) ? 'has-error' : '' ?>">
        <label for="email">Email address <span class="required">*</span></label>
        <input type="email" id="email" name="email" value="<?= h($old['email']) ?>" required>
        <?php if (isset($errors['email'])): ?><p class="field-error"><?= h($errors['email']) ?></p><?php endif; ?>
      </div>

      <div class="form-row <?= isset($errors['subject']) ? 'has-error' : '' ?>">
        <label for="subject">Subject <span class="required">*</span></label>
        <select id="subject" name="subject" required>
          <option value="">Please select...</option>
          <?php foreach (['General enquiry', 'Order question', 'Wholesale', 'Something else'] as $s): ?>
            <option value="<?= h($s) ?>" <?= $old['subject'] === $s ? 'selected' : '' ?>><?= h($s) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if (isset($errors['subject'])): ?><p class="field-error"><?= h($errors['subject']) ?></p><?php endif; ?>
      </div>

      <div class="form-row <?= isset($errors['message']) ? 'has-error' : '' ?>">
        <label for="message">Message <span class="required">*</span></label>
        <textarea id="message" name="message" required minlength="10" maxlength="1000"><?= h($old['message']) ?></textarea>
        <?php if (isset($errors['message'])): ?><p class="field-error"><?= h($errors['message']) ?></p><?php endif; ?>
      </div>

      <button type="submit" class="btn btn-primary">Send message</button>
    </form>
  </div>
</section>

<section class="container">
  <iframe title="Nordic Nest studio location map" class="map-embed" loading="lazy"
    src="https://www.openstreetmap.org/export/embed.html?bbox=151.205%2C-33.895%2C151.220%2C-33.880&layer=mapnik"></iframe>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
