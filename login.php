<?php
require_once 'config.php';

if (isLoggedIn()) redirect('index.php');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($login) || empty($password)) {
        $errors[] = 'Tüm alanları doldurun.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

            flash('success', 'Hoş geldiniz, ' . $user['username'] . '!');
            redirect('index.php');
        } else {
            $errors[] = 'Kullanıcı adı/e-posta veya şifre hatalı.';
        }
    }
}

$pageTitle = 'Giriş Yap';
include 'includes/header.php';
?>

<div class="auth-container">
    <div class="auth-card">
        <h1><i class="fas fa-sign-in-alt" style="color:var(--primary);"></i></h1>
        <h1>Giriş Yap</h1>
        <p class="subtitle">Hesabınıza giriş yapın</p>

        <?php foreach ($errors as $error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= sanitize($error) ?></div>
        <?php endforeach; ?>

        <form method="POST">
            <div class="form-group">
                <label><i class="fas fa-user"></i> Kullanıcı Adı veya E-posta</label>
                <input type="text" name="login" value="<?= sanitize($_POST['login'] ?? '') ?>" placeholder="kullaniciadi veya email" required>
            </div>
            <div class="form-group">
                <label><i class="fas fa-lock"></i> Şifre</label>
                <input type="password" name="password" placeholder="Şifreniz" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:12px;">
                <i class="fas fa-sign-in-alt"></i> Giriş Yap
            </button>
        </form>
        <div class="auth-footer">
            Hesabınız yok mu? <a href="<?= SITE_URL ?>/register.php">Kayıt Olun</a>
        </div>
        <div class="auth-footer" style="margin-top:8px;font-size:.8rem;color:var(--text-muted);">
            Test giriş: <strong>admin</strong> / <strong>password</strong>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
