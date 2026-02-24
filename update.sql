-- =============================================
-- ForumTR Güncelleme SQL
-- phpMyAdmin > SQL sekmesinde çalıştırın
-- =============================================

USE forum_db;
SET NAMES utf8mb4;

-- 1. users tablosuna yeni kolonlar ekle (varsa atla)
ALTER TABLE users ADD COLUMN IF NOT EXISTS dark_mode TINYINT(1) DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS website VARCHAR(255) DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS location VARCHAR(100) DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS signature TEXT DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS banned TINYINT(1) DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS ban_reason VARCHAR(255) DEFAULT NULL;

-- 2. post_count'u gerçek değere senkronize et
UPDATE users u SET post_count = (SELECT COUNT(*) FROM replies WHERE user_id = u.id);

-- 3. Site ayarları tablosu
CREATE TABLE IF NOT EXISTS site_settings (
    `key` VARCHAR(100) PRIMARY KEY,
    `value` TEXT DEFAULT NULL
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT IGNORE INTO site_settings (`key`, `value`) VALUES
('site_name', 'ForumTR'),
('site_desc', 'Türkçe Forum Platformu'),
('favicon_url', ''),
('custom_css', '');

-- 4. Bildirimler
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    from_user_id INT DEFAULT NULL,
    type ENUM('reply','like','follow','mention','dm','badge','report_resolved') NOT NULL,
    topic_id INT DEFAULT NULL,
    reply_id INT DEFAULT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE SET NULL
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 5. Özel mesajlar
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_user_id INT NOT NULL,
    to_user_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    content LONGTEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    deleted_by_sender TINYINT(1) DEFAULT 0,
    deleted_by_receiver TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE CASCADE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 6. Takip
CREATE TABLE IF NOT EXISTS follows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    follower_id INT NOT NULL,
    following_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_follow (follower_id, following_id),
    FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (following_id) REFERENCES users(id) ON DELETE CASCADE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 7. Konu takibi
CREATE TABLE IF NOT EXISTS topic_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    topic_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_sub (user_id, topic_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 8. Rozetler
CREATE TABLE IF NOT EXISTS badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) NOT NULL,
    icon VARCHAR(50) NOT NULL DEFAULT 'fas fa-award',
    color VARCHAR(7) DEFAULT '#6366f1',
    condition_type ENUM('post_count','topic_count','like_count','join_date','manual') NOT NULL,
    condition_value INT DEFAULT 0
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    badge_id INT NOT NULL,
    awarded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_badge (user_id, badge_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT IGNORE INTO badges (name, description, icon, color, condition_type, condition_value) VALUES
('Yeni Üye',     'Foruma hoş geldin!',  'fas fa-seedling',       '#10b981', 'join_date',  0),
('İlk Adım',     'İlk gönderini yaptın','fas fa-shoe-prints',    '#6366f1', 'post_count', 1),
('Aktif Üye',    '10 gönderi yaptın',   'fas fa-fire',           '#f59e0b', 'post_count', 10),
('Katkıcı',      '50 gönderi yaptın',   'fas fa-star',           '#f59e0b', 'post_count', 50),
('Forum Ustası', '100 gönderi yaptın',  'fas fa-crown',          '#ef4444', 'post_count', 100);

-- 9. Beğeniler
CREATE TABLE IF NOT EXISTS likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reply_id INT NOT NULL,
    user_id INT NOT NULL,
    UNIQUE KEY unique_like (reply_id, user_id),
    FOREIGN KEY (reply_id) REFERENCES replies(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 10. Raporlar
CREATE TABLE IF NOT EXISTS reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporter_id INT NOT NULL,
    topic_id INT DEFAULT NULL,
    reply_id INT DEFAULT NULL,
    reason ENUM('spam','hakaret','uygunsuz','yaniltici','diger') NOT NULL,
    description TEXT DEFAULT NULL,
    status ENUM('beklemede','inceleniyor','cozuldu','reddedildi') DEFAULT 'beklemede',
    resolved_by INT DEFAULT NULL,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 11. Unvanlar
CREATE TABLE IF NOT EXISTS titles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(7) DEFAULT '#6366f1',
    icon VARCHAR(50) DEFAULT 'fas fa-medal',
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_titles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title_id INT NOT NULL,
    assigned_by INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_title (user_id, title_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (title_id) REFERENCES titles(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT IGNORE INTO titles (name, color, icon) VALUES
('Çözüm Dehası',   '#10b981', 'fas fa-lightbulb'),
('Forum Efsanesi', '#f59e0b', 'fas fa-crown'),
('Yardımsever',    '#6366f1', 'fas fa-hands-helping'),
('Kod Gurusu',     '#0ea5e9', 'fas fa-code'),
('Topluluk Öncüsü','#8b5cf6', 'fas fa-flag');

-- Tüm tablolar oluşturuldu!
SELECT 'Güncelleme tamamlandı!' as durum;
