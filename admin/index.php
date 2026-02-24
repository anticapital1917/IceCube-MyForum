<?php
require_once '../config.php';
if (!isAdmin()) { flash('error','Bu sayfaya erişim izniniz yok.'); redirect('index.php'); }

$tab = $_GET['tab'] ?? 'dashboard';

// --- SİTE AYARLARI ---
if ($tab === 'settings') {
    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_settings'])) {
        $fields = ['site_name','site_desc','favicon_url','custom_css'];
        foreach ($fields as $f) {
            $v = trim($_POST[$f] ?? '');
            $pdo->prepare("INSERT INTO site_settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=?")->execute([$f,$v,$v]);
        }
        flash('success','Site ayarları kaydedildi.');
        header('Location: ?tab=settings'); exit();
    }
}

// ===================== İŞLEMLER =====================

// --- KULLANICILAR ---
if ($tab === 'users') {
    if (isset($_GET['toggle_role'])) {
        $uid = intval($_GET['toggle_role']);
        if ($uid != $_SESSION['user_id']) {
            $r = $pdo->prepare("SELECT role FROM users WHERE id=?"); $r->execute([$uid]); $r=$r->fetchColumn();
            $pdo->prepare("UPDATE users SET role=? WHERE id=?")->execute([$r==='admin'?'user':'admin', $uid]);
            flash('success','Kullanıcı rolü güncellendi.');
        }
        header("Location: ?tab=users"); exit();
    }
    if (isset($_GET['delete_user']) && intval($_GET['delete_user']) != $_SESSION['user_id']) {
        $pdo->prepare("DELETE FROM users WHERE id=?")->execute([intval($_GET['delete_user'])]);
        flash('success','Kullanıcı silindi.');
        header("Location: ?tab=users"); exit();
    }
}

// --- KATEGORİLER ---
if ($tab === 'categories') {
    if (isset($_GET['delete_cat'])) {
        $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([intval($_GET['delete_cat'])]);
        flash('success','Kategori silindi.');
        header("Location: ?tab=categories"); exit();
    }
    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_cat'])) {
        $name  = trim($_POST['name']??'');
        $desc  = trim($_POST['description']??'');
        $icon  = trim($_POST['icon']??'fas fa-folder');
        $color = trim($_POST['color']??'#6366f1');
        $order = intval($_POST['order_num']??0);
        if (strlen($name)>=2) {
            $slug = createSlug($name);
            if (!empty($_POST['edit_id'])) {
                $pdo->prepare("UPDATE categories SET name=?,slug=?,description=?,icon=?,color=?,order_num=? WHERE id=?")->execute([$name,$slug,$desc,$icon,$color,$order,intval($_POST['edit_id'])]);
                flash('success','Kategori güncellendi.');
            } else {
                $pdo->prepare("INSERT INTO categories (name,slug,description,icon,color,order_num) VALUES (?,?,?,?,?,?)")->execute([$name,$slug,$desc,$icon,$color,$order]);
                flash('success','Kategori eklendi.');
            }
        }
        header("Location: ?tab=categories"); exit();
    }
}

// --- KONULAR ---
if ($tab === 'topics') {
    if (isset($_GET['delete_topic'])) {
        $t = $pdo->prepare("SELECT category_id FROM topics WHERE id=?"); $t->execute([intval($_GET['delete_topic'])]); $t=$t->fetch();
        $pdo->prepare("DELETE FROM topics WHERE id=?")->execute([intval($_GET['delete_topic'])]);
        if ($t) $pdo->prepare("UPDATE categories SET topic_count=topic_count-1 WHERE id=?")->execute([$t['category_id']]);
        flash('success','Konu silindi.');
        header("Location: ?tab=topics"); exit();
    }
}

