</main>
<footer class="site-footer">
  <div class="container footer-inner">
    <div><a class="brand footer-brand" href="<?= e(app_url('/')) ?>"><img src="<?= e(app_url('/assets/collegekhoj-logo.png')) ?>" alt="Collegekhoj" width="160" height="70"></a>
      <p>Explore education options and make informed decisions. Admission and placement outcomes are determined by institutions and are not guaranteed.</p>
      <p class="footer-contact"><span aria-hidden="true">✉️</span> Help &amp; support: <?= contact_email_link('footer-email') ?></p></div>
    <div><strong>Explore</strong><a href="<?= e(app_url('/colleges.php')) ?>">Colleges</a><a href="<?= e(app_url('/compare.php')) ?>">Compare colleges</a><a href="<?= e(app_url('/page.php?slug=privacy-policy')) ?>">Privacy policy</a><a href="<?= e(app_url('/account.php')) ?>">Student account</a><a href="<?= e(app_url('/admin/login.php')) ?>">Admin</a></div>
  </div>
  <div class="container copyright">© <?= date('Y') ?> Collegekhoj. Verify course details with the official institution.</div>
</footer>
<script src="<?= e(app_url('/assets/portal.js')) ?>" defer></script>
</body>
</html>
