<?php
$siteSettings = site_settings();
$contactPhone = trim($siteSettings['contact_phone']);
$contactEmailAddress = trim($siteSettings['contact_email']);
$whatsappLink = whatsapp_url();
$officeHours = trim($siteSettings['office_hours']);
$officeDays = trim($siteSettings['office_days']);
$officeAddress = trim($siteSettings['office_address']);
?>
</main>
<footer class="site-footer">
  <div class="container footer-inner">
    <div><a class="brand footer-brand" href="<?= e(app_url('/')) ?>"><img src="<?= e(app_url('/assets/collegekhoj-logo.png')) ?>" alt="Collegekhoj" width="160" height="70"></a>
      <p>Explore education options and make informed decisions. Admission and placement outcomes are determined by institutions and are not guaranteed.</p>
    </div>
    <div class="footer-office"><strong>Contact Us</strong>
      <ul>
        <?php if ($officeHours !== '' || $officeDays !== ''): ?><li><?= ui_icon('clock') ?><span><small>Opening Hours</small><?= e($officeHours) ?><?php if ($officeHours !== '' && $officeDays !== ''): ?><br><?php endif; ?><?= e($officeDays) ?></span></li><?php endif; ?>
        <?php if ($contactEmailAddress !== ''): ?><li><?= ui_icon('mail') ?><span><small>Email</small><a class="footer-email" href="mailto:<?= e($contactEmailAddress) ?>"><?= e($contactEmailAddress) ?></a></span></li><?php endif; ?>
        <?php if ($contactPhone !== ''): ?><li><?= ui_icon('phone') ?><span><small>Phone</small><a class="footer-email" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $contactPhone)) ?>"><?= e($contactPhone) ?></a></span></li><?php endif; ?>
        <?php if ($officeAddress !== ''): ?><li><?= ui_icon('map-pin') ?><span><small>Address</small><?= nl2br(e($officeAddress)) ?></span></li><?php endif; ?>
      </ul>
    </div>
    <div><strong>Explore</strong><a href="<?= e(app_url('/#courses')) ?>">Courses</a><a href="<?= e(app_url('/#universities')) ?>">Universities</a><a href="<?= e(app_url('/?counsel=1')) ?>" data-counselling>Free counselling</a><a href="<?= e(app_url('/page.php?slug=privacy-policy')) ?>">Privacy policy</a><a href="<?= e(app_url('/admin/login.php')) ?>">Admin</a></div>
  </div>
  <div class="container copyright">© <?= date('Y') ?> Collegekhoj. Verify course details with the official institution.</div>
</footer>

<div class="ck-modal" id="expert-modal" role="dialog" aria-modal="true" aria-labelledby="expert-modal-title" hidden>
  <div class="ck-modal-backdrop" data-close-modal></div>
  <div class="ck-modal-box expert-box">
    <button class="ck-modal-close" type="button" data-close-modal aria-label="Close"><?= ui_icon('close') ?></button>
    <div class="expert-avatar" aria-hidden="true">🧑‍💼</div>
    <h2 id="expert-modal-title"><?= e($siteSettings['expert_title']) ?></h2>
    <?php if ($siteSettings['expert_subtitle'] !== ''): ?><p class="expert-sub"><?= e($siteSettings['expert_subtitle']) ?></p><?php endif; ?>
    <ul class="expert-contacts">
      <?php if ($contactPhone !== ''): ?><li><span class="expert-ico phone" aria-hidden="true"><?= ui_icon('phone') ?></span>Call us: <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $contactPhone)) ?>"><?= e($contactPhone) ?></a></li><?php endif; ?>
      <?php if ($contactEmailAddress !== ''): ?><li><span class="expert-ico mail" aria-hidden="true"><?= ui_icon('mail') ?></span>Email: <a href="mailto:<?= e($contactEmailAddress) ?>"><?= e($contactEmailAddress) ?></a></li><?php endif; ?>
      <?php if ($officeHours !== '' || $officeDays !== ''): ?><li><span class="expert-ico clock" aria-hidden="true"><?= ui_icon('clock') ?></span><?= e(trim($officeHours . ', ' . $officeDays, ', ')) ?></li><?php endif; ?>
      <?php if ($officeAddress !== ''): ?><li><span class="expert-ico pin" aria-hidden="true"><?= ui_icon('map-pin') ?></span><?= e($officeAddress) ?></li><?php endif; ?>
      <?php if ($whatsappLink): ?><li><span class="expert-ico wa" aria-hidden="true"><?= ui_icon('whatsapp') ?></span>WhatsApp: <a href="<?= e($whatsappLink) ?>" target="_blank" rel="noopener">Chat with us</a></li><?php endif; ?>
    </ul>
    <button class="button button-cta" type="button" data-open-modal="counselling-modal"><?= e($siteSettings['expert_button_label']) ?> <span aria-hidden="true">→</span></button>
  </div>
