<!-- ===== FOOTER ===== -->
<footer id="contact">
  <div class="footer-grid">
    <div class="footer-brand">
      <div class="footer-logo">ShirtCraft</div>
      <p class="footer-desc">Nigeria's premier custom clothing brand. Design, preview in 3D, and order custom shirts, polos, and hoodies delivered right to your doorstep.</p>
      <div class="footer-socials">
        <a href="#" class="social-btn" title="Facebook">f</a>
        <a href="#" class="social-btn" title="Instagram">in</a>
        <a href="#" class="social-btn" title="Twitter/X">𝕏</a>
        <a href="#" class="social-btn" title="WhatsApp">W</a>
      </div>
    </div>
    <div class="footer-col">
      <h4>Quick Links</h4>
      <ul>
        <li><a href="designer.php">Design Studio</a></li>
        <li><a href="auth.php">Sign In / Register</a></li>
        <li><a href="my_orders.php">My Orders</a></li>
        <li><a href="index.php#how-it-works">How It Works</a></li>
        <li><a href="index.php#features">Features</a></li>
      </ul>
    </div>
    <div class="footer-col">
      <h4>Customer</h4>
      <ul>
        <li><a href="my_orders.php">Track My Order</a></li>
        <li><a href="#">Size Guide</a></li>
        <li><a href="#">Return Policy</a></li>
        <li><a href="#">FAQ</a></li>
      </ul>
    </div>
    <div class="footer-col">
      <h4>Contact</h4>
      <ul>
        <li><a href="tel:+2348000000000">+234 800 000 0000</a></li>
        <li><a href="mailto:hello@stallioncustom.ng">hello@stallioncustom.ng</a></li>
        <li><a href="#">WhatsApp Us</a></li>
        <li>Mon–Fri: 8am – 6pm</li>
      </ul>
    </div>
  </div>
  <div class="footer-bottom">
    <p>© <?= date('Y') ?> ShirtCraft. All rights reserved.</p>
    <div style="display:flex;gap:16px">
      <a href="#">Privacy Policy</a>
      <a href="#">Terms of Service</a>
    </div>
  </div>
</footer>

<script src="assets/vendor/jquery.min.js"></script>
<script src="assets/js/app.js"></script>
<?php foreach (($extra_js ?? []) as $js): ?>
<script src="<?= h($js) ?>"></script>
<?php endforeach; ?>
</body>
</html>
