<?php
require_once 'config.php';

$id = intval($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT u.*, (SELECT COUNT(*) FROM replies WHERE user_id = u.id) as real_post_count FROM users u WHERE u.id=?");
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user) { flash('error','Kullanıcı bulunamadı.'); redirect('index.php'); }

$isFollowing = false;
if (isLoggedIn() && $_SESSION['user_id'] != $id) {
    $fs = $pdo->prepare("SELECT id FROM follows WHERE follower_id=? AND following_id=?");
    $fs->execute([$_SESSION['user_id'], $id]);
    $isFollowing = (bool)$fs->fetch();
}

$followerCount  = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE following_id=?"); $followerCount->execute([$id]);  $followerCount=$followerCount->fetchColumn();
$followingCount = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE follower_id=?");  $followingCount->execute([$id]); $followingCount=$followingCount->fetchColumn();
$likeCount      = $pdo->prepare("SELECT COUNT(*) FROM likes l JOIN replies r ON l.reply_id=r.id WHERE r.user_id=?"); $likeCount->execute([$id]); $likeCount=$likeCount->fetchColumn();

checkAndAwardBadges($pdo, $id);

$badges = $pdo->prepare("SELECT b.* FROM user_badges ub JOIN badges b ON ub.badge_id=b.id WHERE ub.user_id=? ORDER BY ub.awarded_at ASC");
$badges->execute([$id]);
$badges = $badges->fetchAll();

$topics = $pdo->prepare("SELECT t.*,c.name as cat_name,c.slug as cat_slug FROM topics t JOIN categories c ON t.category_id=c.id WHERE t.user_id=? ORDER BY t.created_at DESC LIMIT 10");
$topics->execute([$id]);
$topics = $topics->fetchAll();

// Unvanlar
$titleStmt = $pdo->prepare("SELECT t.* FROM user_titles ut JOIN titles t ON ut.title_id=t.id WHERE ut.user_id=? ORDER BY ut.assigned_at ASC");
$titleStmt->execute([$id]);
$userTitles = $titleStmt->fetchAll();

$pageTitle = $user['username'] . ' - Profil';
include 'includes/header.php';
?>