</div>

<div class="ck-modal" id="counselling-modal" role="dialog" aria-modal="true" aria-labelledby="counselling-modal-title" hidden>
  <div class="ck-modal-backdrop" data-close-modal></div>
  <div class="ck-modal-box counsel-box">
    <button class="ck-modal-close" type="button" data-close-modal aria-label="Close"><?= ui_icon('close') ?></button>
    <h2 id="counselling-modal-title"><?= e($siteSettings['form_title']) ?></h2>
    <form class="counsel-form" method="post" action="<?= e(app_url('/lead.php')) ?>" novalidate>
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="source" value="">
      <div class="counsel-trap" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
      <input type="text" name="name" placeholder="Full Name*" aria-label="Full name" required maxlength="120" autocomplete="name">
      <input type="email" name="email" placeholder="Email Address*" aria-label="Email address" required maxlength="190" autocomplete="email">
      <input type="tel" name="phone" placeholder="Phone Number*" aria-label="Phone number" required maxlength="16" inputmode="tel" autocomplete="tel" pattern="[+]?[0-9\s\-]{10,16}">
      <input type="text" name="city" placeholder="Your City*" aria-label="Your city" required maxlength="100" autocomplete="address-level2">
      <select name="course_id" aria-label="Select course"><option value="">Select Course</option>
        <?php foreach ($navCourses ?? [] as $formCourse): ?><option value="<?= (int) $formCourse['id'] ?>"><?= e($formCourse['name']) ?></option><?php endforeach; ?>
      </select>
      <select name="university_id" aria-label="Select university"><option value="">Select University</option>
        <?php foreach ($navUniversities ?? [] as $formUniversity): ?><option value="<?= (int) $formUniversity['id'] ?>" data-courses="<?= e((string) $formUniversity['course_ids']) ?>"><?= e($formUniversity['name']) ?></option><?php endforeach; ?>
      </select>
      <label class="counsel-consent"><input type="checkbox" name="consent" value="1" checked required><span><?= e($siteSettings['form_consent_text']) ?></span></label>
      <p class="counsel-message" role="alert" hidden></p>
      <button class="button button-cta counsel-submit" type="submit"><?= e($siteSettings['form_button_label']) ?> <span aria-hidden="true">→</span></button>
    </form>
    <div class="counsel-success" hidden><div class="counsel-success-icon" aria-hidden="true">✓</div><p></p><button class="button button-light" type="button" data-close-modal>Close</button></div>
  </div>
</div>

<?php if ($siteSettings['side_tab_enabled'] === '1'): ?>
<button class="side-tab" type="button" data-open-modal="counselling-modal">
  <span class="side-tab-dot" aria-hidden="true"></span>
  <span class="side-tab-icon" aria-hidden="true"><?= ui_icon('phone') ?></span>
  <span class="side-tab-label"><?= e($siteSettings['side_tab_label']) ?></span>
  <?php if ($siteSettings['side_tab_badge'] !== ''): ?><span class="side-tab-badge"><?= e($siteSettings['side_tab_badge']) ?></span><?php endif; ?>
</button>
<?php endif; ?>
<div class="float-actions">
  <?php if ($siteSettings['back_to_top_enabled'] === '1'): ?><button class="float-btn back-to-top" type="button" aria-label="Back to top" hidden><?= ui_icon('arrow-up') ?></button><?php endif; ?>
  <?php if ($whatsappLink): ?><a class="float-btn whatsapp-btn" href="<?= e($whatsappLink) ?>" target="_blank" rel="noopener" aria-label="Chat with us on WhatsApp"><?= ui_icon('whatsapp') ?></a><?php endif; ?>
</div>
<script src="<?= e(app_url('/assets/portal.js')) ?>" defer></script>
</body>
</html>
