<?php
require_once 'config.php';

if (!isLoggedIn()) {
    flash('error', 'Konu açmak için giriş yapmalısınız.');
    redirect('login.php');
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY order_num ASC")->fetchAll();
$preselect = intval($_GET['cat'] ?? 0);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $cat_id = intval($_POST['category_id'] ?? 0);

    if (strlen($title) < 5) $errors[] = 'Başlık en az 5 karakter olmalıdır.';
    if (strlen($title) > 200) $errors[] = 'Başlık en fazla 200 karakter olabilir.';
    if (strlen($content) < 20) $errors[] = 'İçerik en az 20 karakter olmalıdır.';
    if (!$cat_id) $errors[] = 'Kategori seçin.';

    if (empty($errors)) {
        $slug = createSlug($title);
        $stmt = $pdo->prepare("INSERT INTO topics (category_id, user_id, title, slug, content) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$cat_id, $_SESSION['user_id'], $title, $slug, $content]);
        $topicId = $pdo->lastInsertId();

        $pdo->prepare("UPDATE categories SET topic_count = topic_count + 1 WHERE id = ?")->execute([$cat_id]);
        $pdo->prepare("UPDATE users SET post_count = post_count + 1 WHERE id = ?")->execute([$_SESSION['user_id']]);

        // Slug ile geri dön
        $slugFetched = $pdo->prepare("SELECT slug FROM topics WHERE id = ?");
        $slugFetched->execute([$topicId]);
        $row = $slugFetched->fetch();

        flash('success', 'Konunuz başarıyla oluşturuldu!');
        redirect("topic.php?slug={$row['slug']}");
    }
}

$pageTitle = 'Yeni Konu Aç';
include 'includes/header.php';
?>

<div class="breadcrumb">
    <a href="<?= SITE_URL ?>">Anasayfa</a>
    <i class="fas fa-chevron-right"></i>
    <span>Yeni Konu Aç</span>
</div>

<div style="max-width:760px;margin:0 auto;">
    <div class="page-header">
        <h1><i class="fas fa-plus-circle" style="color:var(--primary);"></i> Yeni Konu Aç</h1>
        <p>Sorunuzu, fikrinizi veya tartışmak istediğiniz konuyu paylaşın.</p>
    </div>

    <?php foreach ($errors as $e): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= sanitize($e) ?></div>
    <?php endforeach; ?>

    <div class="card">
        <form method="POST">
            <div class="form-group">
                <label><i class="fas fa-th-large"></i> Kategori</label>
                <select name="category_id" required>
                    <option value="">-- Kategori Seçin --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $preselect ? 'selected' : '' ?>>
                            <?= sanitize($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label><i class="fas fa-heading"></i> Başlık</label>
                <input type="text" name="title" value="<?= sanitize($_POST['title'] ?? '') ?>" placeholder="Konunuzun başlığını girin" maxlength="200" required>
                <p class="form-hint">Açıklayıcı bir başlık daha fazla yanıt almanızı sağlar.</p>
            </div>
            <div class="form-group">
                <label><i class="fas fa-align-left"></i> İçerik</label>
                <textarea id="richEditor" name="content" placeholder="Konunuzu detaylı açıklayın..." rows="10" required minlength="20"><?= sanitize($_POST['content'] ?? '') ?></textarea>
            </div>
            <div style="display:flex;gap:12px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Konuyu Yayınla</button>
                <a href="<?= SITE_URL ?>" class="btn btn-ghost">İptal</a>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
