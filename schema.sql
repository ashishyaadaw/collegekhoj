-- Collegekhoj database schema.
-- Fresh install: create an empty database (phpMyAdmin or your hosting panel) and import this file.
-- Upgrade: back up the database, then import this file again. It only adds what is missing and
-- removes the old college listing; existing courses, universities, pages and leads are kept.
-- Sample data is added only on a fresh install. Create the first admin login at /admin/login.php.
SET NAMES utf8mb4;

SET @fresh_install := (SELECT COUNT(*) = 0 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courses');
SET @new_course_universities := (SELECT COUNT(*) = 0 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'course_universities');

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','user') NOT NULL DEFAULT 'user',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS universities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  slug VARCHAR(210) NOT NULL UNIQUE,
  logo_url VARCHAR(500) NULL,
  city VARCHAR(100) NULL,
  state VARCHAR(100) NULL,
  location VARCHAR(190) NULL,
  description TEXT NULL,
  established_year SMALLINT UNSIGNED NULL,
  institution_type VARCHAR(80) NULL,
  approvals VARCHAR(255) NULL,
  highlights TEXT NULL,
  meta_title VARCHAR(190) NULL,
  meta_description VARCHAR(320) NULL,
  featured TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_universities_active_featured (is_active, featured, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS course_categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  slug VARCHAR(120) NOT NULL UNIQUE,
  sort_order SMALLINT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS courses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id BIGINT UNSIGNED NULL,
  university_id BIGINT UNSIGNED NULL,
  level ENUM('pg','ug','other') NOT NULL DEFAULT 'ug',
  name VARCHAR(190) NOT NULL,
  full_name VARCHAR(190) NULL,
  slug VARCHAR(210) NOT NULL UNIQUE,
  duration VARCHAR(80) NULL,
  fees_min DECIMAL(12,2) NULL,
  fees_max DECIMAL(12,2) NULL,
  eligibility TEXT NULL,
  description TEXT NULL,
  highlights TEXT NULL,
  syllabus TEXT NULL,
  career_options TEXT NULL,
  meta_title VARCHAR(190) NULL,
  meta_description VARCHAR(320) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_courses_category FOREIGN KEY (category_id) REFERENCES course_categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_courses_university FOREIGN KEY (university_id) REFERENCES universities(id) ON DELETE SET NULL,
  INDEX idx_courses_navigation (is_active, category_id, name),
  INDEX idx_courses_university (university_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS course_universities (
  course_id BIGINT UNSIGNED NOT NULL,
  university_id BIGINT UNSIGNED NOT NULL,
  fee DECIMAL(12,2) NULL,
  PRIMARY KEY (course_id, university_id),
  INDEX idx_course_universities_university (university_id),
  CONSTRAINT fk_cu_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  CONSTRAINT fk_cu_university FOREIGN KEY (university_id) REFERENCES universities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exams (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  slug VARCHAR(180) NOT NULL UNIQUE,
  exam_date DATE NULL,
  application_deadline DATE NULL,
  eligibility TEXT NULL,
  description TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  INDEX idx_exams_date (is_active, exam_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cms_pages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(190) NOT NULL,
  slug VARCHAR(210) NOT NULL UNIQUE,
  body MEDIUMTEXT NOT NULL,
  meta_title VARCHAR(190) NULL,
  meta_description VARCHAR(320) NULL,
  meta_keywords VARCHAR(500) NULL,
  status ENUM('draft','published') NOT NULL DEFAULT 'draft',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_cms_pages_status_slug (status, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tags_manager (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  target_type ENUM('global','page') NOT NULL DEFAULT 'global',
  target_value VARCHAR(210) NULL,
  head_code MEDIUMTEXT NULL,
  body_code MEDIUMTEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_tags_injection (is_active, target_type, target_value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS home_tags (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  group_name VARCHAR(80) NOT NULL,
  label VARCHAR(120) NOT NULL,
  url VARCHAR(500) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_home_tags_display (is_active, group_name, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS nav_links (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  label VARCHAR(80) NOT NULL,
  subtitle VARCHAR(120) NULL,
  url VARCHAR(500) NOT NULL,
  icon VARCHAR(30) NOT NULL DEFAULT 'page',
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_nav_links_display (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_settings (
  setting_key VARCHAR(80) NOT NULL PRIMARY KEY,
  setting_value TEXT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leads (
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
  status ENUM('new','contacted','converted','closed') NOT NULL DEFAULT 'new',
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_leads_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Columns added after the first release.

SET @classify_levels := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courses' AND COLUMN_NAME = 'level') = 0;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courses' AND COLUMN_NAME = 'level') = 0, 'ALTER TABLE courses ADD COLUMN level ENUM(''pg'',''ug'',''other'') NOT NULL DEFAULT ''ug'' AFTER university_id', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courses' AND COLUMN_NAME = 'full_name') = 0, 'ALTER TABLE courses ADD COLUMN full_name VARCHAR(190) NULL AFTER name', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courses' AND COLUMN_NAME = 'highlights') = 0, 'ALTER TABLE courses ADD COLUMN highlights TEXT NULL', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courses' AND COLUMN_NAME = 'syllabus') = 0, 'ALTER TABLE courses ADD COLUMN syllabus TEXT NULL', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courses' AND COLUMN_NAME = 'career_options') = 0, 'ALTER TABLE courses ADD COLUMN career_options TEXT NULL', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courses' AND COLUMN_NAME = 'meta_title') = 0, 'ALTER TABLE courses ADD COLUMN meta_title VARCHAR(190) NULL', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courses' AND COLUMN_NAME = 'meta_description') = 0, 'ALTER TABLE courses ADD COLUMN meta_description VARCHAR(320) NULL', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'universities' AND COLUMN_NAME = 'approvals') = 0, 'ALTER TABLE universities ADD COLUMN approvals VARCHAR(255) NULL', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'universities' AND COLUMN_NAME = 'highlights') = 0, 'ALTER TABLE universities ADD COLUMN highlights TEXT NULL', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'universities' AND COLUMN_NAME = 'meta_title') = 0, 'ALTER TABLE universities ADD COLUMN meta_title VARCHAR(190) NULL', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'universities' AND COLUMN_NAME = 'meta_description') = 0, 'ALTER TABLE universities ADD COLUMN meta_description VARCHAR(320) NULL', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_pages' AND COLUMN_NAME = 'meta_keywords') = 0, 'ALTER TABLE cms_pages ADD COLUMN meta_keywords VARCHAR(500) NULL', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Sort existing courses into the PG / UG menu columns (only when the level column was just added).
UPDATE courses SET level = CASE
  WHEN name REGEXP '(^|[^a-z])(pg|pgd|pgdm|master|m\\.? ?(ba|ca|com|a|sc|tech|ed|lib))([^a-z]|$)' THEN 'pg'
  WHEN name REGEXP '(^|[^a-z])(bachelor|b\\.? ?(ba|ca|com|a|sc|tech|ed|lib))([^a-z]|$)' THEN 'ug'
  ELSE 'other' END
WHERE @classify_levels;

-- Remove the college listing (permanently deletes colleges, their reviews and questions).
DELETE FROM home_tags WHERE url LIKE '/colleges.php%' OR url LIKE '/compare.php%' OR url LIKE '/college-detail.php%';

SET @sql := IF((SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'courses' AND CONSTRAINT_NAME = 'fk_courses_college' AND CONSTRAINT_TYPE = 'FOREIGN KEY') > 0, 'ALTER TABLE courses DROP FOREIGN KEY fk_courses_college', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courses' AND INDEX_NAME = 'idx_courses_university') = 0, 'ALTER TABLE courses ADD INDEX idx_courses_university (university_id)', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courses' AND INDEX_NAME = 'idx_courses_association') > 0, 'ALTER TABLE courses DROP INDEX idx_courses_association', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courses' AND COLUMN_NAME = 'college_id') > 0, 'ALTER TABLE courses DROP COLUMN college_id', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

DROP TABLE IF EXISTS college_questions, reviews, college_exams, colleges;

-- Sample data (fresh install only).
INSERT INTO course_categories (name, slug, sort_order)
SELECT 'Management', 'management', 1 FROM DUAL WHERE @fresh_install UNION ALL
SELECT 'Computer Applications', 'computer-applications', 2 FROM DUAL WHERE @fresh_install UNION ALL
SELECT 'Commerce', 'commerce', 3 FROM DUAL WHERE @fresh_install UNION ALL
SELECT 'Arts & Humanities', 'arts-humanities', 4 FROM DUAL WHERE @fresh_install UNION ALL
SELECT 'Science', 'science', 5 FROM DUAL WHERE @fresh_install;

INSERT INTO universities (name, slug, city, state, location, description, institution_type, featured)
SELECT 'Sikkim Manipal University Online', 'sikkim-manipal-university-online', 'Gangtok', 'Sikkim', 'Gangtok, Sikkim', 'Explore online degree programmes from Sikkim Manipal University. Verify current programme availability directly with the university.', 'Private University', 1 FROM DUAL WHERE @fresh_install UNION ALL
SELECT 'Manipal University Jaipur Online', 'manipal-university-jaipur-online', 'Jaipur', 'Rajasthan', 'Jaipur, Rajasthan', 'Explore online degree programmes from Manipal University Jaipur. Verify current programme availability directly with the university.', 'Private University', 1 FROM DUAL WHERE @fresh_install UNION ALL
SELECT 'Integral University Online', 'integral-university-online', 'Lucknow', 'Uttar Pradesh', 'Lucknow, Uttar Pradesh', 'Explore online degree programmes from Integral University. Verify current programme availability directly with the university.', 'Private University', 1 FROM DUAL WHERE @fresh_install UNION ALL
SELECT 'Mangalayatan University Online', 'mangalayatan-university-online', 'Aligarh', 'Uttar Pradesh', 'Aligarh, Uttar Pradesh', 'Explore online degree programmes from Mangalayatan University. Verify current programme availability directly with the university.', 'Private University', 1 FROM DUAL WHERE @fresh_install;

INSERT INTO courses (category_id, university_id, level, name, full_name, slug, duration, fees_min, fees_max, eligibility, description)
SELECT (SELECT id FROM course_categories WHERE slug = 'management'), (SELECT id FROM universities WHERE slug = 'sikkim-manipal-university-online'), 'pg', 'Online MBA', 'Master of Business Administration', 'online-mba', '2 years', 60000, 150000, 'A bachelor degree from a recognised university; confirm current admission criteria with the institution.', 'Postgraduate study in business administration and management.' FROM DUAL WHERE @fresh_install UNION ALL
SELECT (SELECT id FROM course_categories WHERE slug = 'computer-applications'), (SELECT id FROM universities WHERE slug = 'sikkim-manipal-university-online'), 'pg', 'Online MCA', 'Master of Computer Applications', 'online-mca', '2 years', 70000, 150000, 'A bachelor degree; mathematics requirements may vary by institution.', 'Postgraduate study in computer applications and information technology.' FROM DUAL WHERE @fresh_install UNION ALL
SELECT (SELECT id FROM course_categories WHERE slug = 'management'), (SELECT id FROM universities WHERE slug = 'manipal-university-jaipur-online'), 'ug', 'Online BBA', 'Bachelor of Business Administration', 'online-bba', '3 years', 80000, 160000, '10+2 or equivalent qualification; verify the current admission criteria.', 'Undergraduate study in business administration and management.' FROM DUAL WHERE @fresh_install UNION ALL
SELECT (SELECT id FROM course_categories WHERE slug = 'computer-applications'), (SELECT id FROM universities WHERE slug = 'manipal-university-jaipur-online'), 'ug', 'Online BCA', 'Bachelor of Computer Applications', 'online-bca', '3 years', 90000, 170000, '10+2 or equivalent qualification; mathematics requirements may vary.', 'Undergraduate study in computer applications and information technology.' FROM DUAL WHERE @fresh_install UNION ALL
SELECT (SELECT id FROM course_categories WHERE slug = 'commerce'), (SELECT id FROM universities WHERE slug = 'integral-university-online'), 'ug', 'Online BCom', 'Bachelor of Commerce', 'online-bcom', '3 years', 40000, 90000, '10+2 or equivalent qualification.', 'Undergraduate study in commerce and accounting.' FROM DUAL WHERE @fresh_install UNION ALL
SELECT (SELECT id FROM course_categories WHERE slug = 'commerce'), (SELECT id FROM universities WHERE slug = 'mangalayatan-university-online'), 'pg', 'Online MCom', 'Master of Commerce', 'online-mcom', '2 years', 35000, 80000, 'A bachelor degree; verify institution-specific eligibility.', 'Postgraduate study in commerce and related subjects.' FROM DUAL WHERE @fresh_install UNION ALL
SELECT (SELECT id FROM course_categories WHERE slug = 'arts-humanities'), (SELECT id FROM universities WHERE slug = 'sikkim-manipal-university-online'), 'pg', 'Online MA in English', 'Master of Arts in English', 'online-ma-english', '2 years', 30000, 70000, 'A bachelor degree; verify institution-specific eligibility.', 'Postgraduate study in English and literature.' FROM DUAL WHERE @fresh_install;

INSERT INTO nav_links (label, subtitle, url, icon, sort_order)
SELECT 'Contact Us', 'Get in touch', '#talk-to-expert', 'phone', 10 FROM DUAL WHERE @fresh_install UNION ALL
SELECT 'About Us', 'Who we are', '/page.php?slug=about', 'users', 20 FROM DUAL WHERE @fresh_install UNION ALL
SELECT 'Free Counselling', 'Talk to an expert', '#counselling', 'chat', 30 FROM DUAL WHERE @fresh_install UNION ALL
SELECT 'Privacy Policy', 'Your data, protected', '/page.php?slug=privacy-policy', 'shield', 40 FROM DUAL WHERE @fresh_install;

INSERT INTO exams (name, slug, exam_date, application_deadline, eligibility, description)
SELECT 'CUET UG', 'cuet-ug', NULL, NULL, 'Eligibility and dates are announced by the exam authority.', 'Check the official examination website for the current schedule and eligibility.' FROM DUAL WHERE @fresh_install UNION ALL
SELECT 'CAT', 'cat', NULL, NULL, 'Eligibility and dates are announced by the exam authority.', 'Check the official examination website for the current schedule and eligibility.' FROM DUAL WHERE @fresh_install UNION ALL
SELECT 'NEET UG', 'neet-ug', NULL, NULL, 'Eligibility and dates are announced by the exam authority.', 'Check the official examination website for the current schedule and eligibility.' FROM DUAL WHERE @fresh_install;

INSERT INTO cms_pages (title, slug, body, meta_title, meta_description, status)
SELECT 'About Collegekhoj', 'about', '<p>Collegekhoj helps students explore courses and institutions. Always verify current details with the official university.</p>', 'About Collegekhoj', 'Learn about Collegekhoj education discovery and guidance.', 'published' FROM DUAL WHERE @fresh_install UNION ALL
SELECT 'Privacy policy', 'privacy-policy', '<p><strong>Before launch, have this notice reviewed for your location.</strong></p><p>When you request free counselling, this site collects your name, email address, phone number, city and the course or university you are interested in, along with the page you submitted from and your IP address. The site operator and authorized counsellors use this information only to contact you about admissions guidance by call, SMS, email or WhatsApp, with your consent.</p><p>You may contact the site operator to request account/data access or deletion: <a href="mailto:help@collegekhoj.co.in">help@collegekhoj.co.in</a>.</p><p>Tracking scripts are only added when configured by an administrator. Third-party tags may collect information under their providers’ terms; configure legally required consent controls before enabling them.</p><p>Publish a complete, locally reviewed privacy notice and consent configuration before collecting personal information or enabling analytics.</p><h2>Contact us</h2><p>For privacy requests or questions about content on this site, email <a href="mailto:help@collegekhoj.co.in">help@collegekhoj.co.in</a>.</p>', 'Privacy policy | Collegekhoj', 'How Collegekhoj handles counselling enquiries and configured tracking technologies.', 'published' FROM DUAL WHERE @fresh_install;

-- Link each course to its university (once, when the course_universities table is first created).
INSERT IGNORE INTO course_universities (course_id, university_id) SELECT id, university_id FROM courses WHERE university_id IS NOT NULL AND @new_course_universities;
