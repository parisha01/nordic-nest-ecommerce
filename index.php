<?php
require_once __DIR__ . '/includes/functions.php';

// ---- Newsletter sign-up form (writes to the database) ----
$newsletter_error = null;
$newsletter_success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'newsletter_signup') {
    if (!csrf_check()) {
        $newsletter_error = 'Your session expired. Please try again.';
    } else {
        $email = clean($_POST['newsletter_email'] ?? '');
        if ($email === '' || !is_valid_email($email)) {
            $newsletter_error = 'Please enter a valid email address.';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM newsletter_subscribers WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $newsletter_error = 'That email is already subscribed — thank you!';
            } else {
                $stmt = $pdo->prepare('INSERT INTO newsletter_subscribers (email) VALUES (?)');
                $stmt->execute([$email]);
                $newsletter_success = 'You\'re subscribed! Watch your inbox for restocks and new arrivals.';
            }
        }
    }
}

// ---- Featured products: latest 4 added by admin ----
$stmt = $pdo->query('SELECT id, name, category, price, description, image_url FROM products ORDER BY created_at DESC LIMIT 4');
$featured_products = $stmt->fetchAll();

// ---- One approved testimonial for the quote band ----
$stmt = $pdo->query(
    "SELECT t.comment, u.full_name FROM testimonials t
     JOIN users u ON u.id = t.user_id
     WHERE t.status = 'approved'
     ORDER BY t.created_at DESC LIMIT 1"
);
$featured_review = $stmt->fetch();

$page_title       = 'Nordic Nest | Scandinavian-Inspired Home Goods, Sustainably Made';
$meta_description = 'Shop minimalist furniture, lighting, textiles and kitchenware made from sustainable oak, wool and stoneware. Nordic Nest — furnish a calmer home.';
$active           = 'home';
$base             = '';
require __DIR__ . '/includes/header.php';
?>

<section class="hero">
  <div class="container hero-grid">
    <div>
      <p class="eyebrow">Est. 2021 · Sustainably made</p>
      <h1>Furnish a calmer home.</h1>
      <p>Nordic Nest is an online store for thoughtfully designed home goods inspired by Scandinavian simplicity. We curate minimalist furniture, textiles, and decor crafted from sustainable materials, helping you build calm, beautiful spaces you'll love coming home to.</p>
      <div class="hero-actions">
        <a class="btn btn-primary" href="shop.php">Shop the collection</a>
        <a class="btn btn-outline" href="about.php">Our story</a>
      </div>
    </div>
    <div class="hero-media">
      <img src="https://picsum.photos/seed/nordic-hero/900/700" alt="A softly lit living room styled with minimalist wooden furniture and linen textiles" loading="lazy">
    </div>
  </div>
</section>

<section class="container section">
  <p class="eyebrow center">On the shelf this week</p>
  <h2 class="center">New arrivals</h2>
  <p class="section-intro center">A small edit of new pieces, chosen for how they feel in the hand as much as how they look on a shelf.</p>

  <div class="shelf-row">
    <?php foreach ($featured_products as $p): ?>
      <article class="card">
        <img src="<?= h($p['image_url']) ?>" alt="<?= h($p['name']) ?>" loading="lazy">
        <div class="card-body">
          <span class="card-tag"><?= h($p['category']) ?></span>
          <h3><a href="product.php?id=<?= (int)$p['id'] ?>"><?= h($p['name']) ?></a></h3>
          <p><?= h($p['description']) ?></p>
          <p class="card-price">$<?= number_format((float)$p['price'], 2) ?></p>
        </div>
      </article>
    <?php endforeach; ?>
  </div>

  <p class="center"><a class="btn btn-outline" href="shop.php">View full shop</a></p>
</section>

<section class="split">
  <div class="container" style="display:grid; grid-template-columns:1fr 1fr; gap:3rem; align-items:center;">
    <div>
      <p class="eyebrow">Why Nordic Nest</p>
      <h2>Objects made to be used, not just owned.</h2>
      <p>Every piece we sell is chosen for durability and honest materials — wood, wool, clay, linen — so your home fills up with things that earn their place rather than clutter.</p>
    </div>
    <div>
      <img src="https://picsum.photos/seed/nordic-values/700/500" alt="Close-up of a hand-thrown ceramic bowl and a linen cushion on a wooden bench" loading="lazy">
    </div>
  </div>
</section>

<?php if ($featured_review): ?>
<section class="band-dark">
  <div class="container">
    <p class="eyebrow center">What customers say</p>
    <blockquote class="pull-quote">
      "<?= h($featured_review['comment']) ?>"
      <cite>— <?= h($featured_review['full_name']) ?></cite>
    </blockquote>
    <p class="center"><a class="btn btn-outline" href="testimonials.php">Read more stories</a></p>
  </div>
</section>
<?php endif; ?>

<section class="newsletter">
  <div class="container newsletter-grid">
    <div>
      <h2>Join the nest</h2>
      <p>Restocks, new arrivals and 10% off your first order — straight to your inbox.</p>
    </div>
    <form method="post" action="index.php#newsletter" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="newsletter_signup">
      <label for="newsletter_email" class="visually-hidden">Email address</label>
      <input type="email" id="newsletter_email" name="newsletter_email" placeholder="you@example.com" required>
      <button type="submit" class="btn btn-primary">Subscribe</button>
    </form>
  </div>
  <?php if ($newsletter_success): ?><div class="container"><p class="alert alert-success" role="status"><?= h($newsletter_success) ?></p></div><?php endif; ?>
  <?php if ($newsletter_error): ?><div class="container"><p class="alert alert-error" role="alert"><?= h($newsletter_error) ?></p></div><?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
