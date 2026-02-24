<?php
require_once 'config.php';

$slug = $_GET['slug'] ?? '';
$stmt = $pdo->prepare("SELECT t.*, u.username, u.avatar, u.post_count as author_posts, u.role as author_role, u.created_at as user_joined, u.signature, c.name as cat_name, c.slug as cat_slug FROM topics t JOIN users u ON t.user_id = u.id JOIN categories c ON t.category_id = c.id WHERE t.slug = ?");
$stmt->execute([$slug]);
$topic = $stmt->fetch();

if (!$topic) { flash('error','Konu bulunamadı.'); redirect('index.php'); }

// Görüntülenme artır
$pdo->prepare("UPDATE topics SET views = views + 1 WHERE id = ?")->execute([$topic['id']]);

// Sayfalama
$page     = max(1, intval($_GET['page'] ?? 1));
$perPage  = 15;
$offset   = ($page - 1) * $perPage;
$total    = $pdo->prepare("SELECT COUNT(*) FROM replies WHERE topic_id = ?"); $total->execute([$topic['id']]); $total = $total->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

$replies = $pdo->prepare("
    SELECT r.*, u.username, u.avatar, u.role, u.signature,
        (SELECT COUNT(*) FROM users WHERE id = r.user_id) > 0 as user_exists,
        (SELECT post_count FROM users WHERE id = r.user_id) as author_posts,
        (SELECT created_at FROM users WHERE id = r.user_id) as user_joined,
        (SELECT COUNT(*) FROM likes l WHERE l.reply_id = r.id) as like_count,
        (SELECT COUNT(*) FROM likes l WHERE l.reply_id = r.id AND l.user_id = ?) as user_liked
    FROM replies r JOIN users u ON r.user_id = u.id
    WHERE r.topic_id = ? ORDER BY r.created_at ASC LIMIT ? OFFSET ?
");
$replies->execute([$_SESSION['user_id'] ?? 0, $topic['id'], $perPage, $offset]);
$replies = $replies->fetchAll();

// Yorum ekle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn() && !$topic['is_locked']) {
    $content = trim($_POST['content'] ?? '');
    if (strlen($content) >= 5) {
        $replyStmt = $pdo->prepare("INSERT INTO replies (topic_id, user_id, content) VALUES (?, ?, ?)");
        $replyStmt->execute([$topic['id'], $_SESSION['user_id'], $content]);
        $newReplyId = $pdo->lastInsertId();

        // Sayaçlar
        $pdo->prepare("UPDATE topics SET reply_count = reply_count + 1, last_reply_at = NOW(), last_reply_user_id = ? WHERE id = ?")->execute([$_SESSION['user_id'], $topic['id']]);
        $pdo->prepare("UPDATE users SET post_count = post_count + 1 WHERE id = ?")->execute([$_SESSION['user_id']]);
        $pdo->prepare("UPDATE categories SET post_count = post_count + 1 WHERE id = ?")->execute([$topic['category_id']]);

        // Bu konuya otomatik abone ol
        $pdo->prepare("INSERT IGNORE INTO topic_subscriptions (user_id, topic_id) VALUES (?, ?)")->execute([$_SESSION['user_id'], $topic['id']]);

        // Konu açana bildirim (farklı kişiyse)
        $me = $pdo->prepare("SELECT username FROM users WHERE id = ?"); $me->execute([$_SESSION['user_id']]); $me = $me->fetchColumn();
        createNotification($pdo, $topic['user_id'], $_SESSION['user_id'], 'reply', $me . ' konunuza yorum yaptı: "' . mb_substr($topic['title'],0,40) . '"', $topic['id'], $newReplyId);

        // Takipçilere bildirim
        notifyTopicSubscribers($pdo, $topic['id'], $_SESSION['user_id'], $me . ' takip ettiğiniz konuya yorum yaptı: "' . mb_substr($topic['title'],0,40) . '"');

        // Rozet kontrolü
        checkAndAwardBadges($pdo, $_SESSION['user_id']);

        flash('success','Yorumunuz eklendi.');
        $lastPage = ceil(($total + 1) / $perPage);
        redirect("topic.php?slug={$slug}&page={$lastPage}#replies");
    }
}

