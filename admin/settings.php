<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();
$pdo = db();

// [key, label, max length, input type, help]; type 'toggle' is a checkbox, 'textarea' a multi-line field.
$sections = [
    'Contact details' => [
        ['contact_phone', 'Phone number', 30, 'text', 'Shown in the Talk to Expert popup and footer, e.g. 092056 92942. Leave empty to hide.'],
        ['contact_email', 'Email address', 190, 'email', 'Shown in the Talk to Expert popup and footer. Leave empty to hide.'],
        ['office_hours', 'Opening hours', 60, 'text', 'e.g. 09:00 AM - 5:00 PM. Leave empty to hide.'],
        ['office_days', 'Opening days', 60, 'text', 'e.g. Monday - Saturday.'],
        ['office_address', 'Office address', 300, 'textarea', 'Shown in the footer and the Talk to Expert popup. Leave empty to hide.'],
    ],
    'Header top bar' => [
        ['topbar_enabled', 'Show the top bar above the header', 1, 'toggle', ''],
        ['topbar_phone', 'Phone number', 30, 'text', 'Leave empty to hide.'],
        ['topbar_email', 'Email address', 190, 'email', 'Leave empty to hide.'],
        ['topbar_tagline', 'Tagline', 120, 'text', 'Shown before the social icons. Leave empty to hide.'],
        ['social_facebook', 'Facebook URL', 255, 'url', 'Each social icon is shown only when its URL is set.'],
        ['social_instagram', 'Instagram URL', 255, 'url', ''],
        ['social_linkedin', 'LinkedIn URL', 255, 'url', ''],
        ['social_x', 'X (Twitter) URL', 255, 'url', ''],
        ['social_youtube', 'YouTube URL', 255, 'url', ''],
    ],
    'Buttons' => [
        ['header_button_label', 'Header button label', 40, 'text', 'Header button that opens the Talk to Expert popup.'],
        ['hero_button_label', 'Home page hero button label', 40, 'text', 'Main home page button that opens the counselling form.'],
    ],
    'Talk to Expert popup' => [
        ['expert_title', 'Title', 120, 'text', ''],
        ['expert_subtitle', 'Subtitle', 200, 'text', ''],
        ['expert_button_label', 'Button label', 40, 'text', 'Opens the counselling form.'],
    ],
    'Counselling form' => [
        ['form_title', 'Form title', 160, 'text', ''],
        ['form_button_label', 'Submit button label', 40, 'text', ''],
        ['form_consent_text', 'Consent text', 400, 'textarea', ''],
        ['form_success_message', 'Thank-you message', 300, 'textarea', 'Shown after the student submits the form.'],
    ],
    'Side "Talk to Expert" tab' => [
        ['side_tab_enabled', 'Show the pulsing side tab on every page', 1, 'toggle', ''],
        ['side_tab_label', 'Tab label', 30, 'text', ''],
        ['side_tab_badge', 'Badge', 10, 'text', 'Small highlight under the label, e.g. Free. Leave empty to hide.'],
    ],
    'WhatsApp chat button' => [
        ['whatsapp_enabled', 'Show the WhatsApp chat button on every page', 1, 'toggle', ''],
        ['whatsapp_number', 'WhatsApp number', 20, 'text', 'With country code, e.g. 919205692942. The button is hidden until a number is set.'],
        ['whatsapp_message', 'Pre-filled message', 300, 'textarea', ''],
    ],
    'Courses menu "Most Popular" card' => [
        ['menu_popular_course_id', 'Featured course', 20, 'course', 'Shown in the Courses menu; defaults to the first postgraduate course.'],
        ['menu_popular_badge', 'Badge', 30, 'text', 'Leave empty to hide.'],
        ['menu_popular_text', 'Description', 160, 'textarea', ''],
        ['menu_popular_button', 'Button label', 30, 'text', 'Links to the featured course page.'],
    ],
    'Back to top button' => [
        ['back_to_top_enabled', 'Show the back to top button', 1, 'toggle', ''],
    ],
];

