<?php
require_once 'config.php';
if (!isLoggedIn()) { flash('error','Giriş yapmalısınız.'); redirect('login.php'); }

$user = getCurrentUser();
$errors = [];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $bio      = trim($_POST['bio'] ?? '');
    $website  = trim($_POST['website'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $sig      = trim($_POST['signature'] ?? '');
    $newpass  = $_POST['new_password'] ?? '';
    $curpass  = $_POST['current_password'] ?? '';

    // Avatar yükleme
    $avatar = $user['avatar'];
    if (!empty($_FILES['avatar']['name'])) {
        $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            $errors[] = 'Geçersiz dosya türü. JPG, PNG, GIF veya WebP yükleyin.';
        } elseif ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
            $errors[] = 'Dosya 2MB\'dan küçük olmalıdır.';
        } else {
            $uploadDir = __DIR__ . '/assets/uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $fileName = 'avatar_' . $user['id'] . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadDir . $fileName)) {
                $avatar = $fileName;
            }
        }
    }

    // Şifre değiştirme
    if ($newpass) {
        if (!password_verify($curpass, $user['password'])) {
            $errors[] = 'Mevcut şifre yanlış.';
        } elseif (strlen($newpass) < 6) {
            $errors[] = 'Yeni şifre en az 6 karakter olmalıdır.';
        } else {
            $hashed = password_hash($newpass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hashed, $user['id']]);
        }
    }

    if (empty($errors)) {
        $pdo->prepare("UPDATE users SET bio=?,website=?,location=?,signature=?,avatar=? WHERE id=?")
            ->execute([$bio, $website, $location, $sig, $avatar, $user['id']]);
        flash('success','Profiliniz güncellendi!');
        redirect('profile_edit.php');
    }
}

$pageTitle = 'Profili Düzenle';
include 'includes/header.php';
?>

<div style="max-width:640px;margin:0 auto">
    <div class="breadcrumb">
        <a href="<?= SITE_URL ?>/profile.php?id=<?= $user['id'] ?>">Profilim</a>
        <i class="fas fa-chevron-right"></i>
        <span>Düzenle</span>
    </div>
    <div class="page-header"><h1><i class="fas fa-edit" style="color:var(--primary)"></i> Profili Düzenle</h1></div>

    <?php foreach ($errors as $e): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= sanitize($e) ?></div>
    <?php endforeach; ?>

    <div class="card">
        <form method="POST" enctype="multipart/form-data">
            <!-- Avatar -->
            <div class="form-group" style="display:flex;align-items:center;gap:20px">
                <img src="<?= getAvatar($user) ?>" alt="Avatar" style="width:80px;height:80px;border-radius:50%;object-fit:cover">
                <div style="flex:1">
                    <label><i class="fas fa-camera"></i> Profil Fotoğrafı</label>
                    <input type="file" name="avatar" accept="image/*">
                    <p class="form-hint">Maks. 2MB. JPG, PNG, GIF veya WebP.</p>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div class="form-group">
                    <label><i class="fas fa-globe"></i> Web Sitesi</label>
                    <input type="url" name="website" value="<?= sanitize($user['website'] ?? '') ?>" placeholder="https://...">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-map-marker-alt"></i> Konum</label>
                    <input type="text" name="location" value="<?= sanitize($user['location'] ?? '') ?>" placeholder="Şehir, Ülke">
                </div>
            </div>

            <div class="form-group">
                <label><i class="fas fa-user"></i> Hakkımda</label>
                <textarea name="bio" rows="3" placeholder="Kendinizden bahsedin..."><?= sanitize($user['bio'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label><i class="fas fa-signature"></i> İmza</label>
                <textarea name="signature" rows="2" placeholder="Her yorumunuzun altında görünür..."><?= sanitize($user['signature'] ?? '') ?></textarea>
            </div>

            <hr style="border:none;border-top:1px solid var(--border);margin:24px 0">
            <h3 style="margin-bottom:16px;font-size:1rem"><i class="fas fa-lock"></i> Şifre Değiştir</h3>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div class="form-group">
                    <label>Mevcut Şifre</label>
                    <input type="password" name="current_password" placeholder="••••••">
                </div>
                <div class="form-group">
                    <label>Yeni Şifre</label>
                    <input type="password" name="new_password" placeholder="En az 6 karakter">
                </div>
            </div>

            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Değişiklikleri Kaydet</button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