// Takip et/bırak
if (isset($_GET['subscribe']) && isLoggedIn()) {
    $sub = $pdo->prepare("SELECT id FROM topic_subscriptions WHERE user_id=? AND topic_id=?");
    $sub->execute([$_SESSION['user_id'], $topic['id']]);
    if ($sub->fetch()) {
        $pdo->prepare("DELETE FROM topic_subscriptions WHERE user_id=? AND topic_id=?")->execute([$_SESSION['user_id'], $topic['id']]);
        flash('success','Konu takibini bıraktınız.');
    } else {
        $pdo->prepare("INSERT IGNORE INTO topic_subscriptions (user_id, topic_id) VALUES (?,?)")->execute([$_SESSION['user_id'], $topic['id']]);
        flash('success','Konu takibe alındı! Yeni yorumlarda bildirim alacaksınız.');
    }
    redirect("topic.php?slug={$slug}");
}

// Admin işlemleri
if (isAdmin() && isset($_GET['action'])) {
    $action = $_GET['action'];
    if ($action === 'pin')    $pdo->prepare("UPDATE topics SET is_pinned = !is_pinned WHERE id = ?")->execute([$topic['id']]);
    if ($action === 'lock')   $pdo->prepare("UPDATE topics SET is_locked = !is_locked WHERE id = ?")->execute([$topic['id']]);
    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM topics WHERE id = ?")->execute([$topic['id']]);
        $pdo->prepare("UPDATE categories SET topic_count = topic_count - 1 WHERE id = ?")->execute([$topic['category_id']]);
        flash('success','Konu silindi.'); redirect("category.php?slug={$topic['cat_slug']}");
    }
    flash('success','İşlem yapıldı.');
    redirect("topic.php?slug={$slug}");
}

// Çözüm işareti
if (isset($_GET['mark_solution']) && isLoggedIn() && $topic['user_id'] == $_SESSION['user_id']) {
    $pdo->prepare("UPDATE replies SET is_solution = 0 WHERE topic_id = ?")->execute([$topic['id']]);
    $pdo->prepare("UPDATE replies SET is_solution = 1 WHERE id = ? AND topic_id = ?")->execute([$_GET['mark_solution'], $topic['id']]);
    redirect("topic.php?slug={$slug}");
}

// Yorum sil
if (isset($_GET['delete_reply']) && isAdmin()) {
    $pdo->prepare("DELETE FROM replies WHERE id = ? AND topic_id = ?")->execute([$_GET['delete_reply'], $topic['id']]);
    $pdo->prepare("UPDATE topics SET reply_count = reply_count - 1 WHERE id = ? AND reply_count > 0")->execute([$topic['id']]);
    flash('success','Yorum silindi.'); redirect("topic.php?slug={$slug}");
}

// Kullanıcı bu konuyu takip ediyor mu?
$isSubscribed = false;
if (isLoggedIn()) {
    $subCheck = $pdo->prepare("SELECT id FROM topic_subscriptions WHERE user_id=? AND topic_id=?");
    $subCheck->execute([$_SESSION['user_id'], $topic['id']]);
    $isSubscribed = (bool)$subCheck->fetch();
}

$pageTitle = $topic['title'];
include 'includes/header.php';
?>

<div class="breadcrumb">
    <a href="<?= SITE_URL ?>">Anasayfa</a>
    <i class="fas fa-chevron-right"></i>
    <a href="<?= SITE_URL ?>/category.php?slug=<?= $topic['cat_slug'] ?>"><?= sanitize($topic['cat_name']) ?></a>
    <i class="fas fa-chevron-right"></i>
    <span><?= sanitize(mb_substr($topic['title'],0,50)) ?></span>
</div>

