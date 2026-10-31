CREATE DATABASE IF NOT EXISTS collegekhoj CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE collegekhoj;

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','user') NOT NULL DEFAULT 'user',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE universities (
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
  featured TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_universities_active_featured (is_active, featured, name)
) ENGINE=InnoDB;

CREATE TABLE colleges (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  university_id BIGINT UNSIGNED NULL,
  name VARCHAR(190) NOT NULL,
  slug VARCHAR(210) NOT NULL UNIQUE,
  logo_url VARCHAR(500) NULL,
  city VARCHAR(100) NULL,
  state VARCHAR(100) NULL,
  location VARCHAR(190) NULL,
  description TEXT NULL,
  established_year SMALLINT UNSIGNED NULL,
  ranking INT UNSIGNED NULL,
  rating DECIMAL(2,1) NOT NULL DEFAULT 0.0,
  fees_min DECIMAL(12,2) NULL,
  fees_max DECIMAL(12,2) NULL,
  placement_average DECIMAL(12,2) NULL,
  placement_rate DECIMAL(5,2) NULL,
  brochure_url VARCHAR(500) NULL,
  admission_process TEXT NULL,
  eligibility TEXT NULL,
  cutoff_details TEXT NULL,
  highlights TEXT NULL,
  featured TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_colleges_university FOREIGN KEY (university_id) REFERENCES universities(id) ON DELETE SET NULL,
  INDEX idx_colleges_listing (is_active, featured, state, city),
  INDEX idx_colleges_rating (rating),
  INDEX idx_colleges_fees (fees_min, fees_max)
) ENGINE=InnoDB;

CREATE TABLE course_categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  slug VARCHAR(120) NOT NULL UNIQUE,
  sort_order SMALLINT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE courses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id BIGINT UNSIGNED NULL,
  university_id BIGINT UNSIGNED NULL,
  college_id BIGINT UNSIGNED NULL,
  name VARCHAR(190) NOT NULL,
  slug VARCHAR(210) NOT NULL UNIQUE,
  duration VARCHAR(80) NULL,
  fees_min DECIMAL(12,2) NULL,
  fees_max DECIMAL(12,2) NULL,
  eligibility TEXT NULL,
  description TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_courses_category FOREIGN KEY (category_id) REFERENCES course_categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_courses_university FOREIGN KEY (university_id) REFERENCES universities(id) ON DELETE SET NULL,
  CONSTRAINT fk_courses_college FOREIGN KEY (college_id) REFERENCES colleges(id) ON DELETE SET NULL,
  INDEX idx_courses_navigation (is_active, category_id, name),
  INDEX idx_courses_association (university_id, college_id)
) ENGINE=InnoDB;

CREATE TABLE exams (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  slug VARCHAR(180) NOT NULL UNIQUE,
  exam_date DATE NULL,
  application_deadline DATE NULL,
  eligibility TEXT NULL,
  description TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  INDEX idx_exams_date (is_active, exam_date)
) ENGINE=InnoDB;

CREATE TABLE college_exams (
  college_id BIGINT UNSIGNED NOT NULL,
  exam_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (college_id, exam_id),
  CONSTRAINT fk_college_exams_college FOREIGN KEY (college_id) REFERENCES colleges(id) ON DELETE CASCADE,
  CONSTRAINT fk_college_exams_exam FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
  INDEX idx_college_exams_exam (exam_id, college_id)
) ENGINE=InnoDB;

CREATE TABLE reviews (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  college_id BIGINT UNSIGNED NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  title VARCHAR(180) NULL,
  review_text TEXT NOT NULL,
  pros TEXT NULL,
  cons TEXT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT chk_reviews_rating CHECK (rating BETWEEN 1 AND 5),
  CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_reviews_college FOREIGN KEY (college_id) REFERENCES colleges(id) ON DELETE CASCADE,
  INDEX idx_reviews_college_status (college_id, status, created_at)
) ENGINE=InnoDB;

CREATE TABLE college_questions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  college_id BIGINT UNSIGNED NOT NULL,
  question TEXT NOT NULL,
  answer TEXT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  answered_at TIMESTAMP NULL,
  CONSTRAINT fk_questions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_questions_college FOREIGN KEY (college_id) REFERENCES colleges(id) ON DELETE CASCADE,
  INDEX idx_questions_college_status (college_id, status, created_at)
) ENGINE=InnoDB;

CREATE TABLE cms_pages (
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
) ENGINE=InnoDB;

CREATE TABLE tags_manager (
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
) ENGINE=InnoDB;

CREATE TABLE home_tags (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  group_name VARCHAR(80) NOT NULL,
  label VARCHAR(120) NOT NULL,
  url VARCHAR(500) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_home_tags_display (is_active, group_name, sort_order)
) ENGINE=InnoDB;

INSERT INTO course_categories (name, slug, sort_order) VALUES
('Management', 'management', 1),
('Computer Applications', 'computer-applications', 2),
('Commerce', 'commerce', 3),
('Arts & Humanities', 'arts-humanities', 4),
('Science', 'science', 5);