<div class="layout-with-sidebar">
<div>
    <div class="card mb-3">
        <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
            <img src="<?= getAvatar($user) ?>" alt="Avatar" style="width:90px;height:90px;border-radius:50%;object-fit:cover;flex-shrink:0">
            <div style="flex:1">
                <div class="flex-between" style="align-items:flex-start;flex-wrap:wrap;gap:12px">
                    <div>
                        <h2 style="font-size:1.5rem"><?= sanitize($user['username']) ?></h2>
                        <?php if ($user['role']==='admin'): ?><span class="badge" style="background:var(--primary-light);color:var(--primary)">👑 Admin</span><?php endif; ?>
                        <?php if (!empty($user['location'])): ?><p style="color:var(--text-muted);font-size:.85rem;margin-top:4px"><i class="fas fa-map-marker-alt"></i> <?= sanitize($user['location']) ?></p><?php endif; ?>
                        <?php if (!empty($user['website'])): ?><p style="font-size:.85rem;margin-top:4px"><i class="fas fa-globe"></i> <a href="<?= sanitize($user['website']) ?>" target="_blank" style="color:var(--primary)"><?= sanitize($user['website']) ?></a></p><?php endif; ?>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <?php if (isLoggedIn() && $_SESSION['user_id'] != $id): ?>
                        <button class="follow-btn <?= $isFollowing?'following':'not-following' ?>" id="followBtn" data-uid="<?= $id ?>">
                            <i class="fas fa-<?= $isFollowing?'user-check':'user-plus' ?>"></i>
                            <span id="followText"><?= $isFollowing?'Takip Ediliyor':'Takip Et' ?></span>
                        </button>
                        <a href="<?= SITE_URL ?>/messages.php?new=<?= $id ?>" class="btn btn-outline btn-sm"><i class="fas fa-envelope"></i> Mesaj</a>
                    <?php elseif (isLoggedIn()): ?>
                        <a href="<?= SITE_URL ?>/profile_edit.php" class="btn btn-ghost btn-sm"><i class="fas fa-edit"></i> Profili Düzenle</a>
                    <?php endif; ?>
                    </div>
                </div>
                <?php if (!empty($user['bio'])): ?><p style="margin-top:12px;color:var(--secondary)"><?= sanitize($user['bio']) ?></p><?php endif; ?>
                <?php if (!empty($badges)): ?>
                <div style="margin-top:14px;display:flex;flex-wrap:wrap;gap:4px">
                    <?php foreach ($badges as $b): ?>
                    <span class="badge-item" style="background:<?= sanitize($b['color']) ?>22;color:<?= sanitize($b['color']) ?>" title="<?= sanitize($b['description']) ?>">
                        <i class="<?= sanitize($b['icon']) ?>"></i> <?= sanitize($b['name']) ?>
                    </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($userTitles)): ?>
                <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:6px">
                    <?php foreach ($userTitles as $ut): ?>
                    <span style="display:inline-flex;align-items:center;gap:5px;background:<?= sanitize($ut['color']) ?>22;color:<?= sanitize($ut['color']) ?>;border:1px solid <?= sanitize($ut['color']) ?>44;padding:4px 12px;border-radius:20px;font-size:.82rem;font-weight:700" title="Özel Unvan">
                        <i class="<?= sanitize($ut['icon']) ?>"></i> <?= sanitize($ut['name']) ?>
                    </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <h3 style="margin-bottom:12px;font-size:1.1rem">Son Konular</h3>
    <div class="topics-list">
        <?php foreach ($topics as $topic): ?>
        <div class="topic-row">
            <div class="topic-icon"><i class="fas fa-comment-alt"></i></div>
            <div class="topic-main">
                <div class="topic-title"><a href="<?= SITE_URL ?>/topic.php?slug=<?= $topic['slug'] ?>"><?= sanitize($topic['title']) ?></a></div>
                <div class="topic-meta">
                    <span class="badge badge-cat"><?= sanitize($topic['cat_name']) ?></span>
                    <span><?= timeAgo($topic['created_at']) ?></span>
                </div>
            </div>
            <div class="topic-stats"><div><span><?= $topic['reply_count'] ?></span><span>yorum</span></div></div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($topics)): ?><div class="empty-state card"><i class="fas fa-comments"></i><h3>Henüz konu yok</h3></div><?php endif; ?>
    </div>
</div>

<div>
    <div class="sidebar-card">
        <h3>İstatistikler</h3>
        <div class="stat-row"><span>Gönderi</span><span><?= $user['real_post_count'] ?></span></div>
        <div class="stat-row"><span>Beğeni Aldı</span><span><?= $likeCount ?></span></div>
        <div class="stat-row"><span>Takipçi</span><span id="followerCount"><?= $followerCount ?></span></div>
        <div class="stat-row"><span>Takip Edilen</span><span><?= $followingCount ?></span></div>
        <div class="stat-row"><span>Üyelik</span><span><?= date('M Y', strtotime($user['created_at'])) ?></span></div>
    </div>

    <?php if (!empty($badges)): ?>
    <div class="sidebar-card">
        <h3>Rozetler</h3>
        <div style="display:flex;flex-direction:column;gap:10px">
            <?php foreach ($badges as $b): ?>
            <div style="display:flex;align-items:center;gap:10px">
                <div style="width:32px;height:32px;border-radius:8px;background:<?= sanitize($b['color']) ?>22;display:flex;align-items:center;justify-content:center;color:<?= sanitize($b['color']) ?>"><i class="<?= sanitize($b['icon']) ?>"></i></div>
                <div><div style="font-weight:600;font-size:.85rem"><?= sanitize($b['name']) ?></div><div style="font-size:.75rem;color:var(--text-muted)"><?= sanitize($b['description']) ?></div></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
</div>

<script>
document.getElementById('followBtn')?.addEventListener('click', async function() {
    const res = await fetch('<?= SITE_URL ?>/follow.php', {method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'user_id='+this.dataset.uid});
    const data = await res.json();
    if (data.success) {
        this.classList.toggle('following', data.following);
        this.classList.toggle('not-following', !data.following);
        document.getElementById('followText').textContent = data.following ? 'Takip Ediliyor' : 'Takip Et';
        this.querySelector('i').className = 'fas fa-' + (data.following ? 'user-check' : 'user-plus');
        document.getElementById('followerCount').textContent = data.count;
    }
});
</script>
<?php include 'includes/footer.php'; ?>