// --- RAPORLAR ---
if ($tab === 'reports') {
    if (isset($_GET['report_status']) && isset($_GET['rid'])) {
        $valid = ['beklemede','inceleniyor','cozuldu','reddedildi'];
        $s = $_GET['report_status'];
        if (in_array($s,$valid)) {
            $pdo->prepare("UPDATE reports SET status=?,resolved_by=?,resolved_at=NOW() WHERE id=?")->execute([$s,$_SESSION['user_id'],intval($_GET['rid'])]);
            flash('success','Rapor güncellendi.');
        }
        header("Location: ?tab=reports"); exit();
    }
}

// --- UNVANLAR ---
if ($tab === 'titles') {
    if (isset($_GET['delete_title'])) {
        $pdo->prepare("DELETE FROM titles WHERE id=?")->execute([intval($_GET['delete_title'])]);
        flash('success','Unvan silindi.');
        header("Location: ?tab=titles"); exit();
    }
    if (isset($_GET['revoke'])) {
        $pdo->prepare("DELETE FROM user_titles WHERE id=?")->execute([intval($_GET['revoke'])]);
        flash('success','Unvan geri alındı.');
        header("Location: ?tab=titles"); exit();
    }
    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['create_title'])) {
        $name  = trim($_POST['name']??'');
        $color = trim($_POST['color']??'#6366f1');
        $icon  = trim($_POST['icon']??'fas fa-medal');
        if (strlen($name)>=2) {
            $pdo->prepare("INSERT INTO titles (name,color,icon,created_by) VALUES (?,?,?,?)")->execute([$name,$color,$icon,$_SESSION['user_id']]);
            flash('success','"'.$name.'" unvanı oluşturuldu.');
        }
        header("Location: ?tab=titles"); exit();
    }
    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['assign_title'])) {
        $uid = intval($_POST['user_id']);
        $tid = intval($_POST['title_id']);
        if ($uid && $tid) {
            try {
                $pdo->prepare("INSERT INTO user_titles (user_id,title_id,assigned_by) VALUES (?,?,?)")->execute([$uid,$tid,$_SESSION['user_id']]);
                $uname=$pdo->prepare("SELECT username FROM users WHERE id=?"); $uname->execute([$uid]); $uname=$uname->fetchColumn();
                $tname=$pdo->prepare("SELECT name FROM titles WHERE id=?"); $tname->execute([$tid]); $tname=$tname->fetchColumn();
                createNotification($pdo,$uid,$_SESSION['user_id'],'badge','"'.$tname.'" unvanını kazandınız! Tebrikler 🎉');
                flash('success',$uname.' kullanıcısına "'.$tname.'" unvanı verildi.');
            } catch(Exception $e) { flash('error','Bu kullanıcı zaten bu unvana sahip.'); }
        }
        header("Location: ?tab=titles"); exit();
    }
}

// ===================== VERİ ÇEK =====================

