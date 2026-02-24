<?php
require_once 'config.php';
if (!isLoggedIn()) { flash('error','Giriş yapmalısınız.'); redirect('login.php'); }
$uid = $_SESSION['user_id'];

// Mesaj gönder
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['send'])) {
    $toId   = intval($_POST['to_user_id']);
    $subj   = trim($_POST['subject'] ?? 'Mesaj');
    $cont   = trim($_POST['content'] ?? '');
    if ($toId && $toId !== $uid && strlen($cont) >= 1) {
        $pdo->prepare("INSERT INTO messages (from_user_id,to_user_id,subject,content) VALUES (?,?,?,?)")->execute([$uid,$toId,$subj,$cont]);
        // Alıcıya bildirim
        $me = $pdo->prepare("SELECT username FROM users WHERE id=?"); $me->execute([$uid]); $me=$me->fetch();
        createNotification($pdo,$toId,$uid,'dm',$me['username'].' size bir mesaj gönderdi.');
        flash('success','Mesaj gönderildi!');
    }
    redirect('messages.php?user='.$toId);
}

// Mesaj sil
if (isset($_GET['delete'])) {
    $mid = intval($_GET['delete']);
    $m = $pdo->prepare("SELECT * FROM messages WHERE id=?"); $m->execute([$mid]); $m=$m->fetch();
    if ($m) {
        if ($m['from_user_id']==$uid) $pdo->prepare("UPDATE messages SET deleted_by_sender=1 WHERE id=?")->execute([$mid]);
        if ($m['to_user_id']==$uid)   $pdo->prepare("UPDATE messages SET deleted_by_receiver=1 WHERE id=?")->execute([$mid]);
    }
    redirect('messages.php');
}

// Konuşma görüntüle
$withUser = null;
$conversation = [];
if (isset($_GET['user'])) {
    $otherId = intval($_GET['user']);
    $withStmt = $pdo->prepare("SELECT * FROM users WHERE id=?"); $withStmt->execute([$otherId]); $withUser=$withStmt->fetch();
    if ($withUser) {
        // Okundu işaretle
        $pdo->prepare("UPDATE messages SET is_read=1 WHERE to_user_id=? AND from_user_id=? AND is_read=0")->execute([$uid,$otherId]);
        $convStmt = $pdo->prepare("SELECT m.*,u.username,u.avatar FROM messages m JOIN users u ON m.from_user_id=u.id WHERE ((m.from_user_id=? AND m.to_user_id=? AND m.deleted_by_sender=0) OR (m.from_user_id=? AND m.to_user_id=? AND m.deleted_by_receiver=0)) ORDER BY m.created_at ASC");
        $convStmt->execute([$uid,$otherId,$otherId,$uid]);
        $conversation = $convStmt->fetchAll();
    }
}

