<?php
/**
 * includes/header.php
 * -----------------------------------------------------------
 * Shared page head + site header/nav.
 *
 * Every page must set these variables BEFORE including this
 * file:
 *   $page_title        - unique <title> (SEO)
 *   $meta_description  - unique meta description (SEO)
 *   $active             - nav key to mark aria-current ('home','shop', etc.)
 *   $base              - relative path prefix to the site root
 *                        ('' for top-level pages, '../' from /admin)
 * -----------------------------------------------------------
 */
$active            = $active ?? '';
$base              = $base ?? '';
$page_title        = $page_title ?? 'Nordic Nest';
$meta_description  = $meta_description ?? 'Nordic Nest — Scandinavian-inspired home goods, sustainably made and built to last.';
$user              = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- SEO: unique per-page title + description -->
  <title><?= h($page_title) ?></title>
  <meta name="description" content="<?= h($meta_description) ?>">
  <link rel="canonical" href="<?= h(($_SERVER['REQUEST_URI'] ?? '')) ?>">
  <meta property="og:title" content="<?= h($page_title) ?>">
  <meta property="og:description" content="<?= h($meta_description) ?>">
  <meta property="og:type" content="website">
  <meta name="robots" content="index, follow">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= h($base) ?>css/style.css">
</head>
<body>
  <a class="skip-link" href="#main">Skip to content</a>

  <header class="site-header">
    <div class="nav-wrap container">
      <a class="logo" href="<?= h($base) ?>index.php">Nordic<span>Nest</span></a>

      <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="main-nav" aria-label="Toggle navigation menu">
        <span aria-hidden="true">&#9776;</span>
      </button>

      <nav class="main-nav" id="main-nav" aria-label="Primary">
        <ul>
          <li><a href="<?= h($base) ?>index.php"        <?= $active === 'home'         ? 'aria-current="page"' : '' ?>>Home</a></li>
          <li><a href="<?= h($base) ?>shop.php"          <?= $active === 'shop'         ? 'aria-current="page"' : '' ?>>Shop</a></li>
          <li><a href="<?= h($base) ?>about.php"         <?= $active === 'about'        ? 'aria-current="page"' : '' ?>>About</a></li>
          <li><a href="<?= h($base) ?>gallery.php"       <?= $active === 'gallery'      ? 'aria-current="page"' : '' ?>>Gallery</a></li>
          <li><a href="<?= h($base) ?>testimonials.php"  <?= $active === 'testimonials' ? 'aria-current="page"' : '' ?>>Testimonials</a></li>
          <li><a href="<?= h($base) ?>contact.php"       <?= $active === 'contact'      ? 'aria-current="page"' : '' ?>>Contact</a></li>
          <li>
            <a href="<?= h($base) ?>cart.php" class="cart-link" <?= $active === 'cart' ? 'aria-current="page"' : '' ?>>
              Cart<?php $cart_count = cart_count(); if ($cart_count > 0): ?>
                <span class="cart-count" aria-label="<?= $cart_count ?> item<?= $cart_count === 1 ? '' : 's' ?> in cart"><?= $cart_count ?></span>
              <?php endif; ?>
            </a>
          </li>
          <?php if ($user): ?>
            <?php if ($user['role'] === 'admin'): ?>
              <li><a href="<?= h($base) ?>admin/dashboard.php" <?= $active === 'admin' ? 'aria-current="page"' : '' ?>>Admin</a></li>
            <?php endif; ?>
            <li>
              <a href="<?= h($base) ?>wishlist.php" <?= $active === 'wishlist' ? 'aria-current="page"' : '' ?>>
                Wishlist<?php $wishlist_count = wishlist_count($pdo, $user['id']); if ($wishlist_count > 0): ?>
                  <span class="cart-count" aria-label="<?= $wishlist_count ?> item<?= $wishlist_count === 1 ? '' : 's' ?> saved"><?= $wishlist_count ?></span>
                <?php endif; ?>
              </a>
            </li>
            <li><a href="<?= h($base) ?>profile.php" <?= $active === 'profile' ? 'aria-current="page"' : '' ?>>My account</a></li>
            <li><a href="<?= h($base) ?>logout.php">Log out</a></li>
          <?php else: ?>
            <li><a href="<?= h($base) ?>login.php"    <?= $active === 'login'    ? 'aria-current="page"' : '' ?>>Log in</a></li>
            <li><a href="<?= h($base) ?>register.php" <?= $active === 'register' ? 'aria-current="page"' : '' ?>>Register</a></li>
          <?php endif; ?>
        </ul>
      </nav>
    </div>
  </header>

  <main id="main">
    <?php
    // Site-wide flash messages (success / error), shown once after a redirect.
    $flash_success = flash_get('success');
    $flash_error   = flash_get('error');
    ?>
    <?php if ($flash_success): ?>
      <div class="alert alert-success container" role="status"><?= h($flash_success) ?></div>
    <?php endif; ?>
    <?php if ($flash_error): ?>
      <div class="alert alert-error container" role="alert"><?= h($flash_error) ?></div>
    <?php endif; ?>