INSERT INTO universities (name, slug, city, state, location, description, institution_type, featured) VALUES
('Sikkim Manipal University Online', 'sikkim-manipal-university-online', 'Gangtok', 'Sikkim', 'Gangtok, Sikkim', 'Explore online degree programmes from Sikkim Manipal University. Verify current programme availability directly with the university.', 'Private University', 1),
('Manipal University Jaipur Online', 'manipal-university-jaipur-online', 'Jaipur', 'Rajasthan', 'Jaipur, Rajasthan', 'Explore online degree programmes from Manipal University Jaipur. Verify current programme availability directly with the university.', 'Private University', 1),
('Integral University Online', 'integral-university-online', 'Lucknow', 'Uttar Pradesh', 'Lucknow, Uttar Pradesh', 'Explore online degree programmes from Integral University. Verify current programme availability directly with the university.', 'Private University', 1),
('Mangalayatan University Online', 'mangalayatan-university-online', 'Aligarh', 'Uttar Pradesh', 'Aligarh, Uttar Pradesh', 'Explore online degree programmes from Mangalayatan University. Verify current programme availability directly with the university.', 'Private University', 1);

INSERT INTO colleges (university_id, name, slug, city, state, location, description, rating, fees_min, fees_max, featured) VALUES
(1, 'SMU Online', 'smu-online', 'Gangtok', 'Sikkim', 'Gangtok, Sikkim', 'Online programme options from Sikkim Manipal University. Confirm eligibility, fees and recognition for the current intake with the university.', 4.2, 30000, 150000, 1),
(2, 'MUJ Online', 'muj-online', 'Jaipur', 'Rajasthan', 'Jaipur, Rajasthan', 'Online programme options from Manipal University Jaipur. Confirm eligibility, fees and recognition for the current intake with the university.', 4.4, 40000, 180000, 1),
(3, 'Integral University Online', 'integral-online', 'Lucknow', 'Uttar Pradesh', 'Lucknow, Uttar Pradesh', 'Online programme options from Integral University. Confirm eligibility, fees and recognition for the current intake with the university.', 4.0, 25000, 120000, 1),
(4, 'Mangalayatan University Online', 'mangalayatan-online', 'Aligarh', 'Uttar Pradesh', 'Aligarh, Uttar Pradesh', 'Online programme options from Mangalayatan University. Confirm eligibility, fees and recognition for the current intake with the university.', 3.9, 25000, 110000, 0);

INSERT INTO courses (category_id, university_id, college_id, name, slug, duration, fees_min, fees_max, eligibility, description) VALUES
(1, 1, 1, 'Online MBA', 'online-mba', '2 years', 60000, 150000, 'A bachelor degree from a recognised university; confirm current admission criteria with the institution.', 'Postgraduate study in business administration and management.'),
(2, 1, 1, 'Online MCA', 'online-mca', '2 years', 70000, 150000, 'A bachelor degree; mathematics requirements may vary by institution.', 'Postgraduate study in computer applications and information technology.'),
(1, 2, 2, 'Online BBA', 'online-bba', '3 years', 80000, 160000, '10+2 or equivalent qualification; verify the current admission criteria.', 'Undergraduate study in business administration and management.'),
(2, 2, 2, 'Online BCA', 'online-bca', '3 years', 90000, 170000, '10+2 or equivalent qualification; mathematics requirements may vary.', 'Undergraduate study in computer applications and information technology.'),
(3, 3, 3, 'Online BCom', 'online-bcom', '3 years', 40000, 90000, '10+2 or equivalent qualification.', 'Undergraduate study in commerce and accounting.'),
(3, 4, 4, 'Online MCom', 'online-mcom', '2 years', 35000, 80000, 'A bachelor degree; verify institution-specific eligibility.', 'Postgraduate study in commerce and related subjects.'),
(4, 1, 1, 'Online MA in English', 'online-ma-english', '2 years', 30000, 70000, 'A bachelor degree; verify institution-specific eligibility.', 'Postgraduate study in English and literature.');

INSERT INTO exams (name, slug, exam_date, application_deadline, eligibility, description) VALUES
('CUET UG', 'cuet-ug', NULL, NULL, 'Eligibility and dates are announced by the exam authority.', 'Check the official examination website for the current schedule and eligibility.'),
('CAT', 'cat', NULL, NULL, 'Eligibility and dates are announced by the exam authority.', 'Check the official examination website for the current schedule and eligibility.'),
('NEET UG', 'neet-ug', NULL, NULL, 'Eligibility and dates are announced by the exam authority.', 'Check the official examination website for the current schedule and eligibility.');

INSERT INTO cms_pages (title, slug, body, meta_title, meta_description, status) VALUES
('About Collegekhoj', 'about', '<p>Collegekhoj helps students explore courses and institutions. Always verify current details with the official university.</p>', 'About Collegekhoj', 'Learn about Collegekhoj education discovery and guidance.', 'published'),
('Privacy policy', 'privacy-policy', '<p><strong>Before launch, have this notice reviewed for your location.</strong></p><p>This portal collects account names and email addresses, password hashes, and any reviews or questions submitted by signed-in users. The site operator and authorized administrators can access this information to operate accounts, moderate content, and respond to questions.</p><p>Approved reviews and questions may be displayed publicly with the account name. Do not include sensitive personal information in public submissions. You may contact the site operator to request account/data access or deletion: <a href="mailto:help@collegekhoj.co.in">help@collegekhoj.co.in</a>.</p><p>Tracking scripts are only added when configured by an administrator. Third-party tags may collect information under their providers’ terms; configure legally required consent controls before enabling them.</p><p>Publish a complete, locally reviewed privacy notice and consent configuration before collecting personal information or enabling analytics.</p><h2>Contact us</h2><p>For privacy requests, account help, or questions about reviews and content on this site, email <a href="mailto:help@collegekhoj.co.in">help@collegekhoj.co.in</a>.</p>', 'Privacy policy | Collegekhoj', 'How Collegekhoj handles account details, reviews, questions, and configured tracking technologies.', 'published');
