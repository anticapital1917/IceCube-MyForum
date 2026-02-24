<?php
require_once 'config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Giriş yapın']);
    exit();
}

// Hem GET hem POST'tan al
$replyId = intval($_POST['reply_id'] ?? $_GET['reply_id'] ?? 0);
$userId  = $_SESSION['user_id'];

if (!$replyId) { echo json_encode(['success' => false]); exit(); }

$stmt = $pdo->prepare("SELECT id FROM likes WHERE reply_id = ? AND user_id = ?");
$stmt->execute([$replyId, $userId]);
$existing = $stmt->fetch();

if ($existing) {
    $pdo->prepare("DELETE FROM likes WHERE reply_id = ? AND user_id = ?")->execute([$replyId, $userId]);
    // replies tablosundaki likes sayacını da güncelle
    $pdo->prepare("UPDATE replies SET likes = likes - 1 WHERE id = ? AND likes > 0")->execute([$replyId]);
    $liked = false;
} else {
    $pdo->prepare("INSERT INTO likes (reply_id, user_id) VALUES (?, ?)")->execute([$replyId, $userId]);
    $pdo->prepare("UPDATE replies SET likes = likes + 1 WHERE id = ?")->execute([$replyId]);
    $liked = true;

    // Yorum sahibine bildirim gönder
    $owner = $pdo->prepare("SELECT user_id, topic_id FROM replies WHERE id = ?");
    $owner->execute([$replyId]);
    $owner = $owner->fetch();
    if ($owner) {
        $me = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $me->execute([$userId]);
        $me = $me->fetchColumn();
        createNotification($pdo, $owner['user_id'], $userId, 'like', $me . ' yorumunuzu beğendi.', $owner['topic_id'], $replyId);
    }
}

$count = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE reply_id = ?");
$count->execute([$replyId]);

echo json_encode(['success' => true, 'liked' => $liked, 'likes' => $count->fetchColumn()]);
