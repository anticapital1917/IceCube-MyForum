<?php
require_once 'config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) { echo json_encode(['success'=>false,'message'=>'Giriş yapın']); exit(); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false]); exit(); }

$topicId  = intval($_POST['topic_id'] ?? 0) ?: null;
$replyId  = intval($_POST['reply_id'] ?? 0) ?: null;
$reason   = $_POST['reason'] ?? 'diger';
$desc     = trim($_POST['description'] ?? '');
$uid      = $_SESSION['user_id'];

$validReasons = ['spam','hakaret','uygunsuz','yaniltici','diger'];
if (!in_array($reason, $validReasons)) $reason = 'diger';

// Daha önce raporladı mı?
$check = $pdo->prepare("SELECT id FROM reports WHERE reporter_id=? AND topic_id<=>? AND reply_id<=>?");
$check->execute([$uid,$topicId,$replyId]);
if ($check->fetch()) {
    echo json_encode(['success'=>false,'message'=>'Bu içeriği zaten raporladınız.']);
    exit();
}

$pdo->prepare("INSERT INTO reports (reporter_id,topic_id,reply_id,reason,description) VALUES (?,?,?,?,?)")
    ->execute([$uid,$topicId,$replyId,$reason,$desc]);

echo json_encode(['success'=>true,'message'=>'İçerik raporlandı. Teşekkür ederiz!']);
