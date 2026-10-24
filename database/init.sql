-- -------------------------------------------------------------
-- DTC245200753 - Đề 31: Hệ thống Thư viện Ảnh Gallery
-- Khởi tạo Schema và Dữ liệu mẫu (database/init.sql)
-- -------------------------------------------------------------

CREATE DATABASE IF NOT EXISTS `gallery_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `gallery_db`;

-- 1. Bảng users: Quản trị viên hệ thống
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) DEFAULT 'Quản trị viên',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Bảng albums: Quản lý album ảnh
CREATE TABLE IF NOT EXISTS `albums` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_album_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Bảng categories: Danh mục phân loại ảnh
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Bảng photos: Metadata ảnh
-- Khóa ngoại album_id với ON DELETE RESTRICT: Không cho phép xóa album khi còn ảnh
-- Khóa ngoại category_id với ON DELETE SET NULL: Nếu xóa danh mục, ảnh không bị mất
CREATE TABLE IF NOT EXISTS `photos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `album_id` INT NOT NULL,
    `category_id` INT NULL,
    `title` VARCHAR(150) NOT NULL,
    `stored_filename` VARCHAR(255) NOT NULL,
    `original_filename` VARCHAR(255) NOT NULL,
    `mime_type` VARCHAR(50) NOT NULL,
    `size_bytes` BIGINT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`album_id`) REFERENCES `albums`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_photo_title` (`title`),
    INDEX `idx_photo_album` (`album_id`),
    INDEX `idx_photo_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Bảng share_links: Quản lý liên kết chia sẻ công khai
-- Token lưu trữ dạng SHA-256 hash. Hỗ trợ thu hồi (revoked_at)
CREATE TABLE IF NOT EXISTS `share_links` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `token_hash` VARCHAR(64) NOT NULL UNIQUE,
    `target_type` ENUM('photo', 'album') NOT NULL,
    `photo_id` INT NULL,
    `album_id` INT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `revoked_at` DATETIME NULL,
    FOREIGN KEY (`photo_id`) REFERENCES `photos`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (`album_id`) REFERENCES `albums`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_share_token` (`token_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Dữ liệu mẫu ban đầu (Seed Data)
-- -------------------------------------------------------------

-- Danh mục mẫu
INSERT IGNORE INTO `categories` (`id`, `name`) VALUES
(1, 'Phong cảnh'),
(2, 'Gia đình & Bạn bè'),
(3, 'Du lịch'),
(4, 'Công việc & Học tập');

-- Album mẫu
INSERT IGNORE INTO `albums` (`id`, `name`, `description`) VALUES
(1, 'Album Khởi động Demo', 'Album dùng để kiểm thử hệ thống Gallery môn Triển khai và Quản trị Hệ thống.');