$stats = $pdo->query("SELECT
    (SELECT COUNT(*) FROM users) as users,
    (SELECT COUNT(*) FROM topics) as topics,
    (SELECT COUNT(*) FROM replies) as replies,
    (SELECT COUNT(*) FROM categories) as categories,
    (SELECT COUNT(*) FROM reports WHERE status='beklemede') as pending_reports
")->fetch();

if ($tab==='users')      $users      = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
if ($tab==='categories') { $categories = $pdo->query("SELECT * FROM categories ORDER BY order_num ASC")->fetchAll(); $editCat = isset($_GET['edit_cat']) ? $pdo->query("SELECT * FROM categories WHERE id=".intval($_GET['edit_cat']))->fetch() : null; }
if ($tab==='topics')     { $page=max(1,intval($_GET['page']??1)); $perPage=25; $offset=($page-1)*$perPage; $totalTopics=$pdo->query("SELECT COUNT(*) FROM topics")->fetchColumn(); $topics=$pdo->prepare("SELECT t.*,u.username,c.name as cat_name FROM topics t JOIN users u ON t.user_id=u.id JOIN categories c ON t.category_id=c.id ORDER BY t.created_at DESC LIMIT $perPage OFFSET $offset"); $topics->execute(); $topics=$topics->fetchAll(); }
if ($tab==='reports')    { $rFilter=$_GET['rf']??'beklemede'; $reports=$pdo->prepare("SELECT r.*,u.username as reporter,t.title as topic_title,t.slug as topic_slug,rp.content as reply_content,ru.username as resolved_by_name FROM reports r JOIN users u ON r.reporter_id=u.id LEFT JOIN topics t ON r.topic_id=t.id LEFT JOIN replies rp ON r.reply_id=rp.id LEFT JOIN users ru ON r.resolved_by=ru.id WHERE r.status=? ORDER BY r.created_at DESC"); $reports->execute([$rFilter]); $reports=$reports->fetchAll(); $rCounts=$pdo->query("SELECT status,COUNT(*) as c FROM reports GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR); }
if ($tab==='titles')     { try { $titles=$pdo->query("SELECT * FROM titles ORDER BY created_at DESC")->fetchAll(); $allUsers=$pdo->query("SELECT id,username FROM users ORDER BY username ASC")->fetchAll(); $assigned=$pdo->query("SELECT ut.id as aid,u.username,u.id as uid,t.name as tname,t.color,t.icon,a.username as by_name,ut.assigned_at FROM user_titles ut JOIN users u ON ut.user_id=u.id JOIN titles t ON ut.title_id=t.id JOIN users a ON ut.assigned_by=a.id ORDER BY ut.assigned_at DESC")->fetchAll(); } catch(Exception $e) { $titles=[]; $allUsers=$pdo->query("SELECT id,username FROM users ORDER BY username ASC")->fetchAll(); $assigned=[]; } }
if ($tab==='dashboard')  { $recent_users=$pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 6")->fetchAll(); $recent_topics=$pdo->query("SELECT t.*,u.username FROM topics t JOIN users u ON t.user_id=u.id ORDER BY t.created_at DESC LIMIT 6")->fetchAll(); }
if ($tab==='settings')   { try { $siteSettings=$pdo->query("SELECT `key`, `value` FROM site_settings")->fetchAll(PDO::FETCH_KEY_PAIR); } catch(Exception $e) { $siteSettings=[]; } }

$pageTitle = 'Admin Paneli';
include '../includes/header.php';
?>

<style>
.admin-tabs{display:flex;gap:4px;margin-bottom:24px;border-bottom:2px solid var(--border);padding-bottom:0;flex-wrap:wrap}
.admin-tab{padding:10px 18px;border-radius:8px 8px 0 0;font-weight:600;font-size:.9rem;color:var(--secondary);cursor:pointer;border:none;background:none;font-family:inherit;transition:all .15s;display:flex;align-items:center;gap:7px;position:relative;bottom:-2px;border-bottom:2px solid transparent}
.admin-tab:hover{color:var(--primary);background:var(--bg)}
.admin-tab.active{color:var(--primary);border-bottom:2px solid var(--primary);background:var(--card)}
.admin-tab .tab-badge{background:var(--danger);color:white;font-size:.65rem;font-weight:700;padding:1px 5px;border-radius:8px;min-width:16px;text-align:center}
</style>

<div class="flex-between mb-2" style="flex-wrap:wrap;gap:12px">
    <h1 style="font-size:1.6rem;font-weight:700"><i class="fas fa-cog" style="color:var(--primary)"></i> Admin Paneli</h1>
    <a href="<?= SITE_URL ?>" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left"></i> Siteye Dön</a>
</div>

<!-- SEKMELER -->
<div class="admin-tabs">
    <?php
    $tabs = [
        'dashboard' => ['fas fa-tachometer-alt', 'Dashboard'],
        'users'     => ['fas fa-users',          'Kullanıcılar'],
        'categories'=> ['fas fa-th-large',        'Kategoriler'],
        'topics'    => ['fas fa-file-alt',         'Konular'],
        'reports'   => ['fas fa-flag',             'Raporlar'],
        'titles'    => ['fas fa-medal',            'Unvanlar'],
        'settings'  => ['fas fa-sliders-h',        'Site Ayarları'],
    ];
    foreach ($tabs as $key => [$icon, $label]):
    ?>
    <a href="?tab=<?= $key ?>" class="admin-tab <?= $tab===$key?'active':'' ?>">
        <i class="<?= $icon ?>"></i> <?= $label ?>
        <?php if ($key==='reports' && !empty($stats['pending_reports']) && $stats['pending_reports']>0): ?>
            <span class="tab-badge"><?= $stats['pending_reports'] ?></span>
        <?php endif; ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- ==================== DASHBOARD ==================== -->

<!-- ==================== DASHBOARD ==================== -->
<?php if ($tab==='dashboard'): ?>

<div class="stats-grid">
    <div class="stat-card"><div class="stat-num" style="color:var(--primary)"><?= $stats['users'] ?></div><div class="stat-label"><i class="fas fa-users"></i> Üye</div></div>
    <div class="stat-card"><div class="stat-num" style="color:var(--success)"><?= $stats['topics'] ?></div><div class="stat-label"><i class="fas fa-file-alt"></i> Konu</div></div>
    <div class="stat-card"><div class="stat-num" style="color:var(--warning)"><?= $stats['replies'] ?></div><div class="stat-label"><i class="fas fa-comments"></i> Yorum</div></div>
    <div class="stat-card"><div class="stat-num" style="color:#8b5cf6"><?= $stats['categories'] ?></div><div class="stat-label"><i class="fas fa-th-large"></i> Kategori</div></div>
    <?php if ($stats['pending_reports']>0): ?>
    <div class="stat-card" style="border-color:var(--danger)"><div class="stat-num" style="color:var(--danger)"><?= $stats['pending_reports'] ?></div><div class="stat-label"><i class="fas fa-flag"></i> Bekleyen Rapor</div></div>
    <?php endif; ?>
</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
    <div class="card">
        <h3 style="margin-bottom:14px">Son Üyeler</h3>
        <table><thead><tr><th>Kullanıcı</th><th>Rol</th><th>Tarih</th></tr></thead><tbody>
        <?php foreach ($recent_users as $u): ?>
        <tr>
            <td><a href="<?= SITE_URL ?>/profile.php?id=<?= $u['id'] ?>"><?= sanitize($u['username']) ?></a></td>
            <td><span class="badge" style="background:<?= $u['role']==='admin'?'var(--primary-light)':'var(--bg)' ?>;color:<?= $u['role']==='admin'?'var(--primary)':'var(--secondary)' ?>"><?= $u['role']==='admin'?'👑 ':'' ?><?= $u['role'] ?></span></td>
            <td><?= date('d.m.Y',strtotime($u['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?></tbody></table>
    </div>
    <div class="card">
        <h3 style="margin-bottom:14px">Son Konular</h3>
        <table><thead><tr><th>Başlık</th><th>Yazar</th><th>Tarih</th></tr></thead><tbody>
        <?php foreach ($recent_topics as $t): ?>
        <tr>
            <td><a href="<?= SITE_URL ?>/topic.php?slug=<?= $t['slug'] ?>"><?= sanitize(mb_substr($t['title'],0,32)) ?>...</a></td>
            <td><?= sanitize($t['username']) ?></td>
            <td><?= date('d.m.Y',strtotime($t['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?></tbody></table>
    </div>
</div>

<!-- ==================== KULLANICILAR ==================== -->
<?php elseif ($tab==='users'): ?>

<div class="card">
    <div class="table-wrapper">
        <table>
            <thead><tr><th>#</th><th>Kullanıcı</th><th>E-posta</th><th>Rol</th><th>Gönderi</th><th>Kayıt</th><th>İşlem</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td><?= $u['id'] ?></td>
                <td><a href="<?= SITE_URL ?>/profile.php?id=<?= $u['id'] ?>"><?= sanitize($u['username']) ?></a></td>
                <td><?= sanitize($u['email']) ?></td>
                <td><span class="badge" style="background:<?= $u['role']==='admin'?'var(--primary-light)':'var(--bg)' ?>;color:<?= $u['role']==='admin'?'var(--primary)':'var(--secondary)' ?>"><?= $u['role']==='admin'?'👑 ':'' ?><?= $u['role'] ?></span></td>
                <td><?= $u['post_count'] ?></td>
                <td><?= date('d.m.Y',strtotime($u['created_at'])) ?></td>
                <td>
                    <?php if ($u['id']!=$_SESSION['user_id']): ?>
                        <a href="?tab=users&toggle_role=<?= $u['id'] ?>" class="btn btn-sm btn-ghost" title="Rol Değiştir"><i class="fas fa-user-shield"></i></a>
                        <a href="?tab=users&delete_user=<?= $u['id'] ?>" class="btn btn-sm btn-danger" data-confirm="Kullanıcıyı silmek istediğinize emin misiniz?"><i class="fas fa-trash"></i></a>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ==================== KATEGORİLER ==================== -->
<?php elseif ($tab==='categories'): ?>

<div style="display:grid;grid-template-columns:340px 1fr;gap:24px">
    <div class="card" style="height:fit-content">
        <h3 style="margin-bottom:16px"><?= $editCat ? 'Düzenle' : 'Yeni Kategori' ?></h3>
        <form method="POST">
            <?php if ($editCat): ?><input type="hidden" name="edit_id" value="<?= $editCat['id'] ?>"><?php endif; ?>
            <div class="form-group"><label>Ad</label><input type="text" name="name" value="<?= sanitize($editCat['name']??'') ?>" required></div>
            <div class="form-group"><label>Açıklama</label><input type="text" name="description" value="<?= sanitize($editCat['description']??'') ?>"></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                <div class="form-group"><label>İkon</label><input type="text" name="icon" value="<?= sanitize($editCat['icon']??'fas fa-folder') ?>"></div>
                <div class="form-group"><label>Renk</label><input type="color" name="color" value="<?= sanitize($editCat['color']??'#6366f1') ?>"></div>
            </div>
            <div class="form-group"><label>Sıra</label><input type="number" name="order_num" value="<?= $editCat['order_num']??0 ?>"></div>
            <div style="display:flex;gap:8px">
                <button type="submit" name="save_cat" class="btn btn-primary" style="flex:1;justify-content:center"><i class="fas fa-save"></i> <?= $editCat?'Güncelle':'Ekle' ?></button>
                <?php if ($editCat): ?><a href="?tab=categories" class="btn btn-ghost">İptal</a><?php endif; ?>
            </div>
        </form>
    </div>
    <div class="card">
        <h3 style="margin-bottom:16px">Mevcut Kategoriler</h3>
        <div class="table-wrapper">
            <table><thead><tr><th>Kategori</th><th>Konu</th><th>Sıra</th><th>İşlem</th></tr></thead><tbody>
            <?php foreach ($categories as $c): ?>
            <tr>
                <td><span style="display:flex;align-items:center;gap:10px"><span style="width:30px;height:30px;border-radius:8px;background:<?= $c['color'] ?>;display:flex;align-items:center;justify-content:center;color:white;font-size:.8rem"><i class="<?= sanitize($c['icon']) ?>"></i></span><?= sanitize($c['name']) ?></span></td>
                <td><?= $c['topic_count'] ?></td>
                <td><?= $c['order_num'] ?></td>
                <td>
                    <a href="?tab=categories&edit_cat=<?= $c['id'] ?>" class="btn btn-sm btn-ghost"><i class="fas fa-edit"></i></a>
                    <a href="?tab=categories&delete_cat=<?= $c['id'] ?>" class="btn btn-sm btn-danger" data-confirm="Kategoriyi silmek istediğinize emin misiniz?"><i class="fas fa-trash"></i></a>
                </td>
            </tr>
            <?php endforeach; ?></tbody></table>
        </div>
    </div>
</div>

<!-- ==================== KONULAR ==================== -->
<?php elseif ($tab==='topics'): ?>

<div class="card">
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Başlık</th><th>Yazar</th><th>Kategori</th><th>Görüntüleme</th><th>Yorum</th><th>Tarih</th><th>İşlem</th></tr></thead>
            <tbody>
            <?php foreach ($topics as $t): ?>
            <tr>
                <td>
                    <a href="<?= SITE_URL ?>/topic.php?slug=<?= $t['slug'] ?>"><?= sanitize(mb_substr($t['title'],0,45)) ?>...</a>
                    <?php if($t['is_pinned']): ?><span class="badge badge-pin" style="margin-left:4px">📌</span><?php endif; ?>
                    <?php if($t['is_locked']): ?><span class="badge badge-lock" style="margin-left:4px">🔒</span><?php endif; ?>
                </td>
                <td><?= sanitize($t['username']) ?></td>
                <td><span class="badge badge-cat"><?= sanitize($t['cat_name']) ?></span></td>
                <td><?= $t['views'] ?></td>
                <td><?= $t['reply_count'] ?></td>
                <td><?= date('d.m.Y',strtotime($t['created_at'])) ?></td>
                <td>
                    <a href="<?= SITE_URL ?>/topic.php?slug=<?= $t['slug'] ?>&action=pin"  class="btn btn-sm btn-ghost" title="Sabitle"><i class="fas fa-thumbtack"></i></a>
                    <a href="<?= SITE_URL ?>/topic.php?slug=<?= $t['slug'] ?>&action=lock" class="btn btn-sm btn-ghost" title="Kilitle"><i class="fas fa-lock"></i></a>
                    <a href="?tab=topics&delete_topic=<?= $t['id'] ?>" class="btn btn-sm btn-danger" data-confirm="Konuyu silmek istediğinize emin misiniz?"><i class="fas fa-trash"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (ceil($totalTopics/25)>1): ?>
    <div class="pagination" style="margin-top:16px">
        <?php for($i=1;$i<=ceil($totalTopics/25);$i++): ?>
            <?php if($i==$page): ?><span class="active"><?=$i?></span>
            <?php else: ?><a href="?tab=topics&page=<?=$i?>"><?=$i?></a><?php endif; ?>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ==================== RAPORLAR ==================== -->
<?php elseif ($tab==='reports'): ?>

<div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
    <?php foreach (['beklemede','inceleniyor','cozuldu','reddedildi'] as $s): ?>
    <a href="?tab=reports&rf=<?= $s ?>" class="btn btn-sm <?= ($rFilter??'beklemede')===$s?'btn-primary':'btn-ghost' ?>">
        <?= ucfirst($s) ?><?php if(!empty($rCounts[$s])): ?> (<?= $rCounts[$s] ?>)<?php endif; ?>
    </a>
    <?php endforeach; ?>
</div>
<?php if (empty($reports)): ?>
<div class="empty-state card"><i class="fas fa-check-circle"></i><h3>Rapor yok</h3><p>"<?= $rFilter ?>" durumunda rapor yok.</p></div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:12px">
<?php foreach ($reports as $r): ?>
<div class="card" style="padding:18px">
    <div class="flex-between mb-1" style="flex-wrap:wrap;gap:8px">
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <span class="badge" style="background:#fee2e2;color:#991b1b"><?= sanitize($r['reason']) ?></span>
            <span style="font-size:.85rem;color:var(--text-muted)">Rapor eden: <strong><?= sanitize($r['reporter']) ?></strong> · <?= timeAgo($r['created_at']) ?></span>
        </div>
        <div style="display:flex;gap:6px">
            <?php if ($r['status']==='beklemede'): ?>
                <a href="?tab=reports&rf=<?= $rFilter ?>&rid=<?= $r['id'] ?>&report_status=inceleniyor" class="btn btn-sm btn-ghost"><i class="fas fa-eye"></i> İncele</a>
            <?php endif; ?>
            <?php if (in_array($r['status'],['beklemede','inceleniyor'])): ?>
                <a href="?tab=reports&rf=<?= $rFilter ?>&rid=<?= $r['id'] ?>&report_status=cozuldu" class="btn btn-sm btn-success"><i class="fas fa-check"></i> Çözüldü</a>
                <a href="?tab=reports&rf=<?= $rFilter ?>&rid=<?= $r['id'] ?>&report_status=reddedildi" class="btn btn-sm btn-danger"><i class="fas fa-times"></i> Reddet</a>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($r['topic_title']): ?>
        <div style="font-size:.88rem;margin-bottom:6px"><strong>Konu:</strong> <a href="<?= SITE_URL ?>/topic.php?slug=<?= $r['topic_slug'] ?>" style="color:var(--primary)"><?= sanitize($r['topic_title']) ?></a></div>
    <?php endif; ?>
    <?php if ($r['reply_content']): ?>
        <div style="background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:10px;font-size:.85rem;color:var(--text-muted);margin-bottom:6px"><?= sanitize(mb_substr($r['reply_content'],0,200)) ?>...</div>
    <?php endif; ?>
    <?php if ($r['description']): ?>
        <div style="font-size:.83rem;color:var(--text-muted)"><i class="fas fa-comment"></i> <?= sanitize($r['description']) ?></div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ==================== UNVANLAR ==================== -->
<?php elseif ($tab==='titles'): ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px">
    <div class="card">
        <h3 style="margin-bottom:16px"><i class="fas fa-plus-circle" style="color:var(--primary)"></i> Yeni Unvan Oluştur</h3>
        <form method="POST">
            <div class="form-group"><label>Unvan Adı</label><input type="text" name="name" placeholder="ör. Çözüm Dehası" required maxlength="100"></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                <div class="form-group"><label>Renk</label><input type="color" name="color" value="#6366f1"></div>
                <div class="form-group"><label>İkon (FA)</label><input type="text" name="icon" value="fas fa-medal"></div>
            </div>
            <button type="submit" name="create_title" class="btn btn-primary" style="width:100%;justify-content:center"><i class="fas fa-plus"></i> Oluştur</button>
        </form>
    </div>
    <div class="card">
        <h3 style="margin-bottom:16px"><i class="fas fa-user-tag" style="color:var(--success)"></i> Kullanıcıya Unvan Ver</h3>
        <form method="POST">
            <div class="form-group">
                <label>Kullanıcı</label>
                <select name="user_id" required>
                    <option value="">-- Kullanıcı Seçin --</option>
                    <?php foreach ($allUsers as $u): ?><option value="<?= $u['id'] ?>"><?= sanitize($u['username']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Unvan</label>
                <select name="title_id" required>
                    <option value="">-- Unvan Seçin --</option>
                    <?php foreach ($titles as $t): ?><option value="<?= $t['id'] ?>"><?= sanitize($t['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <button type="submit" name="assign_title" class="btn btn-success" style="width:100%;justify-content:center"><i class="fas fa-check"></i> Unvan Ver</button>
        </form>
    </div>
</div>
<div class="card mb-3">
    <h3 style="margin-bottom:14px">Mevcut Unvanlar</h3>
    <?php if (empty($titles)): ?><p class="text-muted">Henüz unvan yok.</p><?php else: ?>
    <div style="display:flex;flex-wrap:wrap;gap:10px">
        <?php foreach ($titles as $t): ?>
        <div style="display:flex;align-items:center;gap:8px;background:var(--bg);border:1px solid var(--border);border-radius:20px;padding:6px 14px">
            <span style="color:<?= sanitize($t['color']) ?>"><i class="<?= sanitize($t['icon']) ?>"></i></span>
            <span style="font-weight:600;font-size:.9rem"><?= sanitize($t['name']) ?></span>
            <a href="?tab=titles&delete_title=<?= $t['id'] ?>" data-confirm="'<?= sanitize($t['name']) ?>' silinsin mi?" style="color:var(--danger);font-size:.8rem"><i class="fas fa-times"></i></a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<div class="card">
    <h3 style="margin-bottom:14px">Verilen Unvanlar (<?= count($assigned) ?>)</h3>
    <?php if (empty($assigned)): ?><p class="text-muted">Henüz kimseye unvan verilmedi.</p><?php else: ?>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Kullanıcı</th><th>Unvan</th><th>Veren</th><th>Tarih</th><th>İşlem</th></tr></thead>
            <tbody>
            <?php foreach ($assigned as $a): ?>
            <tr>
                <td><a href="<?= SITE_URL ?>/profile.php?id=<?= $a['uid'] ?>"><?= sanitize($a['username']) ?></a></td>
                <td><span style="display:inline-flex;align-items:center;gap:5px;background:<?= sanitize($a['color']) ?>22;color:<?= sanitize($a['color']) ?>;padding:3px 10px;border-radius:20px;font-size:.82rem;font-weight:600"><i class="<?= sanitize($a['icon']) ?>"></i> <?= sanitize($a['tname']) ?></span></td>
                <td><?= sanitize($a['by_name']) ?></td>
                <td><?= date('d.m.Y',strtotime($a['assigned_at'])) ?></td>
                <td><a href="?tab=titles&revoke=<?= $a['aid'] ?>" class="btn btn-sm btn-danger" data-confirm="Unvanı geri al?"><i class="fas fa-times"></i> Geri Al</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- ==================== SİTE AYARLARI ==================== -->
<?php elseif ($tab==='settings'): ?>

<div style="max-width:640px">
<div class="card">
    <h3 style="margin-bottom:20px"><i class="fas fa-sliders-h" style="color:var(--primary)"></i> Site Ayarları</h3>
    <form method="POST">
        <div class="form-group">
            <label><i class="fas fa-heading"></i> Site Adı</label>
            <input type="text" name="site_name" value="<?= sanitize($siteSettings['site_name'] ?? 'ForumTR') ?>" required>
        </div>
        <div class="form-group">
            <label><i class="fas fa-align-left"></i> Site Açıklaması</label>
            <input type="text" name="site_desc" value="<?= sanitize($siteSettings['site_desc'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label><i class="fas fa-image"></i> Favicon URL</label>
            <input type="url" name="favicon_url" value="<?= sanitize($siteSettings['favicon_url'] ?? '') ?>" placeholder="https://example.com/favicon.ico">
            <p class="form-hint">Tarayıcı sekmesinde görünen ikon. Boş bırakırsanız varsayılan 💬 ikonu kullanılır.</p>
            <?php if (!empty($siteSettings['favicon_url'])): ?>
            <div style="margin-top:8px;display:flex;align-items:center;gap:10px">
                <img src="<?= sanitize($siteSettings['favicon_url']) ?>" style="width:32px;height:32px;object-fit:contain">
                <span style="font-size:.85rem;color:var(--text-muted)">Mevcut favicon</span>
            </div>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label><i class="fas fa-code"></i> Özel CSS <small style="color:var(--text-muted)">(opsiyonel)</small></label>
            <textarea name="custom_css" rows="5" placeholder="/* Özel CSS kuralları */"><?= sanitize($siteSettings['custom_css'] ?? '') ?></textarea>
            <p class="form-hint">Tüm sayfalara eklenecek özel CSS.</p>
        </div>
        <button type="submit" name="save_settings" class="btn btn-primary"><i class="fas fa-save"></i> Ayarları Kaydet</button>
    </form>
</div>
</div>

<?php endif; ?>

<?php include '../includes/footer.php'; ?>