<!-- Başlık -->
<div class="topic-header-card">
    <div class="flex-between mb-2" style="flex-wrap:wrap;gap:8px">
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <?php if ($topic['is_pinned']): ?><span class="badge badge-pin"><i class="fas fa-thumbtack"></i> Sabitlenmiş</span><?php endif; ?>
            <?php if ($topic['is_locked']): ?><span class="badge badge-lock"><i class="fas fa-lock"></i> Kilitli</span><?php endif; ?>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <?php if (isLoggedIn()): ?>
            <a href="?slug=<?= $slug ?>&subscribe=1" class="btn btn-sm <?= $isSubscribed ? 'btn-ghost' : 'btn-outline' ?>">
                <i class="fas fa-<?= $isSubscribed ? 'bell-slash' : 'bell' ?>"></i>
                <?= $isSubscribed ? 'Takibi Bırak' : 'Takip Et' ?>
            </a>
            <?php endif; ?>
            <?php if (isAdmin()): ?>
            <a href="?slug=<?= $slug ?>&action=pin"  class="btn btn-sm btn-ghost" title="Sabitle/Kaldır"><i class="fas fa-thumbtack"></i></a>
            <a href="?slug=<?= $slug ?>&action=lock" class="btn btn-sm btn-ghost" title="Kilitle/Aç"><i class="fas fa-lock"></i></a>
            <a href="?slug=<?= $slug ?>&action=delete" class="btn btn-sm btn-danger" data-confirm="Konuyu silmek istediğinize emin misiniz?"><i class="fas fa-trash"></i></a>
            <?php endif; ?>
        </div>
    </div>
    <h1><?= sanitize($topic['title']) ?></h1>
    <div class="topic-meta" style="margin-top:12px">
        <span><i class="fas fa-user"></i>
            <a href="<?= SITE_URL ?>/profile.php?id=<?= $topic['user_id'] ?>" style="color:var(--primary)"><?= sanitize($topic['username']) ?></a>
        </span>
        <span>•</span>
        <span><i class="fas fa-clock"></i> <?= timeAgo($topic['created_at']) ?></span>
        <span>•</span>
        <span><i class="fas fa-eye"></i> <?= $topic['views'] ?> görüntüleme</span>
        <span>•</span>
        <span><i class="fas fa-comment"></i> <?= $topic['reply_count'] ?> yorum</span>
    </div>
</div>

<!-- Konu içeriği (ilk mesaj) -->
<div class="reply-card" id="replies">
    <div class="reply-author">
        <img src="<?= getAvatar(['username'=>$topic['username'],'avatar'=>$topic['avatar']]) ?>" alt="Avatar">
        <div class="reply-author-info">
            <strong><a href="<?= SITE_URL ?>/profile.php?id=<?= $topic['user_id'] ?>"><?= sanitize($topic['username']) ?></a></strong>
            <small>
                <?= $topic['author_role']==='admin' ? '<span style="color:var(--primary)">👑 Admin</span> • ' : '' ?>
                <?= $topic['author_posts'] ?> gönderi • Üye: <?= date('M Y', strtotime($topic['user_joined'])) ?>
            </small>
        </div>
        <small style="margin-left:auto;color:var(--text-muted)"><?= date('d.m.Y H:i', strtotime($topic['created_at'])) ?></small>
    </div>
    <div class="reply-content">
        <?= nl2br(sanitize($topic['content'])) ?>
    </div>
    <?php if (!empty($topic['signature'])): ?>
    <div style="padding:10px 20px;border-top:1px dashed var(--border);font-size:.82rem;color:var(--text-muted);font-style:italic">
        <?= nl2br(sanitize($topic['signature'])) ?>
    </div>
    <?php endif; ?>
    <div class="reply-actions">
        <button class="report-btn" data-topic-id="<?= $topic['id'] ?>" data-reply-id=""><i class="fas fa-flag"></i> Raporla</button>
    </div>
</div>

