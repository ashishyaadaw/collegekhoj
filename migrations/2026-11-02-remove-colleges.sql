-- Upgrade an existing database for the "colleges removed / counselling leads" release.
-- Back up the database first. Run once in phpMyAdmin (or `mysql collegekhoj < this-file`).

-- New tables (the site also creates these automatically on first use).
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

-- "Popular searches" tags that pointed at the removed college listing.
-- Afterwards use Admin > Home page tags > "Add suggested tags from catalog" to recreate them.
DELETE FROM home_tags WHERE url LIKE '/colleges.php%' OR url LIKE '/compare.php%' OR url LIKE '/college-detail.php%';

-- Remove the college data (permanently deletes colleges, their reviews and questions).
ALTER TABLE courses DROP FOREIGN KEY fk_courses_college;
ALTER TABLE courses DROP INDEX idx_courses_association, DROP COLUMN college_id, ADD INDEX idx_courses_university (university_id);
DROP TABLE IF EXISTS college_questions, reviews, college_exams, colleges;
