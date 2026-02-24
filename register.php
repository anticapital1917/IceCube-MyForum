<?php
require_once 'config.php';

if (isLoggedIn()) redirect('index.php');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (strlen($username) < 3 || strlen($username) > 30)
        $errors[] = 'Kullanıcı adı 3-30 karakter olmalıdır.';
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username))
        $errors[] = 'Kullanıcı adı sadece harf, rakam ve alt çizgi içerebilir.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Geçerli bir e-posta adresi girin.';
    if (strlen($password) < 6)
        $errors[] = 'Şifre en az 6 karakter olmalıdır.';
    if ($password !== $password2)
        $errors[] = 'Şifreler eşleşmiyor.';

    if (empty($errors)) {
        // Kullanıcı adı/email kontrolü
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $errors[] = 'Bu kullanıcı adı veya e-posta zaten kullanılıyor.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $stmt->execute([$username, $email, $hashed]);
            
            $userId = $pdo->lastInsertId();
            $_SESSION['user_id'] = $userId;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = 'user';
            
            flash('success', 'Hoş geldiniz, ' . $username . '! Hesabınız oluşturuldu.');
            redirect('index.php');
        }
    }
}

$pageTitle = 'Kayıt Ol';
include 'includes/header.php';
?>

<div class="auth-container">
    <div class="auth-card">
        <h1><i class="fas fa-user-plus" style="color:var(--primary);"></i></h1>
        <h1>Kayıt Ol</h1>
        <p class="subtitle">Topluluğa katılmak için hesap oluşturun</p>

        <?php foreach ($errors as $error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= sanitize($error) ?></div>
        <?php endforeach; ?>

        <form method="POST">
            <div class="form-group">
                <label><i class="fas fa-user"></i> Kullanıcı Adı</label>
                <input type="text" name="username" value="<?= sanitize($_POST['username'] ?? '') ?>" placeholder="kullaniciadi" required maxlength="30">
                <p class="form-hint">Harf, rakam ve alt çizgi kullanabilirsiniz.</p>
            </div>
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> E-posta</label>
                <input type="email" name="email" value="<?= sanitize($_POST['email'] ?? '') ?>" placeholder="ornek@email.com" required>
            </div>
            <div class="form-group">
                <label><i class="fas fa-lock"></i> Şifre</label>
                <input type="password" name="password" placeholder="En az 6 karakter" required>
            </div>
            <div class="form-group">
                <label><i class="fas fa-lock"></i> Şifre Tekrar</label>
                <input type="password" name="password2" placeholder="Şifreyi tekrar girin" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:12px;">
                <i class="fas fa-user-plus"></i> Kayıt Ol
            </button>
        </form>
        <div class="auth-footer">
            Zaten hesabınız var mı? <a href="<?= SITE_URL ?>/login.php">Giriş Yapın</a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
