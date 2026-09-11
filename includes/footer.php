  </main>

  <footer class="site-footer">
    <div class="container footer-grid">
      <div>
        <span class="footer-logo">Nordic Nest</span>
        <p>Scandinavian-inspired home goods, sustainably made and built to last.</p>
      </div>
      <div>
        <h4>Shop</h4>
        <ul>
          <li><a href="<?= h($base) ?>shop.php?category=Furniture">Furniture</a></li>
          <li><a href="<?= h($base) ?>shop.php?category=Lighting">Lighting</a></li>
          <li><a href="<?= h($base) ?>shop.php?category=Textiles">Textiles</a></li>
          <li><a href="<?= h($base) ?>shop.php?category=Kitchen">Kitchen</a></li>
        </ul>
      </div>
      <div>
        <h4>Company</h4>
        <ul>
          <li><a href="<?= h($base) ?>about.php">About us</a></li>
          <li><a href="<?= h($base) ?>gallery.php">Gallery</a></li>
          <li><a href="<?= h($base) ?>testimonials.php">Testimonials</a></li>
          <li><a href="<?= h($base) ?>contact.php">Contact</a></li>
          <li><a href="<?= h($base) ?>privacy.php">Privacy notice</a></li>
        </ul>
      </div>
      <div>
        <h4>Get in touch</h4>
        <ul>
          <li><a href="mailto:hello@nordicnest.example">hello@nordicnest.example</a></li>
          <li>(02) 8000 1234</li>
        </ul>
      </div>
    </div>
    <div class="container footer-bottom">
      <p>&copy; <?= date('Y') ?> Nordic Nest. All rights reserved.</p>
      <p>Coursework prototype for ICT726 Web Development — Assignment 4.</p>
    </div>
  </footer>

  <script src="<?= h($base) ?>js/script.js"></script>
</body>
</html>