$settings = site_settings();
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $values = [];
    foreach ($sections as $fields) {
        foreach ($fields as [$key, $label, $max, $type]) {
            if ($type === 'toggle') {
                $values[$key] = isset($_POST[$key]) ? '1' : '0';
                continue;
            }
            $value = trim(request_value($_POST, $key));
            if (mb_strlen($value) > $max) {
                $errors[] = $label . ' must be ' . $max . ' characters or fewer.';
            }
            $values[$key] = $value;
        }
    }
    foreach (['header_button_label', 'hero_button_label', 'expert_title', 'expert_button_label', 'form_title', 'form_button_label', 'form_consent_text', 'form_success_message', 'side_tab_label'] as $required) {
        if ($values[$required] === '') {
            $errors[] = 'Fill in every label and message (empty: ' . str_replace('_', ' ', $required) . ').';
            break;
        }
    }
    if ($values['contact_email'] !== '' && !filter_var($values['contact_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid contact email address.';
    }
    if ($values['contact_phone'] !== '' && !preg_match('/\A\+?[0-9][0-9\s\-()]{5,28}\z/', $values['contact_phone'])) {
        $errors[] = 'The phone number may contain only digits, spaces, dashes, brackets and a leading +.';
    }
    if ($values['topbar_phone'] !== '' && !preg_match('/\A\+?[0-9][0-9\s\-()]{5,28}\z/', $values['topbar_phone'])) {
        $errors[] = 'The top bar phone number may contain only digits, spaces, dashes, brackets and a leading +.';
    }
    if ($values['topbar_email'] !== '' && !filter_var($values['topbar_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid top bar email address.';
    }
    foreach (['social_facebook', 'social_instagram', 'social_linkedin', 'social_x', 'social_youtube'] as $key) {
        if ($values[$key] !== '' && (!filter_var($values[$key], FILTER_VALIDATE_URL) || !preg_match('#\Ahttps?://#i', $values[$key]))) {
            $errors[] = 'Social links must be full web addresses starting with https:// (check ' . str_replace('social_', '', $key) . ').';
        }
    }
    $values['whatsapp_number'] = preg_replace('/[\s\-()+]+/', '', $values['whatsapp_number']) ?? '';
    if ($values['whatsapp_number'] !== '' && !preg_match('/\A[0-9]{8,15}\z/', $values['whatsapp_number'])) {
        $errors[] = 'Enter the WhatsApp number as 8 to 15 digits including the country code (e.g. 919205692942).';
    }
    if ($values['menu_popular_course_id'] !== '' && !ctype_digit($values['menu_popular_course_id'])) {
        $values['menu_popular_course_id'] = '';
    }
    if ($values['menu_popular_button'] === '') {
        $errors[] = 'Enter a button label for the Most Popular card.';
    }
    if (!$errors) {
        save_site_settings($pdo, $values);
        flash('Settings saved.');
        header('Location: ' . app_url('/admin/settings.php'));
        exit;
    }
    $settings = $values + $settings;
}

$menuCourses = $pdo->query('SELECT id, name FROM courses WHERE is_active = 1 ORDER BY name')->fetchAll();
$adminTitle = 'Counselling & widgets';
require __DIR__ . '/_nav.php';
?>
<?php foreach ($errors as $error): ?><div class="notice error"><?= e($error) ?></div><?php endforeach; ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<?php foreach ($sections as $title => $fields): ?>
  <section class="form-card settings-card"><h2><?= e($title) ?></h2><div class="form-grid">
  <?php foreach ($fields as [$key, $label, $max, $type, $help]): ?>
    <?php if ($type === 'toggle'): ?>
      <label class="full toggle"><input type="checkbox" name="<?= e($key) ?>" value="1" style="width:auto" <?= $settings[$key] === '1' ? 'checked' : '' ?>> <?= e($label) ?></label>
    <?php elseif ($type === 'course'): ?>
      <label><?= e($label) ?><select name="<?= e($key) ?>"><option value="">Automatic</option><?php foreach ($menuCourses as $menuCourse): ?><option value="<?= (int) $menuCourse['id'] ?>" <?= $settings[$key] === (string) $menuCourse['id'] ? 'selected' : '' ?>><?= e($menuCourse['name']) ?></option><?php endforeach; ?></select><?php if ($help): ?><small class="muted"><?= e($help) ?></small><?php endif; ?></label>
    <?php elseif ($type === 'textarea'): ?>
      <label class="full"><?= e($label) ?><textarea name="<?= e($key) ?>" rows="2" maxlength="<?= $max ?>"><?= e($settings[$key]) ?></textarea><?php if ($help): ?><small class="muted"><?= e($help) ?></small><?php endif; ?></label>
    <?php else: ?>
      <label><?= e($label) ?><input type="<?= e($type) ?>" name="<?= e($key) ?>" maxlength="<?= $max ?>" value="<?= e($settings[$key]) ?>"><?php if ($help): ?><small class="muted"><?= e($help) ?></small><?php endif; ?></label>
    <?php endif; ?>
  <?php endforeach; ?>
  </div></section>
<?php endforeach; ?>
<p><button class="button" type="submit">Save settings</button> <a class="button button-light" href="<?= e(app_url('/')) ?>" target="_blank" rel="noopener">View site</a></p>
</form>
<?php require __DIR__ . '/_end.php'; ?>
