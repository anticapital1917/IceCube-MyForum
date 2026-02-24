<?php
require_once 'config.php';

$slug = $_GET['slug'] ?? '';
$stmt = $pdo->prepare("SELECT * FROM categories WHERE slug = ?");
$stmt->execute([$slug]);
$category = $stmt->fetch();

if (!$category) {
    flash('error', 'Kategori bulunamadı.');
    redirect('index.php');
}

$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM topics WHERE category_id = ?");
$totalStmt->execute([$category['id']]);
$total = $totalStmt->fetchColumn();
$totalPages = ceil($total / $perPage);

$topics = $pdo->prepare("
    SELECT t.*, u.username, u.avatar,
        lu.username as last_reply_username
    FROM topics t
    JOIN users u ON t.user_id = u.id
    LEFT JOIN users lu ON t.last_reply_user_id = lu.id
    WHERE t.category_id = ?
    ORDER BY t.is_pinned DESC, COALESCE(t.last_reply_at, t.created_at) DESC
    LIMIT ? OFFSET ?
");
$topics->execute([$category['id'], $perPage, $offset]);
$topics = $topics->fetchAll();

$pageTitle = $category['name'];
include 'includes/header.php';
?>

<div class="breadcrumb">
    <a href="<?= SITE_URL ?>">Anasayfa</a>
    <i class="fas fa-chevron-right"></i>
    <span><?= sanitize($category['name']) ?></span>
</div>

<div class="flex-between mb-3">
    <div class="page-header" style="margin:0;">
        <h1 style="display:flex;align-items:center;gap:12px;">
            <span style="width:44px;height:44px;border-radius:12px;background:<?= sanitize($category['color']) ?>;display:flex;align-items:center;justify-content:center;color:white;">
                <i class="<?= sanitize($category['icon']) ?>"></i>
            </span>
            <?= sanitize($category['name']) ?>
        </h1>
        <p><?= sanitize($category['description']) ?></p>
    </div>
    <?php if (isLoggedIn()): ?>
        <a href="<?= SITE_URL ?>/topic_new.php?cat=<?= $category['id'] ?>" class="btn btn-primary">
            <i class="fas fa-plus"></i> Yeni Konu
        </a>
    <?php endif; ?>
</div>

<div class="topics-list">
    <?php foreach ($topics as $topic): ?>
    <div class="topic-row <?= $topic['is_pinned'] ? 'pinned' : '' ?> <?= $topic['is_locked'] ? 'locked' : '' ?>">
        <div class="topic-icon">
            <i class="fas fa-<?= $topic['is_pinned'] ? 'thumbtack' : ($topic['is_locked'] ? 'lock' : 'comment-alt') ?>"></i>
        </div>
        <div class="topic-main">
            <div class="topic-title">
                <a href="<?= SITE_URL ?>/topic.php?slug=<?= $topic['slug'] ?>"><?= sanitize($topic['title']) ?></a>
            </div>
            <div class="topic-badges">
                <?php if ($topic['is_pinned']): ?><span class="badge badge-pin"><i class="fas fa-thumbtack"></i> Sabitlenmiş</span><?php endif; ?>
                <?php if ($topic['is_locked']): ?><span class="badge badge-lock"><i class="fas fa-lock"></i> Kilitli</span><?php endif; ?>
            </div>
            <div class="topic-meta">
                <span><?= sanitize($topic['username']) ?></span>
                <span>•</span>
                <span><?= timeAgo($topic['created_at']) ?></span>
                <?php if ($topic['last_reply_username']): ?>
                    <span>•</span>
                    <span>Son: <strong><?= sanitize($topic['last_reply_username']) ?></strong></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="topic-stats">
            <div><span><?= $topic['views'] ?></span><span>görüntüleme</span></div>
            <div><span><?= $topic['reply_count'] ?></span><span>yorum</span></div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($topics)): ?>
    <div class="empty-state card">
        <i class="fas fa-comment-slash"></i>
        <h3>Bu kategoride henüz konu yok</h3>
        <p>İlk konuyu sen aç!</p>
        <?php if (isLoggedIn()): ?>
            <a href="<?= SITE_URL ?>/topic_new.php?cat=<?= $category['id'] ?>" class="btn btn-primary mt-2">Konu Aç</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php if ($totalPages > 1): ?>
<div class="pagination">
    <?php if ($page > 1): ?><a href="?slug=<?= $slug ?>&page=<?= $page-1 ?>"><i class="fas fa-chevron-left"></i></a><?php endif; ?>
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <?php if ($i == $page): ?>
            <span class="active"><?= $i ?></span>
        <?php else: ?>
            <a href="?slug=<?= $slug ?>&page=<?= $i ?>"><?= $i ?></a>
        <?php endif; ?>
    <?php endfor; ?>
    <?php if ($page < $totalPages): ?><a href="?slug=<?= $slug ?>&page=<?= $page+1 ?>"><i class="fas fa-chevron-right"></i></a><?php endif; ?>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
