<?php
require_once 'config.php';

// İstatistikler
$stats = $pdo->query("SELECT 
    (SELECT COUNT(*) FROM users) as user_count,
    (SELECT COUNT(*) FROM topics) as topic_count,
    (SELECT COUNT(*) FROM replies) as reply_count,
    (SELECT COUNT(*) FROM categories) as cat_count
")->fetch();

// Kategoriler
$categories = $pdo->query("
    SELECT c.*, 
        (SELECT t.title FROM topics t WHERE t.category_id = c.id ORDER BY t.created_at DESC LIMIT 1) as last_topic_title,
        (SELECT t.id FROM topics t WHERE t.category_id = c.id ORDER BY t.created_at DESC LIMIT 1) as last_topic_id,
        (SELECT u.username FROM users u JOIN topics t ON t.user_id = u.id WHERE t.category_id = c.id ORDER BY t.created_at DESC LIMIT 1) as last_topic_user
    FROM categories c ORDER BY c.order_num ASC
")->fetchAll();

// Son konular
$recent_topics = $pdo->query("
    SELECT t.*, u.username, c.name as cat_name, c.slug as cat_slug
    FROM topics t
    JOIN users u ON t.user_id = u.id
    JOIN categories c ON t.category_id = c.id
    ORDER BY t.created_at DESC LIMIT 6
")->fetchAll();

$pageTitle = 'Anasayfa';
include 'includes/header.php';
?>

<!-- Hero -->
<div class="hero">
    <h1><i class="fas fa-comments"></i> <?= SITE_NAME ?>'a Hoş Geldiniz!</h1>
    <p>Fikir paylaşın, soru sorun, topluluğa katılın.</p>
    <div class="hero-btns">
        <?php if (!isLoggedIn()): ?>
            <a href="<?= SITE_URL ?>/register.php" class="btn btn-primary" style="background:white;color:var(--primary);">
                <i class="fas fa-user-plus"></i> Hemen Kayıt Ol
            </a>
            <a href="<?= SITE_URL ?>/login.php" class="btn btn-outline" style="border-color:rgba(255,255,255,.6);color:white;">
                <i class="fas fa-sign-in-alt"></i> Giriş Yap
            </a>
        <?php else: ?>
            <a href="<?= SITE_URL ?>/topic_new.php" class="btn" style="background:white;color:var(--primary);">
                <i class="fas fa-plus"></i> Yeni Konu Aç
            </a>
        <?php endif; ?>
    </div>
    <div class="hero-stats">
        <div class="hero-stat"><div class="num"><?= number_format($stats['user_count']) ?></div><div class="label">Üye</div></div>
        <div class="hero-stat"><div class="num"><?= number_format($stats['topic_count']) ?></div><div class="label">Konu</div></div>
        <div class="hero-stat"><div class="num"><?= number_format($stats['reply_count']) ?></div><div class="label">Yorum</div></div>
        <div class="hero-stat"><div class="num"><?= number_format($stats['cat_count']) ?></div><div class="label">Kategori</div></div>
    </div>
</div>

<!-- Kategoriler -->
<div class="flex-between mb-2">
    <h2 style="font-size:1.3rem;font-weight:700;"><i class="fas fa-th-large" style="color:var(--primary);margin-right:8px;"></i>Kategoriler</h2>
</div>

<div class="categories-grid mb-3">
    <?php foreach ($categories as $cat): ?>
    <a href="<?= SITE_URL ?>/category.php?slug=<?= $cat['slug'] ?>" class="category-card" style="text-decoration:none;">
        <div class="category-icon" style="background:<?= sanitize($cat['color']) ?>;">
            <i class="<?= sanitize($cat['icon']) ?>"></i>
        </div>
        <div class="category-info">
            <h3><span><?= sanitize($cat['name']) ?></span></h3>
            <p><?= sanitize($cat['description']) ?></p>
            <div class="category-stats">
                <span><i class="fas fa-file-alt"></i> <?= $cat['topic_count'] ?> konu</span>
                <span><i class="fas fa-comment"></i> <?= $cat['post_count'] ?> yorum</span>
            </div>
            <?php if ($cat['last_topic_title']): ?>
            <div style="margin-top:8px;font-size:.8rem;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                <i class="fas fa-clock"></i> <?= sanitize($cat['last_topic_title']) ?>
            </div>
            <?php endif; ?>
        </div>
    </a>
    <?php endforeach; ?>
</div>

<!-- Son Konular -->
<div class="flex-between mb-2">
    <h2 style="font-size:1.3rem;font-weight:700;"><i class="fas fa-clock" style="color:var(--primary);margin-right:8px;"></i>Son Konular</h2>
</div>
<div class="topics-list">
    <?php foreach ($recent_topics as $topic): ?>
    <div class="topic-row <?= $topic['is_pinned'] ? 'pinned' : '' ?> <?= $topic['is_locked'] ? 'locked' : '' ?>">
        <div class="topic-icon">
            <i class="fas fa-<?= $topic['is_pinned'] ? 'thumbtack' : ($topic['is_locked'] ? 'lock' : 'comment-alt') ?>"></i>
        </div>
        <div class="topic-main">
            <div class="topic-title">
                <a href="<?= SITE_URL ?>/topic.php?slug=<?= $topic['slug'] ?>"><?= sanitize($topic['title']) ?></a>
            </div>
            <div class="topic-meta">
                <span><?= sanitize($topic['username']) ?></span>
                <span>•</span>
                <span class="badge badge-cat"><?= sanitize($topic['cat_name']) ?></span>
                <span>•</span>
                <span><?= timeAgo($topic['created_at']) ?></span>
            </div>
        </div>
        <div class="topic-stats">
            <div><span><?= $topic['views'] ?></span><span>görüntüleme</span></div>
            <div><span><?= $topic['reply_count'] ?></span><span>yorum</span></div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($recent_topics)): ?>
    <div class="empty-state card">
        <i class="fas fa-comments"></i>
        <h3>Henüz konu yok</h3>
        <p>İlk konuyu sen aç!</p>
        <?php if (isLoggedIn()): ?>
            <a href="<?= SITE_URL ?>/topic_new.php" class="btn btn-primary mt-2">Konu Aç</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
