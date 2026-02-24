<?php
require_once 'config.php';

// AJAX isteği
if (isset($_GET['ajax']) && isset($_GET['q'])) {
    header('Content-Type: application/json; charset=utf-8');
    $q = '%' . trim($_GET['q']) . '%';
    $results = $pdo->prepare("
        SELECT t.id, t.title, t.slug, t.reply_count, t.views, u.username, c.name as cat_name
        FROM topics t JOIN users u ON t.user_id=u.id JOIN categories c ON t.category_id=c.id
        WHERE t.title LIKE ? OR t.content LIKE ?
        ORDER BY t.created_at DESC LIMIT 8
    ");
    $results->execute([$q, $q]);
    echo json_encode($results->fetchAll());
    exit();
}

$q = trim($_GET['q'] ?? '');
$results = [];
if (strlen($q) >= 2) {
    $like = '%' . $q . '%';
    $stmt = $pdo->prepare("
        SELECT t.*, u.username, c.name as cat_name, c.slug as cat_slug
        FROM topics t JOIN users u ON t.user_id=u.id JOIN categories c ON t.category_id=c.id
        WHERE t.title LIKE ? OR t.content LIKE ?
        ORDER BY t.created_at DESC LIMIT 30
    ");
    $stmt->execute([$like, $like]);
    $results = $stmt->fetchAll();
}

$pageTitle = 'Arama' . ($q ? ": $q" : '');
include 'includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-search" style="color:var(--primary)"></i> Arama</h1>
</div>

<div style="max-width:680px;margin-bottom:28px">
    <form method="GET" style="display:flex;gap:10px">
        <div class="form-group" style="flex:1;margin:0">
            <input type="text" name="q" value="<?= sanitize($q) ?>" placeholder="Konu veya içerik ara..." style="border-radius:20px">
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Ara</button>
    </form>
</div>

<?php if ($q): ?>
<p class="text-muted mb-2">"<strong><?= sanitize($q) ?></strong>" için <?= count($results) ?> sonuç bulundu</p>

<?php if (empty($results)): ?>
<div class="empty-state card"><i class="fas fa-search"></i><h3>Sonuç bulunamadı</h3><p>Farklı anahtar kelimeler deneyin.</p></div>
<?php else: ?>
<div class="topics-list">
    <?php foreach ($results as $t): ?>
    <div class="topic-row">
        <div class="topic-icon"><i class="fas fa-file-alt"></i></div>
        <div class="topic-main">
            <div class="topic-title"><a href="<?= SITE_URL ?>/topic.php?slug=<?= $t['slug'] ?>"><?= sanitize($t['title']) ?></a></div>
            <div class="topic-meta">
                <span><?= sanitize($t['username']) ?></span>
                <span>•</span>
                <span class="badge badge-cat"><?= sanitize($t['cat_name']) ?></span>
                <span>•</span>
                <span><?= timeAgo($t['created_at']) ?></span>
            </div>
        </div>
        <div class="topic-stats">
            <div><span><?= $t['reply_count'] ?></span><span>yorum</span></div>
            <div><span><?= $t['views'] ?></span><span>görüntüleme</span></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
