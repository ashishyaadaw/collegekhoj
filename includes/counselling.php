<?php
declare(strict_types=1);

// Counselling leads and the site-wide widgets (expert popup, side tab, WhatsApp, back to top),
// all managed from admin/settings.php and admin/leads.php.

const LEAD_STATUSES = ['new' => 'New', 'contacted' => 'Contacted', 'converted' => 'Converted', 'closed' => 'Closed'];

function ensure_site_settings_table(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS site_settings (
          setting_key VARCHAR(80) NOT NULL PRIMARY KEY,
          setting_value TEXT NULL,
          updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function ensure_leads_table(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS leads (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          name VARCHAR(120) NOT NULL,
          email VARCHAR(190) NOT NULL,
          phone VARCHAR(20) NOT NULL,
          city VARCHAR(100) NOT NULL,
          course_id BIGINT UNSIGNED NULL,
          course_name VARCHAR(190) NULL,
          university_id BIGINT UNSIGNED NULL,
          university_name VARCHAR(190) NULL,
          source_page VARCHAR(255) NULL,
          ip_address VARCHAR(45) NULL,
          status ENUM(\'new\',\'contacted\',\'converted\',\'closed\') NOT NULL DEFAULT \'new\',
          notes TEXT NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_leads_status_created (status, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

/** Every setting with its default; keys missing from site_settings fall back to these. */
function site_setting_defaults(): array
{
    return [
        'contact_phone' => '',
        'office_hours' => '09:00 AM - 5:00 PM',
        'office_days' => 'Monday - Saturday',
        'office_address' => 'IIJT Campus, Park Rd, Gorakhpur PIN 273009',
        'topbar_enabled' => '1',
        'topbar_phone' => '092056 92942',
        'topbar_email' => 'info@hikeedu.in',
        'topbar_tagline' => 'Your favorite platforms, our latest updates',
        'social_facebook' => '',
        'social_instagram' => '',
        'social_linkedin' => '',
        'social_x' => '',
        'social_youtube' => '',
        'contact_email' => contact_email(),
        'header_button_label' => 'Talk to Expert',
        'hero_button_label' => 'Free Counselling',
        'expert_title' => 'Talk to Our Expert',
        'expert_subtitle' => 'Get free counselling from our education experts',
        'expert_button_label' => 'Contact Us',
        'form_title' => 'Enter Your Details To Get Counselling From Our Experts',
        'form_button_label' => 'Get Free Counselling',
        'form_consent_text' => "I consent to be contacted by Collegekhoj via Call, SMS, Email, or WhatsApp, even if I'm registered on DND/NDNC.",
        'form_success_message' => 'Thank you! Our counsellor will get in touch with you shortly.',
        'side_tab_enabled' => '1',
        'side_tab_label' => 'Talk to Expert',
        'side_tab_badge' => 'Free',
        'whatsapp_enabled' => '1',
        'whatsapp_number' => '',
        'whatsapp_message' => 'Hi, I would like free admission counselling.',
        'back_to_top_enabled' => '1',
        'menu_popular_course_id' => '',
        'menu_popular_badge' => 'Most Popular',
        'menu_popular_text' => 'Get free expert guidance on eligibility, fees and admissions.',
        'menu_popular_button' => 'Explore Now',
    ];
}

function site_settings(bool $refresh = false): array
{
    static $settings;
    if ($settings !== null && !$refresh) {
        return $settings;
    }
    $settings = site_setting_defaults();
    try {
        foreach (db()->query('SELECT setting_key, setting_value FROM site_settings')->fetchAll() as $row) {
            if (array_key_exists($row['setting_key'], $settings)) {
                $settings[$row['setting_key']] = (string) $row['setting_value'];
            }
        }
    } catch (PDOException) {
        // Table not created yet: defaults apply until the settings page is saved.
    }
    return $settings;
}

function site_setting(string $key): string
{
    return site_settings()[$key] ?? '';
}

function save_site_settings(PDO $pdo, array $values): void
{
    ensure_site_settings_table($pdo);
    $statement = $pdo->prepare(
        'INSERT INTO site_settings (setting_key, setting_value) VALUES (:k, :v) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    foreach ($values as $key => $value) {
        $statement->execute(['k' => $key, 'v' => $value]);
    }
    site_settings(true);
}

function whatsapp_url(): ?string
{
    $number = preg_replace('/\D+/', '', site_setting('whatsapp_number')) ?? '';
    if (site_setting('whatsapp_enabled') !== '1' || $number === '') {
        return null;
    }
    $message = site_setting('whatsapp_message');
    return 'https://wa.me/' . $number . ($message !== '' ? '?text=' . rawurlencode($message) : '');
}

function ui_icon(string $name): string
{
    $paths = [
        'phone' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2Z"/>',
        'mail' => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
        'close' => '<path d="M18 6 6 18M6 6l12 12"/>',
        'arrow-up' => '<path d="M12 19V5M5 12l7-7 7 7"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'map-pin' => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
        'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17.5 6.5h.01"/>',
    ];
    $filled = [
        'facebook' => '<path d="M13.5 22v-8.2h2.8l.4-3.2h-3.2V8.5c0-.9.3-1.6 1.6-1.6h1.7V4.1a23 23 0 0 0-2.5-.1c-2.5 0-4.2 1.5-4.2 4.3v2.4H7.3v3.2h2.8V22Z"/>',
        'linkedin' => '<path d="M5 3.5a2.1 2.1 0 1 1 0 4.2 2.1 2.1 0 0 1 0-4.2ZM3.2 9.2h3.6V21H3.2Zm5.9 0h3.5v1.6h.1c.5-.9 1.7-1.9 3.4-1.9 3.7 0 4.4 2.4 4.4 5.6V21h-3.6v-5.8c0-1.4 0-3.2-2-3.2s-2.2 1.5-2.2 3.1V21H9.1Z"/>',
        'x' => '<path d="M17.8 3h3.1l-6.8 7.7L22 21h-6.2l-4.9-6.4L5.3 21H2.2l7.2-8.3L1.8 3h6.4l4.4 5.8Zm-1.1 16.2h1.7L7.3 4.7H5.5Z"/>',
        'youtube' => '<path d="M23 7.2a2.9 2.9 0 0 0-2-2C19.1 4.7 12 4.7 12 4.7s-7.1 0-9 .5a2.9 2.9 0 0 0-2 2A30 30 0 0 0 .5 12a30 30 0 0 0 .5 4.8 2.9 2.9 0 0 0 2 2c1.9.5 9 .5 9 .5s7.1 0 9-.5a2.9 2.9 0 0 0 2-2 30 30 0 0 0 .5-4.8 30 30 0 0 0-.5-4.8ZM9.7 15V9l5.9 3Z"/>',
    ];
    if (isset($filled[$name])) {
        return '<svg class="ui-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">' . $filled[$name] . '</svg>';
    }
    if ($name === 'whatsapp') {
        return '<svg class="ui-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.5 14.4c-.3-.1-1.8-.9-2-1s-.5-.1-.7.1-.8 1-.9 1.2-.3.2-.6.1a8 8 0 0 1-2.4-1.5 9 9 0 0 1-1.6-2c-.2-.3 0-.5.1-.6l.4-.5.3-.5v-.5l-.9-2.2c-.2-.6-.5-.5-.7-.5h-.6a1.1 1.1 0 0 0-.8.4 3.4 3.4 0 0 0-1 2.5 5.9 5.9 0 0 0 1.2 3.1 13.4 13.4 0 0 0 5.2 4.6c.7.3 1.3.5 1.7.6a4.2 4.2 0 0 0 1.9.1 3.1 3.1 0 0 0 2-1.4 2.5 2.5 0 0 0 .2-1.4c-.1-.1-.3-.2-.6-.3ZM12 21.8a9.8 9.8 0 0 1-5-1.4l-.4-.2-3.7 1 1-3.6-.2-.4A9.8 9.8 0 1 1 12 21.8Zm8.4-18.2A11.8 11.8 0 0 0 1.7 17.8L0 24l6.4-1.7a11.8 11.8 0 0 0 5.6 1.4A11.8 11.8 0 0 0 20.4 3.6Z"/></svg>';
    }
    return '<svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}

/** Social profile links set in the admin panel, in display order; empty ones are skipped. */
function social_links(): array
{
    $networks = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'x' => 'X', 'youtube' => 'YouTube'];
    $links = [];
    foreach ($networks as $key => $label) {
        $url = trim(site_setting('social_' . $key));
        if ($url !== '') {
            $links[$key] = ['label' => $label, 'url' => $url];
        }
    }
    return $links;
}

/** Phone numbers as typed by students: digits with an optional leading +, 10 to 13 digits. */
function normalize_phone(string $phone): ?string
{
    $phone = preg_replace('/[\s\-().]+/', '', $phone) ?? '';
    return preg_match('/\A\+?[0-9]{10,13}\z/', $phone) ? $phone : null;
}

/** Only same-site paths are accepted as the page a lead came from (also used as the redirect target). */
function safe_source_path(string $path): string
{
    return str_starts_with($path, '/') && !str_starts_with($path, '//') && !preg_match('/[\s<>"\\\\]/', $path)
        ? mb_substr($path, 0, 255) : '/';
}
