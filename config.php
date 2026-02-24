<?php
// Veritabanı Ayarları
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'forum_db');

// SITE_URL otomatik algılanır
$_protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_script = $_SERVER['SCRIPT_NAME'] ?? '';
$_base = '';
if (preg_match('#^(.*?/forum)#i', $_script, $m)) { $_base = $m[1]; }
else { $_base = rtrim(dirname($_script), '/'); }
define('SITE_URL', $_protocol . '://' . $_host . $_base);

// PHP encoding UTF-8
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
if (!headers_sent()) { header('Content-Type: text/html; charset=UTF-8'); }

// Oturum başlat
session_start();

// Veritabanı bağlantısı
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ]
    );
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("SET CHARACTER SET utf8mb4");
} catch (PDOException $e) {
    die('<div style="font-family:sans-serif;padding:20px;background:#fee2e2;border:1px solid #ef4444;border-radius:8px;margin:20px;">
        <h3 style="color:#dc2626;">Veritabanı Bağlantı Hatası</h3>
        <p>config.php dosyasındaki veritabanı bilgilerini kontrol edin.</p>
        <small>' . $e->getMessage() . '</small>
    </div>');
}

// Site ayarlarını DB'den çek (site_settings tablosu varsa)
function getSiteSettings($pdo) {
    try {
        $rows = $pdo->query("SELECT `key`, `value` FROM site_settings")->fetchAll();
        $s = [];
        foreach ($rows as $r) $s[$r['key']] = $r['value'];
        return $s;
    } catch (Exception $e) {
        return [];
    }
}
$_settings = getSiteSettings($pdo);
define('SITE_NAME', $_settings['site_name'] ?? 'ForumTR');
define('SITE_DESC', $_settings['site_desc'] ?? 'Türkçe Forum Platformu');
define('SITE_FAVICON', $_settings['favicon_url'] ?? '');

// Yardımcı fonksiyonlar
function isLoggedIn()  { return isset($_SESSION['user_id']); }
function isAdmin()     { return isset($_SESSION['role']) && $_SESSION['role'] === 'admin'; }

function getCurrentUser() {
    global $pdo;
    if (!isLoggedIn()) return null;
    $s = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $s->execute([$_SESSION['user_id']]);
    return $s->fetch();
}

function redirect($url) {
    header("Location: " . SITE_URL . "/" . $url);
    exit();
}

function sanitize($str) {
    return htmlspecialchars(trim($str ?? ''), ENT_QUOTES, 'UTF-8');
}

function createSlug($text) {
    $text = mb_strtolower($text, 'UTF-8');
    $tr = ['ş'=>'s','ğ'=>'g','ü'=>'u','ı'=>'i','ö'=>'o','ç'=>'c','Ş'=>'s','Ğ'=>'g','Ü'=>'u','İ'=>'i','Ö'=>'o','Ç'=>'c'];
    $text = strtr($text, $tr);
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-') . '-' . time();
}

function timeAgo($datetime) {
    $now = new DateTime(); $ago = new DateTime($datetime); $diff = $now->diff($ago);
    if ($diff->y > 0) return $diff->y . ' yıl önce';
    if ($diff->m > 0) return $diff->m . ' ay önce';
    if ($diff->d > 0) return $diff->d . ' gün önce';
    if ($diff->h > 0) return $diff->h . ' saat önce';
    if ($diff->i > 0) return $diff->i . ' dakika önce';
    return 'Az önce';
}

function getAvatar($user) {
    if (!empty($user['avatar'])) return SITE_URL . '/assets/uploads/' . $user['avatar'];
    $initial = mb_substr($user['username'], 0, 1, 'UTF-8');
    return "https://ui-avatars.com/api/?name=" . urlencode($initial) . "&background=6366f1&color=fff&size=80";
}

function flash($type, $message) { $_SESSION['flash'] = ['type' => $type, 'message' => $message]; }

function getFlash() {
    if (isset($_SESSION['flash'])) { $f = $_SESSION['flash']; unset($_SESSION['flash']); return $f; }
    return null;
}

function createNotification($pdo, $userId, $fromUserId, $type, $message, $topicId = null, $replyId = null) {
    if ($userId == $fromUserId) return;
    $pdo->prepare("INSERT INTO notifications (user_id, from_user_id, type, topic_id, reply_id, message) VALUES (?, ?, ?, ?, ?, ?)")
        ->execute([$userId, $fromUserId, $type, $topicId, $replyId, $message]);
}

function getUnreadNotifications($pdo, $userId) {
    $s = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $s->execute([$userId]); return $s->fetchColumn();
}

function getUnreadMessages($pdo, $userId) {
    $s = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE to_user_id = ? AND is_read = 0 AND deleted_by_receiver = 0");
    $s->execute([$userId]); return $s->fetchColumn();
}

function checkAndAwardBadges($pdo, $userId) {
    $u = $pdo->prepare("SELECT post_count FROM users WHERE id = ?"); $u->execute([$userId]); $u = $u->fetch();
    if (!$u) return;
    $badges = $pdo->query("SELECT * FROM badges WHERE condition_type = 'post_count'")->fetchAll();
    foreach ($badges as $b) {
        if ($u['post_count'] >= $b['condition_value'])
            $pdo->prepare("INSERT IGNORE INTO user_badges (user_id, badge_id) VALUES (?, ?)")->execute([$userId, $b['id']]);
    }
}

function isDarkMode() { return isset($_SESSION['dark_mode']) && $_SESSION['dark_mode']; }

// Konu takipçilerine bildirim gönder
function notifyTopicSubscribers($pdo, $topicId, $fromUserId, $message) {
    $subs = $pdo->prepare("SELECT user_id FROM topic_subscriptions WHERE topic_id = ? AND user_id != ?");
    $subs->execute([$topicId, $fromUserId]);
    foreach ($subs->fetchAll() as $sub) {
        createNotification($pdo, $sub['user_id'], $fromUserId, 'reply', $message, $topicId);
    }
}