// Konuşma listesi (inbox)
$inbox = $pdo->prepare("
    SELECT DISTINCT CASE WHEN m.from_user_id=? THEN m.to_user_id ELSE m.from_user_id END as other_id,
        u.username, u.avatar,
        (SELECT content FROM messages WHERE ((from_user_id=? AND to_user_id=u.id) OR (from_user_id=u.id AND to_user_id=?)) ORDER BY created_at DESC LIMIT 1) as last_msg,
        (SELECT created_at FROM messages WHERE ((from_user_id=? AND to_user_id=u.id) OR (from_user_id=u.id AND to_user_id=?)) ORDER BY created_at DESC LIMIT 1) as last_time,
        (SELECT COUNT(*) FROM messages WHERE from_user_id=u.id AND to_user_id=? AND is_read=0) as unread_count
    FROM messages m JOIN users u ON (CASE WHEN m.from_user_id=? THEN m.to_user_id ELSE m.from_user_id END)=u.id
    WHERE (m.from_user_id=? AND m.deleted_by_sender=0) OR (m.to_user_id=? AND m.deleted_by_receiver=0)
    ORDER BY last_time DESC
");
$inbox->execute([$uid,$uid,$uid,$uid,$uid,$uid,$uid,$uid,$uid]);
$inbox = $inbox->fetchAll();

// Yeni mesaj gönderilecek kullanıcı (URL'den)
$newTo = null;
if (isset($_GET['new'])) {
    $ns = $pdo->prepare("SELECT * FROM users WHERE id=?"); $ns->execute([intval($_GET['new'])]); $newTo=$ns->fetch();
}

$pageTitle = 'Mesajlar';
include 'includes/header.php';
?>

<div class="flex-between mb-3">
    <h1 class="page-header" style="margin:0"><i class="fas fa-envelope" style="color:var(--primary)"></i> Mesajlar</h1>
    <button class="btn btn-primary btn-sm" onclick="document.getElementById('newMsgModal').style.display='flex'"><i class="fas fa-plus"></i> Yeni Mesaj</button>
</div>

<div style="display:grid;grid-template-columns:280px 1fr;gap:20px;min-height:500px">
    <!-- Sol: Konuşma listesi -->
    <div>
        <div class="msg-list">
            <?php foreach ($inbox as $conv): ?>
            <a href="<?= SITE_URL ?>/messages.php?user=<?= $conv['other_id'] ?>" class="msg-item <?= $conv['unread_count']>0?'unread':'' ?>" style="display:flex">
                <img src="<?= getAvatar(['username'=>$conv['username'],'avatar'=>$conv['avatar']]) ?>" alt="">
                <div class="msg-item-info">
                    <strong><?= sanitize($conv['username']) ?></strong>
                    <span><?= sanitize(mb_substr($conv['last_msg'],0,40)) ?>...</span>
                </div>
                <?php if($conv['unread_count']>0):?>
                    <span style="background:var(--primary);color:white;border-radius:50%;width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;flex-shrink:0"><?=$conv['unread_count']?></span>
                <?php endif;?>
            </a>
            <?php endforeach; ?>
            <?php if (empty($inbox)): ?>
            <div class="empty-state" style="padding:30px"><i class="fas fa-inbox"></i><h3 style="font-size:1rem">Mesaj yok</h3></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Sağ: Konuşma -->
    <div>
        <?php if ($withUser): ?>
        <div class="card" style="padding:0;overflow:hidden">
            <div class="reply-author" style="justify-content:space-between">
                <div style="display:flex;align-items:center;gap:12px">
                    <img src="<?= getAvatar($withUser) ?>" alt="" style="width:40px;height:40px;border-radius:50%">
                    <strong><a href="<?= SITE_URL ?>/profile.php?id=<?= $withUser['id'] ?>"><?= sanitize($withUser['username']) ?></a></strong>
                </div>
            </div>
            <div style="padding:20px;min-height:300px;max-height:500px;overflow-y:auto;display:flex;flex-direction:column;gap:10px" id="msgThread">
                <?php foreach ($conversation as $msg): ?>
                <div>
                    <div class="msg-bubble <?= $msg['from_user_id']==$uid?'sent':'recv' ?>">
                        <?= nl2br(sanitize($msg['content'])) ?>
                        <div class="msg-bubble-meta"><?= date('d.m.Y H:i', strtotime($msg['created_at'])) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="padding:16px;border-top:1px solid var(--border)">
                <form method="POST" style="display:flex;gap:10px">
                    <input type="hidden" name="to_user_id" value="<?= $withUser['id'] ?>">
                    <input type="hidden" name="subject" value="Mesaj">
                    <input type="text" name="content" placeholder="Mesajınızı yazın..." style="flex:1;padding:10px 14px;border:2px solid var(--border);border-radius:20px;background:var(--bg);color:var(--text);font-family:inherit" required>
                    <button type="submit" name="send" class="btn btn-primary"><i class="fas fa-paper-plane"></i></button>
                </form>
            </div>
        </div>
        <script>document.getElementById('msgThread').scrollTop=99999</script>
        <?php elseif ($newTo): ?>
        <div class="card">
            <h3 style="margin-bottom:16px"><i class="fas fa-paper-plane" style="color:var(--primary)"></i> <?= sanitize($newTo['username']) ?>'a Mesaj Gönder</h3>
            <form method="POST">
                <input type="hidden" name="to_user_id" value="<?= $newTo['id'] ?>">
                <div class="form-group"><label>Konu</label><input type="text" name="subject" value="Merhaba" required></div>
                <div class="form-group"><label>Mesaj</label><textarea name="content" required></textarea></div>
                <button type="submit" name="send" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Gönder</button>
            </form>
        </div>
        <?php else: ?>
        <div class="empty-state card"><i class="fas fa-comments"></i><h3>Bir konuşma seçin</h3><p>Sol taraftan bir konuşma seçin veya yeni mesaj gönderin.</p></div>
        <?php endif; ?>
    </div>
</div>

<!-- Yeni Mesaj Modal -->
<div id="newMsgModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div class="card" style="width:480px;max-width:90vw;position:relative">
        <button onclick="document.getElementById('newMsgModal').style.display='none'" style="position:absolute;top:16px;right:16px;background:none;border:none;font-size:1.2rem;cursor:pointer;color:var(--text-muted)"><i class="fas fa-times"></i></button>
        <h3 style="margin-bottom:20px"><i class="fas fa-plus"></i> Yeni Mesaj</h3>
        <form method="POST">
            <div class="form-group">
                <label>Kullanıcı Adı</label>
                <input type="text" id="userSearch" placeholder="Kullanıcı adı ara..." autocomplete="off">
                <input type="hidden" name="to_user_id" id="toUserId">
                <div id="userSearchResults" style="border:1px solid var(--border);border-radius:8px;margin-top:4px;display:none;background:var(--card)"></div>
            </div>
            <div class="form-group"><label>Konu</label><input type="text" name="subject" required></div>
            <div class="form-group"><label>Mesaj</label><textarea name="content" required rows="4"></textarea></div>
            <button type="submit" name="send" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Gönder</button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
