-- Forum Veritabanı Yapısı
SET NAMES utf8mb4;
SET character_set_client = utf8mb4;
SET character_set_connection = utf8mb4;
SET character_set_results = utf8mb4;
SET collation_connection = utf8mb4_unicode_ci;

CREATE DATABASE IF NOT EXISTS forum_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE forum_db;

-- Kullanıcılar tablosu
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    avatar VARCHAR(255) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    post_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

-- Kategoriler tablosu
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    icon VARCHAR(50) DEFAULT 'fas fa-folder',
    color VARCHAR(7) DEFAULT '#6366f1',
    topic_count INT DEFAULT 0,
    post_count INT DEFAULT 0,
    order_num INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Konular tablosu
CREATE TABLE topics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    content LONGTEXT NOT NULL,
    views INT DEFAULT 0,
    reply_count INT DEFAULT 0,
    is_pinned TINYINT(1) DEFAULT 0,
    is_locked TINYINT(1) DEFAULT 0,
    last_reply_at TIMESTAMP NULL,
    last_reply_user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Yorumlar tablosu
CREATE TABLE replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    topic_id INT NOT NULL,
    user_id INT NOT NULL,
    content LONGTEXT NOT NULL,
    is_solution TINYINT(1) DEFAULT 0,
    likes INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Beğeniler tablosu
CREATE TABLE likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reply_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_like (reply_id, user_id),
    FOREIGN KEY (reply_id) REFERENCES replies(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Örnek veriler
INSERT INTO users (username, email, password, role) VALUES
('admin', 'admin@forum.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('kullanici1', 'kullanici1@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user');
-- Şifre: password

INSERT INTO categories (name, slug, description, icon, color, order_num) VALUES
('Genel Tartışma', 'genel-tartisma', 'Her türlü konuyu burda tartışabilirsiniz', 'fas fa-comments', '#6366f1', 1),
('Teknoloji', 'teknoloji', 'Yazılım, donanım ve teknoloji haberleri', 'fas fa-microchip', '#0ea5e9', 2),
('Oyunlar', 'oyunlar', 'Oyun haberleri, incelemeler ve tartışmalar', 'fas fa-gamepad', '#10b981', 3),
('Yardım & Destek', 'yardim-destek', 'Sorularınızı buraya sorabilirsiniz', 'fas fa-life-ring', '#f59e0b', 4),
('Duyurular', 'duyurular', 'Forum ile ilgili önemli duyurular', 'fas fa-bullhorn', '#ef4444', 5);

INSERT INTO topics (category_id, user_id, title, slug, content, views, reply_count) VALUES
(1, 1, 'Foruma Hoş Geldiniz!', 'foruma-hos-geldiniz', 'Forumumuza hoş geldiniz! Burada her türlü konuyu tartışabilirsiniz. Lütfen kurallara uyun ve saygılı olun.', 150, 2),
(2, 2, 'PHP 8.3 Yenilikleri', 'php-83-yenilikleri', 'PHP 8.3 ile gelen önemli yenilikler hakkında konuşalım. Typed class constants, json_validate() fonksiyonu ve daha fazlası!', 89, 1);

INSERT INTO replies (topic_id, user_id, content) VALUES
(1, 2, 'Teşekkürler! Çok güzel bir forum olmuş.'),
(1, 1, 'Hoş bulduk! Sorularınız için yardım kategorisini kullanabilirsiniz.'),
(2, 1, 'PHP 8.3 gerçekten harika özellikler getirdi. Özellikle typed constants çok işe yarıyor.');
