<?php
require_once 'config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) { echo json_encode(['success'=>false,'message'=>'Giriş yapın']); exit(); }

$targetId = intval($_POST['user_id'] ?? 0);
$uid = $_SESSION['user_id'];

if (!$targetId || $targetId === $uid) { echo json_encode(['success'=>false]); exit(); }

$stmt = $pdo->prepare("SELECT id FROM follows WHERE follower_id=? AND following_id=?");
$stmt->execute([$uid, $targetId]);
$exists = $stmt->fetch();

if ($exists) {
    $pdo->prepare("DELETE FROM follows WHERE follower_id=? AND following_id=?")->execute([$uid,$targetId]);
    $following = false;
} else {
    $pdo->prepare("INSERT INTO follows (follower_id,following_id) VALUES (?,?)")->execute([$uid,$targetId]);
    $following = true;
    // Bildirim gönder
    $me = $pdo->prepare("SELECT username FROM users WHERE id=?"); $me->execute([$uid]); $me=$me->fetch();
    createNotification($pdo,$targetId,$uid,'follow',$me['username'].' sizi takip etmeye başladı.');
}

$count = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE following_id=?");
$count->execute([$targetId]);

echo json_encode(['success'=>true,'following'=>$following,'count'=>$count->fetchColumn()]);
