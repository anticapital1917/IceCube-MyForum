<?php
require_once 'config.php';
if (!isLoggedIn()) { flash('error','Giriş yapmalısınız.'); redirect('login.php'); }

$uid = $_SESSION['user_id'];

// Tümünü okundu yap
if (isset($_GET['mark_all'])) {
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$uid]);
    redirect('notifications.php');
}
// Tekil okundu + yönlendirme
if (isset($_GET['read'])) {
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([intval($_GET['read']), $uid]);
    if (isset($_GET['goto'])) { header("Location: " . $_GET['goto']); exit(); }
    redirect('notifications.php');
}

$notifs = $pdo->prepare("SELECT n.*,u.username,u.avatar FROM notifications n LEFT JOIN users u ON n.from_user_id=u.id WHERE n.user_id=? ORDER BY n.created_at DESC LIMIT 50");
$notifs->execute([$uid]);
$notifs = $notifs->fetchAll();

$pageTitle = 'Bildirimler';
include 'includes/header.php';
?>
<div class="page-header flex-between">
    <div><h1><i class="fas fa-bell" style="color:var(--primary)"></i> Bildirimler</h1></div>
    <a href="?mark_all=1" class="btn btn-ghost btn-sm"><i class="fas fa-check-double"></i> Tümünü Okundu Say</a>
</div>

<div style="max-width:680px">
    <?php if (empty($notifs)): ?>
    <div class="empty-state card"><i class="fas fa-bell-slash"></i><h3>Bildirim yok</h3><p>Yeni bildirimleriniz burada görünecek.</p></div>
    <?php endif; ?>
    <?php
    $icons = ['reply'=>['fa-reply','#6366f1'],'like'=>['fa-heart','#ef4444'],'follow'=>['fa-user-plus','#10b981'],'mention'=>['fa-at','#f59e0b'],'dm'=>['fa-envelope','#0ea5e9'],'badge'=>['fa-award','#f59e0b'],'report_resolved'=>['fa-check','#10b981']];
    foreach ($notifs as $n):
        [$ic, $col] = $icons[$n['type']] ?? ['fa-bell','#6366f1'];
        $link = $n['topic_id'] ? SITE_URL.'/topic.php?id='.$n['topic_id'] : '#';
    ?>
    <div class="reply-card mb-1" style="<?= !$n['is_read']?'border-left:3px solid var(--primary)':'' ?>" onclick="location.href='?read=<?= $n['id'] ?>&goto=<?= urlencode($link) ?>';this.style.borderLeft=''" style="cursor:pointer">
        <div class="reply-author">
            <?php if ($n['from_user_id']): ?>
                <img src="<?= getAvatar(['username'=>$n['username'],'avatar'=>$n['avatar']]) ?>" alt="">
            <?php else: ?>
                <div style="width:44px;height:44px;border-radius:50%;background:var(--primary-light);display:flex;align-items:center;justify-content:center;color:var(--primary)"><i class="fas fa-bell"></i></div>
            <?php endif; ?>
            <div class="reply-author-info" style="flex:1">
                <div style="font-size:.9rem"><?= sanitize($n['message']) ?></div>
                <small><?= timeAgo($n['created_at']) ?></small>
            </div>
            <?php if (!$n['is_read']): ?>
                <div style="width:8px;height:8px;border-radius:50%;background:var(--primary);flex-shrink:0"></div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php include 'includes/footer.php'; ?>
