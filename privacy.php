<?php
require_once __DIR__ . '/includes/functions.php';

$page_title       = 'Privacy Notice | Nordic Nest';
$meta_description = 'How Nordic Nest collects, uses and protects your personal information.';
$active           = 'privacy';
$base             = '';
require __DIR__ . '/includes/header.php';
?>

<section class="page-header">
  <div class="container">
    <p class="breadcrumb"><a href="index.php">Home</a> / Privacy notice</p>
    <h1>Privacy notice</h1>
    <p class="page-subheading">Last updated: <?= date('F Y') ?>. This is a coursework prototype — the practices below describe how this demo application is built, not a live commercial service.</p>
  </div>
</section>

<section class="container section narrow-form privacy-content">
  <h2>What we collect</h2>
  <ul>
    <li><strong>Account details:</strong> your name, email address and a securely hashed password when you register.</li>
    <li><strong>Reviews:</strong> the star rating and comment text you submit, linked to your account.</li>
    <li><strong>Contact form messages:</strong> your name, email address, subject and message when you contact us.</li>
    <li><strong>Newsletter sign-up:</strong> just your email address, if you choose to subscribe.</li>
  </ul>

  <h2>How we use it</h2>
  <ul>
    <li>To create and manage your account and let you log in.</li>
    <li>To display your product reviews publicly once approved (only your name and review are shown — never your email or password).</li>
    <li>To respond to enquiries submitted through the contact form.</li>
    <li>To send occasional emails to newsletter subscribers about new arrivals and restocks.</li>
  </ul>

  <h2>How we protect it</h2>
  <ul>
    <li>Passwords are never stored as plain text — they are hashed using PHP's <code>password_hash()</code> (bcrypt) before being saved to the database.</li>
    <li>All database queries use parameterised prepared statements to prevent SQL injection.</li>
    <li>All forms are protected against cross-site request forgery (CSRF) with a per-session token.</li>
    <li>Access to admin-only areas (managing products, moderating reviews, viewing enquiries) is restricted by role-based access control enforced on every request, not just hidden in the menu.</li>
    <li>Session cookies are set as HTTP-only and SameSite to reduce the risk of session theft via cross-site scripts.</li>
  </ul>

  <h2>Your rights</h2>
  <p>You can update your name, email and password at any time from <a href="profile.php">My account</a>. Since this is a university coursework prototype rather than a live business, no real personal data should be submitted — please use fictional details when testing forms. If this were a live service, users could request access to, correction of, or deletion of their data by contacting us at the address in the footer.</p>

  <h2>Cookies</h2>
  <p>This site uses a single session cookie required to keep you logged in. It does not use third-party advertising or tracking cookies.</p>

  <h2>No real data, ever</h2>
  <p>This website was built for an academic assignment (ICT726 Web Development). Please do not submit real personal information, real payment details, or real passwords you use elsewhere — treat every field on this site as a sandbox.</p>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