<!-- Yorumlar -->
<?php foreach ($replies as $reply): ?>
<div class="reply-card" id="reply-<?= $reply['id'] ?>" <?= $reply['is_solution'] ? 'style="border-color:var(--success)"' : '' ?>>
    <div class="reply-author">
        <img src="<?= getAvatar(['username'=>$reply['username'],'avatar'=>$reply['avatar']]) ?>" alt="Avatar">
        <div class="reply-author-info">
            <strong><a href="<?= SITE_URL ?>/profile.php?id=<?= $reply['user_id'] ?>"><?= sanitize($reply['username']) ?></a></strong>
            <small>
                <?= $reply['role']==='admin' ? '<span style="color:var(--primary)">👑 Admin</span> • ' : '' ?>
                <?= $reply['author_posts'] ?> gönderi
            </small>
        </div>
        <div style="margin-left:auto;display:flex;align-items:center;gap:12px">
            <?php if ($reply['is_solution']): ?>
                <span class="solution-badge"><i class="fas fa-check-circle"></i> Çözüm</span>
            <?php endif; ?>
            <small style="color:var(--text-muted)"><?= date('d.m.Y H:i', strtotime($reply['created_at'])) ?></small>
        </div>
    </div>
    <div class="reply-content">
        <?= nl2br(sanitize($reply['content'])) ?>
    </div>
    <?php if (!empty($reply['signature'])): ?>
    <div style="padding:10px 20px;border-top:1px dashed var(--border);font-size:.82rem;color:var(--text-muted);font-style:italic">
        <?= nl2br(sanitize($reply['signature'])) ?>
    </div>
    <?php endif; ?>
    <div class="reply-actions">
        <?php if (isLoggedIn()): ?>
        <button class="like-btn <?= $reply['user_liked'] ? 'liked' : '' ?>" data-reply-id="<?= $reply['id'] ?>">
            <i class="fas fa-heart"></i>
            <span class="like-count"><?= $reply['like_count'] ?></span>
        </button>
        <?php if ($topic['user_id'] == $_SESSION['user_id'] && !$reply['is_solution']): ?>
        <a href="?slug=<?= $slug ?>&mark_solution=<?= $reply['id'] ?>" class="btn btn-sm btn-success">
            <i class="fas fa-check"></i> Çözüm Olarak İşaretle
        </a>
        <?php endif; ?>
        <?php endif; ?>
        <button class="report-btn" data-topic-id="<?= $topic['id'] ?>" data-reply-id="<?= $reply['id'] ?>"><i class="fas fa-flag"></i> Raporla</button>
        <?php if (isAdmin()): ?>
        <a href="?slug=<?= $slug ?>&delete_reply=<?= $reply['id'] ?>" class="btn btn-sm btn-danger" data-confirm="Yorumu silmek istediğinize emin misiniz?" style="margin-left:auto">
            <i class="fas fa-trash"></i>
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<?php if ($totalPages > 1): ?>
<div class="pagination">
    <?php if ($page > 1): ?><a href="?slug=<?= $slug ?>&page=<?= $page-1 ?>#replies"><i class="fas fa-chevron-left"></i></a><?php endif; ?>
    <?php for ($i=1; $i<=$totalPages; $i++): ?>
        <?php if ($i==$page): ?><span class="active"><?= $i ?></span>
        <?php else: ?><a href="?slug=<?= $slug ?>&page=<?= $i ?>#replies"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
    <?php if ($page < $totalPages): ?><a href="?slug=<?= $slug ?>&page=<?= $page+1 ?>#replies"><i class="fas fa-chevron-right"></i></a><?php endif; ?>
</div>
<?php endif; ?>

<!-- Yorum Formu -->
<?php if (isLoggedIn() && !$topic['is_locked']): ?>
<div class="card mt-3" id="reply-form">
    <h3 style="margin-bottom:16px"><i class="fas fa-reply" style="color:var(--primary)"></i> Yorum Yaz</h3>
    <form method="POST">
        <div class="form-group">
            <textarea id="richEditor" name="content" placeholder="Yorumunuzu yazın..." rows="5" required minlength="5"></textarea>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Gönder</button>
    </form>
</div>
<?php elseif (!isLoggedIn()): ?>
<div class="card mt-3 text-center">
    <p style="color:var(--secondary);margin-bottom:16px">Yorum yapmak için giriş yapmalısınız.</p>
    <a href="<?= SITE_URL ?>/login.php" class="btn btn-primary"><i class="fas fa-sign-in-alt"></i> Giriş Yap</a>
    <a href="<?= SITE_URL ?>/register.php" class="btn btn-outline">Kayıt Ol</a>
</div>
<?php elseif ($topic['is_locked']): ?>
<div class="alert alert-info mt-3"><i class="fas fa-lock"></i> Bu konu kilitlenmiştir.</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
