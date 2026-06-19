-- Database structure for Portranger CMS
-- Run this file to set up all tables

SET FOREIGN_KEY_CHECKS=0;

-- --------------------------------------------------------
-- Table: users
-- --------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: profile
-- --------------------------------------------------------
DROP TABLE IF EXISTS `profile`;
CREATE TABLE `profile` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(100) NOT NULL,
    `professional_title` VARCHAR(100) NOT NULL,
    `short_intro` TEXT,
    `about_description` LONGTEXT,
    `email` VARCHAR(100),
    `phone` VARCHAR(50),
    `location` VARCHAR(255),
    `profile_image_id` INT NULL,
    `about_image_id` INT NULL,
    `cover_image_id` INT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: media
-- --------------------------------------------------------
DROP TABLE IF EXISTS `media`;
CREATE TABLE `media` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `file_name` VARCHAR(255) NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `file_type` VARCHAR(100),
    `file_size` INT,
    `mime_type` VARCHAR(100),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: cover_slides
-- --------------------------------------------------------
DROP TABLE IF EXISTS `cover_slides`;
CREATE TABLE `cover_slides` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `media_id` INT NOT NULL,
    `title` VARCHAR(255),
    `caption` VARCHAR(500),
    `display_order` INT DEFAULT 0,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`media_id`) REFERENCES `media`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: sections
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sections`;
CREATE TABLE `sections` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(100) NOT NULL,
    `description` LONGTEXT,
    `icon` VARCHAR(50),
    `display_order` INT DEFAULT 0,
    `is_visible` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: skills
-- --------------------------------------------------------
DROP TABLE IF EXISTS `skills`;
CREATE TABLE `skills` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `level` VARCHAR(50),
    `icon` VARCHAR(50),
    `display_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: experiences
-- --------------------------------------------------------
DROP TABLE IF EXISTS `experiences`;
CREATE TABLE `experiences` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `company` VARCHAR(100) NOT NULL,
    `position` VARCHAR(100) NOT NULL,
    `start_date` DATE,
    `end_date` DATE,
    `is_current` BOOLEAN DEFAULT FALSE,
    `description` TEXT,
    `display_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: educations
-- --------------------------------------------------------
DROP TABLE IF EXISTS `educations`;
CREATE TABLE `educations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `institution` VARCHAR(100) NOT NULL,
    `degree` VARCHAR(100) NOT NULL,
    `start_date` DATE,
    `end_date` DATE,
    `description` TEXT,
    `display_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: projects
-- --------------------------------------------------------
DROP TABLE IF EXISTS `projects`;
CREATE TABLE `projects` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(200) NOT NULL,
    `short_description` TEXT,
    `detailed_description` LONGTEXT,
    `technologies` VARCHAR(500),
    `thumbnail_media_id` INT NULL,
    `repo_link` VARCHAR(500),
    `demo_link` VARCHAR(500),
    `is_featured` BOOLEAN DEFAULT FALSE,
    `display_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`thumbnail_media_id`) REFERENCES `media`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: project_gallery
-- --------------------------------------------------------
DROP TABLE IF EXISTS `project_gallery`;
CREATE TABLE `project_gallery` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `project_id` INT NOT NULL,
    `media_id` INT NOT NULL,
    `display_order` INT DEFAULT 0,
    FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`media_id`) REFERENCES `media`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: downloads
-- --------------------------------------------------------
DROP TABLE IF EXISTS `downloads`;
CREATE TABLE `downloads` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT,
    `media_id` INT NOT NULL,
    `display_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`media_id`) REFERENCES `media`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: social_links
-- --------------------------------------------------------
DROP TABLE IF EXISTS `social_links`;
CREATE TABLE `social_links` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `platform` VARCHAR(50) NOT NULL,
    `url` VARCHAR(500) NOT NULL,
    `icon` VARCHAR(50),
    `display_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: navigation_items
-- --------------------------------------------------------
DROP TABLE IF EXISTS `navigation_items`;
CREATE TABLE `navigation_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(50) NOT NULL,
    `url` VARCHAR(500) NOT NULL,
    `target` VARCHAR(20) DEFAULT '_self',
    `is_active` BOOLEAN DEFAULT TRUE,
    `display_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: footer_links
-- --------------------------------------------------------
DROP TABLE IF EXISTS `footer_links`;
CREATE TABLE `footer_links` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(100) NOT NULL,
    `url` VARCHAR(500) NOT NULL,
    `target` VARCHAR(20) DEFAULT '_self',
    `display_order` INT DEFAULT 0,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: appearance
-- --------------------------------------------------------
DROP TABLE IF EXISTS `appearance`;
CREATE TABLE `appearance` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `logo_media_id` INT NULL,
    `favicon_media_id` INT NULL,
    `primary_color` VARCHAR(7) DEFAULT '#3b82f6',
    `secondary_color` VARCHAR(7) DEFAULT '#1e293b',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`logo_media_id`) REFERENCES `media`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`favicon_media_id`) REFERENCES `media`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: seo
-- --------------------------------------------------------
DROP TABLE IF EXISTS `seo`;
CREATE TABLE `seo` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `site_title` VARCHAR(100) DEFAULT 'Portfolio',
    `meta_description` TEXT,
    `meta_keywords` TEXT,
    `social_image_media_id` INT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`social_image_media_id`) REFERENCES `media`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: contact_messages
-- --------------------------------------------------------
DROP TABLE IF EXISTS `contact_messages`;
CREATE TABLE `contact_messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `message` TEXT NOT NULL,
    `is_read` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: activity_logs
-- --------------------------------------------------------
DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `username` VARCHAR(50) NOT NULL DEFAULT '',
    `action` VARCHAR(255) NOT NULL,
    `details` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Insert default data (excluding admin user)
-- --------------------------------------------------------

-- Profile
INSERT INTO `profile` (`full_name`, `professional_title`, `short_intro`, `about_description`, `email`, `phone`, `location`) 
VALUES ('Your Name', 'Web Developer & Designer', 'I build amazing web experiences', 'More about me...', 'hello@example.com', '+1234567890', 'New York, USA');

-- Navigation items
INSERT INTO `navigation_items` (`title`, `url`, `display_order`) VALUES
('Home', '/', 1),
('About', '/#about', 2),
('Skills', '/#skills', 3),
('Experience', '/#experience', 4),
('Projects', '/#projects', 5),
('Contact', '/#contact', 6);

-- Appearance
INSERT INTO `appearance` (`primary_color`, `secondary_color`) 
VALUES ('#3b82f6', '#1e293b');

-- SEO
INSERT INTO `seo` (`site_title`, `meta_description`) 
VALUES ('Portfolio', 'Professional portfolio management system');

-- Sample footer links
INSERT INTO `footer_links` (`title`, `url`, `display_order`) VALUES
('Privacy Policy', '/privacy', 1),
('Terms of Service', '/terms', 2),
('Contact', '/#contact', 3);

SET FOREIGN_KEY_CHECKS=1;